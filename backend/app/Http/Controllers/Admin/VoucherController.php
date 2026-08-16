<?php

namespace App\Http\Controllers\Admin;

use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class VoucherController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        // Tự động xóa các voucher đã hết hạn khỏi database
        Voucher::query()->whereNotNull('ends_at')->where('ends_at', '<', now())->delete();

        $hotelId = $this->scopedHotelId($request, $request->filled('hotel_id') ? (string) $request->input('hotel_id') : null);

        return response()->json(['data' => Voucher::query()->with('hotel')->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->scopedHotelId($request, isset($data['hotel_id']) ? (string) $data['hotel_id'] : null);
        $data['normalized_code'] = strtoupper(trim($data['code']));

        return response()->json(['data' => Voucher::query()->create($data)->load('hotel')], 201);
    }

    public function show(Request $request, Voucher $voucher): JsonResponse
    {
        $this->scopedHotelId($request, $voucher->hotel_id);

        return response()->json(['data' => $voucher->load('hotel')]);
    }

    public function update(Request $request, Voucher $voucher): JsonResponse
    {
        $this->scopedHotelId($request, $voucher->hotel_id);
        $data = $this->validated($request, $voucher);
        $this->scopedHotelId($request, isset($data['hotel_id']) ? (string) $data['hotel_id'] : null);
        $data['normalized_code'] = strtoupper(trim($data['code']));
        $voucher->update($data);

        return response()->json(['data' => $voucher->refresh()->load('hotel')]);
    }

    public function destroy(Request $request, Voucher $voucher): Response
    {
        $this->scopedHotelId($request, $voucher->hotel_id);
        $voucher->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        return $request->validate([
            'hotel_id' => ['nullable', 'exists:hotels,id'], 'code' => ['required', 'string', 'max:255', Rule::unique('vouchers')->ignore($voucher)],
            'type' => ['required', Rule::in(['fixed', 'percent'])], 'value' => ['required', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'], 'min_order' => ['sometimes', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'], 'per_user_limit' => ['nullable', 'integer', 'min:1'], 'active' => ['sometimes', 'boolean'],
        ]);
    }
}
