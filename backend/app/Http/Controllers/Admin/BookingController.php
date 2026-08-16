<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CounterBookingRequest;
use App\Http\Resources\Admin\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomNight;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\BookingStateService;
use App\Services\CancellationService;
use App\Services\PaymentMockService;
use App\Services\RoomTurnoverService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends AdminController
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'cancelled'],
        'checked_in' => ['checked_out'],
        'checked_out' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    public function __construct(
        private readonly BookingStateService $states,
        private readonly PaymentMockService $payments,
        private readonly AvailabilityService $availability,
        private readonly RoomTurnoverService $turnover,
        private readonly CancellationService $cancellations,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_keys(self::TRANSITIONS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'hotel_id' => ['nullable', 'string', 'exists:hotels,id'],
        ]);
        $query = $this->scopeBookings(Booking::query()->with(['rooms.roomType.hotel']), $request, $request->filled('hotel_id') ? (string) $request->input('hotel_id') : null)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('from'), fn (Builder $query) => $query->where('checkin', '>=', $request->date('from')->toDateString()))
            ->when($request->filled('to'), fn (Builder $query) => $query->where('checkout', '<=', $request->date('to')->toDateString()))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn (Builder $nested) => $nested->where('code', 'like', $term)
                    ->orWhere('guest_name', 'like', $term)->orWhere('guest_email', 'like', $term));
            })->latest();

        return BookingResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking->id), $request)->exists(), 404);

        return new BookingResource($booking->load(['rooms.roomType.hotel']));
    }

    public function store(CounterBookingRequest $request): BookingResource
    {
        $data = $request->validated();
        $booking = DB::transaction(function () use ($request, $data) {
            $roomType = RoomType::query()->findOrFail($data['room_type_id']);
            $hotel = $roomType->hotel()->firstOrFail();
            $this->scopedHotelId($request, $roomType->hotel_id);
            $count = isset($data['room_ids']) ? count($data['room_ids']) : (int) $data['rooms'];
            $children = (int) ($data['children'] ?? 0);
            if ($data['adults'] > $roomType->max_adults * $count || $children > $roomType->max_children * $count) {
                throw ValidationException::withMessages(['guests' => 'The selected room type cannot accommodate all guests.']);
            }

            $rooms = $this->availability->rooms($roomType, $data['checkin'], $data['checkout'])
                ->when(isset($data['room_ids']), fn ($items) => $items->whereIn('id', $data['room_ids']))
                ->take($count)
                ->values();
            if ($rooms->count() !== $count) {
                abort(409, 'Not enough rooms are available for the selected dates.');
            }

            $nights = CarbonImmutable::parse($data['checkin'])->diffInDays($data['checkout']);
            $total = (string) ((float) $roomType->price_per_night * $nights * $count);
            $turnoverSnapshot = $this->turnover->bookingSnapshot($hotel, $data['checkin'], $data['checkout'], $data['arrival_time'] ?? null, $data['checkout_time'] ?? null);
            $booking = Booking::query()->create([
                'code' => $this->newCode(),
                'guest_name' => $data['guest_name'], 'guest_email' => strtolower($data['guest_email']), 'guest_phone' => $data['guest_phone'],
                'checkin' => $data['checkin'], 'checkout' => $data['checkout'], 'rooms_count' => $count,
                'adults' => $data['adults'], 'children' => $children, 'nights' => $nights,
                'subtotal' => $total, 'total' => $total, 'status' => 'pending',
                'payment_method' => 'cash', 'payment_status' => 'pending', 'payment_option' => 'full',
                'payment_state' => 'unpaid', 'paid_amount' => 0, 'deposit_amount' => 0,
                'service_total' => 0, 'discount_total' => 0, 'currency' => 'VND',
                'created_by' => $request->user()->id, 'user_id' => $request->user()->id,
                'hotel_id' => $roomType->hotel_id,
                'room_ids' => $rooms->modelKeys(),
                'source' => 'walk_in',
            ] + $turnoverSnapshot + $this->cancellations->snapshot($hotel, $roomType, $turnoverSnapshot['scheduled_checkin_at']));
            $booking->rooms()->sync($rooms->modelKeys());
            foreach ($rooms as $room) {
                foreach ($this->availability->nights($data['checkin'], $data['checkout']) as $night) {
                    RoomNight::query()->create([
                        'room_id' => $room->id, 'booking_id' => $booking->id, 'hotel_id' => $roomType->hotel_id,
                        'room_type_id' => $roomType->id, 'night' => $night, 'state' => 'booked',
                    ]);
                }
            }
            $booking->statusHistories()->create(['from_status' => null, 'to_status' => 'pending', 'actor_id' => $request->user()->id]);

            return $booking->refresh();
        }, 3);

        $payment = $this->payments->createIntent($booking, [
            'method' => 'cash', 'type' => 'full', 'actor' => $request->user(),
        ], $request->user()->id);
        $this->payments->confirm($payment, 'success');
        $booking->refresh();

        return new BookingResource($booking->load(['rooms.roomType.hotel']));
    }

    public function updateStatus(Request $request, Booking $booking): BookingResource
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::TRANSITIONS))]]);

        return $this->transition($request, $booking, $data['status']);
    }

    public function checkIn(Request $request, Booking $booking): BookingResource
    {
        return $this->transition($request, $booking, 'checked_in');
    }

    public function checkOut(Request $request, Booking $booking): BookingResource
    {
        return $this->transition($request, $booking, 'checked_out');
    }

    public function invoice(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking->id), $request)->exists(), 404);

        return response()->json(['data' => $booking->invoice]);
    }

    private function transition(Request $request, Booking $booking, string $target): BookingResource
    {
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking->id), $request)->exists(), 404);
        $booking->refresh();
        $from = $booking->status;
        abort_unless(in_array($target, self::TRANSITIONS[$from] ?? [], true), 422, "Invalid booking transition from {$from} to {$target}.");

        if ($target === 'checked_in') {
            abort_if(now()->isBefore($this->turnover->scheduledCheckin($booking)), 422, 'Check-in is not available before the scheduled check-in time.');
            $rooms = Room::query()->whereIn('id', $booking->room_ids ?? [])->get();
            $invalidRoom = $rooms->count() !== count($booking->room_ids ?? []) || $rooms->contains(
                fn (Room $room) => ! $room->active
                    || $room->operational_status !== 'available'
                    || ($room->available_at !== null && now()->isBefore($room->available_at))
            );
            abort_if($invalidRoom, 422, 'Every assigned room must be active, cleaned, and past available_at for check-in.');
        }

        if ($target === 'cancelled') {
            $this->cancellations->cancel($booking, null, $request->user()->id);
        } else {
            $this->states->transition($booking, $target, null, $request->user()->id);
        }

        return new BookingResource($booking->refresh()->load(['rooms.roomType.hotel']));
    }

    private function newCode(): string
    {
        do {
            $code = 'CT-'.Str::upper(Str::random(10));
        } while (Booking::query()->where('code', $code)->exists());

        return $code;
    }
}
