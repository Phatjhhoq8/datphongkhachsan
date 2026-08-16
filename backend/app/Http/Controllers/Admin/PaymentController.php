<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\BookingResource;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Services\PaymentMockService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class PaymentController extends AdminController
{
    public function __construct(private readonly PaymentMockService $payments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['created', 'succeeded', 'failed', 'refunded'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'hotel_id' => ['nullable', 'string', 'exists:hotels,id'],
        ]);
        $bookingIds = $this->scopeBookings(Booking::query(), $request, $request->filled('hotel_id') ? (string) $request->input('hotel_id') : null)->pluck('id')->all();
        $query = PaymentTransaction::query()->with('booking')->whereIn('booking_id', $bookingIds)
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->when(isset($data['from']), fn ($query) => $query->where('created_at', '>=', CarbonImmutable::parse($data['from'])->startOfDay()))
            ->when(isset($data['to']), fn ($query) => $query->where('created_at', '<=', CarbonImmutable::parse($data['to'])->endOfDay()))
            ->latest();

        return JsonResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, Booking $booking): BookingResource
    {
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking->id), $request)->exists(), 404);
        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'pay_at_hotel', 'paypal'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        abort_if($booking->payment_status === 'paid', 422, 'This booking is already paid.');

        $payment = $this->payments->createIntent($booking, [
            'method' => $data['method'], 'type' => 'full', 'amount' => $data['amount'] ?? null, 'actor' => $request->user(),
        ], $request->user()->id);
        $this->payments->confirm($payment, 'success');

        return new BookingResource($booking->refresh()->load('rooms.roomType.hotel'));
    }
}
