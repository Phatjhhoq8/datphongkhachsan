<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = ['booking_id', 'number', 'subtotal', 'service_total', 'discount_total', 'total', 'paid', 'balance', 'cancellation_fee', 'refunded', 'issued_at'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer', 'service_total' => 'integer', 'discount_total' => 'integer',
            'total' => 'integer', 'paid' => 'integer', 'balance' => 'integer',
            'cancellation_fee' => 'integer', 'refunded' => 'integer', 'issued_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
