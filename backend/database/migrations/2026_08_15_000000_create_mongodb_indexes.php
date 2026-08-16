<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();

        $database->selectCollection('users')->createIndex(['email' => 1], ['unique' => true]);
        $database->selectCollection('users')->createIndex(['hotel_id' => 1, 'role' => 1]);
        $database->selectCollection('personal_access_tokens')->createIndex(['token' => 1], ['unique' => true]);
        $database->selectCollection('personal_access_tokens')->createIndex(['tokenable_id' => 1, 'tokenable_type' => 1]);
        $database->selectCollection('password_reset_otps')->createIndex(['email' => 1, 'created_at' => -1]);
        $database->selectCollection('password_reset_otps')->createIndex(['expires_at' => 1], ['expireAfterSeconds' => 0]);

        $database->selectCollection('hotels')->createIndex(['slug' => 1], ['unique' => true]);
        $database->selectCollection('hotels')->createIndex(['city' => 1]);
        $database->selectCollection('room_types')->createIndex(['hotel_id' => 1, 'slug' => 1], ['unique' => true]);
        $database->selectCollection('room_types')->createIndex(['hotel_id' => 1, 'price_per_night' => 1]);
        $database->selectCollection('rooms')->createIndex(['hotel_id' => 1, 'room_number' => 1], ['unique' => true]);
        $database->selectCollection('rooms')->createIndex(['room_type_id' => 1, 'operational_status' => 1]);
        $database->selectCollection('rooms')->createIndex(['hotel_id' => 1, 'operational_status' => 1, 'available_at' => 1]);
        $database->selectCollection('amenities')->createIndex(['slug' => 1], ['unique' => true]);
        $database->selectCollection('room_images')->createIndex(['room_type_id' => 1, 'sort_order' => 1]);

        $database->selectCollection('bookings')->createIndex(['code' => 1], ['unique' => true]);
        $database->selectCollection('bookings')->createIndex(
            ['idempotency_key' => 1],
            ['unique' => true, 'partialFilterExpression' => ['idempotency_key' => ['$type' => 'string']]],
        );
        $database->selectCollection('bookings')->createIndex(['hotel_id' => 1, 'checkin' => 1, 'checkout' => 1, 'status' => 1]);
        $database->selectCollection('bookings')->createIndex(['user_id' => 1, 'created_at' => -1]);
        $database->selectCollection('bookings')->createIndex(['guest_email' => 1, 'created_at' => -1]);
        $database->selectCollection('bookings')->createIndex(['status' => 1, 'hold_expires_at' => 1]);
        $database->selectCollection('room_nights')->createIndex(['room_id' => 1, 'night' => 1], ['unique' => true]);
        $database->selectCollection('room_nights')->createIndex(['booking_id' => 1]);
        $database->selectCollection('room_nights')->createIndex(['room_type_id' => 1, 'night' => 1]);

        $database->selectCollection('services')->createIndex(['hotel_id' => 1, 'code' => 1], ['unique' => true]);
        $database->selectCollection('vouchers')->createIndex(['normalized_code' => 1], ['unique' => true]);
        $database->selectCollection('voucher_redemptions')->createIndex(['booking_id' => 1], ['unique' => true]);
        $database->selectCollection('voucher_redemptions')->createIndex(['voucher_id' => 1, 'user_id' => 1]);
        $database->selectCollection('booking_services')->createIndex(['booking_id' => 1]);

        $database->selectCollection('payment_transactions')->createIndex(['uuid' => 1], ['unique' => true]);
        $database->selectCollection('payment_transactions')->createIndex(['reference' => 1], ['unique' => true]);
        $database->selectCollection('payment_transactions')->createIndex(
            ['idempotency_key' => 1],
            ['unique' => true, 'partialFilterExpression' => ['idempotency_key' => ['$type' => 'string']]],
        );
        $database->selectCollection('payment_transactions')->createIndex(['booking_id' => 1, 'status' => 1]);
        $database->selectCollection('invoices')->createIndex(['booking_id' => 1], ['unique' => true]);
        $database->selectCollection('invoices')->createIndex(['number' => 1], ['unique' => true]);
        $database->selectCollection('booking_status_histories')->createIndex(['booking_id' => 1, 'created_at' => 1]);

        $database->selectCollection('wishlists')->createIndex(['user_id' => 1, 'room_type_id' => 1], ['unique' => true]);
        $database->selectCollection('reviews')->createIndex(['booking_id' => 1, 'room_type_id' => 1], ['unique' => true]);
        $database->selectCollection('reviews')->createIndex(['hotel_id' => 1, 'status' => 1, 'created_at' => -1]);
        $database->selectCollection('outbox_events')->createIndex(['event_id' => 1], ['unique' => true]);
        $database->selectCollection('outbox_events')->createIndex(['published_at' => 1, 'occurred_at' => 1]);
    }

    public function down(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();

        foreach ([
            'outbox_events', 'reviews', 'wishlists', 'booking_status_histories', 'invoices',
            'payment_transactions', 'booking_services', 'voucher_redemptions', 'vouchers',
            'services', 'room_nights', 'bookings', 'room_images', 'amenities', 'rooms',
            'room_types', 'hotels', 'password_reset_otps', 'personal_access_tokens', 'users',
        ] as $collection) {
            $database->dropCollection($collection);
        }
    }
};
