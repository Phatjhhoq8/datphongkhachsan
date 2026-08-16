<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Hotel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RoomTurnoverService
{
    public function bookingSnapshot(Hotel $hotel, string $checkin, string $checkout, ?string $arrivalTime = null, ?string $checkoutTime = null): array
    {
        $time = $arrivalTime ?: $hotel->checkin_time;
        $outTime = $checkoutTime ?: $hotel->checkout_time;
        return [
            'scheduled_checkin_at' => $this->hotelTime($checkin, $time, $hotel->timezone),
            'scheduled_checkout_at' => $this->hotelTime($checkout, $outTime, $hotel->timezone),
            'late_checkout_grace_minutes_snapshot' => (int) $hotel->late_checkout_grace_minutes,
            'cleaning_duration_minutes_snapshot' => (int) $hotel->cleaning_duration_minutes,
        ];
    }

    public function availableAfterCheckout(Booking $booking, CarbonInterface $actualCheckout): CarbonImmutable
    {
        $hotel = Hotel::query()->findOrFail($booking->hotel_id);
        $scheduledCheckout = $booking->scheduled_checkout_at
            ? CarbonImmutable::instance($booking->scheduled_checkout_at)
            : $this->hotelTime($booking->checkout->toDateString(), $hotel->checkout_time, $hotel->timezone);
        $grace = $booking->late_checkout_grace_minutes_snapshot ?? $hotel->late_checkout_grace_minutes;
        $cleaning = $booking->cleaning_duration_minutes_snapshot ?? $hotel->cleaning_duration_minutes;
        $turnoverStartsAt = $scheduledCheckout->addMinutes((int) $grace)->max($actualCheckout);

        return $turnoverStartsAt->addMinutes((int) $cleaning);
    }

    public function scheduledCheckin(Booking $booking): CarbonImmutable
    {
        if ($booking->scheduled_checkin_at) {
            return CarbonImmutable::instance($booking->scheduled_checkin_at);
        }

        $hotel = Hotel::query()->findOrFail($booking->hotel_id);

        return $this->hotelTime($booking->checkin->toDateString(), $hotel->checkin_time, $hotel->timezone);
    }

    private function hotelTime(string $date, string $time, string $timezone): CarbonImmutable
    {
        $hasTime = str_contains($date, ' ') || str_contains($date, 'T');
        $dateTimeString = $hasTime ? $date : "{$date} {$time}";
        return CarbonImmutable::parse($dateTimeString, $timezone)->utc();
    }
}
