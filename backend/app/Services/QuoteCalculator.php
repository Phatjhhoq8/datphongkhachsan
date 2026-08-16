<?php

namespace App\Services;

use App\Models\RoomType;
use App\Models\Service;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class QuoteCalculator
{
    protected AvailabilityService $availability;

    public function __construct(AvailabilityService $availability)
    {
        $this->availability = $availability;
    }

    public function calculate(array $data, ?string $userId = null, ?string $guestEmail = null, bool $lockVoucher = false): array
    {
        $roomType = RoomType::query()->with('hotel')->findOrFail($data['room_type_id']);
        $rooms = (int) ($data['rooms'] ?? 1);
        $adults = (int) ($data['adults'] ?? 1);
        $children = (int) ($data['children'] ?? 0);
        $checkinTime = CarbonImmutable::parse($data['checkin']);
        $checkoutTime = CarbonImmutable::parse($data['checkout']);
        
        if ($checkoutTime->lessThanOrEqualTo($checkinTime)) {
            throw ValidationException::withMessages(['checkout' => 'Checkout must be after checkin.']);
        }

        // Kiểm tra trùng lịch và phòng trống
        $checkinParam = isset($data['arrival_time']) ? "{$data['checkin']} {$data['arrival_time']}" : $data['checkin'];
        $checkoutParam = isset($data['checkout_time']) ? "{$data['checkout']} {$data['checkout_time']}" : $data['checkout'];

        $availableRooms = $this->availability->rooms($roomType, $checkinParam, $checkoutParam);
        if ($availableRooms->count() < $rooms) {
            throw ValidationException::withMessages([
                'checkin' => 'Không có phòng trống cho thời gian này do trùng lịch hoặc hết phòng.'
            ]);
        }

        $nights = (int) ceil($checkinTime->diffInMinutes($checkoutTime) / 1440);
        $nights = max(1, $nights);

        $subtotal = $this->money($roomType->getRawOriginal('price_per_night')) * $nights * $rooms;
        $requested = $this->normalizeServices($data);
        $services = Service::query()
            ->whereIn('id', array_keys($requested))
            ->where('hotel_id', $roomType->hotel_id)
            ->where('active', true)
            ->get();

        if ($services->count() !== count($requested)) {
            throw ValidationException::withMessages(['service_ids' => 'One or more services are invalid for this hotel.']);
        }

        $lines = $services->map(function (Service $service) use ($requested, $nights, $adults, $children) {
            $requestedQuantity = $requested[$service->id];
            $quantity = match ($service->pricing_type) {
                'per_booking' => $requestedQuantity,
                'per_night' => $nights * $requestedQuantity,
                'per_guest' => ($adults + $children) * $requestedQuantity,
                'per_unit' => $requestedQuantity,
                default => throw ValidationException::withMessages(['service_ids' => "Unsupported pricing type: {$service->pricing_type}."]),
            };
            $unitPrice = $this->money($service->getRawOriginal('price'));

            return [
                'service_id' => $service->id,
                'name' => $service->name,
                'pricing_type' => $service->pricing_type,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $unitPrice * $quantity,
            ];
        });

        $serviceTotal = $lines->sum('total');
        $beforeDiscount = $subtotal + $serviceTotal;
        [$voucher, $discount] = $this->voucher($data['voucher_code'] ?? null, $roomType->hotel_id, $beforeDiscount, $userId, $guestEmail, $lockVoucher);
        $total = $beforeDiscount - $discount;

        return [
            'room_type' => $roomType,
            'nights' => $nights,
            'rooms' => $rooms,
            'subtotal' => $subtotal,
            'services' => $lines,
            'service_total' => $serviceTotal,
            'voucher' => $voucher,
            'discount_total' => $discount,
            'total' => $total,
            'currency' => 'VND',
            'deposit_amount' => intdiv($total * 30 + 99, 100),
        ];
    }

    private function normalizeServices(array $data): array
    {
        $result = [];

        foreach ($data['service_ids'] ?? [] as $id) {
            $result[(string) $id] = 1;
        }

        foreach ($data['services'] ?? [] as $line) {
            $result[(string) $line['id']] = (int) ($line['quantity'] ?? 1);
        }

        return array_filter($result, fn (int $quantity) => $quantity > 0);
    }

    private function voucher(?string $code, string $hotelId, int $order, ?string $userId, ?string $guestEmail, bool $lock): array
    {
        if (! $code) {
            return [null, 0];
        }

        $voucher = Voucher::query()->where('normalized_code', strtoupper(trim($code)))->first();
        $now = now();

        if (! $voucher || ! $voucher->active || ($voucher->hotel_id && (int) $voucher->hotel_id !== (int) $hotelId)
            || ($voucher->starts_at && $voucher->starts_at->isAfter($now))
            || ($voucher->ends_at && $voucher->ends_at->isBefore($now))
            || ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit)
            || $order < $voucher->min_order) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher is invalid or not applicable.']);
        }

        if ($voucher->per_user_limit !== null) {
            $used = $voucher->redemptions()
                ->when($userId, fn ($query) => $query->where('user_id', $userId))
                ->when(! $userId, fn ($query) => $query->whereNull('user_id')->where('guest_email', strtolower((string) $guestEmail)))
                ->count();
            if ($used >= $voucher->per_user_limit) {
                throw ValidationException::withMessages(['voucher_code' => 'Voucher usage limit has been reached.']);
            }
        }

        $discount = $voucher->type === 'percent'
            ? intdiv($order * $voucher->value, 100)
            : $voucher->value;
        if ($voucher->max_discount !== null) {
            $discount = min($discount, $voucher->max_discount);
        }

        return [$voucher, min($discount, $order)];
    }

    private function money(mixed $value): int
    {
        $normalized = (string) $value;

        return (int) explode('.', $normalized, 2)[0];
    }
}
