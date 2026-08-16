<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $events = DB::connection('mongodb')->getMongoDB()->selectCollection('activity_events');

        $events->createIndex(['event' => 1, 'created_at' => -1], ['name' => 'event_created_at']);
        $events->createIndex(['session_id' => 1, 'created_at' => -1], ['name' => 'session_created_at']);
        $events->createIndex(['hotel_id' => 1, 'event' => 1, 'created_at' => -1], ['name' => 'hotel_event_created_at']);
        $events->createIndex(['room_type_id' => 1, 'created_at' => -1], ['name' => 'room_type_created_at']);
        $events->createIndex(['expires_at' => 1], ['name' => 'expires_at_ttl', 'expireAfterSeconds' => 0]);
    }

    public function down(): void
    {
        $events = DB::connection('mongodb')->getMongoDB()->selectCollection('activity_events');

        foreach (['event_created_at', 'session_created_at', 'hotel_event_created_at', 'room_type_created_at', 'expires_at_ttl'] as $index) {
            $events->dropIndex($index);
        }
    }
};
