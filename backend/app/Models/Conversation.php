<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends MongoModel
{
    protected $fillable = [
        'hotel_id', 'guest_name', 'token_hash', 'status', 'last_message_at', 'closed_at', 'closed_by',
    ];

    protected $hidden = ['token_hash'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
