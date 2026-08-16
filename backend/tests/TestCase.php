<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    public function createApplication(): Application
    {
        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=mysql');
        putenv('DB_DATABASE=datphong_test');
        putenv('MONGODB_DATABASE=datphong_test');
        putenv('MONGODB_URI=mongodb://mongodb:27017/datphong_test?replicaSet=rs0');
        putenv('APP_LOCALE=en');

        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = 'datphong_test';
        $_ENV['MONGODB_DATABASE'] = $_SERVER['MONGODB_DATABASE'] = 'datphong_test';
        $_ENV['MONGODB_URI'] = $_SERVER['MONGODB_URI'] = 'mongodb://mongodb:27017/datphong_test?replicaSet=rs0';
        $_ENV['APP_LOCALE'] = $_SERVER['APP_LOCALE'] = 'en';

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.locale' => 'en']);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame('mysql', config('database.default'), 'Test phải sử dụng MySQL làm kết nối mặc định.');
        $this->assertStringStartsWith('datphong_test', config('database.connections.mysql.database'), 'Test phải sử dụng MySQL database dành riêng cho kiểm thử.');
        $this->assertStringStartsWith('datphong_test', config('database.connections.mongodb.database'), 'Test phải sử dụng MongoDB database dành riêng cho kiểm thử.');
    }
}
