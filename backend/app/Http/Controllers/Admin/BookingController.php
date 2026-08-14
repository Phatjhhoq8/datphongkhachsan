<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CounterBookingRequest;
use App\Http\Resources\Admin\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingStateService;
use App\Services\PaymentMockService;
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
    ];

    public function __construct(private readonly BookingStateService $states, private readonly PaymentMockService $payments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_keys(self::TRANSITIONS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
        ]);
        $query = $this->scopeBookings(Booking::query()->with(['rooms.roomType.hotel']), $request, $request->integer('hotel_id') ?: null)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('checkin', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('checkout', '<=', $request->date('to')))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn (Builder $nested) => $nested->where('code', 'like', $term)
                    ->orWhere('guest_name', 'like', $term)->orWhere('guest_email', 'like', $term));
            })->latest();

        return BookingResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking), $request)->exists(), 404);

        return new BookingResource($booking->load(['rooms.roomType.hotel']));
    }

    public function store(CounterBookingRequest $request): BookingResource
    {
        $data = $request->validated();
        $booking = DB::transaction(function () use ($request, $data) {
            $roomType = RoomType::query()->lockForUpdate()->findOrFail($data['room_type_id']);
            $this->scopedHotelId($request, $roomType->hotel_id);
            $count = isset($data['room_ids']) ? count($data['room_ids']) : (int) $data['rooms'];
            $children = (int) ($data['children'] ?? 0);
            if ($data['adults'] > $roomType->max_adults * $count || $children > $roomType->max_children * $count) {
                throw ValidationException::withMessages(['guests' => 'The selected room type cannot accommodate all guests.']);
            }

            $rooms = Room::query()->where('room_type_id', $roomType->id)->where('hotel_id', $roomType->hotel_id)
                ->where('active', true)->where('operational_status', 'available')
                ->when(isset($data['room_ids']), fn (Builder $query) => $query->whereKey($data['room_ids']))
                ->whereDoesntHave('bookings', fn (Builder $query) => $query->whereIn('status', Booking::INVENTORY_STATUSES)
                    ->where('checkin', '<', $data['checkout'])->where('checkout', '>', $data['checkin']))
                ->orderBy('id')->lockForUpdate()->limit($count)->get();
            if ($rooms->count() !== $count) {
                abort(409, 'Not enough rooms are available for the selected dates.');
            }

            $nights = CarbonImmutable::parse($data['checkin'])->diffInDays($data['checkout']);
            $total = (string) ((float) $roomType->price_per_night * $nights * $count);
            $booking = Booking::query()->create([
                'code' => $this->newCode(),
                'guest_name' => $data['guest_name'], 'guest_email' => strtolower($data['guest_email']), 'guest_phone' => $data['guest_phone'],
                'checkin' => $data['checkin'], 'checkout' => $data['checkout'], 'rooms_count' => $count,
                'adults' => $data['adults'], 'children' => $children, 'nights' => $nights,
                'subtotal' => $total, 'total' => $total, 'status' => 'pending',
                'payment_method' => 'cash', 'payment_status' => 'pending', 'created_by' => $request->user()->id,
            ]);
            $booking->rooms()->attach($rooms->modelKeys());
            $payment = $this->payments->createIntent($booking, [
                'method' => 'cash', 'type' => 'full', 'actor' => $request->user(),
            ], $request->user()->id);
            $this->payments->confirm($payment, 'success');

            return $booking->refresh();
        }, 3);

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
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking), $request)->exists(), 404);

        return response()->json(['data' => $booking->invoice]);
    }

    private function transition(Request $request, Booking $booking, string $target): BookingResource
    {
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking), $request)->exists(), 404);
        DB::transaction(function () use ($request, $booking, $target) {
            $booking->refresh();
            $from = $booking->status;
            abort_unless(in_array($target, self::TRANSITIONS[$from] ?? [], true), 422, "Invalid booking transition from {$from} to {$target}.");

            if ($target === 'checked_in') {
                $invalidRoom = $booking->rooms()->where(fn (Builder $query) => $query->where('active', false)->orWhere('operational_status', '!=', 'available'))->exists();
                abort_if($invalidRoom, 422, 'Every assigned room must be active and available for check-in.');
            }

            $this->states->transition($booking, $target, null, $request->user()->id);
            if ($target === 'checked_out') {
                $booking->rooms()->update(['operational_status' => 'cleaning']);
            }
        });

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
