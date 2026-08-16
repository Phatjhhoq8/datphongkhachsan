<?php

namespace App\Models;

class PasswordResetOtp extends MongoModel
{
    protected $attributes = [
        'attempts' => 0,
    ];

    protected $fillable = ['email', 'otp_hash', 'expires_at', 'attempts', 'used_at'];

    protected $hidden = ['otp_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
