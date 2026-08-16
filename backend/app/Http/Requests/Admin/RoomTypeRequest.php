<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('room_type')?->id;
        $hotelId = $this->string('hotel_id')->toString();

        return [
            'hotel_id' => ['required', 'exists:hotels,id'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('room_types')->where('hotel_id', $hotelId)->ignore($id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('room_types')->where('hotel_id', $hotelId)->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'size_m2' => ['nullable', 'numeric', 'min:0'],
            'bed_description' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'max_adults' => ['required', 'integer', 'min:1', 'max:255'],
            'max_children' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'price_per_night' => ['required', 'numeric', 'min:0'],
            'base_cost_per_night' => ['nullable', 'numeric', 'min:0'],
            'refundable' => ['sometimes', 'boolean'],
            'breakfast_included' => ['sometimes', 'boolean'],
            'amenity_ids' => ['sometimes', 'array'],
            'amenity_ids.*' => ['string', 'exists:amenities,id'],
        ];
    }
}
