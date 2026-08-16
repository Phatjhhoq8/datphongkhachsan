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
        $hotelsData = [
            [
                'slug' => 'an-nhien-da-lat',
                'name' => 'An Nhiên Đà Lạt Hotel',
                'city' => 'Đà Lạt',
                'address' => '18 Trần Phú, Phường 3, Đà Lạt, Lâm Đồng',
                'rating' => 4.8,
                'star_rating' => 4,
                'description' => 'Khách sạn nghỉ dưỡng thanh lịch giữa trung tâm Đà Lạt, dành riêng cho những kỳ nghỉ thư thái.',
                'checkin_time' => '15:00:00',
                'checkout_time' => '12:00:00',
                'hero_image' => '/images/rooms/4/1.jpg',
            ]
        ];

        if (!app()->environment('testing')) {
            $hotelsData = array_merge($hotelsData, [
                [
                    'slug' => 'hanoi-grand-mercure',
                    'name' => 'Hanoi Grand Mercure Hotel',
                    'city' => 'Hà Nội',
                    'address' => '12 Cát Linh, Quốc Tử Giám, Đống Đa, Hà Nội',
                    'rating' => 4.7,
                    'star_rating' => 5,
                    'description' => 'Trải nghiệm sự sang trọng và nét văn hoá truyền thống tại trái tim của thủ đô Hà Nội.',
                    'checkin_time' => '14:00:00',
                    'checkout_time' => '12:00:00',
                    'hero_image' => '/images/rooms/3/1.jpg',
                ],
                [
                    'slug' => 'saigon-riverside-luxury',
                    'name' => 'Saigon Riverside Luxury Hotel',
                    'city' => 'Hồ Chí Minh',
                    'address' => '2A Tôn Đức Thắng, Bến Nghé, Quận 1, Hồ Chí Minh',
                    'rating' => 4.9,
                    'star_rating' => 5,
                    'description' => 'Tầm nhìn tuyệt đẹp hướng ra sông Sài Gòn cùng dịch vụ đẳng cấp hoàng gia.',
                    'checkin_time' => '14:00:00',
                    'checkout_time' => '12:00:00',
                    'hero_image' => '/images/rooms/4/2.jpg',
                ],
                [
                    'slug' => 'danang-beach-resort',
                    'name' => 'Da Nang Beach Resort & Spa',
                    'city' => 'Đà Nẵng',
                    'address' => '274 Võ Nguyên Giáp, Mỹ An, Ngũ Hành Sơn, Đà Nẵng',
                    'rating' => 4.6,
                    'star_rating' => 4,
                    'description' => 'Toạ lạc ngay cạnh bãi biển Mỹ Khê xinh đẹp, nơi lý tưởng cho kỳ nghỉ gia đình.',
                    'checkin_time' => '14:00:00',
                    'checkout_time' => '12:00:00',
                    'hero_image' => '/images/rooms/2/1.jpg',
                ],
                [
                    'slug' => 'phu-quoc-sunset-paradise',
                    'name' => 'Phu Quoc Sunset Paradise Resort',
                    'city' => 'Phú Quốc',
                    'address' => 'Bãi Trường, Dương Tơ, Phú Quốc, Kiên Giang',
                    'rating' => 4.8,
                    'star_rating' => 5,
                    'description' => 'Ngắm hoàng hôn tuyệt mỹ bên bờ biển riêng cát trắng mịn màng của Phú Quốc.',
                    'checkin_time' => '14:00:00',
                    'checkout_time' => '12:00:00',
                    'hero_image' => '/images/rooms/4/3.jpg',
                ],
                [
                    'slug' => 'nha-trang-ocean-view',
                    'name' => 'Nha Trang Ocean View Hotel',
                    'city' => 'Nha Trang',
                    'address' => '36 Trần Phú, Lộc Thọ, Nha Trang, Khánh Hòa',
                    'rating' => 4.5,
                    'star_rating' => 4,
                    'description' => 'Gần gũi thiên nhiên, ban công hướng biển đón gió lành từ vịnh Nha Trang xanh mát.',
                    'checkin_time' => '14:00:00',
                    'checkout_time' => '12:00:00',
                    'hero_image' => '/images/rooms/1/1.jpg',
                ]
            ]);
        }

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

        foreach ($hotelsData as $hotelItem) {
            $hotel = Hotel::query()->firstOrCreate(
                ['slug' => $hotelItem['slug']],
                [
                    'name' => $hotelItem['name'],
                    'city' => $hotelItem['city'],
                    'address' => $hotelItem['address'],
                    'rating' => $hotelItem['rating'],
                    'star_rating' => $hotelItem['star_rating'],
                    'description' => $hotelItem['description'],
                    'checkin_time' => $hotelItem['checkin_time'],
                    'checkout_time' => $hotelItem['checkout_time'],
                    'hero_image' => $hotelItem['hero_image'],
                ]
            );

            // Factor for prices depending on star rating
            $priceMultiplier = $hotel->star_rating == 5 ? 1.5 : 1.0;

            foreach ($types as $index => [$slug, $name, $prefix, $price, $adults, $children, $refundable, $breakfast]) {
                $roomType = RoomType::query()->firstOrCreate(
                    ['hotel_id' => $hotel->id, 'slug' => $slug],
                    [
                        'name' => $name,
                        'description' => "Không gian {$name} ấm cúng, tiện nghi và sang trọng tại {$hotel->city}.",
                        'max_adults' => $adults,
                        'max_children' => $children,
                        'price_per_night' => round($price * $priceMultiplier),
                        'refundable' => $refundable,
                        'breakfast_included' => $breakfast,
                    ]
                );

                $roomType->amenities()->syncWithoutDetaching($amenities->take($index + 3)->pluck('id')->all());

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
        }

        $this->call([
            AuthSeeder::class,
            BusinessSeeder::class,
        ]);
    }
}
