<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\PaymentTransaction;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Voucher;
use App\Models\Wishlist;
use App\Services\PaymentMockService;
use App\Services\QuoteCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function quote(Request $request, QuoteCalculator $calculator): JsonResponse
    {
        $data = Validator::make($request->all(), $this->quoteRules())->validate();
        $quote = $calculator->calculate($data, $this->user($request)?->id, $request->input('guest_email'));

        unset($quote['room_type'], $quote['voucher']);

        return response()->json(['data' => $quote]);
    }

    public function services(Hotel $hotel): JsonResponse
    {
        return response()->json(['data' => $hotel->services()->where('active', true)->orderBy('name')->get()]);
    }

    public function vouchers(): JsonResponse
    {
        // Tự động xóa các voucher đã hết hạn khỏi database
        Voucher::query()->whereNotNull('ends_at')->where('ends_at', '<', now())->delete();

        $vouchers = Voucher::query()
            ->with('hotel:id,slug,name,city')
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('ends_at')
            ->get()
            ->filter(fn (Voucher $voucher) => $voucher->usage_limit === null || $voucher->used_count < $voucher->usage_limit)
            ->values();

        return response()->json(['data' => $vouchers]);
    }

    public function validateVoucher(Request $request, QuoteCalculator $calculator): JsonResponse
    {
        $data = Validator::make($request->all(), $this->quoteRules(true))->validate();
        $quote = $calculator->calculate($data, $this->user($request)?->id, $request->input('guest_email'));

        return response()->json(['data' => ['valid' => true, 'code' => $quote['voucher']?->code, 'discount_total' => $quote['discount_total']]]);
    }

    public function createPaymentIntent(Request $request, Booking $booking, PaymentMockService $payments): JsonResponse
    {
        $this->authorizeBooking($request, $booking);
        $data = Validator::make($request->all(), [
            'method' => ['required', Rule::in(['paypal_mock', 'card_mock', 'vietqr_mock', 'cash'])],
            'type' => ['nullable', Rule::in(['deposit', 'full', 'refund'])],
            'amount' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'card_last_four' => ['nullable', 'digits:4'],
            'card_number' => ['prohibited'],
            'cvc' => ['prohibited'],
            'email' => ['nullable', 'email:rfc'],
        ])->validate();
        $data['actor'] = $this->user($request);

        $payment = $payments->createIntent($booking, $data, $this->user($request)?->id);

        return response()->json(['data' => $payment], 201);
    }

    public function confirmPayment(Request $request, PaymentTransaction $payment, PaymentMockService $payments): JsonResponse
    {
        $this->authorizeBooking($request, $payment->booking);
        $data = Validator::make($request->all(), [
            'outcome' => ['required', Rule::in(['success', 'failure'])],
            'email' => ['nullable', 'email:rfc'],
        ])->validate();

        return response()->json(['data' => $payments->confirm($payment, $data['outcome'])]);
    }

    public function bookingPayments(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);

        return response()->json(['data' => $booking->payments()->latest()->get()]);
    }

    public function bookingInvoice(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);

        return response()->json(['data' => $booking->invoice]);
    }

    public function wishlistIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => Wishlist::query()->where('user_id', $request->user()->id)->with('roomType.hotel')->latest()->get()]);
    }

    public function wishlistStore(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), ['room_type_id' => ['required', 'integer', 'exists:room_types,id']])->validate();
        $item = Wishlist::query()->firstOrCreate(['user_id' => $request->user()->id, 'room_type_id' => $data['room_type_id']]);

        return response()->json(['data' => $item], $item->wasRecentlyCreated ? 201 : 200);
    }

    public function wishlistDestroy(Request $request, RoomType $roomType): JsonResponse
    {
        Wishlist::query()->where('user_id', $request->user()->id)->where('room_type_id', $roomType->id)->delete();

        return response()->json(status: 204);
    }

    public function reviews(Hotel $hotel): JsonResponse
    {
        return response()->json(['data' => $hotel->reviews()->where('status', 'published')->with('roomType')->latest()->paginate(20)]);
    }

    public function createReview(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'booking_code' => ['required', 'string', 'exists:bookings,code'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'rating_overall' => ['required', 'integer', 'between:1,5'],
            'rating_room' => ['required', 'integer', 'between:1,5'],
            'rating_service' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:5000'],
        ])->validate();
        $booking = Booking::query()->where('code', $data['booking_code'])->firstOrFail();
        abort_unless($booking->created_by === $request->user()->id && $booking->status === 'checked_out', 403, 'Only the owner of a checked-out booking may review it.');
        $roomType = RoomType::query()->findOrFail($data['room_type_id']);
        abort_unless(Room::query()->whereIn('id', $booking->room_ids ?? [])->where('room_type_id', $roomType->id)->exists(), 422, 'Room type does not belong to this booking.');

        $review = Review::query()->create($data + [
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'hotel_id' => $roomType->hotel_id,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $review], 201);
    }

    public function myBookings(Request $request): JsonResponse
    {
        return response()->json(['data' => Booking::query()->where('created_by', $request->user()->id)->latest()->paginate(20)]);
    }

    public function myBooking(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->created_by === $request->user()->id, 404);

        return response()->json(['data' => $booking->load(['rooms.roomType.hotel', 'services', 'payments', 'invoice', 'statusHistories'])]);
    }

    private function quoteRules(bool $voucherRequired = false): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'rooms' => ['required', 'integer', 'between:1,20'],
            'adults' => ['required', 'integer', 'between:1,100'],
            'children' => ['nullable', 'integer', 'between:0,100'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'distinct'],
            'services' => ['nullable', 'array'],
            'services.*.id' => ['required', 'integer'],
            'services.*.quantity' => ['nullable', 'integer', 'between:1,100'],
            'voucher_code' => [$voucherRequired ? 'required' : 'nullable', 'string', 'max:100'],
            'guest_email' => ['nullable', 'email:rfc'],
            'arrival_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'checkout_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        $user = $this->user($request);
        $owner = $user && $booking->created_by === $user->id;
        $guest = $request->filled('email') && strcasecmp($booking->guest_email, (string) $request->input('email')) === 0;
        abort_unless($owner || $guest, 404);
    }

    private function user(Request $request): mixed
    {
        try {
            return auth('sanctum')->user() ?? $request->user();
        } catch (\InvalidArgumentException) {
            return $request->user();
        }
    }
}
