<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\RoomNight;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\CancellationService;
use App\Services\QuoteCalculator;
use App\Services\RoomTurnoverService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MongoDB\Driver\Exception\BulkWriteException;

class BookingController extends Controller
{
    public function store(
        StoreBookingRequest $request,
        QuoteCalculator $calculator,
        RoomTurnoverService $turnover,
        AvailabilityService $availability,
        CancellationService $cancellations,
    ): JsonResponse {
        $data = $request->validated();
        $key = $request->header('Idempotency-Key');

        if ($key !== null && (strlen($key) > 255 || trim($key) === '')) {
            throw ValidationException::withMessages(['idempotency_key' => 'The Idempotency-Key header must contain 1 to 255 characters.']);
        }

        if ($key && $existing = Booking::query()->where('idempotency_key', $key)->first()) {
            return $this->bookingResponse($existing, 200);
        }

        try {
            $booking = DB::transaction(function () use ($data, $key, $calculator, $request, $turnover, $availability, $cancellations) {
                if ($key && $existing = Booking::query()->where('idempotency_key', $key)->first()) {
                    return $existing;
                }

                $roomType = RoomType::query()->findOrFail($data['room_type_id']);
                $hotel = $roomType->hotel()->firstOrFail();
                $roomsCount = (int) $data['rooms'];
                $children = (int) ($data['children'] ?? 0);

                if ($data['adults'] > $roomType->max_adults * $roomsCount || $children > $roomType->max_children * $roomsCount) {
                    throw ValidationException::withMessages(['guests' => 'The selected room type cannot accommodate all guests.']);
                }

                $nights = $this->stayNights($data['checkin'], $data['checkout']);
                $rooms = $availability->rooms($roomType, $data['checkin'], $data['checkout'])
                    ->take($roomsCount)
                    ->values();

                if ($rooms->count() < $roomsCount) {
                    abort(409, 'Not enough rooms are available for the selected dates.');
                }

                $user = $this->optionalUser($request);
                $quote = $calculator->calculate($data, $user?->id, $data['guest_email'], true);
                $voucher = $quote['voucher'];
                $onlinePayment = in_array($data['payment_method'], ['paypal', 'paypal_mock', 'card_mock', 'vietqr_mock'], true);
                $holdExpiresAt = $onlinePayment ? now()->addMinutes(15) : null;

                $turnoverSnapshot = $turnover->bookingSnapshot($hotel, $data['checkin'], $data['checkout'], $data['arrival_time'] ?? null, $data['checkout_time'] ?? null);
                $booking = Booking::query()->create([
                    'code' => $this->newCode(),
                    'idempotency_key' => $key,
                    'guest_name' => $data['guest_name'],
                    'guest_email' => $data['guest_email'],
                    'guest_phone' => $data['guest_phone'],
                    'checkin' => CarbonImmutable::parse($data['checkin'])->toDateString(),
                    'checkout' => CarbonImmutable::parse($data['checkout'])->toDateString(),
                    'rooms_count' => $roomsCount,
                    'adults' => $data['adults'],
                    'children' => $children,
                    'nights' => $quote['nights'],
                    'subtotal' => $quote['subtotal'],
                    'service_total' => $quote['service_total'],
                    'discount_total' => $quote['discount_total'],
                    'total' => $quote['total'],
                    'status' => 'pending',
                    'payment_method' => $data['payment_method'] === 'paypal_mock' ? 'paypal' : $data['payment_method'],
                    'payment_status' => 'pending',
                    'payment_option' => $data['payment_option'] ?? 'full',
                    'payment_state' => 'unpaid',
                    'deposit_amount' => $quote['deposit_amount'],
                    'currency' => 'VND',
                    'voucher_id' => $voucher?->id,
                    'hotel_id' => $roomType->hotel_id,
                    'room_ids' => $rooms->modelKeys(),
                    'source' => 'online',
                    'special_requests' => $data['special_requests'] ?? null,
                    'user_id' => $user?->id,
                    'created_by' => $user?->id,
                    'hold_expires_at' => $holdExpiresAt,
                ] + $turnoverSnapshot + $cancellations->snapshot($hotel, $roomType, $turnoverSnapshot['scheduled_checkin_at']));
                $booking->rooms()->sync($rooms->modelKeys());
                foreach ($rooms as $room) {
                    foreach ($nights as $night) {
                        RoomNight::query()->create([
                            'room_id' => $room->id,
                            'booking_id' => $booking->id,
                            'hotel_id' => $roomType->hotel_id,
                            'room_type_id' => $roomType->id,
                            'night' => $night,
                            'state' => 'held',
                            'expires_at' => $holdExpiresAt,
                        ]);
                    }
                }
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
        } catch (BulkWriteException $exception) {
            if ($key && $existing = Booking::query()->where('idempotency_key', $key)->first()) {
                return $this->bookingResponse($existing, 200);
            }

            if ($exception->getCode() === 11000) {
                abort(409, 'Not enough rooms are available for the selected dates.');
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

    public function cancel(Request $request, Booking $booking, CancellationService $cancellations): JsonResponse
    {
        $email = $this->validatedEmail($request);
        abort_unless(strcasecmp($booking->guest_email, $email) === 0, 404);

        if (! in_array($booking->status, ['pending', 'confirmed'], true)) {
            return response()->json(['message' => 'This booking can no longer be cancelled.'], 422);
        }

        $reason = Validator::make($request->all(), ['reason' => ['nullable', 'string', 'max:2000']])->validate()['reason'] ?? null;
        $booking = $cancellations->cancel($booking, $reason, $this->optionalUser($request)?->id);

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

    /** @return list<string> */
    private function stayNights(string $checkin, string $checkout): array
    {
        $night = CarbonImmutable::parse($checkin);
        $end = CarbonImmutable::parse($checkout);
        $nights = [];

        while ($night->lessThan($end)) {
            $nights[] = $night->toDateString();
            $night = $night->addDay();
        }

        return $nights;
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
