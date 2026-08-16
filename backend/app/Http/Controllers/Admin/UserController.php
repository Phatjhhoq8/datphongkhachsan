<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StaffRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends AdminController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $hotelId = $this->scopedHotelId($request, $request->filled('hotel_id') ? (string) $request->input('hotel_id') : null);
        $users = User::query()->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->orderBy('name')->paginate($request->integer('per_page', 20));

        return UserResource::collection($users);
    }

    public function store(StaffRequest $request): UserResource
    {
        abort_unless($request->user()->role === 'super_admin', 403, 'Only super administrators can create staff.');
        $data = $request->validated();
        abort_unless(isset($data['role']) && $data['role'] !== 'customer', 422, 'A staff role is required.');

        return new UserResource(User::query()->create($data));
    }

    public function update(StaffRequest $request, User $user): UserResource
    {
        $this->scopedHotelId($request, $user->hotel_id);
        $data = $request->validated();
        if (array_key_exists('role', $data) || array_key_exists('hotel_id', $data)) {
            abort_unless($request->user()->role === 'super_admin', 403, 'Only super administrators can assign roles or hotel scope.');
        }
        $user->update($data);

        return new UserResource($user->refresh());
    }

    public function updateStatus(Request $request, User $user): UserResource
    {
        $this->scopedHotelId($request, $user->hotel_id);
        $data = $request->validate(['status' => ['required', 'in:active,inactive']]);
        abort_if($user->is($request->user()) && $data['status'] === 'inactive', 422, 'You cannot deactivate your own account.');
        $user->update($data);

        return new UserResource($user->refresh());
    }
}
