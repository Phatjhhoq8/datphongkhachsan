<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class MongoIndexMigrationTest extends TestCase
{
    public function test_migrations_define_mongodb_and_sql_schemas(): void
    {
        $migrationDirectory = dirname(__DIR__, 2).'/database/migrations';
        $migrations = glob($migrationDirectory.'/*.php');

        $this->assertNotEmpty($migrations);

        $source = implode("\n", array_map('file_get_contents', $migrations));
        
        // Assert MongoDB indexes are defined
        $this->assertStringContainsString("selectCollection('conversations')", $source);
        $this->assertStringContainsString("selectCollection('messages')", $source);
        $this->assertStringContainsString("selectCollection('activity_events')", $source);
        
        // Assert MySQL tables are defined
        $tableNames = [
            'hotels', 'users', 'personal_access_tokens', 'password_reset_otps',
            'oauth_exchange_codes', 'amenities', 'room_types', 'room_type_amenity',
            'rooms', 'room_images', 'vouchers', 'bookings', 'booking_room',
            'room_nights', 'services', 'voucher_redemptions', 'booking_services',
            'booking_status_histories', 'payment_transactions', 'invoices',
            'wishlists', 'reviews', 'outbox_events'
        ];

        foreach ($tableNames as $table) {
            $this->assertStringContainsString("Schema::create('{$table}'", $source);
        }
    }
}
