<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();
        $database->selectCollection('oauth_exchange_codes')->createIndex(['code_hash' => 1], ['unique' => true]);
        $database->selectCollection('oauth_exchange_codes')->createIndex(['expires_at' => 1], ['expireAfterSeconds' => 0]);
        $database->selectCollection('users')->createIndex(
            ['provider' => 1, 'provider_id' => 1],
            ['unique' => true, 'partialFilterExpression' => ['provider' => ['$type' => 'string'], 'provider_id' => ['$type' => 'string']]],
        );
    }

    public function down(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();
        $database->dropCollection('oauth_exchange_codes');
        $database->selectCollection('users')->dropIndex('provider_1_provider_id_1');
    }
};
