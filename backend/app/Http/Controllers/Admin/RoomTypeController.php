<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\RoomTypeRequest;
use App\Http\Resources\Admin\RoomTypeResource;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RoomTypeController extends AdminController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $hotelId = $this->scopedHotelId($request, $request->filled('hotel_id') ? (string) $request->input('hotel_id') : null);
        $items = RoomType::query()->with(['amenities', 'images'])->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderBy('name')->paginate($request->integer('per_page', 20));

        return RoomTypeResource::collection($items);
    }

    public function store(RoomTypeRequest $request): RoomTypeResource
    {
        $data = $request->validated();
        $this->scopedHotelId($request, (string) $data['hotel_id']);
        $item = RoomType::query()->create($data);
        $item->amenities()->sync($data['amenity_ids'] ?? []);

        return new RoomTypeResource($item->load(['amenities', 'images']));
    }

    public function show(Request $request, RoomType $roomType): RoomTypeResource
    {
        $this->scopedHotelId($request, $roomType->hotel_id);

        return new RoomTypeResource($roomType->load(['amenities', 'images', 'rooms']));
    }

    public function update(RoomTypeRequest $request, RoomType $roomType): RoomTypeResource
    {
        $data = $request->validated();
        $this->scopedHotelId($request, $roomType->hotel_id);
        $this->scopedHotelId($request, (string) $data['hotel_id']);
        $roomType->update($data);
        if (array_key_exists('amenity_ids', $data)) {
            $roomType->amenities()->sync($data['amenity_ids']);
        }

        return new RoomTypeResource($roomType->refresh()->load(['amenities', 'images']));
    }

    public function destroy(Request $request, RoomType $roomType): Response
    {
        $this->scopedHotelId($request, $roomType->hotel_id);
        $roomType->delete();

        return response()->noContent();
    }
}
