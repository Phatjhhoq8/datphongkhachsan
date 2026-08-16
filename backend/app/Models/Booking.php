<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public const INVENTORY_STATUSES = ['pending', 'confirmed', 'checked_in'];

    protected $fillable = [
        'code', 'idempotency_key', 'guest_name', 'guest_email', 'guest_phone', 'checkin', 'checkout',
        'rooms_count', 'adults', 'children', 'nights', 'subtotal', 'total', 'status', 'payment_method',
        'payment_status', 'special_requests', 'currency', 'service_total', 'discount_total', 'paid_amount',
        'deposit_amount', 'payment_option', 'payment_state', 'voucher_id', 'checked_in_at', 'checked_out_at',
        'cancelled_at', 'cancellation_reason', 'created_by', 'user_id', 'hotel_id', 'room_ids',
        'scheduled_checkin_at', 'scheduled_checkout_at', 'late_checkout_grace_minutes_snapshot',
        'cleaning_duration_minutes_snapshot', 'hold_expires_at',
        'refundable', 'free_cancellation_until', 'late_cancellation_fee_percent',
        'cancellation_fee', 'refund_amount', 'source',
    ];

    protected $hidden = ['idempotency_key'];

    protected function casts(): array
    {
        return [
            'checkin' => 'date:Y-m-d',
            'checkout' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'service_total' => 'integer',
            'discount_total' => 'integer',
            'paid_amount' => 'integer',
            'deposit_amount' => 'integer',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'scheduled_checkin_at' => 'datetime',
            'scheduled_checkout_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'refundable' => 'boolean',
            'free_cancellation_until' => 'datetime',
            'late_cancellation_fee_percent' => 'integer',
            'cancellation_fee' => 'integer',
            'refund_amount' => 'integer',
            'late_checkout_grace_minutes_snapshot' => 'integer',
            'cleaning_duration_minutes_snapshot' => 'integer',
            'room_ids' => 'array',
        ];
    }

    public function getRoomIdsAttribute(): array
    {
        $value = $this->attributes['room_ids'] ?? null;
        if ($value !== null) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($decoded)) {
                return array_map('intval', $decoded);
            }
        }
        return $this->rooms()->pluck('rooms.id')->all();
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class)->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(VoucherRedemption::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
