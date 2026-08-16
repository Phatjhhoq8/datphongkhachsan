<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\HotelRequest;
use App\Http\Resources\Admin\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class HotelController extends AdminController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $hotelId = $this->scopedHotelId($request, $request->filled('hotel_id') ? (string) $request->input('hotel_id') : null);
        $hotels = Hotel::query()->when($hotelId, fn ($query) => $query->whereKey($hotelId))
            ->orderBy('name')->paginate($request->integer('per_page', 20));

        return HotelResource::collection($hotels);
    }

    public function store(HotelRequest $request): HotelResource
    {
        abort_unless($request->user()->role === 'super_admin', 403);

        return new HotelResource(Hotel::query()->create($request->validated()));
    }

    public function show(Request $request, Hotel $hotel): HotelResource
    {
        $this->scopedHotelId($request, $hotel->id);

        return new HotelResource($hotel->load('roomTypes'));
    }

    public function update(HotelRequest $request, Hotel $hotel): HotelResource
    {
        $this->scopedHotelId($request, $hotel->id);
        $hotel->update($request->validated());

        return new HotelResource($hotel->refresh());
    }

    public function destroy(Request $request, Hotel $hotel): Response
    {
        $this->scopedHotelId($request, $hotel->id);
        $hotel->delete();

        return response()->noContent();
    }
}
