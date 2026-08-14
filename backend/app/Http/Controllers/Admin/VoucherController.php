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
        $hotelId = $this->scopedHotelId($request, $request->integer('hotel_id') ?: null);

        return response()->json(['data' => Voucher::query()->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->scopedHotelId($request, isset($data['hotel_id']) ? (int) $data['hotel_id'] : null);

        return response()->json(['data' => Voucher::query()->create($data)], 201);
    }

    public function show(Request $request, Voucher $voucher): JsonResponse
    {
        $this->scopedHotelId($request, $voucher->hotel_id);

        return response()->json(['data' => $voucher]);
    }

    public function update(Request $request, Voucher $voucher): JsonResponse
    {
        $this->scopedHotelId($request, $voucher->hotel_id);
        $data = $this->validated($request, $voucher);
        $this->scopedHotelId($request, isset($data['hotel_id']) ? (int) $data['hotel_id'] : null);
        $voucher->update($data);

        return response()->json(['data' => $voucher->refresh()]);
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
