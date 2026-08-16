<?php

namespace App\Http\Controllers\Admin;

use App\Models\Conversation;
use App\Services\ChatMessageService;
use App\Support\HotelScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:open,closed'],
            'hotel_id' => ['nullable', 'string', 'max:64'],
        ]);
        $query = HotelScope::apply(Conversation::query(), $request->user())
            ->when(isset($data['hotel_id']), fn ($query) => $query->where('hotel_id', $data['hotel_id']))
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        return response()->json(['data' => $query->limit(100)->get()]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json(['data' => [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->oldest()->limit(500)->get(),
        ]]);
    }

    public function reply(Request $request, Conversation $conversation, ChatMessageService $chat): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);

        return response()->json(['data' => $chat->send(
            $conversation,
            $data['text'],
            'staff',
            (string) $request->user()->id,
        )], 201);
    }

    public function close(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => (string) $request->user()->id,
        ]);

        return response()->json(['data' => $conversation->fresh()]);
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        abort_unless(HotelScope::allows($request->user(), (string) $conversation->hotel_id), 404);
    }
}
