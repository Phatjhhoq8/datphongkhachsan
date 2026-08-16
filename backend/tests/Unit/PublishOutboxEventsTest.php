<?php

namespace Tests\Unit;

use App\Console\Commands\PublishOutboxEvents;
use App\Models\OutboxEvent;
use PHPUnit\Framework\TestCase;

class PublishOutboxEventsTest extends TestCase
{
    public function test_room_event_preserves_opaque_object_id_strings(): void
    {
        $hotelId = '64f1c2a3b4d5e6f7890abcde';
        $roomId = '64f1c2a3b4d5e6f7890abcdf';
        $event = new OutboxEvent;
        $event->setRawAttributes([
            'event_id' => 'evt-1',
            'event_type' => 'room.updated',
            'payload' => json_encode(['hotel_id' => $hotelId, 'room_id' => $roomId], JSON_THROW_ON_ERROR),
        ]);

        $message = (new PublishOutboxEvents)->realtimeMessage($event);

        $this->assertSame($hotelId, $message['hotel_id']);
        $this->assertSame($hotelId, $message['data']['hotel_id']);
        $this->assertSame($roomId, $message['data']['room_id']);
    }

    public function test_chat_message_preserves_opaque_conversation_and_message_ids(): void
    {
        $event = new OutboxEvent;
        $event->setRawAttributes([
            'event_id' => 'evt-chat-1',
            'aggregate_type' => 'conversation',
            'aggregate_id' => '64f1c2a3b4d5e6f7890abcde',
            'event_type' => 'chat.message',
            'payload' => json_encode([
                'hotel_id' => '64f1c2a3b4d5e6f7890abc01',
                'conversation_id' => '64f1c2a3b4d5e6f7890abcde',
                'message_id' => '64f1c2a3b4d5e6f7890abcdf',
                'text' => 'Hello',
                'sender_type' => 'guest',
            ], JSON_THROW_ON_ERROR),
        ]);

        $message = (new PublishOutboxEvents)->realtimeMessage($event);

        $this->assertSame('chat.message', $message['type']);
        $this->assertSame('64f1c2a3b4d5e6f7890abcde', $message['conversation_id']);
        $this->assertSame('64f1c2a3b4d5e6f7890abcdf', $message['data']['message_id']);
    }
}
