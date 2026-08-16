<?php

namespace App\Http\Controllers\Admin;

use App\Models\RoomImage;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class RoomImageController extends AdminController
{
    public function index(Request $request, RoomType $roomType): JsonResponse
    {
        $this->scopedHotelId($request, $roomType->hotel_id);

        return response()->json(['data' => $roomType->images()->get()]);
    }

    public function store(Request $request, RoomType $roomType): JsonResponse
    {
        $this->scopedHotelId($request, $roomType->hotel_id);
        $data = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $path = $request->file('image')->store("room-types/{$roomType->id}", 'public');
        $image = $roomType->images()->create([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $image], 201);
    }

    public function update(Request $request, RoomImage $roomImage): JsonResponse
    {
        $this->scopedHotelId($request, $roomImage->roomType->hotel_id);
        $roomImage->update($request->validate(['sort_order' => ['required', 'integer', 'min:0']]));

        return response()->json(['data' => $roomImage->refresh()]);
    }

    public function destroy(Request $request, RoomImage $roomImage): Response
    {
        $this->scopedHotelId($request, $roomImage->roomType->hotel_id);
        if ($roomImage->path) {
            Storage::disk('public')->delete($roomImage->path);
        }
        $roomImage->delete();

        return response()->noContent();
    }
}
