<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('city')->index();
            $table->string('address');
            $table->decimal('rating', 2, 1)->default(0);
            $table->text('description')->nullable();
            $table->time('checkin_time');
            $table->time('checkout_time');
            $table->string('hero_image')->nullable();
            $table->timestamps();
        });

        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_adults');
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->decimal('price_per_night', 12, 2);
            $table->boolean('refundable')->default(false);
            $table->boolean('breakfast_included')->default(false);
            $table->timestamps();
            $table->unique(['hotel_id', 'slug']);
            $table->index('price_per_night');
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('room_number')->unique();
            $table->unsignedSmallInteger('floor')->nullable();
            $table->enum('operational_status', ['available', 'cleaning', 'maintenance', 'out_of_service'])->default('available')->index();
            $table->timestamps();
            $table->index(['room_type_id', 'operational_status']);
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('room_type_amenity', function (Blueprint $table) {
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['room_type_id', 'amenity_id']);
        });

        Schema::create('room_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['room_type_id', 'sort_order']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('guest_name');
            $table->string('guest_email')->index();
            $table->string('guest_phone', 30);
            $table->date('checkin');
            $table->date('checkout');
            $table->unsignedTinyInteger('rooms_count');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('nights');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total', 14, 2);
            $table->enum('status', ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'])->default('pending')->index();
            $table->enum('payment_method', ['pay_at_hotel', 'paypal']);
            $table->enum('payment_status', ['pending', 'paid', 'refunded'])->default('pending');
            $table->timestamps();
            $table->index(['checkin', 'checkout', 'status']);
        });

        Schema::create('booking_room', function (Blueprint $table) {
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['booking_id', 'room_id']);
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('room_images');
        Schema::dropIfExists('room_type_amenity');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('hotels');
    }
};
