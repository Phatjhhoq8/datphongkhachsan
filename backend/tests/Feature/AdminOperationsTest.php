<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->hotel = Hotel::query()->firstOrFail();
    }

    public function test_customer_is_forbidden_from_admin_operations(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'status' => 'active']));

        $this->getJson('/api/v1/admin/hotels')->assertForbidden();
    }

    public function test_only_super_admin_can_create_staff(): void
    {
        Sanctum::actingAs($this->staff('super_admin'));

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Reception Staff', 'email' => 'staff@example.com', 'password' => 'password123',
            'role' => 'receptionist', 'hotel_id' => $this->hotel->id,
        ])->assertCreated()->assertJsonPath('data.role', 'receptionist');

        $this->assertDatabaseHas('users', ['email' => 'staff@example.com', 'role' => 'receptionist']);
    }

    public function test_manager_is_restricted_to_their_hotel(): void
    {
        $other = Hotel::query()->create($this->hotelData('other-hotel'));
        Sanctum::actingAs($this->staff('hotel_manager', $this->hotel->id));

        $this->getJson('/api/v1/admin/hotels')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/hotels/{$other->id}")->assertForbidden();
    }

    public function test_counter_booking_is_paid_by_cash(): void
    {
        Sanctum::actingAs($this->staff('receptionist', $this->hotel->id));
        $type = RoomType::query()->firstOrFail();

        $response = $this->postJson('/api/v1/admin/bookings/counter', $this->counterPayload($type))->assertCreated();

        $response->assertJsonPath('data.payment_method', 'cash')->assertJsonPath('data.payment_status', 'paid');
        $this->assertDatabaseHas('bookings', ['id' => $response->json('data.id'), 'status' => 'confirmed', 'payment_method' => 'cash']);
        $this->assertDatabaseHas('payment_transactions', [
            'booking_id' => $response->json('data.id'),
            'method' => 'cash',
            'status' => 'succeeded',
        ]);
    }

    public function test_booking_transitions_and_room_cleaning_flow(): void
    {
        Sanctum::actingAs($this->staff('receptionist', $this->hotel->id));
        $type = RoomType::query()->firstOrFail();
        $bookingId = $this->postJson('/api/v1/admin/bookings/counter', $this->counterPayload($type))->json('data.id');
        $booking = Booking::query()->findOrFail($bookingId);
        $room = $booking->rooms()->firstOrFail();

        $this->postJson("/api/v1/admin/bookings/{$booking->id}/check-in")->assertOk()->assertJsonPath('data.status', 'checked_in');
        $this->postJson("/api/v1/admin/bookings/{$booking->id}/check-out")->assertOk()->assertJsonPath('data.status', 'checked_out');
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'operational_status' => 'cleaning']);

        $this->postJson("/api/v1/admin/rooms/{$room->id}/cleaning-complete")->assertOk()->assertJsonPath('data.operational_status', 'available');
    }

    public function test_invalid_booking_transition_is_rejected(): void
    {
        Sanctum::actingAs($this->staff('receptionist', $this->hotel->id));
        $type = RoomType::query()->firstOrFail();
        $bookingId = $this->postJson('/api/v1/admin/bookings/counter', $this->counterPayload($type))->json('data.id');

        $this->postJson("/api/v1/admin/bookings/{$bookingId}/check-out")->assertUnprocessable();
    }

    public function test_dashboard_returns_scoped_counts(): void
    {
        Sanctum::actingAs($this->staff('hotel_manager', $this->hotel->id));
        $type = RoomType::query()->firstOrFail();
        $payload = $this->counterPayload($type);
        $payload['checkin'] = CarbonImmutable::today()->toDateString();
        $payload['checkout'] = CarbonImmutable::tomorrow()->toDateString();
        $this->postJson('/api/v1/admin/bookings/counter', $payload)->assertCreated();

        $this->getJson('/api/v1/admin/dashboard')->assertOk()
            ->assertJsonPath('data.bookings_count', 1)
            ->assertJsonPath('data.rooms_total', Room::query()->where('hotel_id', $this->hotel->id)->count());
    }

    private function staff(string $role, ?int $hotelId = null): User
    {
        return User::factory()->create(['role' => $role, 'hotel_id' => $hotelId, 'status' => 'active']);
    }

    private function counterPayload(RoomType $type): array
    {
        $checkin = CarbonImmutable::today()->addDay()->toDateString();

        return [
            'room_type_id' => $type->id, 'rooms' => 1, 'guest_name' => 'Walk-in Guest',
            'guest_email' => 'walkin@example.com', 'guest_phone' => '0900000000',
            'checkin' => $checkin, 'checkout' => CarbonImmutable::parse($checkin)->addDay()->toDateString(),
            'adults' => 1, 'children' => 0,
        ];
    }

    private function hotelData(string $slug): array
    {
        return [
            'slug' => $slug, 'name' => 'Other Hotel', 'city' => 'Hanoi', 'address' => '1 Main Street',
            'checkin_time' => '14:00', 'checkout_time' => '12:00',
        ];
    }
}
