<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('pricing_type', 30);
            $table->decimal('price', 14, 0);
            $table->decimal('cost', 14, 0)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['hotel_id', 'code']);
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('type', 20);
            $table->decimal('value', 14, 0);
            $table->decimal('max_discount', 14, 0)->nullable();
            $table->decimal('min_order', 14, 0)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->text('special_requests')->nullable();
            $table->string('currency', 3)->default('VND');
            $table->decimal('service_total', 14, 0)->default(0);
            $table->decimal('discount_total', 14, 0)->default(0);
            $table->decimal('paid_amount', 14, 0)->default(0);
            $table->decimal('deposit_amount', 14, 0)->default(0);
            $table->string('payment_option', 20)->default('full');
            $table->string('payment_state', 30)->default('unpaid');
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('pricing_type', 30);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 0);
            $table->decimal('total', 14, 0);
            $table->string('status', 30)->default('pending');
            $table->timestamps();
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_email')->nullable()->index();
            $table->decimal('amount', 14, 0);
            $table->timestamp('redeemed_at');
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30);
            $table->string('type', 20);
            $table->decimal('amount', 14, 0);
            $table->string('status', 20)->default('created');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('card_last_four', 4)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->decimal('subtotal', 14, 0);
            $table->decimal('service_total', 14, 0);
            $table->decimal('discount_total', 14, 0);
            $table->decimal('total', 14, 0);
            $table->decimal('paid', 14, 0);
            $table->decimal('balance', 14, 0);
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'room_type_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating_overall');
            $table->unsignedTinyInteger('rating_room');
            $table->unsignedTinyInteger('rating_service');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['booking_id', 'room_type_id']);
            $table->index(['hotel_id', 'status']);
        });

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['published_at', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('booking_services');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn([
                'special_requests', 'currency', 'service_total', 'discount_total', 'paid_amount',
                'deposit_amount', 'payment_option', 'payment_state', 'checked_in_at', 'checked_out_at',
                'cancelled_at', 'cancellation_reason',
            ]);
        });

        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('services');
    }
};
