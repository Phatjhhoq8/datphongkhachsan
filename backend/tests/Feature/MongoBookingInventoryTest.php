<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\RoomNight;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class MongoBookingInventoryTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_booking_claims_unique_room_nights_in_the_same_transaction(): void
    {
        $this->seed(DatabaseSeeder::class);
        $roomType = RoomType::query()->where('slug', 'general')->firstOrFail();
        $checkin = CarbonImmutable::today()->addMonth()->toDateString();
        $checkout = CarbonImmutable::parse($checkin)->addDays(2)->toDateString();

        $response = $this->postJson('/api/v1/bookings', [
            'room_type_id' => $roomType->id,
            'guest_name' => 'Khách kiểm thử',
            'guest_email' => 'inventory@example.com',
            'guest_phone' => '0901234567',
            'checkin' => $checkin,
            'checkout' => $checkout,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'payment_method' => 'pay_at_hotel',
        ], ['Idempotency-Key' => 'mongo-room-night-test'])->assertCreated();

        $booking = Booking::query()->findOrFail($response->json('data.id'));

        $this->assertCount(1, $booking->room_ids);
        $this->assertSame(2, RoomNight::query()->where('booking_id', $booking->id)->count());
        $this->assertSame([$checkin, CarbonImmutable::parse($checkin)->addDay()->toDateString()],
            RoomNight::query()->where('booking_id', $booking->id)->orderBy('night')->pluck('night')->all());
        $this->assertSame(1, OutboxEvent::query()->where('aggregate_id', $booking->id)->where('event_type', 'booking.created')->count());
    }
}
