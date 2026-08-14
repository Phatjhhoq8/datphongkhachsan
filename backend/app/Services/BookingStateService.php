<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingStateService
{
    public function transition(Booking $booking, string $status, ?string $reason = null, ?int $actorId = null): Booking
    {
        $allowed = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['checked_in', 'cancelled'],
            'checked_in' => ['checked_out'],
        ];

        if (! in_array($status, $allowed[$booking->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Cannot transition booking from {$booking->status} to {$status}."]);
        }

        return DB::transaction(function () use ($booking, $status, $reason, $actorId) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $allowed = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['checked_in', 'cancelled'],
                'checked_in' => ['checked_out'],
            ];
            if (! in_array($status, $allowed[$booking->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Cannot transition booking from {$booking->status} to {$status}."]);
            }

            $from = $booking->status;
            $attributes = ['status' => $status];

            if ($status === 'cancelled') {
                $attributes += ['cancelled_at' => now(), 'cancellation_reason' => $reason];
                if ($booking->redemption) {
                    $booking->voucher()->lockForUpdate()->first()?->decrement('used_count');
                    $booking->redemption->delete();
                }
            } elseif ($status === 'checked_in') {
                $attributes['checked_in_at'] = now();
            } elseif ($status === 'checked_out') {
                $attributes['checked_out_at'] = now();
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
}
