<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class CancellationPolicyTest extends TestCase
{
    use RefreshMongoDatabase;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->hotel = Hotel::query()->firstOrFail();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 12:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_hotel_defaults_and_public_booking_snapshot_refundable_policy(): void
    {
        $this->assertSame(24, $this->hotel->free_cancellation_hours);
        $this->assertSame(30, $this->hotel->late_cancellation_fee_percent);

        $roomType = RoomType::query()->where('slug', 'deluxe')->firstOrFail();
        $created = $this->postJson('/api/v1/bookings', $this->bookingPayload($roomType, '2026-09-10', '2026-09-12'))
            ->assertCreated()
            ->assertJsonPath('data.refundable', true)
            ->assertJsonPath('data.late_cancellation_fee_percent', 30);

        $booking = Booking::query()->findOrFail($created->json('data.id'));
        $this->assertSame('2026-09-09 15:00:00', $booking->free_cancellation_until->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'));

        $roomType->update(['refundable' => false]);
        $this->assertTrue($booking->refresh()->refundable);
    }

    public function test_counter_booking_snapshots_policy(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'receptionist',
            'hotel_id' => $this->hotel->id,
            'status' => 'active',
        ]));
        $roomType = RoomType::query()->where('slug', 'deluxe')->firstOrFail();

        $this->postJson('/api/v1/admin/bookings/counter', $this->counterPayload($roomType))
            ->assertCreated()
            ->assertJsonPath('data.refundable', true)
            ->assertJsonPath('data.late_cancellation_fee_percent', 30)
            ->assertJsonPath('data.free_cancellation_until', '2026-09-09T08:00:00.000000Z');
    }

    public function test_pending_unpaid_booking_is_free_even_when_non_refundable(): void
    {
        $roomType = RoomType::query()->where('slug', 'general')->firstOrFail();
        $booking = $this->createBooking($roomType, '2026-09-02', '2026-09-03');

        $this->postJson("/api/v1/bookings/{$booking->code}/cancel", ['email' => $booking->guest_email])
            ->assertOk()
            ->assertJsonPath('data.cancellation_fee', 0)
            ->assertJsonPath('data.refund_amount', 0);
    }

    public function test_refundable_booking_is_free_at_exact_deadline_and_charged_one_second_after(): void
    {
        $roomType = RoomType::query()->where('slug', 'deluxe')->firstOrFail();
        $atDeadline = $this->createPaidConfirmedBooking($roomType, 'deadline@example.com');
        CarbonImmutable::setTestNow($atDeadline->free_cancellation_until);

        $this->postJson("/api/v1/bookings/{$atDeadline->code}/cancel", ['email' => $atDeadline->guest_email])
            ->assertOk()
            ->assertJsonPath('data.cancellation_fee', 0)
            ->assertJsonPath('data.refund_amount', 3000000);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 12:00:00', 'Asia/Ho_Chi_Minh'));
        $late = $this->createPaidConfirmedBooking($roomType, 'late@example.com');
        CarbonImmutable::setTestNow($late->free_cancellation_until->addSecond());

        $this->postJson("/api/v1/bookings/{$late->code}/cancel", ['email' => $late->guest_email])
            ->assertOk()
            ->assertJsonPath('data.cancellation_fee', 900000)
            ->assertJsonPath('data.refund_amount', 2100000);
    }

    public function test_non_refundable_confirmed_booking_charges_full_total(): void
    {
        $roomType = RoomType::query()->where('slug', 'general')->firstOrFail();
        $booking = $this->createPaidConfirmedBooking($roomType, 'non-refundable@example.com');

        $this->postJson("/api/v1/bookings/{$booking->code}/cancel", ['email' => $booking->guest_email])
            ->assertOk()
            ->assertJsonPath('data.cancellation_fee', 1800000)
            ->assertJsonPath('data.refund_amount', 0);
    }

    public function test_paid_cancellation_appends_mock_refund_and_updates_invoice_atomically(): void
    {
        $roomType = RoomType::query()->where('slug', 'deluxe')->firstOrFail();
        $booking = $this->createPaidConfirmedBooking($roomType, 'refund@example.com');
        CarbonImmutable::setTestNow($booking->free_cancellation_until->addSecond());

        $this->postJson("/api/v1/bookings/{$booking->code}/cancel", ['email' => $booking->guest_email])->assertOk();

        $this->assertDatabaseHas('payment_transactions', [
            'booking_id' => $booking->id,
            'type' => 'refund',
            'amount' => 2100000,
            'status' => 'refunded',
        ]);
        $this->assertDatabaseCount('payment_transactions', 2);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'paid_amount' => 900000,
            'payment_state' => 'partially_refunded',
        ]);
        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking->id,
            'cancellation_fee' => 900000,
            'refunded' => 2100000,
            'paid' => 900000,
            'balance' => 0,
        ]);
        $this->assertDatabaseMissing('room_nights', ['booking_id' => $booking->id]);
        $this->assertDatabaseHas('outbox_events', ['aggregate_id' => (string) $booking->id, 'event_type' => 'booking.cancelled']);
    }

    public function test_public_lookup_cancel_and_payment_mutations_are_throttled(): void
    {
        foreach ([
            ['GET', 'api/v1/bookings/{booking}'],
            ['POST', 'api/v1/bookings/{booking}/cancel'],
            ['POST', 'api/v1/bookings/{booking}/payments/mock/intents'],
            ['POST', 'api/v1/booking/{booking}/payments/mock/intents'],
            ['POST', 'api/v1/payments/mock/{payment}/confirm'],
        ] as [$method, $uri]) {
            $route = collect(RouteFacade::getRoutes()->getRoutes())->first(
                fn (Route $route) => in_array($method, $route->methods(), true) && $route->uri() === $uri
            );

            $this->assertNotNull($route, "Missing route {$method} {$uri}");
            $this->assertTrue(
                collect($route->gatherMiddleware())->contains(fn (string $middleware) => str_starts_with($middleware, 'throttle:')),
                "Route {$method} {$uri} must be throttled"
            );
        }
    }

    private function createBooking(RoomType $roomType, string $checkin = '2026-09-10', string $checkout = '2026-09-12', string $email = 'guest@example.com'): Booking
    {
        $response = $this->postJson('/api/v1/bookings', $this->bookingPayload($roomType, $checkin, $checkout, $email))->assertCreated();

        return Booking::query()->findOrFail($response->json('data.id'));
    }

    private function createPaidConfirmedBooking(RoomType $roomType, string $email): Booking
    {
        $booking = $this->createBooking($roomType, email: $email);
        $booking->update([
            'status' => 'confirmed',
            'paid_amount' => (int) $booking->total,
            'payment_state' => 'paid',
            'payment_status' => 'paid',
            'hold_expires_at' => null,
        ]);
        $booking->payments()->create([
            'uuid' => fake()->uuid(),
            'reference' => 'PAY-'.strtoupper(fake()->bothify('????????????????')),
            'method' => 'card_mock',
            'type' => 'full',
            'amount' => (int) $booking->total,
            'status' => 'succeeded',
            'payload' => ['provider' => 'mock'],
            'processed_at' => now(),
        ]);
        Invoice::query()->create([
            'booking_id' => $booking->id,
            'number' => 'INV-'.$booking->code,
            'subtotal' => (int) $booking->subtotal,
            'service_total' => 0,
            'discount_total' => 0,
            'total' => (int) $booking->total,
            'paid' => (int) $booking->total,
            'balance' => 0,
            'issued_at' => now(),
        ]);

        return $booking->refresh();
    }

    private function bookingPayload(RoomType $roomType, string $checkin, string $checkout, string $email = 'guest@example.com'): array
    {
        return [
            'room_type_id' => $roomType->id,
            'guest_name' => 'Cancellation Guest',
            'guest_email' => $email,
            'guest_phone' => '0901234567',
            'checkin' => $checkin,
            'checkout' => $checkout,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'payment_method' => 'pay_at_hotel',
        ];
    }

    private function counterPayload(RoomType $roomType): array
    {
        return [
            'room_type_id' => $roomType->id,
            'rooms' => 1,
            'guest_name' => 'Counter Guest',
            'guest_email' => 'counter@example.com',
            'guest_phone' => '0901234567',
            'checkin' => '2026-09-10',
            'checkout' => '2026-09-12',
            'adults' => 2,
            'children' => 0,
        ];
    }
}
