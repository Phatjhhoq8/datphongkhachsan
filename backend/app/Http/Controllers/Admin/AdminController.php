<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class AdminController extends Controller
{
    protected function scopedHotelId(Request $request, ?int $requested = null): ?int
    {
        $user = $request->user();

        if ($user->role !== 'super_admin') {
            abort_if($user->hotel_id === null, 403, 'This staff account has no hotel scope.');
            abort_if($requested !== null && $requested !== (int) $user->hotel_id, 403);

            return (int) $user->hotel_id;
        }

        return $requested;
    }

    protected function scopeBookings(Builder $query, Request $request, ?int $requested = null): Builder
    {
        $hotelId = $this->scopedHotelId($request, $requested);

        return $query->when($hotelId, fn (Builder $bookingQuery) => $bookingQuery
            ->whereHas('rooms', fn (Builder $roomQuery) => $roomQuery->where('rooms.hotel_id', $hotelId)));
    }
}
