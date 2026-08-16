<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('guest_email')) {
            $this->merge(['guest_email' => strtolower(trim((string) $this->guest_email))]);
        }
    }

    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'string', 'exists:room_types,id'],
            'guest_name' => ['required', 'string', 'max:150'],
            'guest_email' => ['required', 'email:rfc', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'checkin' => ['required', 'date', 'after_or_equal:today'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'rooms' => ['required', 'integer', 'min:1', 'max:20'],
            'adults' => ['required', 'integer', 'min:1', 'max:100'],
            'children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payment_method' => ['required', 'in:pay_at_hotel,paypal,paypal_mock,card_mock,vietqr_mock,cash'],
            'payment_option' => ['nullable', 'in:deposit,full'],
            'special_requests' => ['nullable', 'string', 'max:5000'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['string', 'distinct', 'exists:services,id'],
            'services' => ['nullable', 'array'],
            'services.*.id' => ['required', 'string', 'exists:services,id'],
            'services.*.quantity' => ['nullable', 'integer', 'between:1,100'],
            'voucher_code' => ['nullable', 'string', 'max:100'],
            'arrival_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }
}
