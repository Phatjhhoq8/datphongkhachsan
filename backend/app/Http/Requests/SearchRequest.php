<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['amenities', 'room_type', 'stars'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => array_values(array_filter(explode(',', $this->input($key))))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'location' => ['nullable', 'string', 'max:120'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'checkin' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'checkout' => ['required', 'date_format:Y-m-d', 'after:checkin'],
            'rooms' => ['required', 'integer', 'min:1', 'max:20'],
            'adults' => ['required', 'integer', 'min:1', 'max:100'],
            'children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'gte:min_price'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'distinct', 'exists:amenities,slug'],
            'room_type' => ['nullable', 'array'],
            'room_type.*' => ['string', 'distinct', 'max:100'],
            'refundable' => ['nullable', 'boolean'],
            'stars' => ['nullable', 'array'],
            'stars.*' => ['integer', 'between:1,5', 'distinct'],
            'sort' => ['nullable', 'in:recommended,price_asc,price_desc,rating_desc'],
            'arrival_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'checkout_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }
}
