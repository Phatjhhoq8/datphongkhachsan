<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthExchangeCode extends Model
{
    protected $table = 'oauth_exchange_codes';

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
