<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::query()->firstOrCreate(
            ['slug' => 'an-nhien-da-lat'],
            [
                'name' => 'An Nhiên Đà Lạt Hotel',
                'city' => 'Đà Lạt',
                'address' => '18 Trần Phú, Phường 3, Đà Lạt, Lâm Đồng',
                'rating' => 4.8,
                'star_rating' => 4,
                'description' => 'Khách sạn nghỉ dưỡng thanh lịch giữa trung tâm Đà Lạt, dành riêng cho những kỳ nghỉ thư thái.',
                'checkin_time' => '14:00:00',
                'checkout_time' => '12:00:00',
                'hero_image' => '/images/rooms/4/1.jpg',
            ]
        );

        $amenities = collect([
            'wifi' => 'Wifi',
            'ho-boi' => 'Hồ bơi',
            'an-sang' => 'Ăn sáng',
            'dieu-hoa' => 'Điều hòa',
            'spa' => 'Spa',
            'dua-don-san-bay' => 'Đưa đón sân bay',
        ])->map(fn (string $name, string $slug) => Amenity::query()->firstOrCreate(['slug' => $slug], ['name' => $name]));

        $types = [
            ['general', 'Phòng Tiêu Chuẩn', 'GR', 900000, 2, 1, false, false],
            ['deluxe', 'Phòng Cao Cấp', 'DR', 1500000, 2, 2, true, true],
            ['executive', 'Phòng Hạng Thương Gia', 'ER', 2200000, 3, 2, true, true],
            ['luxury', 'Phòng Hạng Sang', 'LR', 3200000, 4, 2, true, true],
        ];

        foreach ($types as $index => [$slug, $name, $prefix, $price, $adults, $children, $refundable, $breakfast]) {
            $roomType = RoomType::query()->firstOrCreate(
                ['hotel_id' => $hotel->id, 'slug' => $slug],
                [
                    'name' => $name,
                    'description' => "Không gian {$name} ấm cúng, tiện nghi và có tầm nhìn đặc trưng của Đà Lạt.",
                    'max_adults' => $adults,
                    'max_children' => $children,
                    'price_per_night' => $price,
                    'refundable' => $refundable,
                    'breakfast_included' => $breakfast,
                ]
            );

            $roomType->amenities()->syncWithoutDetaching($amenities->take($index + 3)->pluck('id'));

            foreach (range(1, 4) as $image) {
                RoomImage::query()->firstOrCreate(
                    ['room_type_id' => $roomType->id, 'sort_order' => $image],
                    ['url' => '/images/rooms/'.($index + 1)."/{$image}.jpg"]
                );
            }

            foreach (range(101, 105) as $number) {
                Room::query()->firstOrCreate(
                    ['hotel_id' => $hotel->id, 'room_number' => "{$prefix}-{$number}"],
                    ['room_type_id' => $roomType->id, 'floor' => 1, 'operational_status' => 'available']
                );
            }
        }

        $this->call([
            AuthSeeder::class,
            BusinessSeeder::class,
        ]);
    }
}
