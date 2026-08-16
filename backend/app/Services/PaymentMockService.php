<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\RoomNight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MongoDB\Driver\Exception\BulkWriteException;

class PaymentMockService
{
    public function createIntent(Booking $booking, array $data, ?string $actorId = null): PaymentTransaction
    {
        if (in_array($booking->status, ['cancelled', 'expired'], true)) {
            throw ValidationException::withMessages(['booking' => 'Payments are not allowed for a cancelled or expired booking.']);
        }

        if (! empty($data['idempotency_key'])) {
            $existing = PaymentTransaction::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ($existing->booking_id !== $booking->id || $existing->method !== $data['method']) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key belongs to a different payment request.']);
                }

                return $existing;
            }
        }

        if ($data['method'] === 'cash' && ! $this->cashAllowed($data['actor'] ?? null)) {
            abort(403, 'Cash payments are restricted to staff.');
        }

        $type = $data['type'] ?? $booking->payment_option;
        $balance = max(0, (int) $booking->total - $booking->paid_amount);
        $amount = isset($data['amount'])
            ? (int) $data['amount']
            : match ($type) {
                'deposit' => min($booking->deposit_amount, $balance),
                'refund' => $booking->paid_amount,
                default => $balance,
            };

        if ($amount < 1 || ($type !== 'refund' && $amount > $balance) || ($type === 'refund' && $amount > $booking->paid_amount)) {
            throw ValidationException::withMessages(['amount' => 'Payment amount is outside the allowed balance.']);
        }

        $lastFour = $data['method'] === 'card_mock' ? ($data['card_last_four'] ?? null) : null;

        try {
            return PaymentTransaction::query()->create([
                'uuid' => (string) Str::uuid(),
                'reference' => 'PAY-'.Str::upper(Str::random(16)),
                'booking_id' => $booking->id,
                'method' => $data['method'],
                'type' => $type,
                'amount' => $amount,
                'status' => 'created',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'card_last_four' => $lastFour,
                'payload' => ['provider' => 'mock', 'method' => $data['method']],
                'actor_id' => $actorId,
            ]);
        } catch (BulkWriteException $exception) {
            $existing = ! empty($data['idempotency_key'])
                ? PaymentTransaction::query()->where('idempotency_key', $data['idempotency_key'])->first()
                : null;
            if ($existing && $existing->booking_id === $booking->id && $existing->method === $data['method']) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function confirm(PaymentTransaction $payment, string $outcome): PaymentTransaction
    {
        if ($payment->status !== 'created') {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $outcome) {
            $payment = PaymentTransaction::query()->findOrFail($payment->id);
            if ($payment->status !== 'created') {
                return $payment;
            }

            $booking = Booking::query()->findOrFail($payment->booking_id);
            if (in_array($booking->status, ['cancelled', 'expired'], true)) {
                $payment->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'payload' => ['provider' => 'mock', 'reason' => 'booking_unpayable'],
                ]);

                return $payment->refresh();
            }
            if ($outcome === 'failure') {
                $payment->update(['status' => 'failed', 'processed_at' => now()]);

                return $payment->refresh();
            }

            $available = $payment->type === 'refund'
                ? $booking->paid_amount
                : max(0, (int) $booking->total - $booking->paid_amount);
            if ($payment->amount > $available) {
                $payment->update(['status' => 'failed', 'processed_at' => now(), 'payload' => ['provider' => 'mock', 'reason' => 'amount_exceeds_current_balance']]);

                return $payment->refresh();
            }

            $paid = $payment->type === 'refund'
                ? max(0, $booking->paid_amount - $payment->amount)
                : min((int) $booking->total, $booking->paid_amount + $payment->amount);
            $state = $payment->type === 'refund' && $paid === 0 ? 'refunded' : ($paid >= (int) $booking->total ? 'paid' : 'partially_paid');
            $previousStatus = $booking->status;
            $booking->update([
                'paid_amount' => $paid,
                'payment_state' => $state,
                'payment_status' => $state === 'paid' ? 'paid' : ($state === 'refunded' ? 'refunded' : 'pending'),
                'status' => $booking->status === 'pending' && $payment->type !== 'refund' ? 'confirmed' : $booking->status,
                'hold_expires_at' => $payment->type !== 'refund' ? null : $booking->hold_expires_at,
            ]);
            if ($payment->type !== 'refund') {
                RoomNight::query()->where('booking_id', $booking->id)->update([
                    'state' => 'booked',
                    'expires_at' => null,
                ]);
            }
            if ($previousStatus !== $booking->status) {
                $booking->statusHistories()->create([
                    'from_status' => $previousStatus,
                    'to_status' => $booking->status,
                    'reason' => 'Mock payment succeeded',
                    'actor_id' => $payment->actor_id,
                ]);
            }
            $payment->update(['status' => $payment->type === 'refund' ? 'refunded' : 'succeeded', 'processed_at' => now()]);

            Invoice::query()->updateOrCreate(['booking_id' => $booking->id], [
                'number' => 'INV-'.$booking->code,
                'subtotal' => (int) $booking->subtotal,
                'service_total' => $booking->service_total,
                'discount_total' => $booking->discount_total,
                'total' => (int) $booking->total,
                'paid' => $paid,
                'balance' => max(0, (int) $booking->total - $paid),
                'issued_at' => now(),
            ]);
            OutboxEvent::query()->create([
                'event_id' => (string) Str::uuid(), 'aggregate_type' => 'payment', 'aggregate_id' => (string) $payment->id,
                'event_type' => 'payment.'.$payment->status, 'payload' => ['booking_id' => $booking->id, 'reference' => $payment->reference], 'occurred_at' => now(),
            ]);

            return $payment->refresh();
        });
    }

    private function cashAllowed(mixed $actor): bool
    {
        return $actor && in_array($actor->getAttribute('role'), ['super_admin', 'hotel_manager', 'receptionist', 'accountant'], true);
    }
}
