<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. hotels
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('city');
            $table->text('address');
            $table->decimal('rating', 3, 1)->default(0.0);
            $table->integer('star_rating')->default(0);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->string('timezone')->default('Asia/Ho_Chi_Minh');
            $table->text('description')->nullable();
            $table->string('checkin_time')->default('15:00');
            $table->string('checkout_time')->default('12:00');
            $table->integer('late_checkout_grace_minutes')->default(30);
            $table->integer('cleaning_duration_minutes')->default(150);
            $table->string('hero_image')->nullable();
            $table->integer('free_cancellation_hours')->default(24);
            $table->integer('late_cancellation_fee_percent')->default(30);
            $table->timestamps();
        });

        // 2. users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->unsignedBigInteger('hotel_id')->nullable();
            $table->string('status')->default('active');
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('set null');
        });

        // 3. personal_access_tokens (Sanctum)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
        });

        // 4. password_reset_otps
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->integer('attempts')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });

        // 5. oauth_exchange_codes
        Schema::create('oauth_exchange_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash');
            $table->unsignedBigInteger('user_id');
            $table->string('provider');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 6. amenities
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // 7. room_types
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_id');
            $table->string('slug');
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('size_m2', 8, 2)->nullable();
            $table->string('bed_description')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('max_adults');
            $table->integer('max_children')->default(0);
            $table->decimal('price_per_night', 12, 2);
            $table->decimal('base_cost_per_night', 12, 2)->nullable();
            $table->boolean('refundable')->default(false);
            $table->boolean('breakfast_included')->default(false);
            $table->timestamps();

            $table->unique(['hotel_id', 'slug']);
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
        });

        // 8. room_type_amenity (pivot)
        Schema::create('room_type_amenity', function (Blueprint $table) {
            $table->unsignedBigInteger('room_type_id');
            $table->unsignedBigInteger('amenity_id');
            $table->timestamps();

            $table->primary(['room_type_id', 'amenity_id']);
            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('cascade');
            $table->foreign('amenity_id')->references('id')->on('amenities')->onDelete('cascade');
        });

        // 9. rooms
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('room_type_id');
            $table->string('room_number');
            $table->integer('floor');
            $table->boolean('active')->default(true);
            $table->decimal('map_x', 8, 2)->nullable();
            $table->decimal('map_y', 8, 2)->nullable();
            $table->string('operational_status')->default('available');
            $table->timestamp('cleaning_started_at')->nullable();
            $table->timestamp('cleaning_completed_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'room_number']);
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('cascade');
        });

        // 10. room_images
        Schema::create('room_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_type_id');
            $table->string('path')->nullable();
            $table->string('url');
            $table->integer('sort_order');
            $table->timestamps();

            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('cascade');
        });

        // 11. vouchers
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_id')->nullable();
            $table->string('code');
            $table->string('normalized_code')->unique();
            $table->string('type');
            $table->integer('value');
            $table->integer('max_discount')->nullable();
            $table->integer('min_order')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->integer('usage_limit');
            $table->integer('per_user_limit');
            $table->integer('used_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
        });

        // 12. bookings
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone');
            $table->date('checkin');
            $table->date('checkout');
            $table->integer('rooms_count');
            $table->integer('adults');
            $table->integer('children');
            $table->integer('nights');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->text('special_requests')->nullable();
            $table->string('currency')->default('VND');
            $table->integer('service_total')->default(0);
            $table->integer('discount_total')->default(0);
            $table->integer('paid_amount')->default(0);
            $table->integer('deposit_amount')->default(0);
            $table->string('payment_option')->nullable();
            $table->string('payment_state')->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('hotel_id');
            $table->json('room_ids')->nullable();
            $table->timestamp('scheduled_checkin_at')->nullable();
            $table->timestamp('scheduled_checkout_at')->nullable();
            $table->integer('late_checkout_grace_minutes_snapshot')->nullable();
            $table->integer('cleaning_duration_minutes_snapshot')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->boolean('refundable')->default(true);
            $table->timestamp('free_cancellation_until')->nullable();
            $table->integer('late_cancellation_fee_percent')->default(100);
            $table->integer('cancellation_fee')->default(0);
            $table->integer('refund_amount')->default(0);
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['status', 'hold_expires_at']);
            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
        });

        // 13. booking_room (pivot)
        Schema::create('booking_room', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('room_id');
            $table->timestamps();

            $table->primary(['booking_id', 'room_id']);
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
        });

        // 14. room_nights
        Schema::create('room_nights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('room_type_id');
            $table->date('night');
            $table->string('state');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'night']);
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('cascade');
        });

        // 15. services
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('pricing_type');
            $table->integer('price');
            $table->integer('cost');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
        });

        // 16. voucher_redemptions
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->unique();
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('guest_email')->nullable();
            $table->integer('amount');
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 17. booking_services
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('service_id');
            $table->string('name');
            $table->string('pricing_type');
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->integer('total');
            $table->string('status')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });

        // 18. booking_status_histories
        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
        });

        // 19. payment_transactions
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('booking_id');
            $table->string('method');
            $table->string('type');
            $table->integer('amount');
            $table->string('status');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('card_last_four')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
        });

        // 20. invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->unique();
            $table->string('number')->unique();
            $table->integer('subtotal')->default(0);
            $table->integer('service_total')->default(0);
            $table->integer('discount_total')->default(0);
            $table->integer('total')->default(0);
            $table->integer('paid')->default(0);
            $table->integer('balance')->default(0);
            $table->integer('cancellation_fee')->default(0);
            $table->integer('refunded')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
        });

        // 21. wishlists
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('room_type_id');
            $table->timestamps();

            $table->unique(['user_id', 'room_type_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('cascade');
        });

        // 22. reviews
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('room_type_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('hotel_id');
            $table->integer('rating_overall');
            $table->integer('rating_room');
            $table->integer('rating_service');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->unique(['booking_id', 'room_type_id']);
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
        });

        // 23. outbox_events
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('booking_services');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('services');
        Schema::dropIfExists('room_nights');
        Schema::dropIfExists('booking_room');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('room_images');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_type_amenity');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('oauth_exchange_codes');
        Schema::dropIfExists('password_reset_otps');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('hotels');
    }
};
