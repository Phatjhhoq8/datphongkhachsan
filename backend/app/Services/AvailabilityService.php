<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomNight;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /** @return Collection<int, Room> */
    public function rooms(RoomType $roomType, ?string $checkin = null, ?string $checkout = null): Collection
    {
        $rooms = Room::query()
            ->where('room_type_id', $roomType->id)
            ->where('active', true)
            ->whereIn('operational_status', ['available', 'cleaning'])
            ->orderBy('room_number')
            ->get();

        if (! $checkin || ! $checkout || $rooms->isEmpty()) {
            return $rooms;
        }

        $hotel = $roomType->hotel()->firstOrFail();
        $hasCheckinTime = str_contains($checkin, ' ') || str_contains($checkin, 'T');
        $scheduledCheckin = CarbonImmutable::parse(
            $hasCheckinTime ? $checkin : "{$checkin} {$hotel->checkin_time}",
            $hotel->timezone
        )->utc();

        $rooms = $rooms->filter(fn (Room $room) => $room->operational_status !== 'cleaning'
            || ($room->available_at !== null && ! $room->available_at->isAfter($scheduledCheckin)))->values();

        if ($rooms->isEmpty()) {
            return $rooms;
        }

        $hasCheckoutTime = str_contains($checkout, ' ') || str_contains($checkout, 'T');
        $scheduledCheckout = CarbonImmutable::parse(
            $hasCheckoutTime ? $checkout : "{$checkout} {$hotel->checkout_time}",
            $hotel->timezone
        )->utc();

        // Kiểm tra trùng lặp (overlap) với các booking đang hoạt động:
        // pending, confirmed, checked_in
        // Công thức overlap: A1 < B2 AND A2 > B1
        $blockedRoomIds = \App\Models\Booking::query()
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->whereNull('cancelled_at')
            ->where(function ($query) use ($scheduledCheckin, $scheduledCheckout) {
                $query->where('scheduled_checkin_at', '<', $scheduledCheckout)
                      ->where('scheduled_checkout_at', '>', $scheduledCheckin);
            })
            ->get(['room_ids'])
            ->flatMap(fn ($b) => (array) ($b->room_ids ?? []))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->all();

        return $rooms->reject(fn (Room $room) => in_array((string) $room->id, $blockedRoomIds, true))->values();
    }

    /** @return list<string> */
    public function nights(string $checkin, string $checkout): array
    {
        $night = CarbonImmutable::parse($checkin);
        $end = CarbonImmutable::parse($checkout);
        $nights = [];

        while ($night->lessThan($end)) {
            $nights[] = $night->toDateString();
            $night = $night->addDay();
        }

        return $nights;
    }
}
