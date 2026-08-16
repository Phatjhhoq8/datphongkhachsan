<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $fillable = ['event_id', 'aggregate_type', 'aggregate_id', 'event_type', 'payload', 'occurred_at', 'published_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
