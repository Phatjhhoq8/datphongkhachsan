<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CounterBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_ids' => ['sometimes', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'distinct', 'exists:rooms,id'],
            'rooms' => ['required_without:room_ids', 'integer', 'min:1'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
