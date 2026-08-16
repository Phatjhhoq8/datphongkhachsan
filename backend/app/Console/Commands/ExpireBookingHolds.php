<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingStateService;
use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Expire unpaid online booking holds and release their inventory';

    public function handle(BookingStateService $states): int
    {
        $expired = 0;

        Booking::query()
            ->where('status', 'pending')
            ->where('hold_expires_at', '<=', now())
            ->each(function (Booking $booking) use ($states, &$expired) {
                if ($states->expireHold($booking)) {
                    $expired++;
                }
            });

        $this->info("Expired {$expired} booking hold(s).");

        return self::SUCCESS;
    }
}
