<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Room extends Model
{
    protected $attributes = [
        'active' => true,
        'operational_status' => 'available',
    ];

    protected $fillable = [
        'hotel_id', 'room_type_id', 'room_number', 'floor', 'active', 'map_x', 'map_y',
        'operational_status', 'cleaning_started_at', 'cleaning_completed_at', 'available_at',
    ];

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'active' => 'boolean',
            'map_x' => 'decimal:2',
            'map_y' => 'decimal:2',
            'cleaning_started_at' => 'datetime',
            'cleaning_completed_at' => 'datetime',
            'available_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Room $room) {
            if ($room->hotel_id === null && $room->room_type_id !== null) {
                $room->hotel_id = RoomType::query()->whereKey($room->room_type_id)->value('hotel_id');
            }
        });
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class)->withTimestamps();
    }
}
