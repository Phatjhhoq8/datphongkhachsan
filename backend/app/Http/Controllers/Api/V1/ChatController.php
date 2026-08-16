<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Hotel;
use App\Services\ChatMessageService;
use App\Support\HotelScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function storeConversation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hotel_id' => ['required', 'string', 'max:64'],
            'guest_name' => ['nullable', 'string', 'max:100'],
        ]);
        abort_unless(Hotel::query()->whereKey($data['hotel_id'])->where('status', 'active')->exists(), 422, 'Khách sạn không hợp lệ.');

        $token = Str::random(64);
        $conversation = Conversation::query()->create([
            'hotel_id' => $data['hotel_id'],
            'guest_name' => isset($data['guest_name']) ? trim(strip_tags($data['guest_name'])) : null,
            'token_hash' => hash('sha256', $token),
            'status' => 'open',
        ]);

        return response()->json(['data' => [
            'conversation' => $conversation,
            'access_token' => $token,
        ]], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeGuest($request, $conversation);

        return response()->json(['data' => $conversation->messages()->oldest()->limit(200)->get()]);
    }

    public function send(Request $request, Conversation $conversation, ChatMessageService $chat): JsonResponse
    {
        $this->authorizeGuest($request, $conversation);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);

        return response()->json(['data' => $chat->send($conversation, $data['text'], 'guest')], 201);
    }

    public function socketAuth(Request $request): JsonResponse
    {
        $data = $request->validate(['conversation_id' => ['required', 'string', 'max:64']]);
        $conversation = Conversation::query()->findOrFail($data['conversation_id']);
        $token = $request->header('X-Conversation-Token');

        if (is_string($token) && $token !== '') {
            $this->authorizeGuest($request, $conversation);

            return response()->json(['data' => [
                'kind' => 'guest',
                'conversation_id' => (string) $conversation->id,
                'hotel_id' => (string) $conversation->hotel_id,
            ]]);
        }

        $user = Auth::guard('sanctum')->user();
        abort_unless($user && $user->status === 'active' && in_array($user->role, ['super_admin', 'hotel_manager', 'receptionist'], true), 403);
        abort_unless(HotelScope::allows($user, (string) $conversation->hotel_id), 403);

        return response()->json(['data' => [
            'kind' => 'staff',
            'conversation_id' => (string) $conversation->id,
            'hotel_id' => (string) $conversation->hotel_id,
            'user_id' => (string) $user->id,
        ]]);
    }

    private function authorizeGuest(Request $request, Conversation $conversation): void
    {
        $token = $request->header('X-Conversation-Token');
        abort_unless(is_string($token) && $token !== '', 401, 'Thiếu conversation token.');
        abort_unless(hash_equals((string) $conversation->token_hash, hash('sha256', $token)), 403);
    }
}
