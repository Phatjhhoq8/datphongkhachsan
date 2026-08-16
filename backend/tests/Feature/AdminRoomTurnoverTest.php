<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class AdminRoomTurnoverTest extends TestCase
{
    use RefreshMongoDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::query()->create([
            'slug' => 'turnover-hotel',
            'name' => 'Turnover Hotel',
            'city' => 'Da Lat',
            'address' => '1 Main Street',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'checkin_time' => '15:00',
            'checkout_time' => '12:00',
            'late_checkout_grace_minutes' => 30,
            'cleaning_duration_minutes' => 150,
        ]);
        $this->roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'slug' => 'standard',
            'name' => 'Standard',
            'max_adults' => 2,
            'max_children' => 1,
            'price_per_night' => 1000000,
        ]);
        $this->room = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => '101',
            'floor' => 1,
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'receptionist',
            'hotel_id' => $this->hotel->id,
            'status' => 'active',
        ]));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_advance_booking_snapshots_hotel_turnover_policy(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 03:00:00 UTC');
        $this->room->update([
            'operational_status' => 'cleaning',
            'available_at' => CarbonImmutable::parse('2026-08-15 08:00:00 UTC'),
        ]);

        $response = $this->postJson('/api/v1/admin/bookings/counter', [
            'room_type_id' => (string) $this->roomType->id,
            'room_ids' => [(string) $this->room->id],
            'guest_name' => 'Advance Guest',
            'guest_email' => 'advance@example.com',
            'guest_phone' => '0900000000',
            'checkin' => '2026-09-01',
            'checkout' => '2026-09-02',
            'adults' => 1,
            'children' => 0,
        ])->assertCreated();

        $booking = Booking::query()->findOrFail($response->json('data.id'));
        $this->assertSame('2026-09-01 08:00:00', $booking->scheduled_checkin_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-02 05:00:00', $booking->scheduled_checkout_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(30, $booking->late_checkout_grace_minutes_snapshot);
        $this->assertSame(150, $booking->cleaning_duration_minutes_snapshot);
    }

    public function test_booking_rejects_a_cleaning_room_not_available_by_scheduled_checkin(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 03:00:00 UTC');
        $this->room->update([
            'operational_status' => 'cleaning',
            'available_at' => CarbonImmutable::parse('2026-09-01 08:01:00 UTC'),
        ]);

        $this->postJson('/api/v1/admin/bookings/counter', [
            'room_type_id' => (string) $this->roomType->id,
            'room_ids' => [(string) $this->room->id],
            'guest_name' => 'Too Early Guest',
            'guest_email' => 'too-early@example.com',
            'guest_phone' => '0900000000',
            'checkin' => '2026-09-01',
            'checkout' => '2026-09-02',
            'adults' => 1,
            'children' => 0,
        ])->assertConflict();
    }

    public function test_checkout_atomically_starts_cleaning_and_sets_on_time_availability_to_15_00(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 05:00:00 UTC');
        $booking = $this->booking('checked_in');

        $this->postJson("/api/v1/admin/bookings/{$booking->id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_out');

        $booking->refresh();
        $this->room->refresh();
        $this->assertSame('2026-09-02 05:00:00', $booking->checked_out_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('cleaning', $this->room->operational_status);
        $this->assertSame('2026-09-02 08:00:00', $this->room->available_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-02 05:00:00', $this->room->cleaning_started_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_actual_late_checkout_pushes_available_at(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 06:00:00 UTC');
        $booking = $this->booking('checked_in');

        $this->postJson("/api/v1/admin/bookings/{$booking->id}/check-out")->assertOk();

        $this->room->refresh();
        $this->assertSame('2026-09-02 08:30:00', $this->room->available_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_cleaning_complete_does_not_bypass_available_at_and_checkin_succeeds_at_15_00(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 07:45:00 UTC');
        $this->room->update([
            'operational_status' => 'cleaning',
            'cleaning_started_at' => CarbonImmutable::parse('2026-09-02 05:00:00 UTC'),
            'available_at' => CarbonImmutable::parse('2026-09-02 08:00:00 UTC'),
        ]);
        $booking = $this->booking('confirmed');

        $this->postJson("/api/v1/admin/rooms/{$this->room->id}/cleaning-complete")
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'available');
        $this->postJson("/api/v1/admin/bookings/{$booking->id}/check-in")->assertUnprocessable();

        CarbonImmutable::setTestNow('2026-09-02 08:00:00 UTC');
        $this->postJson("/api/v1/admin/bookings/{$booking->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in');
    }

    public function test_checkin_is_blocked_while_room_is_still_cleaning_even_after_available_at(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 08:00:00 UTC');
        $this->room->update([
            'operational_status' => 'cleaning',
            'available_at' => CarbonImmutable::parse('2026-09-02 07:30:00 UTC'),
        ]);
        $booking = $this->booking('confirmed');

        $this->postJson("/api/v1/admin/bookings/{$booking->id}/check-in")->assertUnprocessable();
    }

    private function booking(string $status): Booking
    {
        $booking = Booking::query()->create([
            'code' => 'TURN-'.strtoupper($status),
            'guest_name' => 'Turnover Guest',
            'guest_email' => 'turnover@example.com',
            'guest_phone' => '0900000000',
            'checkin' => '2026-09-02',
            'checkout' => '2026-09-02',
            'rooms_count' => 1,
            'adults' => 1,
            'children' => 0,
            'nights' => 1,
            'subtotal' => 1000000,
            'total' => 1000000,
            'status' => $status,
            'hotel_id' => (string) $this->hotel->id,
            'room_ids' => [(string) $this->room->id],
            'scheduled_checkin_at' => CarbonImmutable::parse('2026-09-02 08:00:00 UTC'),
            'scheduled_checkout_at' => CarbonImmutable::parse('2026-09-02 05:00:00 UTC'),
            'late_checkout_grace_minutes_snapshot' => 30,
            'cleaning_duration_minutes_snapshot' => 150,
        ]);

        return $booking;
    }

    public function test_custom_checkout_time_validation_and_late_checkout_fee(): void
    {
        $type = $this->roomType;
        
        $this->postJson('/api/v1/bookings', [
            'room_type_id' => $type->id,
            'guest_name' => 'Late Guest',
            'guest_email' => 'late@example.com',
            'guest_phone' => '0901234567',
            'checkin' => CarbonImmutable::tomorrow()->toDateString(),
            'checkout' => CarbonImmutable::tomorrow()->addDay()->toDateString(),
            'rooms' => 1,
            'adults' => 2,
            'payment_method' => 'pay_at_hotel',
            'checkout_time' => '13:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['checkout_time']);

        $response = $this->postJson('/api/v1/bookings', [
            'room_type_id' => $type->id,
            'guest_name' => 'Late Guest',
            'guest_email' => 'late@example.com',
            'guest_phone' => '0901234567',
            'checkin' => CarbonImmutable::tomorrow()->toDateString(),
            'checkout' => CarbonImmutable::tomorrow()->addDay()->toDateString(),
            'rooms' => 1,
            'adults' => 2,
            'payment_method' => 'pay_at_hotel',
            'checkout_time' => '12:00',
        ])->assertCreated();

        $bookingId = $response->json('data.id');
        $booking = Booking::query()->findOrFail($bookingId);
        
        $booking->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'scheduled_checkout_at' => CarbonImmutable::now()->subMinutes(55),
            'late_checkout_grace_minutes_snapshot' => 30,
        ]);
        
        $booking->invoice()->create([
            'number' => 'INV-' . $booking->code,
            'hotel_id' => $booking->hotel_id,
            'customer_name' => $booking->guest_name,
            'customer_email' => $booking->guest_email,
            'room_total' => $booking->subtotal,
            'service_total' => 0,
            'discount_total' => 0,
            'total' => $booking->total,
            'paid_amount' => 0,
            'balance' => $booking->total,
            'status' => 'unpaid',
        ]);
        
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        
        $this->postJson("/api/v1/admin/bookings/{$booking->id}/check-out")->assertOk();
        
        $booking->refresh();
        
        $this->assertSame(1100000.0, (float) $booking->total);
        $this->assertSame(100000.0, (float) $booking->service_total);
        $this->assertSame(1100000.0, (float) $booking->invoice->total);
        $this->assertSame(100000.0, (float) $booking->invoice->service_total);
        $this->assertSame(1100000.0, (float) $booking->invoice->balance);
        
        $this->assertStringContainsString('Charged late checkout fee of 100,000 VND', $booking->statusHistories()->orderByDesc('id')->first()->reason);
    }
}
