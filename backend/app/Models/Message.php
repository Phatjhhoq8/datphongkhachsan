<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends MongoModel
{
    protected $fillable = ['conversation_id', 'hotel_id', 'sender_type', 'sender_id', 'text'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
