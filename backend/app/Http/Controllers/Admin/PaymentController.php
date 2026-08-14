<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\BookingResource;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Services\PaymentMockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class PaymentController extends AdminController
{
    public function __construct(private readonly PaymentMockService $payments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = $this->scopeBookings(Booking::query(), $request, $request->integer('hotel_id') ?: null)->select('bookings.id');
        $query = PaymentTransaction::query()->with('booking')->whereIn('booking_id', $bookings)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest();

        return JsonResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, Booking $booking): BookingResource
    {
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking), $request)->exists(), 404);
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
