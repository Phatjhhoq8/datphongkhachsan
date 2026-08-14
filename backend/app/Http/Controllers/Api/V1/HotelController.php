<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hotels = Hotel::query()
            ->where('status', 'active')
            ->when($request->filled('location'), function (Builder $query) use ($request) {
                $location = (string) $request->string('location')->trim();
                $query->where(fn (Builder $hotel) => $hotel
                    ->where('city', 'like', "%{$location}%")
                    ->orWhere('name', 'like', "%{$location}%")
                    ->orWhere('address', 'like', "%{$location}%"));
            })
            ->with(['roomTypes' => fn ($query) => $query
                ->where('active', true)
                ->with(['images', 'amenities'])
                ->withCount(['rooms as available_rooms' => fn ($rooms) => $rooms
                    ->where('active', true)->where('operational_status', 'available')])])
            ->withCount(['roomTypes as room_types_count' => fn (Builder $query) => $query->where('active', true), 'approvedReviews'])
            ->withAvg('approvedReviews', 'rating_overall')
            ->withMin(['roomTypes as room_types_min_price_per_night' => fn (Builder $query) => $query->where('active', true)], 'price_per_night')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $hotels]);
    }

    public function show(Request $request, Hotel $hotel): JsonResponse
    {
        $data = $request->validate([
            'checkin' => ['nullable', 'required_with:checkout', 'date_format:Y-m-d'],
            'checkout' => ['nullable', 'required_with:checkin', 'date_format:Y-m-d', 'after:checkin'],
            'rooms' => ['nullable', 'integer', 'between:1,20'],
            'adults' => ['nullable', 'integer', 'between:1,100'],
            'children' => ['nullable', 'integer', 'between:0,100'],
        ]);
        $rooms = (int) ($data['rooms'] ?? 1);
        $roomTypes = fn (Builder $query) => $query
            ->where('active', true)
            ->with(['images', 'amenities'])
            ->when(isset($data['checkin'], $data['checkout']), fn (Builder $query) => $query
                ->matchingStay($data['checkin'], $data['checkout'], $rooms))
            ->when(! isset($data['checkin']), fn (Builder $query) => $query
                ->withCount(['rooms as available_rooms' => fn (Builder $roomsQuery) => $roomsQuery
                    ->where('active', true)->where('operational_status', 'available')]))
            ->when(isset($data['adults']), fn (Builder $query) => $query->where('max_adults', '>=', (int) ceil($data['adults'] / $rooms)))
            ->when(isset($data['children']), fn (Builder $query) => $query->where('max_children', '>=', (int) ceil($data['children'] / $rooms)));

        $hotel->load(['roomTypes' => $roomTypes])
            ->loadCount('approvedReviews')
            ->loadAvg('approvedReviews', 'rating_overall');

        return response()->json(['data' => $hotel]);
    }
}
