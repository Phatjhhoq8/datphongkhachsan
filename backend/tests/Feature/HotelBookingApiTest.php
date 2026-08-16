<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\RoomNight;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class HotelBookingApiTest extends TestCase
{
    use RefreshMongoDatabase;

    private string $checkin;

    private string $checkout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->checkin = CarbonImmutable::today()->addMonth()->format('Y-m-d');
        $this->checkout = CarbonImmutable::today()->addMonth()->addDays(2)->format('Y-m-d');
    }

    public function test_search_excludes_a_fully_booked_room_type_during_an_overlap(): void
    {
        $type = RoomType::query()->where('slug', 'general')->firstOrFail();
        $hotel = $type->hotel;
        $scheduledCheckin = CarbonImmutable::parse("{$this->checkin} {$hotel->checkin_time}", $hotel->timezone)->utc();
        $scheduledCheckout = CarbonImmutable::parse("{$this->checkout} {$hotel->checkout_time}", $hotel->timezone)->utc();

        $booking = Booking::query()->create($this->bookingAttributes([
            'code' => 'DP-OVERLAP',
            'rooms_count' => 5,
            'subtotal' => 9000000,
            'total' => 9000000,
            'hotel_id' => $type->hotel_id,
            'scheduled_checkin_at' => $scheduledCheckin,
            'scheduled_checkout_at' => $scheduledCheckout,
        ]));
        $roomIds = $type->rooms()->pluck('id')->all();
        $booking->update(['hotel_id' => $type->hotel_id, 'room_ids' => $roomIds]);
        foreach ($roomIds as $roomId) {
            foreach ([$this->checkin, CarbonImmutable::parse($this->checkin)->addDay()->toDateString()] as $night) {
                RoomNight::query()->create([
                    'room_id' => $roomId, 'booking_id' => $booking->id, 'hotel_id' => $type->hotel_id,
                    'room_type_id' => $type->id, 'night' => $night, 'state' => 'booked',
                ]);
            }
        }

        $response = $this->getJson('/api/v1/search?'.http_build_query([
            'location' => 'Đà Lạt',
            'checkin' => CarbonImmutable::parse($this->checkin)->addDay()->format('Y-m-d'),
            'checkout' => CarbonImmutable::parse($this->checkout)->addDay()->format('Y-m-d'),
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
        ]));

        $response->assertOk();
        $this->assertNotContains('general', $response->json('data.*.slug'));
    }

    public function test_hotel_catalog_only_returns_real_locations_and_database_metadata(): void
    {
        $this->getJson('/api/v1/hotels?location=Đà Nẵng')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/hotels?location=Đà Lạt')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city', 'Đà Lạt')
            ->assertJsonPath('data.0.star_rating', 4)
            ->assertJsonPath('data.0.approved_reviews_count', 0)
            ->assertJsonCount(4, 'data.0.room_types');
    }

    public function test_booking_calculates_price_server_side_and_ignores_client_total(): void
    {
        $type = RoomType::query()->where('slug', 'general')->firstOrFail();

        $response = $this->postJson('/api/v1/bookings', $this->requestPayload($type->id) + [
            'subtotal' => 1,
            'total' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.nights', 2)
            ->assertJsonPath('data.subtotal', '1800000.00')
            ->assertJsonPath('data.total', '1800000.00')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.source', 'online');
        $this->assertDatabaseHas('room_nights', ['booking_id' => $response->json('data.id')]);
    }

    public function test_booking_returns_conflict_when_inventory_is_insufficient(): void
    {
        $type = RoomType::query()->where('slug', 'general')->firstOrFail();

        $this->postJson('/api/v1/bookings', $this->requestPayload($type->id, ['rooms' => 6, 'adults' => 6]))
            ->assertConflict()
            ->assertJsonPath('message', 'Not enough rooms are available for the selected dates.');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_idempotency_key_does_not_create_a_duplicate_booking(): void
    {
        $type = RoomType::query()->where('slug', 'deluxe')->firstOrFail();
        $headers = ['Idempotency-Key' => 'checkout-attempt-123'];

        $first = $this->postJson('/api/v1/bookings', $this->requestPayload($type->id), $headers)->assertCreated();
        $second = $this->postJson('/api/v1/bookings', $this->requestPayload($type->id), $headers)->assertOk();

        $this->assertSame($first->json('data.code'), $second->json('data.code'));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('room_nights', 2);
    }

    public function test_booking_detail_requires_matching_email_and_cancel_releases_inventory(): void
    {
        $type = RoomType::query()->where('slug', 'general')->firstOrFail();
        $created = $this->postJson('/api/v1/bookings', $this->requestPayload($type->id))->assertCreated();
        $code = $created->json('data.code');

        $this->getJson("/api/v1/bookings/{$code}?email=wrong@example.com")->assertNotFound();
        $this->getJson("/api/v1/bookings/{$code}?email=guest@example.com")
            ->assertOk()
            ->assertJsonPath('data.code', $code);

        $this->postJson("/api/v1/bookings/{$code}/cancel", ['email' => 'guest@example.com'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('bookings', ['code' => $code, 'status' => 'cancelled']);
        $this->getJson('/api/v1/search?'.http_build_query([
            'checkin' => $this->checkin,
            'checkout' => $this->checkout,
            'rooms' => 5,
            'adults' => 2,
            'children' => 0,
        ]))->assertOk()->assertJsonFragment(['slug' => 'general', 'available_rooms' => 5]);
    }

    private function requestPayload(string $roomTypeId, array $overrides = []): array
    {
        return array_merge([
            'room_type_id' => $roomTypeId,
            'guest_name' => 'Nguyễn Văn An',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '0901234567',
            'checkin' => $this->checkin,
            'checkout' => $this->checkout,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'payment_method' => 'pay_at_hotel',
        ], $overrides);
    }

    private function bookingAttributes(array $overrides = []): array
    {
        return array_merge([
            'code' => 'DP-TEST',
            'guest_name' => 'Existing Guest',
            'guest_email' => 'existing@example.com',
            'guest_phone' => '0900000000',
            'checkin' => $this->checkin,
            'checkout' => $this->checkout,
            'rooms_count' => 1,
            'adults' => 2,
            'children' => 0,
            'nights' => 2,
            'subtotal' => 1800000,
            'total' => 1800000,
            'status' => 'confirmed',
            'payment_method' => 'pay_at_hotel',
            'payment_status' => 'pending',
        ], $overrides);
    }
}
