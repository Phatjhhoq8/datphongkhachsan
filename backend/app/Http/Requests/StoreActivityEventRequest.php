<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in(['page_view', 'search', 'room_view', 'voice_search'])],
            'session_id' => ['required', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'path' => ['required', 'string', 'max:500', 'starts_with:/'],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'duration_seconds' => ['nullable', 'integer', 'between:0,86400'],
            'metadata' => ['nullable', 'array', 'max:20'],
        ];
    }
}
