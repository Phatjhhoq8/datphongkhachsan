<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function destinations(): JsonResponse
    {
        $destinations = Hotel::query()
            ->where('status', 'active')
            ->orderBy('city')
            ->get(['city', 'hero_image'])
            ->reduce(function (array $result, Hotel $hotel): array {
                $city = trim((string) $hotel->city);

                if ($city === '') {
                    return $result;
                }

                $result[$city] ??= ['name' => $city, 'count' => 0, 'image' => $hotel->hero_image];
                $result[$city]['count']++;

                if (! $result[$city]['image'] && $hotel->hero_image) {
                    $result[$city]['image'] = $hotel->hero_image;
                }

                return $result;
            }, []);

        return response()->json(['data' => array_values($destinations)]);
    }

    public function index(Request $request, AvailabilityService $availability): JsonResponse
    {
        $hotels = Hotel::query()
            ->where('status', 'active')
            ->with(['roomTypes.images', 'roomTypes.amenities', 'approvedReviews'])
            ->orderBy('name')
            ->get();

        if ($request->filled('location')) {
            $needle = mb_strtolower((string) $request->string('location')->trim());
            $hotels = $hotels->filter(fn (Hotel $hotel) => collect([$hotel->city, $hotel->name, $hotel->address])
                ->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $needle)))->values();
        }

        $hotels->each(function (Hotel $hotel) use ($availability): void {
            $roomTypes = $hotel->roomTypes->where('active', true)->values();
            $roomTypes->each(fn (RoomType $roomType) => $roomType->setAttribute('available_rooms', $availability->rooms($roomType)->count()));
            $hotel->setRelation('roomTypes', $roomTypes);
            $hotel->setAttribute('room_types_count', $roomTypes->count());
            $hotel->setAttribute('approved_reviews_count', $hotel->approvedReviews->count());
            $hotel->setAttribute('approved_reviews_avg_rating', $hotel->approvedReviews->avg('rating_overall'));
            $hotel->setAttribute('room_types_min_price_per_night', $roomTypes->min('price_per_night'));
            $hotel->unsetRelation('approvedReviews');
        });

        return response()->json(['data' => $hotels]);
    }

    public function show(Request $request, Hotel $hotel, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'checkin' => ['nullable', 'required_with:checkout', 'date_format:Y-m-d'],
            'checkout' => ['nullable', 'required_with:checkin', 'date_format:Y-m-d', 'after:checkin'],
            'rooms' => ['nullable', 'integer', 'between:1,20'],
            'adults' => ['nullable', 'integer', 'between:1,100'],
            'children' => ['nullable', 'integer', 'between:0,100'],
        ]);
        $rooms = (int) ($data['rooms'] ?? 1);
        $hotel->load(['roomTypes.images', 'roomTypes.amenities', 'approvedReviews']);
        $roomTypes = $hotel->roomTypes
            ->where('active', true)
            ->filter(function (RoomType $roomType) use ($data, $rooms, $availability): bool {
                $available = $availability->rooms($roomType, $data['checkin'] ?? null, $data['checkout'] ?? null)->count();
                $roomType->setAttribute('available_rooms', $available);

                return $available >= $rooms
                    && (! isset($data['adults']) || $roomType->max_adults >= (int) ceil($data['adults'] / $rooms))
                    && (! isset($data['children']) || $roomType->max_children >= (int) ceil($data['children'] / $rooms));
            })->values();
        $hotel->setRelation('roomTypes', $roomTypes);
        $hotel->setAttribute('approved_reviews_count', $hotel->approvedReviews->count());
        $hotel->setAttribute('approved_reviews_avg_rating', $hotel->approvedReviews->avg('rating_overall'));
        $hotel->unsetRelation('approvedReviews');

        return response()->json(['data' => $hotel]);
    }
}
