<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class MongoOnlyArchitectureTest extends TestCase
{
    public function test_backend_is_configured_for_hybrid_db(): void
    {
        $backendRoot = dirname(__DIR__, 2);

        $composer = json_decode(file_get_contents($backendRoot.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $phpunit = file_get_contents($backendRoot.'/phpunit.xml');
        $databaseConfig = file_get_contents($backendRoot.'/config/database.php');

        $this->assertArrayHasKey('mongodb/laravel-mongodb', $composer['require']);
        $this->assertStringContainsString('DB_CONNECTION" value="mysql"', $phpunit);
        $this->assertStringContainsString("env('DB_CONNECTION', 'mysql')", $databaseConfig);
        $this->assertStringContainsString("'mysql' => [", $databaseConfig);
        $this->assertStringContainsString("'mongodb' => [", $databaseConfig);
    }
}
