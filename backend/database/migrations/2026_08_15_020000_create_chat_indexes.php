<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();
        $database->selectCollection('conversations')->createIndex(['hotel_id' => 1, 'status' => 1, 'last_message_at' => -1]);
        $database->selectCollection('conversations')->createIndex(['token_hash' => 1]);
        $database->selectCollection('messages')->createIndex(['conversation_id' => 1, 'created_at' => 1]);
        $database->selectCollection('messages')->createIndex(['hotel_id' => 1, 'created_at' => -1]);
    }

    public function down(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();
        $database->dropCollection('messages');
        $database->dropCollection('conversations');
    }
};
