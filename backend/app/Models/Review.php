<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['booking_id', 'room_type_id', 'user_id', 'hotel_id', 'rating_overall', 'rating_room', 'rating_service', 'title', 'content', 'status'];

    protected function casts(): array
    {
        return ['rating_overall' => 'integer', 'rating_room' => 'integer', 'rating_service' => 'integer'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
