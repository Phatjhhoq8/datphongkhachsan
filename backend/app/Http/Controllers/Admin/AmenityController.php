<?php

namespace App\Http\Controllers\Admin;

use App\Models\Amenity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AmenityController extends AdminController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Amenity::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['slug' => ['required', 'string', 'max:255', 'unique:amenities'], 'name' => ['required', 'string', 'max:255']]);

        return response()->json(['data' => Amenity::query()->create($data)], 201);
    }

    public function update(Request $request, Amenity $amenity): JsonResponse
    {
        $amenity->update($request->validate([
            'slug' => ['required', 'string', 'max:255', Rule::unique('amenities')->ignore($amenity)],
            'name' => ['required', 'string', 'max:255'],
        ]));

        return response()->json(['data' => $amenity->refresh()]);
    }

    public function destroy(Amenity $amenity): Response
    {
        $amenity->delete();

        return response()->noContent();
    }
}
