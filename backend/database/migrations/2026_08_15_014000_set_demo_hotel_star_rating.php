<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('hotels')
            ->where('slug', 'an-nhien-da-lat')
            ->whereNull('star_rating')
            ->update(['star_rating' => 4]);
    }

    public function down(): void
    {
        DB::table('hotels')
            ->where('slug', 'an-nhien-da-lat')
            ->where('star_rating', 4)
            ->update(['star_rating' => null]);
    }
};
