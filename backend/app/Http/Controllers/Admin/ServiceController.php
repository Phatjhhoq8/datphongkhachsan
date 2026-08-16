<?php

namespace App\Http\Controllers\Admin;

use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ServiceController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $hotelId = $this->scopedHotelId($request, $request->filled('hotel_id') ? (string) $request->input('hotel_id') : null);

        return response()->json(['data' => Service::query()->with('hotel')->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->scopedHotelId($request, (string) $data['hotel_id']);

        return response()->json(['data' => Service::query()->create($data)->load('hotel')], 201);
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $this->scopedHotelId($request, $service->hotel_id);

        return response()->json(['data' => $service->load('hotel')]);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $this->scopedHotelId($request, $service->hotel_id);
        $data = $this->validated($request, $service);
        $this->scopedHotelId($request, (string) $data['hotel_id']);
        $service->update($data);

        return response()->json(['data' => $service->refresh()->load('hotel')]);
    }

    public function destroy(Request $request, Service $service): Response
    {
        $this->scopedHotelId($request, $service->hotel_id);
        $service->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'hotel_id' => ['required', 'exists:hotels,id'],
            'code' => ['required', 'string', 'max:255', Rule::unique('services')->where('hotel_id', (string) $request->input('hotel_id'))->ignore($service)],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'pricing_type' => ['required', Rule::in(['per_booking', 'per_night', 'per_guest', 'per_unit'])],
            'price' => ['required', 'integer', 'min:0'], 'cost' => ['nullable', 'integer', 'min:0'], 'active' => ['sometimes', 'boolean'],
        ]);
    }
}
