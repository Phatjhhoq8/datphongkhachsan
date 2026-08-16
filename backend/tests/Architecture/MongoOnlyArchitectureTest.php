<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class MongoOnlyArchitectureTest extends TestCase
{
    public function test_backend_is_configured_for_mongodb_only(): void
    {
        $backendRoot = dirname(__DIR__, 2);

        $composer = json_decode(file_get_contents($backendRoot.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $phpunit = file_get_contents($backendRoot.'/phpunit.xml');
        $databaseConfig = file_get_contents($backendRoot.'/config/database.php');
        $queueConfig = file_get_contents($backendRoot.'/config/queue.php');

        $this->assertArrayHasKey('mongodb/laravel-mongodb', $composer['require']);
        $this->assertStringContainsString('DB_CONNECTION" value="mongodb"', $phpunit);
        $this->assertStringNotContainsString('DB_CONNECTION" value="sqlite"', $phpunit);
        $this->assertStringContainsString("env('DB_CONNECTION', 'mongodb')", $databaseConfig);
        $this->assertStringContainsString("env('QUEUE_CONNECTION', 'redis')", $queueConfig);
    }
}
