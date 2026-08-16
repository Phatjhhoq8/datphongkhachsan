<?php

namespace App\Http\Controllers\Admin;

use App\Models\ActivityEvent;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AnalyticsController extends AdminController
{
    public function overview(Request $request): JsonResponse
    {
        [$from, $to, $hotelId] = $this->range($request);
        $bookings = $this->createdBookings($request, $from, $to, $hotelId)->get();
        $totalBookings = $bookings->count();
        $paidBookings = $bookings->where('payment_status', 'paid');
        $revenue = (float) $paidBookings->sum('total');
        $roomCount = Room::query()->where('active', true)->when($hotelId, fn (Builder $query) => is_array($hotelId) ? $query->whereIn('hotel_id', $hotelId) : $query->where('hotel_id', $hotelId))->count();
        $rangeStart = CarbonImmutable::parse($from)->startOfDay();
        $rangeEnd = CarbonImmutable::parse($to)->addDay()->startOfDay();
        $days = max(1, $rangeStart->diffInDays($rangeEnd));
        $occupiedNights = $this->scopeBookings(Booking::query()->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->where('checkin', '<', $rangeEnd->toDateString())->where('checkout', '>', $from), $request, $hotelId)->get()
            ->sum(function (Booking $booking) use ($rangeStart, $rangeEnd) {
                $start = CarbonImmutable::parse($booking->checkin)->max($rangeStart);
                $end = CarbonImmutable::parse($booking->checkout)->min($rangeEnd);

                return $start->diffInDays($end) * count($booking->room_ids ?? []);
            });
        $revenueByDate = $paidBookings->groupBy(fn (Booking $booking) => $booking->created_at->toDateString())
            ->map(fn (Collection $items) => (float) $items->sum('total'));
        $revenueByPeriod = [];
        for ($date = $rangeStart; $date->lessThan($rangeEnd); $date = $date->addDay()) {
            $revenueByPeriod[] = [
                'date' => $date->toDateString(),
                'revenue' => number_format((float) $revenueByDate->get($date->toDateString(), 0), 2, '.', ''),
            ];
        }
        $sourceCounts = $bookings->groupBy(fn (Booking $booking) => $booking->source === 'walk_in' ? 'walk_in' : 'online')->map->count();
        $bookingSources = collect(['online', 'walk_in'])->map(fn (string $source) => [
            'source' => $source,
            'bookings' => (int) $sourceCounts->get($source, 0),
            'percentage' => $totalBookings ? round((int) $sourceCounts->get($source, 0) / $totalBookings * 100, 2) : 0,
        ])->all();
        $behavior = $this->behavior($from, $to, $hotelId);
        $roomTypeReport = $this->roomTypeReport($request, $from, $to, $hotelId);

        return response()->json(['data' => [
            'range' => compact('from', 'to'),
            'total_revenue' => number_format($revenue, 2, '.', ''),
            'total_bookings' => $totalBookings,
            'average_booking_value' => $totalBookings ? number_format($revenue / $totalBookings, 2, '.', '') : '0.00',
            'occupancy_rate' => $roomCount ? round($occupiedNights / ($roomCount * $days) * 100, 2) : 0,
            'revenue_by_period' => $revenueByPeriod,
            'booking_sources' => $bookingSources,
            'behavior' => $behavior,
            'room_type_performance' => $roomTypeReport['room_types'],
            'room_type_alerts' => $roomTypeReport['alerts'],
        ]]);
    }

    public function roomTypes(Request $request): JsonResponse
    {
        [$from, $to, $hotelId] = $this->range($request);

        return response()->json(['data' => ['range' => compact('from', 'to')] + $this->roomTypeReport($request, $from, $to, $hotelId)]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'hotel_id' => ['nullable', 'string', 'exists:hotels,id'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->toDateString() : now()->subDays(6)->toDateString();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->toDateString() : now()->toDateString();
        $singleId = $this->scopedHotelId($request, isset($data['hotel_id']) ? (string) $data['hotel_id'] : null);
        $city = $request->filled('city') ? (string) $request->input('city') : null;

        if ($request->user()->role === 'super_admin' && !$singleId && $city) {
            $hotelId = \App\Models\Hotel::query()->where('city', $city)->pluck('id')->map(fn($id) => (string)$id)->all();
        } else {
            $hotelId = $singleId ? [$singleId] : null;
        }

        $bookings = $this->bookings($request, $from, $to, $hotelId);
        $rooms = Room::query()->when($hotelId, fn (Builder $query) => is_array($hotelId) ? $query->whereIn('hotel_id', $hotelId) : $query->where('hotel_id', $hotelId));

        $paidBookings = (clone $bookings)->where('payment_status', 'paid')->get();
        $revenueTotal = (float) $paidBookings->sum('total');

        // Doanh thu theo ngày (revenue_chart)
        $rangeStart = CarbonImmutable::parse($from)->startOfDay();
        $rangeEnd = CarbonImmutable::parse($to)->addDay()->startOfDay();
        $revenueByDate = $paidBookings->groupBy(fn (Booking $booking) => $booking->created_at->toDateString())
            ->map(fn (Collection $items) => (float) $items->sum('total'));
        $revenueChart = [];
        $revenueMax = 0;
        for ($date = $rangeStart; $date->lessThan($rangeEnd); $date = $date->addDay()) {
            $val = (float) $revenueByDate->get($date->toDateString(), 0);
            if ($val > $revenueMax) {
                $revenueMax = $val;
            }
            $revenueChart[] = [
                'date' => $date->toDateString(),
                'revenue' => $val,
            ];
        }

        // Chỉ số chất lượng
        // 1. Công suất phòng
        $days = max(1, $rangeStart->diffInDays($rangeEnd));
        $roomCount = (clone $rooms)->where('active', true)->count();
        $occupiedNights = $this->scopeBookings(Booking::query()->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->where('checkin', '<', $rangeEnd->toDateString())->where('checkout', '>', $from), $request, $hotelId)->get()
            ->sum(function (Booking $booking) use ($rangeStart, $rangeEnd) {
                $start = CarbonImmutable::parse($booking->checkin)->max($rangeStart);
                $end = CarbonImmutable::parse($booking->checkout)->min($rangeEnd);
                return $start->diffInDays($end) * count($booking->room_ids ?? []);
            });
        $capacity = $roomCount * $days;
        $occupancyRate = $capacity ? round($occupiedNights / $capacity * 100, 2) : 0;

        // 2. Khách hàng quay lại (loyalty)
        $loyaltyBookings = (clone $bookings)->whereNot('status', 'cancelled')->get();
        $guests = $loyaltyBookings->groupBy(fn (Booking $b) => strtolower($b->guest_email));
        $loyaltyRate = $guests->count() ? round($guests->filter(fn ($items) => $items->count() > 1)->count() / $guests->count() * 100, 2) : 0;

        // 3. Mức độ hài lòng (satisfaction)
        $averageRating = Review::query()
            ->whereBetween('created_at', [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()])
            ->when($hotelId, fn (Builder $query) => is_array($hotelId) ? $query->whereIn('hotel_id', $hotelId) : $query->where('hotel_id', $hotelId))
            ->avg('rating_overall');
        $satisfactionScore = $averageRating === null ? 0 : round((float) $averageRating, 2);

        return response()->json(['data' => [
            'range' => compact('from', 'to'),
            'bookings_count' => $bookings->count(),
            'pending_count' => (clone $bookings)->where('status', 'pending')->count(),
            'checked_in_count' => (clone $bookings)->where('status', 'checked_in')->count(),
            'revenue' => (string) $revenueTotal,
            'rooms_total' => $roomCount,
            'rooms_occupied' => $this->scopeBookings(Booking::query()->where('status', 'checked_in'), $request, $hotelId)->get()
                ->flatMap(fn (Booking $booking) => $booking->room_ids ?? [])->unique()->count(),
            'rooms_cleaning' => (clone $rooms)->where('operational_status', 'cleaning')->count(),

            'revenue_chart' => $revenueChart,
            'revenue_total' => $revenueTotal,
            'revenue_max' => $revenueMax,
            'occupancy_rate' => $occupancyRate,
            'loyalty_rate' => $loyaltyRate,
            'satisfaction_score' => $satisfactionScore,
        ]]);
    }

    public function revenue(Request $request): JsonResponse
    {
        [$from, $to, $hotelId] = $this->range($request);
        $bookings = $this->bookings($request, $from, $to, $hotelId)->where('payment_status', 'paid')
            ->with('rooms.roomType')->get();
        $revenue = (float) $bookings->sum('total');
        $costAvailable = $bookings->isNotEmpty() && $bookings->every(fn (Booking $booking) => $booking->rooms->isNotEmpty()
            && $booking->rooms->every(fn (Room $room) => $room->roomType->base_cost_per_night !== null));
        $cost = $costAvailable ? $bookings->sum(fn (Booking $booking) => $booking->rooms->sum(
            fn (Room $room) => (float) $room->roomType->base_cost_per_night * $booking->nights
        )) : null;

        return response()->json(['data' => [
            'range' => compact('from', 'to'),
            'revenue' => number_format($revenue, 2, '.', ''),
            'cost_available' => $costAvailable,
            'cost' => $cost === null ? null : number_format($cost, 2, '.', ''),
            'estimated_profit' => $cost === null ? null : number_format($revenue - $cost, 2, '.', ''),
        ]]);
    }

    public function occupancy(Request $request): JsonResponse
    {
        [$from, $to, $hotelId] = $this->range($request);
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to)->addDay();
        $days = $start->diffInDays($end);
        $roomCount = Room::query()->where('active', true)->when($hotelId, fn (Builder $query) => is_array($hotelId) ? $query->whereIn('hotel_id', $hotelId) : $query->where('hotel_id', $hotelId))->count();
        $bookings = $this->scopeBookings(Booking::query()->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->where('checkin', '<', $end->toDateString())->where('checkout', '>', $from), $request, $hotelId)->get();
        $occupiedNights = $bookings->sum(function (Booking $booking) use ($start, $end) {
            $overlapStart = CarbonImmutable::parse($booking->checkin)->max($start);
            $overlapEnd = CarbonImmutable::parse($booking->checkout)->min($end);

            return $overlapStart->diffInDays($overlapEnd) * count($booking->room_ids ?? []);
        });
        $capacity = $roomCount * $days;

        return response()->json(['data' => [
            'range' => compact('from', 'to'), 'available_room_nights' => $capacity,
            'occupied_room_nights' => $occupiedNights,
            'occupancy_rate' => $capacity ? round($occupiedNights / $capacity * 100, 2) : 0,
        ]]);
    }

    public function loyalty(Request $request): JsonResponse
    {
        [$from, $to, $hotelId] = $this->range($request);
        $guests = $this->bookings($request, $from, $to, $hotelId)->whereNot('status', 'cancelled')->get()
            ->groupBy(fn (Booking $booking) => strtolower($booking->guest_email));

        return response()->json(['data' => [
            'range' => compact('from', 'to'),
            'unique_guests' => $guests->count(),
            'returning_guests' => $guests->filter(fn (Collection $items) => $items->count() > 1)->count(),
            'repeat_booking_rate' => $guests->count() ? round($guests->filter(fn (Collection $items) => $items->count() > 1)->count() / $guests->count() * 100, 2) : 0,
        ]]);
    }

    public function satisfaction(Request $request): JsonResponse
    {
        [$from, $to, $hotelId] = $this->range($request);
        $query = Review::query()->whereBetween('created_at', [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()])
            ->when($hotelId, fn (Builder $query) => is_array($hotelId) ? $query->whereIn('hotel_id', $hotelId) : $query->where('hotel_id', $hotelId));
        $average = $query->avg('rating_overall');

        return response()->json(['data' => [
            'range' => compact('from', 'to'), 'available' => true,
            'average_rating' => $average === null ? null : round((float) $average, 2),
            'reviews_count' => $query->count(),
        ]]);
    }

    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'hotel_id' => ['nullable', 'string', 'exists:hotels,id'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);
        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth())->toDateString();
        $to = CarbonImmutable::parse($data['to'] ?? now())->toDateString();
        $singleId = $this->scopedHotelId($request, isset($data['hotel_id']) ? (string) $data['hotel_id'] : null);
        $city = $request->filled('city') ? (string) $request->input('city') : null;

        if ($request->user()->role === 'super_admin' && !$singleId && $city) {
            $hotelIds = \App\Models\Hotel::query()->where('city', $city)->pluck('id')->map(fn($id) => (string)$id)->all();
            return [$from, $to, $hotelIds];
        }

        return [$from, $to, $singleId ? [$singleId] : null];
    }

    private function bookings(Request $request, string $from, string $to, string|array|null $hotelId): Builder
    {
        return $this->scopeBookings(Booking::query()->where('checkin', '<=', $to)->where('checkout', '>=', $from), $request, $hotelId);
    }

    private function createdBookings(Request $request, string $from, string $to, string|array|null $hotelId): Builder
    {
        return $this->scopeBookings(Booking::query()->whereBetween('created_at', [
            CarbonImmutable::parse($from)->startOfDay(),
            CarbonImmutable::parse($to)->endOfDay(),
        ]), $request, $hotelId);
    }

    private function behavior(string $from, string $to, string|array|null $hotelId): array
    {
        $events = ActivityEvent::query()
            ->whereBetween('created_at', [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()])
            ->when($hotelId, function ($query) use ($hotelId) {
                $ids = array_map(fn($id) => is_numeric($id) ? (int) $id : $id, (array) $hotelId);
                return $query->whereIn('hotel_id', $ids);
            })
            ->get();
        $durations = $events->whereNotNull('duration_seconds');

        return [
            'page_views' => $events->where('event', 'page_view')->count(),
            'unique_sessions' => $events->pluck('session_id')->filter()->unique()->count(),
            'average_duration_seconds' => $durations->isEmpty() ? 0 : round((float) $durations->avg('duration_seconds'), 2),
            'voice_searches' => $events->where('event', 'voice_search')->count(),
        ];
    }

    private function roomTypeReport(Request $request, string $from, string $to, string|array|null $hotelId): array
    {
        $rangeStart = CarbonImmutable::parse($from)->startOfDay();
        $rangeEnd = CarbonImmutable::parse($to)->addDay()->startOfDay();
        $roomTypes = RoomType::query()->when($hotelId, fn (Builder $query) => is_array($hotelId) ? $query->whereIn('hotel_id', $hotelId) : $query->where('hotel_id', $hotelId))->get();
        $rooms = Room::query()->when($hotelId, fn (Builder $query) => is_array($hotelId) ? $query->whereIn('hotel_id', $hotelId) : $query->where('hotel_id', $hotelId))->get()->keyBy(fn (Room $room) => (string) $room->id);
        $bookings = $this->scopeBookings(Booking::query()->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->where('checkin', '<', $rangeEnd->toDateString())->where('checkout', '>', $from), $request, $hotelId)->get();
        $views = ActivityEvent::query()->where('event', 'room_view')
            ->whereBetween('created_at', [$rangeStart, CarbonImmutable::parse($to)->endOfDay()])
            ->when($hotelId, function ($query) use ($hotelId) {
                $ids = array_map(fn($id) => is_numeric($id) ? (int) $id : $id, (array) $hotelId);
                return $query->whereIn('hotel_id', $ids);
            })
            ->get()->groupBy(fn (ActivityEvent $event) => (string) $event->room_type_id)->map->count();

        $rows = $roomTypes->map(function (RoomType $roomType) use ($bookings, $rooms, $views, $rangeStart, $rangeEnd) {
            $bookingsCount = 0;
            $occupiedNights = 0;
            foreach ($bookings as $booking) {
                $roomsOfType = collect($booking->room_ids ?? [])->filter(
                    fn ($roomId) => (string) $rooms->get((string) $roomId)?->room_type_id === (string) $roomType->id
                )->count();
                if ($roomsOfType === 0) {
                    continue;
                }

                $bookingsCount++;
                $start = CarbonImmutable::parse($booking->checkin)->max($rangeStart);
                $end = CarbonImmutable::parse($booking->checkout)->min($rangeEnd);
                $occupiedNights += $start->diffInDays($end) * $roomsOfType;
            }

            $viewCount = (int) $views->get((string) $roomType->id, 0);
            $alerts = [];
            if ($viewCount === 0) {
                $alerts[] = 'low_interaction';
            }
            if ($bookingsCount === 0) {
                $alerts[] = 'low_bookings';
            }

            return [
                'id' => (string) $roomType->id,
                'name' => $roomType->name,
                'bookings' => $bookingsCount,
                'occupied_nights' => $occupiedNights,
                'views' => $viewCount,
                'conversion_rate' => $viewCount ? round($bookingsCount / $viewCount * 100, 2) : 0,
                'alerts' => $alerts,
            ];
        })->values();

        return [
            'room_types' => $rows->all(),
            'alerts' => $rows->filter(fn (array $row) => $row['alerts'] !== [])->values()->all(),
        ];
    }
}
