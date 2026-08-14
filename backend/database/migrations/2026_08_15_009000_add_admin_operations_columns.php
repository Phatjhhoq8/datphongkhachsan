<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedTinyInteger('star_rating')->nullable()->after('rating');
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('status', 20)->default('active')->index()->after('email');
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh')->after('status');
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('slug');
            $table->decimal('size_m2', 8, 2)->nullable()->after('description');
            $table->string('bed_description')->nullable()->after('size_m2');
            $table->boolean('active')->default(true)->index()->after('bed_description');
            $table->decimal('base_cost_per_night', 12, 2)->nullable()->after('price_per_night');
            $table->unique(['hotel_id', 'code'], 'room_types_hotel_code_unique');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('active')->default(true)->index()->after('floor');
            $table->decimal('map_x', 8, 2)->nullable()->after('active');
            $table->decimal('map_y', 8, 2)->nullable()->after('map_x');
        });

        DB::statement('UPDATE rooms SET hotel_id = (SELECT room_types.hotel_id FROM room_types WHERE room_types.id = rooms.room_type_id) WHERE hotel_id IS NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('customer')->index()->after('password');
            $table->foreignId('hotel_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active')->index()->after('hotel_id');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_id');
            $table->dropColumn(['role', 'status']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_id');
            $table->dropColumn(['active', 'map_x', 'map_y']);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropUnique('room_types_hotel_code_unique');
            $table->dropColumn(['code', 'size_m2', 'bed_description', 'active', 'base_cost_per_night']);
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['star_rating', 'phone', 'email', 'status', 'timezone']);
        });
    }
};
