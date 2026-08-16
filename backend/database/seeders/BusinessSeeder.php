<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Service;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = Hotel::all();

        foreach ($hotels as $hotel) {
            foreach ([
                ['AIRPORT', 'Đưa đón sân bay', 'per_booking', 350000, 250000],
                ['BREAKFAST', 'Bữa sáng', 'per_guest', 150000, 90000],
                ['EXTRA-BED', 'Giường phụ', 'per_night', 300000, 180000],
                ['SPA', 'Trị liệu Spa', 'per_unit', 500000, 300000],
            ] as [$code, $name, $pricingType, $price, $cost]) {
                Service::query()->updateOrCreate(
                    ['hotel_id' => $hotel->id, 'code' => $code],
                    ['name' => $name, 'pricing_type' => $pricingType, 'price' => $price, 'cost' => $cost, 'active' => true]
                );
            }

            if (!app()->environment('testing')) {
                Voucher::query()->updateOrCreate(
                    ['code' => strtoupper($hotel->slug) . 'WELCOME'],
                    [
                        'normalized_code' => strtoupper($hotel->slug) . 'WELCOME',
                        'hotel_id' => $hotel->id, 'type' => 'percent', 'value' => 10, 'max_discount' => 500000,
                        'min_order' => 1000000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
                        'usage_limit' => 1000, 'per_user_limit' => 1, 'active' => true,
                    ]
                );
            }
        }

        // Always keep the exact vouchers needed by test cases
        $anNhien = Hotel::query()->where('slug', 'an-nhien-da-lat')->first();
        if ($anNhien) {
            Voucher::query()->updateOrCreate(
                ['code' => 'WELCOME10'],
                [
                    'normalized_code' => 'WELCOME10',
                    'hotel_id' => null, 'type' => 'percent', 'value' => 10, 'max_discount' => 500000,
                    'min_order' => 1000000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
                    'usage_limit' => 1000, 'per_user_limit' => 1, 'active' => true,
                ]
            );
            Voucher::query()->updateOrCreate(
                ['code' => 'DALAT200'],
                [
                    'normalized_code' => 'DALAT200',
                    'hotel_id' => $anNhien->id, 'type' => 'fixed', 'value' => 200000, 'max_discount' => null,
                    'min_order' => 1500000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(6),
                    'usage_limit' => 500, 'per_user_limit' => 1, 'active' => true,
                ]
            );
        }

        // Đảm bảo tất cả các voucher hiện có trong DB tuân thủ giới hạn mới
        Voucher::query()->where('per_user_limit', '!=', 1)->update(['per_user_limit' => 1]);
        Voucher::query()->whereNull('per_user_limit')->update(['per_user_limit' => 1]);
        Voucher::query()->whereNull('usage_limit')->update(['usage_limit' => 100]);
    }
}
