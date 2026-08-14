<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Models\Hotel;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rooms = (int) $data['rooms'];
        $children = (int) ($data['children'] ?? 0);
        $nights = CarbonImmutable::parse($data['checkin'])->diffInDays($data['checkout']);

        $query = RoomType::query()
            ->with([
                'hotel' => fn ($hotel) => $hotel
                    ->withCount('approvedReviews')
                    ->withAvg('approvedReviews', 'rating_overall'),
                'images',
                'amenities',
            ])
            ->matchingStay($data['checkin'], $data['checkout'], $rooms)
            ->where('max_adults', '>=', (int) ceil($data['adults'] / $rooms))
            ->where('max_children', '>=', (int) ceil($children / $rooms));

        if (! empty($data['location'])) {
            $location = $data['location'];
            $query->whereHas('hotel', fn (Builder $hotel) => $hotel
                ->where('city', 'like', "%{$location}%")
                ->orWhere('name', 'like', "%{$location}%")
                ->orWhere('address', 'like', "%{$location}%"));
        }

        $query->when(isset($data['min_price']), fn (Builder $q) => $q->where('price_per_night', '>=', $data['min_price']))
            ->when(isset($data['max_price']), fn (Builder $q) => $q->where('price_per_night', '<=', $data['max_price']));

        foreach ($data['amenities'] ?? [] as $amenity) {
            $query->whereHas('amenities', fn (Builder $q) => $q->where('slug', $amenity));
        }

        if (! empty($data['room_type'])) {
            $query->where(function (Builder $types) use ($data) {
                foreach ($data['room_type'] as $type) {
                    $types->orWhere('slug', 'like', "%{$type}%")->orWhere('name', 'like', "%{$type}%");
                }
            });
        }

        $query->when($data['refundable'] ?? false, fn (Builder $q) => $q->where('refundable', true))
            ->when(! empty($data['stars']), fn (Builder $q) => $q->whereHas('hotel', fn (Builder $hotel) => $hotel->whereIn('star_rating', $data['stars'])));

        match ($data['sort'] ?? 'recommended') {
            'price_desc' => $query->orderByDesc('price_per_night'),
            'rating_desc' => $query->orderByDesc(Hotel::query()->select('rating')->whereColumn('hotels.id', 'room_types.hotel_id')),
            default => $query->orderBy('price_per_night'),
        };

        $results = $query->get()->each(function (RoomType $roomType) use ($nights, $rooms) {
            $roomType->setAttribute('nights', $nights);
            $roomType->setAttribute('total_price', number_format((float) $roomType->price_per_night * $nights * $rooms, 2, '.', ''));
        });

        return response()->json(['data' => $results]);
    }
}
