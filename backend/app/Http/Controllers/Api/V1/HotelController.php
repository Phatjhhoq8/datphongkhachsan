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
            $stripAccents = function (string $str): string {
                $dict = [
                    'a' => ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','A','À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ'],
                    'e' => ['è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','E','È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ'],
                    'i' => ['ì','í','ị','ỉ','ĩ','I','Ì','Í','Ị','Ỉ','Ĩ'],
                    'o' => ['ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','O','Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ'],
                    'u' => ['ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','U','Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ'],
                    'y' => ['ỳ','ý','ỵ','ỷ','ỹ','Y','Ỳ','Ý','Ỵ','Ỷ','Ỹ'],
                    'd' => ['đ','Đ']
                ];
                $str = mb_strtolower($str);
                foreach ($dict as $replacement => $accents) {
                    $str = str_replace($accents, $replacement, $str);
                }
                return $str;
            };
            $needle = $stripAccents((string) $request->string('location')->trim());
            $hotels = $hotels->filter(fn (Hotel $hotel) => collect([$hotel->city, $hotel->name, $hotel->address])
                ->contains(fn ($value) => str_contains($stripAccents((string) $value), $needle)))->values();
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
            'arrival_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'checkout_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        if (isset($data['checkout_time']) && isset($data['checkin'])) {
            $timeToMinutes = function (string $time): int {
                list($hours, $minutes) = explode(':', $time);
                return ((int) $hours * 60) + (int) $minutes;
            };

            $checkinTimeStr = $hotel->checkin_time;
            $grace = (int) $hotel->late_checkout_grace_minutes;
            $cleaning = (int) $hotel->cleaning_duration_minutes;
            $totalBufferMinutes = $grace + $cleaning;

            $checkinMinutes = $timeToMinutes($checkinTimeStr);
            $checkoutMinutes = $timeToMinutes($data['checkout_time']);

            if ($checkoutMinutes + $totalBufferMinutes > $checkinMinutes) {
                $latestAllowedMinutes = $checkinMinutes - $totalBufferMinutes;
                $latestTime = sprintf('%02d:%02d', floor($latestAllowedMinutes / 60), $latestAllowedMinutes % 60);
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'checkout_time' => "Giờ checkout không được muộn hơn {$latestTime} để đảm bảo thời gian dọn phòng ({$cleaning} phút) và trả trễ ({$grace} phút)."
                ]);
            }
        }

        $rooms = (int) ($data['rooms'] ?? 1);
        $hotel->load(['roomTypes.images', 'roomTypes.amenities', 'approvedReviews']);
        $roomTypes = $hotel->roomTypes
            ->where('active', true)
            ->filter(function (RoomType $roomType) use ($data, $rooms, $availability): bool {
                $checkinParam = isset($data['arrival_time']) ? "{$data['checkin']} {$data['arrival_time']}" : ($data['checkin'] ?? null);
                $checkoutParam = isset($data['checkout_time']) ? "{$data['checkout']} {$data['checkout_time']}" : ($data['checkout'] ?? null);
                $available = $availability->rooms($roomType, $checkinParam, $checkoutParam)->count();
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
