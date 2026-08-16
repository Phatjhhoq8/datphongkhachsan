<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $database = getenv('TEST_DB_DATABASE') ?: getenv('DB_DATABASE') ?: 'datphong_test';
        $uri = getenv('TEST_MONGODB_URI') ?: getenv('MONGODB_URI') ?: "mongodb://127.0.0.1:27017/{$database}?replicaSet=rs0";

        putenv('DB_CONNECTION=mongodb');
        putenv("DB_DATABASE={$database}");
        putenv("MONGODB_URI={$uri}");
        $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'mongodb';
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $database;
        $_ENV['MONGODB_URI'] = $_SERVER['MONGODB_URI'] = $uri;

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mongodb', config('database.default'), 'Test phải sử dụng MongoDB dành riêng cho kiểm thử.');
        $this->assertStringStartsWith('datphong_test', config('database.connections.mongodb.database'));
    }
}
