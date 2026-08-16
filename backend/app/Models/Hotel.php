<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    protected $attributes = [
        'status' => 'active',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'rating' => 0,
        'star_rating' => 0,
        'checkin_time' => '15:00',
        'checkout_time' => '12:00',
        'late_checkout_grace_minutes' => 30,
        'cleaning_duration_minutes' => 150,
        'free_cancellation_hours' => 24,
        'late_cancellation_fee_percent' => 30,
    ];

    protected $fillable = [
        'slug', 'name', 'city', 'address', 'rating', 'star_rating', 'phone', 'email', 'status',
        'timezone', 'description', 'checkin_time', 'checkout_time', 'late_checkout_grace_minutes',
        'cleaning_duration_minutes', 'hero_image',
        'free_cancellation_hours', 'late_cancellation_fee_percent',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'star_rating' => 'integer',
            'late_checkout_grace_minutes' => 'integer',
            'cleaning_duration_minutes' => 'integer',
            'free_cancellation_hours' => 'integer',
            'late_cancellation_fee_percent' => 'integer',
        ];
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'published');
    }
}
