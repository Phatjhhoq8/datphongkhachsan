<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends MongoModel
{
    protected $fillable = ['slug', 'name'];

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class, 'room_type_amenity')->withTimestamps();
    }
}
