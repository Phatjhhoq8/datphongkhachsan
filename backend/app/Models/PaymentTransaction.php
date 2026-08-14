<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = ['uuid', 'reference', 'booking_id', 'method', 'type', 'amount', 'status', 'idempotency_key', 'card_last_four', 'payload', 'processed_at', 'actor_id'];

    protected $hidden = ['idempotency_key'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'payload' => 'array', 'processed_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
