<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $hotelId = Hotel::query()->value('id');

        foreach ([
            ['StayGo Admin', 'admin@staygo.local', 'Admin123!', 'super_admin', null],
            ['StayGo Reception', 'reception@staygo.local', 'Reception123!', 'receptionist', $hotelId],
            ['StayGo Accountant', 'accountant@staygo.local', 'Account123!', 'accountant', $hotelId],
        ] as [$name, $email, $password, $role, $assignedHotelId]) {
            User::query()->firstOrCreate(
                ['email' => $email],
                compact('name', 'password', 'role') + [
                    'hotel_id' => $assignedHotelId,
                    'status' => 'active',
                ]
            );
        }
    }
}
