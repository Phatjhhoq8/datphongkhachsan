<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class HotelScope
{
    public static function allows(User $user, string $hotelId): bool
    {
        return $user->role === 'super_admin' || $user->hotel_id === $hotelId;
    }

    public static function apply(Builder $query, User $user, string $column = 'hotel_id'): Builder
    {
        if ($user->role === 'super_admin') {
            return $query;
        }

        return $user->hotel_id === null
            ? $query->where($query->getModel()->getKeyName(), '__no_hotel_scope__')
            : $query->where($column, $user->hotel_id);
    }
}
