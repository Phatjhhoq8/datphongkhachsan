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
            'room_ids.*' => ['string', 'distinct', 'exists:rooms,id'],
            'rooms' => ['required_without:room_ids', 'integer', 'min:1'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['sometimes', 'integer', 'min:0'],
            'arrival_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'checkout_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $checkoutTime = $this->input('checkout_time');
            if ($checkoutTime) {
                $roomType = \App\Models\RoomType::find($this->input('room_type_id'));
                if ($roomType) {
                    $hotel = $roomType->hotel;
                    $this->validateCheckoutTimeConstraint($validator, $hotel, $checkoutTime);
                }
            }
        });
    }

    private function validateCheckoutTimeConstraint($validator, $hotel, string $checkoutTime): void
    {
        $checkinTimeStr = $hotel->checkin_time;
        $grace = (int) $hotel->late_checkout_grace_minutes;
        $cleaning = (int) $hotel->cleaning_duration_minutes;
        $totalBufferMinutes = $grace + $cleaning;

        $checkinMinutes = $this->timeToMinutes($checkinTimeStr);
        $checkoutMinutes = $this->timeToMinutes($checkoutTime);

        if ($checkoutMinutes + $totalBufferMinutes > $checkinMinutes) {
            $latestAllowedMinutes = $checkinMinutes - $totalBufferMinutes;
            $latestTime = sprintf('%02d:%02d', floor($latestAllowedMinutes / 60), $latestAllowedMinutes % 60);
            $validator->errors()->add('checkout_time', "Giờ checkout không được muộn hơn {$latestTime} để đảm bảo thời gian dọn phòng ({$cleaning} phút) và trả trễ ({$grace} phút).");
        }
    }

    private function timeToMinutes(string $time): int
    {
        list($hours, $minutes) = explode(':', $time);
        return ((int) $hours * 60) + (int) $minutes;
    }
}
