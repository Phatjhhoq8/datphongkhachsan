<?php

namespace App\Http\Controllers\Admin;

use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class ReviewController extends AdminController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'published', 'rejected'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'hotel_id' => ['nullable', 'string', 'exists:hotels,id'],
        ]);
        $hotelId = $this->scopedHotelId($request, isset($data['hotel_id']) ? (string) $data['hotel_id'] : null);
        $query = Review::query()->with(['user', 'hotel', 'roomType'])
            ->when($hotelId, fn (Builder $query) => $query->where('hotel_id', $hotelId))
            ->when(isset($data['status']), fn (Builder $query) => $query->where('status', $data['status']))
            ->when(isset($data['rating']), fn (Builder $query) => $query->where('rating_overall', $data['rating']))
            ->latest();

        return JsonResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function update(Request $request, Review $review): JsonResource
    {
        $hotelId = $this->scopedHotelId($request, $review->hotel_id);
        abort_if($hotelId !== null && $review->hotel_id !== $hotelId, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'published', 'rejected'])],
        ]);
        $review->update(['status' => $data['status']]);

        $hotel = $review->hotel;
        if ($hotel) {
            $avgRating = $hotel->reviews()->where('status', 'published')->avg('rating_overall');
            $hotel->update(['rating' => round($avgRating ?? 0, 1)]);
        }

        return new JsonResource($review->load(['user', 'hotel', 'roomType']));
    }
}
