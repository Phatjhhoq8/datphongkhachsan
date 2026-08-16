<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatMessageService
{
    public function send(Conversation $conversation, string $text, string $senderType, ?string $senderId = null): Message
    {
        $text = trim(strip_tags($text));
        if ($text === '') {
            throw ValidationException::withMessages(['text' => 'Nội dung tin nhắn không được để trống.']);
        }

        abort_if($conversation->status === 'closed', 409, 'Hội thoại đã đóng.');

        return DB::transaction(function () use ($conversation, $text, $senderType, $senderId) {
            $message = Message::query()->create([
                'conversation_id' => (string) $conversation->id,
                'hotel_id' => (string) $conversation->hotel_id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'text' => $text,
            ]);
            $conversation->update(['last_message_at' => $message->created_at]);
            OutboxEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'aggregate_type' => 'conversation',
                'aggregate_id' => (string) $conversation->id,
                'event_type' => 'chat.message',
                'payload' => [
                    'hotel_id' => (string) $conversation->hotel_id,
                    'conversation_id' => (string) $conversation->id,
                    'message_id' => (string) $message->id,
                    'sender_type' => $senderType,
                    'sender_id' => $senderId,
                    'text' => $text,
                    'created_at' => $message->created_at?->toIso8601String(),
                ],
                'occurred_at' => now(),
            ]);

            return $message;
        });
    }
}
