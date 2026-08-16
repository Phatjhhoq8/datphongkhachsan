<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\RoomNight;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CancellationService
{
    public function snapshot(Hotel $hotel, RoomType $roomType, CarbonInterface $scheduledCheckin): array
    {
        return [
            'refundable' => (bool) $roomType->refundable,
            'free_cancellation_until' => CarbonImmutable::instance($scheduledCheckin)
                ->subHours((int) $hotel->free_cancellation_hours),
            'late_cancellation_fee_percent' => (int) $hotel->late_cancellation_fee_percent,
        ];
    }

    public function cancel(Booking $booking, ?string $reason = null, ?string $actorId = null): Booking
    {
        return DB::transaction(function () use ($booking, $reason, $actorId) {
            $booking = Booking::query()->findOrFail($booking->id);
            if (! in_array($booking->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['status' => 'This booking can no longer be cancelled.']);
            }

            if ($booking->refundable === null || $booking->free_cancellation_until === null || $booking->late_cancellation_fee_percent === null) {
                $hotel = Hotel::query()->findOrFail($booking->hotel_id);
                $roomType = $booking->rooms()->first()?->roomType;
                $booking->update($this->snapshot(
                    $hotel,
                    $roomType ?? RoomType::query()->where('hotel_id', $hotel->id)->firstOrFail(),
                    $booking->scheduled_checkin_at ?? CarbonImmutable::parse("{$booking->checkin->toDateString()} {$hotel->checkin_time}", $hotel->timezone)->utc(),
                ));
                $booking->refresh();
            }

            $paid = (int) ($booking->paid_amount ?? 0);
            $fee = $this->fee($booking, $paid);
            $refund = max(0, $paid - $fee);
            $retained = $paid - $refund;

            if ($booking->redemption) {
                $booking->voucher?->decrement('used_count');
                $booking->redemption->delete();
            }
            RoomNight::query()->where('booking_id', $booking->id)->delete();

            $paymentState = $booking->payment_state ?? 'unpaid';
            $paymentStatus = $booking->payment_status ?? 'pending';
            if ($refund > 0) {
                $paymentState = $retained > 0 ? 'partially_refunded' : 'refunded';
                $paymentStatus = $paymentState;
                $payment = PaymentTransaction::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'reference' => 'REF-'.Str::upper(Str::random(16)),
                    'booking_id' => $booking->id,
                    'method' => 'mock',
                    'type' => 'refund',
                    'amount' => $refund,
                    'status' => 'refunded',
                    'payload' => ['provider' => 'mock', 'reason' => 'booking_cancelled'],
                    'processed_at' => now(),
                    'actor_id' => $actorId,
                ]);
                $this->outbox('payment', $payment->id, 'payment.refunded', [
                    'booking_id' => (string) $booking->id,
                    'reference' => $payment->reference,
                    'amount' => $refund,
                ]);
            }

            $from = $booking->status;
            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_fee' => $fee,
                'refund_amount' => $refund,
                'paid_amount' => $retained,
                'payment_state' => $paymentState,
                'payment_status' => $paymentStatus,
                'hold_expires_at' => null,
            ]);
            $booking->statusHistories()->create([
                'from_status' => $from,
                'to_status' => 'cancelled',
                'reason' => $reason,
                'actor_id' => $actorId,
            ]);

            if ($paid > 0 || $fee > 0 || $booking->invoice) {
                Invoice::query()->updateOrCreate(['booking_id' => $booking->id], [
                    'number' => 'INV-'.$booking->code,
                    'subtotal' => (int) $booking->subtotal,
                    'service_total' => (int) ($booking->service_total ?? 0),
                    'discount_total' => (int) ($booking->discount_total ?? 0),
                    'total' => (int) $booking->total,
                    'cancellation_fee' => $fee,
                    'refunded' => $refund,
                    'paid' => $retained,
                    'balance' => max(0, $fee - $retained),
                    'issued_at' => now(),
                ]);
            }

            $this->outbox('booking', $booking->id, 'booking.cancelled', [
                'booking_id' => (string) $booking->id,
                'code' => $booking->code,
                'cancellation_fee' => $fee,
                'refund_amount' => $refund,
            ]);

            return $booking->refresh();
        }, 3);
    }

    private function fee(Booking $booking, int $paid): int
    {
        if ($booking->status === 'pending' || $paid === 0) {
            return 0;
        }
        if (! $booking->refundable) {
            return (int) $booking->total;
        }
        if ($booking->free_cancellation_until && now()->lessThanOrEqualTo($booking->free_cancellation_until)) {
            return 0;
        }

        return (int) round((int) $booking->total * (int) $booking->late_cancellation_fee_percent / 100);
    }

    private function outbox(string $aggregateType, mixed $aggregateId, string $eventType, array $payload): void
    {
        OutboxEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
