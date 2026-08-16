<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class MongoIndexMigrationTest extends TestCase
{
    public function test_migrations_define_mongodb_indexes_without_sql_schema(): void
    {
        $migrationDirectory = dirname(__DIR__, 2).'/database/migrations';
        $migrations = glob($migrationDirectory.'/*.php');

        $this->assertNotEmpty($migrations);

        $source = implode("\n", array_map('file_get_contents', $migrations));
        $this->assertStringContainsString("selectCollection('room_nights')", $source);
        $this->assertStringContainsString("['room_id' => 1, 'night' => 1]", $source);
        $this->assertStringContainsString("'unique' => true", $source);
        $this->assertStringContainsString("selectCollection('bookings')", $source);
        $this->assertStringContainsString("selectCollection('personal_access_tokens')", $source);
        $this->assertStringContainsString("selectCollection('activity_events')", $source);
        $this->assertStringContainsString("['expires_at' => 1]", $source);
        $this->assertStringContainsString("'expireAfterSeconds' => 0", $source);
        $this->assertStringNotContainsString('Schema::create', $source);
        $this->assertStringNotContainsString('foreignId', $source);
    }
}
