<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hotel = $this->route('hotel');

        return [
            'slug' => ['required', 'string', 'max:255', Rule::unique('hotels')->ignore($hotel)],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'star_rating' => ['nullable', 'integer', 'between:1,5'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'timezone' => ['sometimes', 'timezone'],
            'description' => ['nullable', 'string'],
            'checkin_time' => ['required', 'date_format:H:i'],
            'checkout_time' => ['required', 'date_format:H:i'],
            'late_checkout_grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'cleaning_duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'free_cancellation_hours' => ['sometimes', 'integer', 'min:0', 'max:8760'],
            'late_cancellation_fee_percent' => ['sometimes', 'integer', 'between:0,100'],
            'hero_image' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
