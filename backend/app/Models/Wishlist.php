<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends MongoModel
{
    protected $fillable = ['user_id', 'room_type_id'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
