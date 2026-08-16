<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomImage extends MongoModel
{
    protected $fillable = ['room_type_id', 'path', 'url', 'sort_order'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
