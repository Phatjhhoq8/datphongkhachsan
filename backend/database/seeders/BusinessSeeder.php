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
        $hotel = Hotel::query()->where('slug', 'an-nhien-da-lat')->firstOrFail();

        foreach ([
            ['AIRPORT', 'Airport transfer', 'per_booking', 350000, 250000],
            ['BREAKFAST', 'Breakfast', 'per_person', 150000, 90000],
            ['EXTRA-BED', 'Extra bed', 'per_night', 300000, 180000],
            ['SPA', 'Spa treatment', 'quantity', 500000, 300000],
        ] as [$code, $name, $pricingType, $price, $cost]) {
            Service::query()->firstOrCreate(
                ['hotel_id' => $hotel->id, 'code' => $code],
                ['name' => $name, 'pricing_type' => $pricingType, 'price' => $price, 'cost' => $cost, 'active' => true]
            );
        }

        Voucher::query()->firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'hotel_id' => null, 'type' => 'percent', 'value' => 10, 'max_discount' => 500000,
                'min_order' => 1000000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
                'usage_limit' => 1000, 'per_user_limit' => 1, 'active' => true,
            ]
        );
        Voucher::query()->firstOrCreate(
            ['code' => 'DALAT200'],
            [
                'hotel_id' => $hotel->id, 'type' => 'fixed', 'value' => 200000, 'max_discount' => null,
                'min_order' => 1500000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(6),
                'usage_limit' => 500, 'per_user_limit' => 2, 'active' => true,
            ]
        );
    }
}
