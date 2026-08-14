<?php

namespace App\Policies;

use App\Models\Hotel;
use App\Models\User;
use App\Support\HotelScope;

class HotelPolicy
{
    public function access(User $user, Hotel $hotel): bool
    {
        return HotelScope::allows($user, $hotel->id);
    }
}
