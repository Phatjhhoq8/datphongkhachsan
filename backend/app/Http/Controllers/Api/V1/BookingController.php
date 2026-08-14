<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingStateService;
use App\Services\QuoteCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, QuoteCalculator $calculator): JsonResponse
    {
        $data = $request->validated();
        $key = $request->header('Idempotency-Key');

        if ($key !== null && (strlen($key) > 255 || trim($key) === '')) {
            throw ValidationException::withMessages(['idempotency_key' => 'The Idempotency-Key header must contain 1 to 255 characters.']);
        }

        if ($key && $existing = Booking::query()->where('idempotency_key', $key)->first()) {
            return $this->bookingResponse($existing, 200);
        }

        try {
            $booking = DB::transaction(function () use ($data, $key, $calculator, $request) {
                if ($key && $existing = Booking::query()->where('idempotency_key', $key)->lockForUpdate()->first()) {
                    return $existing;
                }

                $roomType = RoomType::query()->findOrFail($data['room_type_id']);
                $roomsCount = (int) $data['rooms'];
                $children = (int) ($data['children'] ?? 0);

                if ($data['adults'] > $roomType->max_adults * $roomsCount || $children > $roomType->max_children * $roomsCount) {
                    throw ValidationException::withMessages(['guests' => 'The selected room type cannot accommodate all guests.']);
                }

                $rooms = Room::query()
                    ->where('room_type_id', $roomType->id)
                    ->where('operational_status', 'available')
                    ->whereDoesntHave('bookings', fn (Builder $query) => $query
                        ->whereIn('status', Booking::INVENTORY_STATUSES)
                        ->where('checkin', '<', $data['checkout'])
                        ->where('checkout', '>', $data['checkin']))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->limit($roomsCount)
                    ->get();

                if ($rooms->count() < $roomsCount) {
                    abort(409, 'Not enough rooms are available for the selected dates.');
                }

                $user = $this->optionalUser($request);
                $quote = $calculator->calculate($data, $user?->id, $data['guest_email'], true);
                $voucher = $quote['voucher'];

                $booking = Booking::query()->create([
                    'code' => $this->newCode(),
                    'idempotency_key' => $key,
                    'guest_name' => $data['guest_name'],
                    'guest_email' => $data['guest_email'],
                    'guest_phone' => $data['guest_phone'],
                    'checkin' => $data['checkin'],
                    'checkout' => $data['checkout'],
                    'rooms_count' => $roomsCount,
                    'adults' => $data['adults'],
                    'children' => $children,
                    'nights' => $quote['nights'],
                    'subtotal' => $quote['subtotal'],
                    'service_total' => $quote['service_total'],
                    'discount_total' => $quote['discount_total'],
                    'total' => $quote['total'],
                    'status' => 'pending',
                    'payment_method' => in_array($data['payment_method'], ['paypal', 'paypal_mock'], true) ? 'paypal' : 'pay_at_hotel',
                    'payment_status' => 'pending',
                    'payment_option' => $data['payment_option'] ?? 'full',
                    'payment_state' => 'unpaid',
                    'deposit_amount' => $quote['deposit_amount'],
                    'currency' => 'VND',
                    'voucher_id' => $voucher?->id,
                    'special_requests' => $data['special_requests'] ?? null,
                    'user_id' => $user?->id,
                    'created_by' => $user?->id,
                ]);
                $booking->rooms()->attach($rooms->modelKeys());
                $booking->services()->createMany($quote['services']->map(fn (array $line) => $line + ['status' => 'pending'])->all());
                $booking->statusHistories()->create(['from_status' => null, 'to_status' => 'pending', 'actor_id' => $user?->id]);

                if ($voucher) {
                    $voucher->increment('used_count');
                    $booking->redemption()->create([
                        'voucher_id' => $voucher->id,
                        'user_id' => $user?->id,
                        'guest_email' => $data['guest_email'],
                        'amount' => $quote['discount_total'],
                        'redeemed_at' => now(),
                    ]);
                }

                OutboxEvent::query()->create([
                    'event_id' => (string) Str::uuid(), 'aggregate_type' => 'booking', 'aggregate_id' => (string) $booking->id,
                    'event_type' => 'booking.created', 'payload' => ['booking_id' => $booking->id, 'code' => $booking->code], 'occurred_at' => now(),
                ]);

                return $booking;
            }, 3);
        } catch (QueryException $exception) {
            if ($key && $existing = Booking::query()->where('idempotency_key', $key)->first()) {
                return $this->bookingResponse($existing, 200);
            }

            throw $exception;
        }

        return $this->bookingResponse($booking, 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $email = $this->validatedEmail($request);

        abort_unless(strcasecmp($booking->guest_email, $email) === 0, 404);

        return $this->bookingResponse($booking, 200);
    }

    public function cancel(Request $request, Booking $booking, BookingStateService $states): JsonResponse
    {
        $email = $this->validatedEmail($request);
        abort_unless(strcasecmp($booking->guest_email, $email) === 0, 404);

        if (! in_array($booking->status, ['pending', 'confirmed'], true)) {
            return response()->json(['message' => 'This booking can no longer be cancelled.'], 422);
        }

        $reason = Validator::make($request->all(), ['reason' => ['nullable', 'string', 'max:2000']])->validate()['reason'] ?? null;
        $booking = $states->transition($booking, 'cancelled', $reason, $this->optionalUser($request)?->id);

        return $this->bookingResponse($booking, 200);
    }

    private function validatedEmail(Request $request): string
    {
        return strtolower(Validator::make($request->all(), [
            'email' => ['required', 'email:rfc'],
        ])->validate()['email']);
    }

    private function bookingResponse(Booking $booking, int $status): JsonResponse
    {
        $booking->load(['rooms.roomType.hotel', 'rooms.roomType.images', 'rooms.roomType.amenities', 'services', 'invoice']);

        return response()->json(['data' => $booking], $status);
    }

    private function newCode(): string
    {
        do {
            $code = 'DP-'.Str::upper(Str::random(10));
        } while (Booking::query()->where('code', $code)->exists());

        return $code;
    }

    private function optionalUser(Request $request): mixed
    {
        try {
            return auth('sanctum')->user() ?? $request->user();
        } catch (\InvalidArgumentException) {
            return $request->user();
        }
    }
}
