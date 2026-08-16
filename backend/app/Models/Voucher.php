<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $attributes = [
        'used_count' => 0,
        'active' => true,
        'min_order' => 0,
    ];

    protected $fillable = ['hotel_id', 'code', 'normalized_code', 'type', 'value', 'max_discount', 'min_order', 'starts_at', 'ends_at', 'usage_limit', 'per_user_limit', 'used_count', 'active'];

    protected function casts(): array
    {
        return [
            'value' => 'integer', 'max_discount' => 'integer', 'min_order' => 'integer',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'usage_limit' => 'integer',
            'per_user_limit' => 'integer', 'used_count' => 'integer', 'active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }
}
