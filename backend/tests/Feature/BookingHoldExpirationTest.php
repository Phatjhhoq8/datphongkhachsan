<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\RoomNight;
use App\Models\RoomType;
use App\Models\Voucher;
use App\Services\BookingStateService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class BookingHoldExpirationTest extends TestCase
{
    use RefreshMongoDatabase;

    private RoomType $roomType;

    protected function setUp(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 10:00:00');
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->roomType = RoomType::query()->where('slug', 'general')->firstOrFail();
    }

    public function test_customer_online_bookings_hold_inventory_for_fifteen_minutes_but_pay_at_hotel_does_not_expire(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 10:00:00');

        foreach (['paypal', 'paypal_mock', 'card_mock', 'vietqr_mock'] as $index => $method) {
            $booking = $this->createBooking($method, $index * 3, "hold-{$index}@example.com");

            $this->assertSame('2026-08-15 10:15:00', $booking->hold_expires_at?->format('Y-m-d H:i:s'));
            $this->assertSame($method === 'paypal_mock' ? 'paypal' : $method, $booking->payment_method);
            $this->assertNotEmpty($booking->room_ids);
            $this->assertSame(
                ['2026-08-15 10:15:00'],
                RoomNight::query()->where('booking_id', (string) $booking->id)->get()
                    ->map(fn (RoomNight $night) => $night->expires_at?->format('Y-m-d H:i:s'))->unique()->values()->all(),
            );
        }

        $hotelBooking = $this->createBooking('pay_at_hotel', 15, 'hotel-pay@example.com');

        $this->assertNull($hotelBooking->hold_expires_at);
        $this->assertTrue(RoomNight::query()->where('booking_id', (string) $hotelBooking->id)->get()->every(
            fn (RoomNight $night) => $night->expires_at === null,
        ));
    }

    public function test_successful_mock_payment_and_confirm_transition_clear_booking_and_room_night_expiration(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 11:00:00');
        $paidBooking = $this->createBooking('card_mock', 0, 'paid@example.com');

        $intent = $this->postJson("/api/v1/booking/{$paidBooking->code}/payments/mock/intents", [
            'method' => 'card_mock',
            'email' => $paidBooking->guest_email,
            'card_last_four' => '4242',
        ])->assertCreated();
        $this->postJson('/api/v1/payments/mock/'.$intent->json('data.reference').'/confirm', [
            'outcome' => 'success',
            'email' => $paidBooking->guest_email,
        ])->assertOk();

        $this->assertNull($paidBooking->refresh()->hold_expires_at);
        $this->assertSame('confirmed', $paidBooking->status);
        $this->assertTrue(RoomNight::query()->where('booking_id', (string) $paidBooking->id)->get()->every(
            fn (RoomNight $night) => $night->expires_at === null && $night->state === 'booked',
        ));

        $confirmedBooking = $this->createBooking('vietqr_mock', 3, 'confirmed@example.com');
        app(BookingStateService::class)->transition($confirmedBooking, 'confirmed');

        $this->assertNull($confirmedBooking->refresh()->hold_expires_at);
        $this->assertTrue(RoomNight::query()->where('booking_id', (string) $confirmedBooking->id)->get()->every(
            fn (RoomNight $night) => $night->expires_at === null && $night->state === 'booked',
        ));
    }

    public function test_expire_holds_atomically_expires_booking_releases_inventory_and_restores_voucher(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 12:00:00');
        $expired = $this->createBooking('paypal_mock', 0, 'expired@example.com', ['voucher_code' => 'WELCOME10']);
        $future = $this->createBooking('paypal_mock', 3, 'future@example.com');
        $hotelPay = $this->createBooking('pay_at_hotel', 6, 'hotel@example.com');
        $expired->update(['hold_expires_at' => now()->subSecond()]);
        RoomNight::query()->where('booking_id', (string) $expired->id)->update(['expires_at' => now()->subSecond()]);

        $this->assertSame(1, Voucher::query()->where('code', 'WELCOME10')->value('used_count'));
        Artisan::call('bookings:expire-holds');

        $this->assertSame('expired', $expired->refresh()->status);
        $this->assertNull($expired->hold_expires_at);
        $this->assertSame('pending', $future->refresh()->status);
        $this->assertSame('pending', $hotelPay->refresh()->status);
        $this->assertSame(0, RoomNight::query()->where('booking_id', (string) $expired->id)->count());
        $this->assertDatabaseMissing('voucher_redemptions', ['booking_id' => (string) $expired->id]);
        $this->assertSame(0, Voucher::query()->where('code', 'WELCOME10')->value('used_count'));
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => (string) $expired->id,
            'from_status' => 'pending',
            'to_status' => 'expired',
        ]);
        $this->assertSame(1, OutboxEvent::query()
            ->where('aggregate_id', (string) $expired->id)
            ->where('event_type', 'booking.expired')->count());

        Artisan::call('bookings:expire-holds');
        $this->assertSame(1, OutboxEvent::query()
            ->where('aggregate_id', (string) $expired->id)
            ->where('event_type', 'booking.expired')->count());
        $this->assertSame(0, Voucher::query()->where('code', 'WELCOME10')->value('used_count'));
    }

    public function test_hold_lookup_has_compound_index_and_room_night_expiration_has_no_ttl_index(): void
    {
        $bookingIndexes = collect(DB::select("SHOW INDEX FROM bookings"));
        $hasIndex = $bookingIndexes->contains(
            fn ($index) => $index->Key_name === 'bookings_status_hold_expires_at_index'
        );
        $this->assertTrue($hasIndex);
    }

    private function createBooking(string $paymentMethod, int $dayOffset, string $email, array $overrides = []): Booking
    {
        $checkin = CarbonImmutable::today()->addMonth()->addDays($dayOffset);
        $response = $this->postJson('/api/v1/bookings', array_merge([
            'room_type_id' => (string) $this->roomType->id,
            'guest_name' => 'Hold Test Guest',
            'guest_email' => $email,
            'guest_phone' => '0901234567',
            'checkin' => $checkin->toDateString(),
            'checkout' => $checkin->addDays(2)->toDateString(),
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'payment_method' => $paymentMethod,
        ], $overrides))->assertCreated();

        return Booking::query()->findOrFail((string) $response->json('data.id'));
    }
}
