<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\Room;
use App\Models\RoomNight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingStateService
{
    public function __construct(private readonly RoomTurnoverService $turnover) {}

    public function transition(Booking $booking, string $status, ?string $reason = null, ?string $actorId = null): Booking
    {
        $allowed = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['checked_in', 'cancelled'],
            'checked_in' => ['checked_out'],
            'expired' => [],
        ];

        if (! in_array($status, $allowed[$booking->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Cannot transition booking from {$booking->status} to {$status}."]);
        }

        return DB::transaction(function () use ($booking, $status, $reason, $actorId) {
            $booking = Booking::query()->findOrFail($booking->id);
            $allowed = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['checked_in', 'cancelled'],
                'checked_in' => ['checked_out'],
                'expired' => [],
            ];
            if (! in_array($status, $allowed[$booking->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Cannot transition booking from {$booking->status} to {$status}."]);
            }

            $from = $booking->status;
            $attributes = ['status' => $status];

            if ($status === 'confirmed') {
                $attributes['hold_expires_at'] = null;
                RoomNight::query()->where('booking_id', $booking->id)->update([
                    'state' => 'booked',
                    'expires_at' => null,
                ]);
            } elseif ($status === 'cancelled') {
                $attributes += ['cancelled_at' => now(), 'cancellation_reason' => $reason];
                if ($booking->redemption) {
                    $booking->voucher?->decrement('used_count');
                    $booking->redemption->delete();
                }
                RoomNight::query()->where('booking_id', $booking->id)->delete();
            } elseif ($status === 'checked_in') {
                $attributes['checked_in_at'] = now();
            } elseif ($status === 'checked_out') {
                $checkedOutAt = now();
                $attributes['checked_out_at'] = $checkedOutAt;

                $hotel = \App\Models\Hotel::query()->findOrFail($booking->hotel_id);
                $scheduledCheckout = $booking->scheduled_checkout_at
                    ? \Carbon\CarbonImmutable::instance($booking->scheduled_checkout_at)->utc()
                    : \Carbon\CarbonImmutable::parse("{$booking->checkout->toDateString()} {$hotel->checkout_time}", $hotel->timezone)->utc();
                
                $grace = (int) ($booking->late_checkout_grace_minutes_snapshot ?? $hotel->late_checkout_grace_minutes);
                $graceCheckoutTime = $scheduledCheckout->addMinutes($grace);
                $actualCheckoutUtc = $checkedOutAt->utc();
                
                if ($actualCheckoutUtc->gt($graceCheckoutTime)) {
                    $lateMinutes = abs($actualCheckoutUtc->diffInMinutes($scheduledCheckout));
                    $lateHours = (int) ceil($lateMinutes / 60);
                    
                    $pricePerNight = $booking->nights > 0 ? ((float) $booking->subtotal / $booking->nights) : 0;
                    $lateFeePerHour = round($pricePerNight * 0.1);
                    $totalLateFee = $lateHours * $lateFeePerHour;
                    
                    if ($totalLateFee > 0) {
                        $booking->increment('total', $totalLateFee);
                        $booking->increment('service_total', $totalLateFee);
                        
                        $invoice = $booking->invoice;
                        if ($invoice) {
                            $invoice->increment('service_total', $totalLateFee);
                            $invoice->increment('total', $totalLateFee);
                            $invoice->increment('balance', $totalLateFee);
                        }
                        
                        $reason = ($reason ? $reason . ". " : "") . "Checked out late by {$lateMinutes} minutes. Charged late checkout fee of " . number_format($totalLateFee) . " VND.";
                    }
                }

                Room::query()->whereIn('id', $booking->room_ids ?? [])->update([
                    'operational_status' => 'cleaning',
                    'cleaning_started_at' => $checkedOutAt,
                    'cleaning_completed_at' => null,
                    'available_at' => $this->turnover->availableAfterCheckout($booking, $checkedOutAt),
                ]);
            }

            $booking->update($attributes);
            $booking->statusHistories()->create(['from_status' => $from, 'to_status' => $status, 'reason' => $reason, 'actor_id' => $actorId]);
            OutboxEvent::query()->create([
                'event_id' => (string) Str::uuid(), 'aggregate_type' => 'booking', 'aggregate_id' => (string) $booking->id,
                'event_type' => "booking.{$status}", 'payload' => ['booking_id' => $booking->id, 'code' => $booking->code], 'occurred_at' => now(),
            ]);

            return $booking->refresh();
        });
    }

    public function expireHold(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()
                ->whereKey($booking->id)
                ->where('status', 'pending')
                ->where('hold_expires_at', '<=', now())
                ->first();

            if (! $booking) {
                return false;
            }

            if ($booking->redemption) {
                $booking->voucher?->decrement('used_count');
                $booking->redemption->delete();
            }

            RoomNight::query()->where('booking_id', $booking->id)->delete();
            $booking->update(['status' => 'expired', 'hold_expires_at' => null]);
            $booking->statusHistories()->create([
                'from_status' => 'pending',
                'to_status' => 'expired',
                'reason' => 'Online payment hold expired',
            ]);
            OutboxEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'aggregate_type' => 'booking',
                'aggregate_id' => (string) $booking->id,
                'event_type' => 'booking.expired',
                'payload' => ['booking_id' => (string) $booking->id, 'code' => $booking->code],
                'occurred_at' => now(),
            ]);

            return true;
        }, 3);
    }
}
