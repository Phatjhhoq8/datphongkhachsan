<?php

namespace App\Models;

class OAuthExchangeCode extends MongoModel
{
    protected $fillable = [
        'code_hash',
        'user_id',
        'provider',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
