<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'amenity_ids' => $this->whenLoaded('amenities', fn () => $this->amenities->pluck('id')->map(fn ($id) => (string) $id)->values()),
        ];
    }
}
