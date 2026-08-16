<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    protected $attributes = [
        'active' => true,
        'max_children' => 0,
        'refundable' => false,
        'breakfast_included' => false,
    ];

    protected $fillable = ['hotel_id', 'slug', 'code', 'name', 'description', 'size_m2', 'bed_description', 'active', 'max_adults', 'max_children', 'price_per_night', 'base_cost_per_night', 'refundable', 'breakfast_included'];

    protected function casts(): array
    {
        return [
            'price_per_night' => 'decimal:2',
            'base_cost_per_night' => 'decimal:2',
            'size_m2' => 'decimal:2',
            'active' => 'boolean',
            'refundable' => 'boolean',
            'breakfast_included' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'room_type_amenity')->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
