<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class AdminAnalyticsReportTest extends TestCase
{
    use RefreshMongoDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->hotel = Hotel::query()->firstOrFail();
        $this->roomType = RoomType::query()->where('hotel_id', $this->hotel->id)->firstOrFail();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_overview_returns_daily_revenue_sources_and_behavior_for_the_requested_range(): void
    {
        Sanctum::actingAs($this->staff('hotel_manager', $this->hotel->id));
        $room = $this->roomType->rooms()->firstOrFail();

        CarbonImmutable::setTestNow('2026-08-10 10:00:00');
        $this->booking('PAID-ONLINE', $room, ['total' => 1200000, 'payment_status' => 'paid', 'source' => 'online']);
        $this->event('page_view', 'session-a', ['duration_seconds' => 20]);
        $this->event('voice_search', 'session-a', ['duration_seconds' => 10]);

        CarbonImmutable::setTestNow('2026-08-12 10:00:00');
        $this->booking('PAID-WALKIN', $room, ['total' => 800000, 'payment_status' => 'paid', 'source' => 'walk_in']);
        $this->booking('UNPAID-ONLINE', $room, ['payment_status' => 'pending', 'source' => 'online']);
        $this->event('page_view', 'session-b', ['duration_seconds' => 30]);

        CarbonImmutable::setTestNow('2026-08-20 10:00:00');
        $this->booking('OUTSIDE', $room, ['total' => 9000000, 'payment_status' => 'paid', 'source' => 'online']);
        $this->event('page_view', 'outside-session', ['duration_seconds' => 999]);

        $response = $this->getJson('/api/v1/admin/analytics?from=2026-08-10&to=2026-08-12')->assertOk();

        $response->assertJsonPath('data.total_revenue', '2000000.00')
            ->assertJsonPath('data.total_bookings', 3)
            ->assertJsonPath('data.revenue_by_period.0', ['date' => '2026-08-10', 'revenue' => '1200000.00'])
            ->assertJsonPath('data.revenue_by_period.1', ['date' => '2026-08-11', 'revenue' => '0.00'])
            ->assertJsonPath('data.revenue_by_period.2', ['date' => '2026-08-12', 'revenue' => '800000.00'])
            ->assertJsonPath('data.booking_sources.0', ['source' => 'online', 'bookings' => 2, 'percentage' => 66.67])
            ->assertJsonPath('data.booking_sources.1', ['source' => 'walk_in', 'bookings' => 1, 'percentage' => 33.33])
            ->assertJsonPath('data.behavior.page_views', 2)
            ->assertJsonPath('data.behavior.unique_sessions', 2)
            ->assertJsonPath('data.behavior.average_duration_seconds', 20)
            ->assertJsonPath('data.behavior.voice_searches', 1);
    }

    public function test_room_type_performance_is_hotel_scoped_and_reports_conversion_and_low_activity_alerts(): void
    {
        Sanctum::actingAs($this->staff('hotel_manager', $this->hotel->id));
        $room = $this->roomType->rooms()->firstOrFail();
        $quietType = RoomType::query()->where('hotel_id', $this->hotel->id)->where('id', '!=', $this->roomType->id)->firstOrFail();

        CarbonImmutable::setTestNow('2026-08-10 10:00:00');
        $this->booking('TYPE-BOOKING', $room, [
            'checkin' => '2026-08-10', 'checkout' => '2026-08-13', 'nights' => 3,
        ]);
        CarbonImmutable::setTestNow('2026-08-11 10:00:00');
        $this->event('room_view', 'viewer-a', ['room_type_id' => $this->roomType->id]);
        $this->event('room_view', 'viewer-b', ['room_type_id' => $this->roomType->id]);

        $otherHotel = Hotel::query()->create([
            'slug' => 'other-analytics', 'name' => 'Other', 'city' => 'Hue', 'address' => '1 Main',
            'checkin_time' => '14:00', 'checkout_time' => '12:00',
        ]);
        $otherType = RoomType::query()->create([
            'hotel_id' => $otherHotel->id, 'slug' => 'other', 'name' => 'Other Type',
            'max_adults' => 2, 'max_children' => 0, 'price_per_night' => 100000,
        ]);
        $this->event('room_view', 'other-viewer', ['hotel_id' => $otherHotel->id, 'room_type_id' => $otherType->id]);

        $response = $this->getJson('/api/v1/admin/analytics/room-types?from=2026-08-11&to=2026-08-12')->assertOk();
        $rows = collect($response->json('data.room_types'));
        $active = $rows->firstWhere('id', (string) $this->roomType->id);
        $quiet = $rows->firstWhere('id', (string) $quietType->id);

        $this->assertSame(1, $active['bookings']);
        $this->assertSame(2, $active['occupied_nights']);
        $this->assertSame(2, $active['views']);
        $this->assertSame(50, $active['conversion_rate']);
        $this->assertSame([], $active['alerts']);
        $this->assertContains('low_interaction', $quiet['alerts']);
        $this->assertContains('low_bookings', $quiet['alerts']);
        $this->assertFalse($rows->contains('id', (string) $otherType->id));
    }

    public function test_analytics_requires_an_allowed_role_and_rejects_cross_hotel_scope(): void
    {
        Sanctum::actingAs($this->staff('receptionist', $this->hotel->id));
        $this->getJson('/api/v1/admin/analytics')->assertForbidden();

        Sanctum::actingAs($this->staff('accountant', $this->hotel->id));
        $other = Hotel::query()->create([
            'slug' => 'forbidden-analytics', 'name' => 'Other', 'city' => 'Hue', 'address' => '1 Main',
            'checkin_time' => '14:00', 'checkout_time' => '12:00',
        ]);
        $this->getJson("/api/v1/admin/analytics?hotel_id={$other->id}")->assertForbidden();
        $this->getJson('/api/v1/admin/analytics?from=2026-08-12&to=2026-08-10')->assertUnprocessable();
    }

    private function booking(string $code, Room $room, array $overrides = []): Booking
    {
        return Booking::query()->create(array_merge([
            'code' => $code, 'guest_name' => 'Guest', 'guest_email' => strtolower($code).'@example.com',
            'guest_phone' => '0900000000', 'checkin' => '2026-08-10', 'checkout' => '2026-08-11',
            'rooms_count' => 1, 'adults' => 1, 'children' => 0, 'nights' => 1,
            'subtotal' => 500000, 'total' => 500000, 'status' => 'confirmed',
            'payment_method' => 'cash', 'payment_status' => 'pending', 'source' => 'online',
            'hotel_id' => $this->hotel->id, 'room_ids' => [$room->id],
        ], $overrides));
    }

    private function event(string $event, string $session, array $overrides = []): ActivityEvent
    {
        return ActivityEvent::query()->create(array_merge([
            'event' => $event, 'session_id' => $session, 'path' => '/hotel', 'hotel_id' => $this->hotel->id,
        ], $overrides));
    }

    private function staff(string $role, ?string $hotelId = null): User
    {
        return User::factory()->create(['role' => $role, 'hotel_id' => $hotelId, 'status' => 'active']);
    }
}
