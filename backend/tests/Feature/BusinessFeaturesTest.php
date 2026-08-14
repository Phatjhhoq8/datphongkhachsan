<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    private string $checkin;

    private string $checkout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->roomType = RoomType::query()->where('slug', 'general')->firstOrFail();
        $this->checkin = CarbonImmutable::today()->addMonth()->format('Y-m-d');
        $this->checkout = CarbonImmutable::today()->addMonth()->addDays(2)->format('Y-m-d');
    }

    public function test_quote_calculates_services_and_voucher_using_integer_vnd(): void
    {
        $breakfast = Service::query()->where('code', 'BREAKFAST')->firstOrFail();

        $this->postJson('/api/v1/quotes', $this->quotePayload([
            'service_ids' => [$breakfast->id],
            'voucher_code' => 'WELCOME10',
            'guest_email' => 'quote@example.com',
        ]))->assertOk()
            ->assertJsonPath('data.subtotal', 1800000)
            ->assertJsonPath('data.service_total', 300000)
            ->assertJsonPath('data.discount_total', 210000)
            ->assertJsonPath('data.total', 1890000)
            ->assertJsonPath('data.currency', 'VND');
    }

    public function test_hotel_services_and_voucher_validation_are_public(): void
    {
        $hotel = $this->roomType->hotel;
        $this->getJson("/api/v1/hotels/{$hotel->slug}/services")
            ->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/vouchers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['code' => 'WELCOME10']);
        $this->postJson('/api/v1/vouchers/validate', $this->quotePayload(['voucher_code' => 'DALAT200']))
            ->assertOk()->assertJsonPath('data.discount_total', 200000);
    }

    public function test_booking_snapshots_services_redeems_voucher_and_cancel_releases_quota_with_history(): void
    {
        $service = Service::query()->where('code', 'AIRPORT')->firstOrFail();
        $created = $this->postJson('/api/v1/bookings', $this->bookingPayload([
            'service_ids' => [$service->id],
            'voucher_code' => 'WELCOME10',
            'payment_option' => 'deposit',
        ]))->assertCreated();
        $bookingId = $created->json('data.id');

        $this->assertDatabaseHas('booking_services', ['booking_id' => $bookingId, 'name' => 'Airport transfer', 'unit_price' => 350000, 'total' => 350000]);
        $this->assertDatabaseHas('voucher_redemptions', ['booking_id' => $bookingId, 'amount' => 215000]);
        $this->assertSame(1, Voucher::query()->where('code', 'WELCOME10')->value('used_count'));

        $code = $created->json('data.code');
        $this->postJson("/api/v1/bookings/{$code}/cancel", ['email' => 'guest@example.com', 'reason' => 'Plans changed'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('booking_status_histories', ['booking_id' => $bookingId, 'to_status' => 'cancelled', 'reason' => 'Plans changed']);
        $this->assertDatabaseMissing('voucher_redemptions', ['booking_id' => $bookingId]);
        $this->assertSame(0, Voucher::query()->where('code', 'WELCOME10')->value('used_count'));
    }

    public function test_three_mock_methods_support_success_and_failure(): void
    {
        foreach (['paypal_mock', 'card_mock', 'vietqr_mock'] as $index => $method) {
            $booking = $this->createBooking(['guest_email' => "pay{$index}@example.com"]);
            $intent = $this->postJson("/api/v1/booking/{$booking->code}/payments/mock/intents", [
                'method' => $method,
                'email' => $booking->guest_email,
                'card_last_four' => $method === 'card_mock' ? '1234' : null,
            ])->assertCreated();
            $outcome = $index === 2 ? 'failure' : 'success';
            $this->postJson('/api/v1/payments/mock/'.$intent->json('data.reference').'/confirm', [
                'outcome' => $outcome,
                'email' => $booking->guest_email,
            ])->assertOk()->assertJsonPath('data.status', $outcome === 'success' ? 'succeeded' : 'failed');
        }
    }

    public function test_card_data_is_stripped_payment_is_idempotent_and_invoice_tracks_full_payment(): void
    {
        $booking = $this->createBooking();
        $payload = [
            'method' => 'card_mock', 'email' => $booking->guest_email, 'idempotency_key' => 'pay-once',
            'card_last_four' => '4242',
        ];
        $first = $this->postJson("/api/v1/booking/{$booking->code}/payments/mock/intents", $payload)->assertCreated();
        $second = $this->postJson("/api/v1/booking/{$booking->code}/payments/mock/intents", $payload)->assertCreated();
        $this->assertSame($first->json('data.reference'), $second->json('data.reference'));
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseHas('payment_transactions', ['card_last_four' => '4242']);
        $stored = json_encode($this->app['db']->table('payment_transactions')->value('payload'));
        $this->assertStringNotContainsString('4242', $stored);

        $reference = $first->json('data.reference');
        $confirm = ['outcome' => 'success', 'email' => $booking->guest_email];
        $this->postJson("/api/v1/payments/mock/{$reference}/confirm", $confirm)->assertOk();
        $this->postJson("/api/v1/payments/mock/{$reference}/confirm", $confirm)->assertOk();
        $this->assertDatabaseHas('invoices', ['booking_id' => $booking->id, 'paid' => 1800000, 'balance' => 0]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_state' => 'paid', 'paid_amount' => 1800000]);
    }

    public function test_deposit_then_full_payment_updates_state_and_invoice(): void
    {
        $booking = $this->createBooking(['payment_option' => 'deposit']);
        $deposit = $this->postJson("/api/v1/booking/{$booking->code}/payments/mock/intents", [
            'method' => 'paypal_mock', 'email' => $booking->guest_email,
        ])->assertCreated();
        $this->postJson('/api/v1/payments/mock/'.$deposit->json('data.reference').'/confirm', ['outcome' => 'success', 'email' => $booking->guest_email])->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'paid_amount' => 540000, 'payment_state' => 'partially_paid']);

        $full = $this->postJson("/api/v1/booking/{$booking->code}/payments/mock/intents", [
            'method' => 'vietqr_mock', 'type' => 'full', 'email' => $booking->guest_email,
        ])->assertCreated();
        $this->postJson('/api/v1/payments/mock/'.$full->json('data.reference').'/confirm', ['outcome' => 'success', 'email' => $booking->guest_email])->assertOk();
        $this->getJson("/api/v1/booking/{$booking->code}/invoice?email={$booking->guest_email}")
            ->assertOk()->assertJsonPath('data.balance', 0);
        $this->getJson("/api/v1/booking/{$booking->code}/payments?email={$booking->guest_email}")
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_cash_is_restricted_to_operational_roles(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $booking = $this->createBooking(['guest_email' => $customer->email]);
        $this->postJson("/api/v1/booking/{$booking->code}/payments/mock/intents", ['method' => 'cash'])
            ->assertForbidden();

        $receptionist = User::factory()->create(['role' => 'receptionist']);
        Sanctum::actingAs($receptionist);
        $staffBooking = $this->createBooking(['guest_email' => $receptionist->email]);
        $this->postJson("/api/v1/booking/{$staffBooking->code}/payments/mock/intents", ['method' => 'cash'])
            ->assertCreated();
    }

    public function test_wishlist_and_review_require_an_eligible_authenticated_owner(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wishlist', ['room_type_id' => $this->roomType->id])->assertCreated();
        $this->postJson('/api/v1/wishlist', ['room_type_id' => $this->roomType->id])->assertOk();
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(1, 'data');

        $booking = $this->createBooking(['guest_email' => $user->email]);
        $review = [
            'booking_code' => $booking->code, 'room_type_id' => $this->roomType->id,
            'rating_overall' => 5, 'rating_room' => 4, 'rating_service' => 5,
        ];
        $this->postJson('/api/v1/reviews', $review)->assertForbidden();
        $booking->update(['status' => 'checked_out', 'checked_out_at' => now()]);
        $this->postJson('/api/v1/reviews', $review)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->getJson('/api/v1/me/bookings')->assertOk()->assertJsonPath('data.data.0.code', $booking->code);
        $this->getJson("/api/v1/me/bookings/{$booking->code}")->assertOk();
    }

    private function createBooking(array $overrides = []): Booking
    {
        $response = $this->postJson('/api/v1/bookings', $this->bookingPayload($overrides))->assertCreated();

        return Booking::query()->findOrFail($response->json('data.id'));
    }

    private function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'room_type_id' => $this->roomType->id, 'checkin' => $this->checkin, 'checkout' => $this->checkout,
            'rooms' => 1, 'adults' => 2, 'children' => 0,
        ], $overrides);
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge($this->quotePayload(), [
            'guest_name' => 'Business Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '0901234567',
            'payment_method' => 'pay_at_hotel',
        ], $overrides);
    }
}
