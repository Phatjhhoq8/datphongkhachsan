<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class AdminController extends Controller
{
    protected function scopedHotelId(Request $request, ?string $requested = null): ?string
    {
        $user = $request->user();

        if ($user->role !== 'super_admin') {
            abort_if($user->hotel_id === null, 403, 'This staff account has no hotel scope.');
            abort_if($requested !== null && $requested !== (string) $user->hotel_id, 403);

            return (string) $user->hotel_id;
        }

        return $requested;
    }

    protected function scopeBookings(Builder $query, Request $request, $requested = null): Builder
    {
        $user = $request->user();

        if ($user->role !== 'super_admin') {
            abort_if($user->hotel_id === null, 403, 'This staff account has no hotel scope.');
            return $query->where('hotel_id', (string) $user->hotel_id);
        }

        if (is_array($requested)) {
            $ids = array_map('strval', $requested);
            return $query->whereIn('hotel_id', $ids);
        }

        return $query->when($requested, fn (Builder $bookingQuery) => $bookingQuery->where('hotel_id', (string) $requested));
    }
}
