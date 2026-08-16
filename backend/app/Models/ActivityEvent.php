<?php

namespace App\Models;

class ActivityEvent extends MongoModel
{
    protected $fillable = [
        'event', 'session_id', 'path', 'hotel_id', 'room_type_id',
        'duration_seconds', 'metadata', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
