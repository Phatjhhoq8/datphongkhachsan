<?php

namespace App\Http\Controllers\Admin;

use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsController extends AdminController
{
    public function overview(Request $request): JsonResponse
    {
        [$from, $to, $hotelId] = $this->range($request);
        $bookings = $this->bookings($request, $from, $to, $hotelId);
        $totalBookings = (clone $bookings)->count();
        $revenue = (float) (clone $bookings)->where('payment_status', 'paid')->sum('total');
        $roomCount = Room::query()->where('active', true)->when($hotelId, fn (Builder $query) => $query->where('hotel_id', $hotelId))->count();
        $days = max(1, CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)->addDay()));
        $occupiedNights = $this->scopeBookings(Booking::query()->with('rooms')->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->where('checkin', '<=', $to)->where('checkout', '>=', $from), $request, $hotelId)->get()
            ->sum(fn (Booking $booking) => $booking->rooms->count() * $booking->nights);

        return response()->json(['data' => [
            'range' => compact('from', 'to'),
            'total_revenue' => number_format($revenue, 2, '.', ''),
            'total_bookings' => $totalBookings,
            'average_booking_value' => $totalBookings ? number_format($revenue / $totalBookings, 2, '.', '') : '0.00',
            'occupancy_rate' => $roomCount ? round($occupiedNights / ($roomCount * $days) * 100, 2) : 0,
            'revenue_by_period' => [],
            'booking_sources' => [],
        ]]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        [$from, $to, $hotelId] = $this->range($request);
        $bookings = $this->bookings($request, $from, $to, $hotelId);
        $rooms = Room::query()->when($hotelId, fn (Builder $query) => $query->where('hotel_id', $hotelId));

        return response()->json(['data' => [
            'range' => compact('from', 'to'),
            'bookings_count' => $bookings->count(),
            'pending_count' => (clone $bookings)->where('status', 'pending')->count(),
            'checked_in_count' => (clone $bookings)->where('status', 'checked_in')->count(),
            'revenue' => (string) (clone $bookings)->where('payment_status', 'paid')->sum('total'),
            'rooms_total' => (clone $rooms)->where('active', true)->count(),
            'rooms_occupied' => (clone $rooms)->whereHas('bookings', fn (Builder $query) => $query->where('status', 'checked_in'))->count(),
            'rooms_cleaning' => (clone $rooms)->where('operational_status', 'cleaning')->count(),
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
        $roomCount = Room::query()->where('active', true)->when($hotelId, fn (Builder $query) => $query->where('hotel_id', $hotelId))->count();
        $bookings = $this->scopeBookings(Booking::query()->with('rooms')->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->where('checkin', '<', $end->toDateString())->where('checkout', '>', $from), $request, $hotelId)->get();
        $occupiedNights = $bookings->sum(function (Booking $booking) use ($start, $end) {
            $overlapStart = CarbonImmutable::parse($booking->checkin)->max($start);
            $overlapEnd = CarbonImmutable::parse($booking->checkout)->min($end);

            return $overlapStart->diffInDays($overlapEnd) * $booking->rooms->count();
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
        $ratingColumn = Schema::hasColumn('reviews', 'rating_overall') ? 'rating_overall' : 'rating';
        if (! Schema::hasTable('reviews') || ! Schema::hasColumn('reviews', $ratingColumn)) {
            return response()->json(['data' => ['range' => compact('from', 'to'), 'available' => false, 'average_rating' => null, 'reviews_count' => 0]]);
        }

        $query = DB::table('reviews')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        if ($hotelId && Schema::hasColumn('reviews', 'hotel_id')) {
            $query->where('hotel_id', $hotelId);
        }

        return response()->json(['data' => [
            'range' => compact('from', 'to'), 'available' => true,
            'average_rating' => $query->avg($ratingColumn) === null ? null : round((float) $query->avg($ratingColumn), 2),
            'reviews_count' => $query->count(),
        ]]);
    }

    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
        ]);
        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth())->toDateString();
        $to = CarbonImmutable::parse($data['to'] ?? now())->toDateString();
        $hotelId = $this->scopedHotelId($request, isset($data['hotel_id']) ? (int) $data['hotel_id'] : null);

        return [$from, $to, $hotelId];
    }

    private function bookings(Request $request, string $from, string $to, ?int $hotelId): Builder
    {
        return $this->scopeBookings(Booking::query()->whereDate('checkin', '<=', $to)->whereDate('checkout', '>=', $from), $request, $hotelId);
    }
}
