<?php

namespace App\Http\Controllers\Admin;

use App\Models\RoomImage;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoomImageController extends AdminController
{
    public function store(Request $request, RoomType $roomType): JsonResponse
    {
        $this->scopedHotelId($request, $roomType->hotel_id);
        $data = $request->validate(['url' => ['required', 'string', 'max:2048'], 'sort_order' => ['sometimes', 'integer', 'min:0']]);

        return response()->json(['data' => $roomType->images()->create($data)], 201);
    }

    public function update(Request $request, RoomImage $roomImage): JsonResponse
    {
        $this->scopedHotelId($request, $roomImage->roomType->hotel_id);
        $roomImage->update($request->validate(['url' => ['sometimes', 'string', 'max:2048'], 'sort_order' => ['sometimes', 'integer', 'min:0']]));

        return response()->json(['data' => $roomImage->refresh()]);
    }

    public function destroy(Request $request, RoomImage $roomImage): Response
    {
        $this->scopedHotelId($request, $roomImage->roomType->hotel_id);
        $roomImage->delete();

        return response()->noContent();
    }
}
