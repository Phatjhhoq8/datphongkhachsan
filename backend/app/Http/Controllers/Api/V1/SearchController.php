<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validated();
        $rooms = (int) $data['rooms'];
        $children = (int) ($data['children'] ?? 0);
        $nights = CarbonImmutable::parse($data['checkin'])->diffInDays($data['checkout']);

        $location = mb_strtolower((string) ($data['location'] ?? ''));
        $requestedAmenities = $data['amenities'] ?? [];
        $requestedTypes = array_map('mb_strtolower', $data['room_type'] ?? []);
        $keyword = mb_strtolower((string) ($data['keyword'] ?? ''));
        $stars = array_map('intval', $data['stars'] ?? []);

        $results = RoomType::query()->where('active', true)->with(['hotel.approvedReviews', 'images', 'amenities'])->get()
            ->filter(function (RoomType $roomType) use ($data, $rooms, $children, $location, $requestedAmenities, $requestedTypes, $keyword, $stars, $availability): bool {
                $timeToMinutes = function (string $time): int {
                    list($hours, $minutes) = explode(':', $time);
                    return ((int) $hours * 60) + (int) $minutes;
                };

                if (isset($data['checkout_time'])) {
                    $hotel = $roomType->hotel;
                    if ($hotel) {
                        $checkinTimeStr = $hotel->checkin_time;
                        $grace = (int) $hotel->late_checkout_grace_minutes;
                        $cleaning = (int) $hotel->cleaning_duration_minutes;
                        $totalBufferMinutes = $grace + $cleaning;

                        $checkinMinutes = $timeToMinutes($checkinTimeStr);
                        $checkoutMinutes = $timeToMinutes($data['checkout_time']);

                        if ($checkoutMinutes + $totalBufferMinutes > $checkinMinutes) {
                            return false;
                        }
                    }
                }

                $checkinParam = isset($data['arrival_time']) ? "{$data['checkin']} {$data['arrival_time']}" : $data['checkin'];
                $checkoutParam = isset($data['checkout_time']) ? "{$data['checkout']} {$data['checkout_time']}" : $data['checkout'];
                $available = $availability->rooms($roomType, $checkinParam, $checkoutParam)->count();
                $roomType->setAttribute('available_rooms', $available);
                $hotelText = mb_strtolower(implode(' ', [$roomType->hotel?->city, $roomType->hotel?->name, $roomType->hotel?->address]));
                $amenitySlugs = $roomType->amenities->pluck('slug')->all();
                $typeText = mb_strtolower("{$roomType->slug} {$roomType->name}");

                return $available >= $rooms
                    && $roomType->max_adults >= (int) ceil($data['adults'] / $rooms)
                    && $roomType->max_children >= (int) ceil($children / $rooms)
                    && ($location === '' || str_contains($hotelText, $location))
                    && (! isset($data['min_price']) || $roomType->price_per_night >= $data['min_price'])
                    && (! isset($data['max_price']) || $roomType->price_per_night <= $data['max_price'])
                    && count(array_diff($requestedAmenities, $amenitySlugs)) === 0
                    && ($requestedTypes === [] || collect($requestedTypes)->contains(fn ($type) => str_contains($typeText, $type)))
                    && ($keyword === '' || str_contains($typeText, $keyword))
                    && (! ($data['refundable'] ?? false) || $roomType->refundable)
                    && (empty($stars) || in_array((int) $roomType->hotel?->star_rating, $stars, true));
            })
            ->each(function (RoomType $roomType) use ($nights, $rooms) {
                if ($roomType->hotel) {
                    $roomType->hotel->setAttribute('approved_reviews_count', $roomType->hotel->approvedReviews->count());
                    $roomType->hotel->setAttribute('approved_reviews_avg_rating', $roomType->hotel->approvedReviews->avg('rating_overall'));
                    $roomType->hotel->unsetRelation('approvedReviews');
                }
                $roomType->setAttribute('nights', $nights);
                $roomType->setAttribute('total_price', number_format((float) $roomType->price_per_night * $nights * $rooms, 2, '.', ''));
            });

        $results = match ($data['sort'] ?? 'recommended') {
            'price_desc' => $results->sortByDesc('price_per_night'),
            'rating_desc' => $results->sortByDesc(fn (RoomType $roomType) => $roomType->hotel?->rating ?? 0),
            default => $results->sortBy('price_per_night'),
        };

        return response()->json(['data' => $results->values()]);
    }
}
