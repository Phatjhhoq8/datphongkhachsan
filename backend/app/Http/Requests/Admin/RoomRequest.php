<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('room')?->id;
        $hotelId = $this->string('hotel_id')->toString();

        return [
            'hotel_id' => ['required', 'exists:hotels,id'],
            'room_type_id' => ['required', Rule::exists('room_types', 'id')->where('hotel_id', $hotelId)],
            'room_number' => ['required', 'string', 'max:255', Rule::unique('rooms')->where('hotel_id', $hotelId)->ignore($id)],
            'floor' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'active' => ['sometimes', 'boolean'],
            'map_x' => ['nullable', 'numeric'],
            'map_y' => ['nullable', 'numeric'],
            'operational_status' => ['sometimes', Rule::in(['available', 'cleaning', 'maintenance', 'out_of_service'])],
        ];
    }
}
