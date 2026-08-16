<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends MongoModel
{
    protected $fillable = ['hotel_id', 'code', 'name', 'description', 'pricing_type', 'price', 'cost', 'active'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'cost' => 'integer', 'active' => 'boolean'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
