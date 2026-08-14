<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\RoomRequest;
use App\Http\Resources\Admin\RoomResource;
use App\Models\OutboxEvent;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoomController extends AdminController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $hotelId = $this->scopedHotelId($request, $request->integer('hotel_id') ?: null);
        $rooms = Room::query()->with('roomType')->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->when($request->filled('floor'), fn ($query) => $query->where('floor', $request->integer('floor')))
            ->orderBy('room_number')->paginate($request->integer('per_page', 50));

        return RoomResource::collection($rooms);
    }

    public function store(RoomRequest $request): RoomResource
    {
        $data = $request->validated();
        $this->scopedHotelId($request, (int) $data['hotel_id']);

        return new RoomResource(Room::query()->create($data)->load('roomType'));
    }

    public function show(Request $request, Room $room): RoomResource
    {
        $this->scopedHotelId($request, $room->hotel_id);

        return new RoomResource($room->load('roomType'));
    }

    public function update(RoomRequest $request, Room $room): RoomResource
    {
        $data = $request->validated();
        $this->scopedHotelId($request, $room->hotel_id);
        $this->scopedHotelId($request, (int) $data['hotel_id']);
        $room->update($data);
        $this->recordRoomUpdate($room->refresh()->load('roomType'));

        return new RoomResource($room);
    }

    public function destroy(Request $request, Room $room): Response
    {
        $this->scopedHotelId($request, $room->hotel_id);
        $room->delete();

        return response()->noContent();
    }

    public function map(Request $request): array
    {
        $hotelId = $this->scopedHotelId($request, $request->integer('hotel_id') ?: null);
        abort_if($hotelId === null, 422, 'hotel_id is required.');
        $rooms = Room::query()->with('roomType')->where('hotel_id', $hotelId)
            ->withExists(['bookings as occupied' => fn (Builder $query) => $query->where('status', 'checked_in')])
            ->orderBy('floor')->orderBy('room_number')->get()
            ->map(function (Room $room) {
                $data = (new RoomResource($room))->resolve();
                $data['effective_status'] = $room->occupied ? 'occupied' : $room->operational_status;

                return $data;
            })->groupBy(fn (array $room) => (string) ($room['floor'] ?? 'unassigned'));

        return ['data' => $rooms];
    }

    public function cleaningComplete(Request $request, Room $room): RoomResource
    {
        $this->scopedHotelId($request, $room->hotel_id);
        abort_unless($room->operational_status === 'cleaning', 422, 'Only a room being cleaned can be completed.');
        $room->update(['operational_status' => 'available']);
        $this->recordRoomUpdate($room->refresh()->load('roomType'));

        return new RoomResource($room);
    }

    private function recordRoomUpdate(Room $room): void
    {
        $data = (new RoomResource($room))->resolve();
        $data['effective_status'] = $room->operational_status;

        OutboxEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'aggregate_type' => 'room',
            'aggregate_id' => (string) $room->id,
            'event_type' => 'room.updated',
            'payload' => ['hotel_id' => $room->hotel_id, 'room' => $data],
            'occurred_at' => now(),
        ]);
    }
}
