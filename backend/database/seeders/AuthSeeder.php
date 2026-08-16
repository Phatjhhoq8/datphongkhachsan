<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Hotel;
use Illuminate\Database\Seeder;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        // Dọn dẹp các tài khoản cũ trong database để chỉ giữ lại bộ tài khoản test mới
        $testEmails = [
            'admin@gmail.com',
            'manager@gmail.com',
            'receptionist@gmail.com',
            'accountant@gmail.com',
            'customer@gmail.com'
        ];
        User::query()->whereNotIn('email', $testEmails)->delete();

        // Lấy khách sạn đầu tiên để gán cho các vai trò quản lý
        $hotel = Hotel::query()->first();
        $hotelId = $hotel ? $hotel->id : null;

        // 1. Super Admin
        User::query()->updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'System Admin',
                'password' => 'admin123',
                'role' => 'super_admin',
                'hotel_id' => null,
                'status' => 'active',
            ]
        );

        // 2. Hotel Manager (Quản lý khách sạn)
        User::query()->updateOrCreate(
            ['email' => 'manager@gmail.com'],
            [
                'name' => 'Hotel Manager',
                'password' => 'manager123',
                'role' => 'hotel_manager',
                'hotel_id' => $hotelId,
                'status' => 'active',
            ]
        );

        // 3. Receptionist (Lễ tân)
        User::query()->updateOrCreate(
            ['email' => 'receptionist@gmail.com'],
            [
                'name' => 'Hotel Receptionist',
                'password' => 'receptionist123',
                'role' => 'receptionist',
                'hotel_id' => $hotelId,
                'status' => 'active',
            ]
        );

        // 4. Accountant (Kế toán)
        User::query()->updateOrCreate(
            ['email' => 'accountant@gmail.com'],
            [
                'name' => 'Hotel Accountant',
                'password' => 'accountant123',
                'role' => 'accountant',
                'hotel_id' => $hotelId,
                'status' => 'active',
            ]
        );

        // 5. Customer (Khách hàng thông thường)
        User::query()->updateOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Normal Customer',
                'password' => 'customer123',
                'role' => 'customer',
                'hotel_id' => null,
                'status' => 'active',
            ]
        );

        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        if ($email && $password) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => env('SEED_ADMIN_NAME', 'System Admin'),
                    'password' => $password,
                    'role' => 'super_admin',
                    'hotel_id' => null,
                    'status' => 'active',
                ]
            );
        }
    }
}
