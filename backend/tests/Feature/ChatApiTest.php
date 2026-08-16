<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\Message;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_guest_token_is_returned_once_and_cannot_access_another_conversation(): void
    {
        $hotel = $this->hotel('chat-hotel');
        $first = $this->postJson('/api/v1/chat/conversations', [
            'hotel_id' => (string) $hotel->id,
            'guest_name' => 'Guest One',
        ])->assertCreated();
        $second = $this->postJson('/api/v1/chat/conversations', [
            'hotel_id' => (string) $hotel->id,
        ])->assertCreated();

        $token = $first->json('data.access_token');
        $firstId = $first->json('data.conversation.id');
        $secondId = $second->json('data.conversation.id');

        $this->assertNotEmpty($token);
        $this->assertArrayNotHasKey('access_token', Conversation::query()->findOrFail($firstId)->toArray());
        $this->assertNotSame($token, Conversation::query()->findOrFail($firstId)->token_hash);

        $this->withHeader('X-Conversation-Token', $token)
            ->postJson("/api/v1/chat/conversations/{$firstId}/messages", ['text' => '<b>Hello</b> staff'])
            ->assertCreated()
            ->assertJsonPath('data.text', 'Hello staff');

        $this->withHeader('X-Conversation-Token', $token)
            ->getJson("/api/v1/chat/conversations/{$firstId}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeader('X-Conversation-Token', $token)
            ->getJson("/api/v1/chat/conversations/{$secondId}/messages")
            ->assertForbidden();
    }

    public function test_message_persistence_updates_conversation_and_writes_outbox(): void
    {
        $hotel = $this->hotel('message-hotel');
        $created = $this->postJson('/api/v1/chat/conversations', ['hotel_id' => (string) $hotel->id])
            ->assertCreated()
            ->json('data');
        $conversationId = $created['conversation']['id'];

        $response = $this->withHeader('X-Conversation-Token', $created['access_token'])
            ->postJson("/api/v1/chat/conversations/{$conversationId}/messages", ['text' => 'Need a late check-in'])
            ->assertCreated();

        $messageId = $response->json('data.id');
        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_type' => 'guest',
            'text' => 'Need a late check-in',
        ], 'mongodb');
        $this->assertNotNull(Conversation::query()->findOrFail($conversationId)->last_message_at);
        $event = OutboxEvent::query()->where('event_type', 'chat.message')->firstOrFail();
        $this->assertSame($messageId, $event->payload['message_id']);
        $this->assertSame($conversationId, $event->payload['conversation_id']);
        $this->assertSame((string) $hotel->id, $event->payload['hotel_id']);
    }

    public function test_admin_list_show_reply_and_close_are_hotel_scoped(): void
    {
        $hotel = $this->hotel('staff-hotel');
        $otherHotel = $this->hotel('other-hotel');
        $own = $this->conversation($hotel);
        $other = $this->conversation($otherHotel);
        Sanctum::actingAs(User::factory()->create([
            'role' => 'receptionist',
            'status' => 'active',
            'hotel_id' => (string) $hotel->id,
        ]));

        $this->getJson('/api/v1/admin/chat/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $own->id);
        $this->getJson("/api/v1/admin/chat/conversations/{$other->id}")->assertNotFound();

        $this->postJson("/api/v1/admin/chat/conversations/{$own->id}/messages", ['text' => 'Welcome'])
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'staff');
        $this->postJson("/api/v1/admin/chat/conversations/{$own->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertSame(1, Message::query()->where('conversation_id', (string) $own->id)->count());
        $this->assertSame(1, Conversation::query()->where('status', 'open')->count());
    }

    public function test_socket_auth_accepts_only_matching_guest_token_or_scoped_staff(): void
    {
        $hotel = $this->hotel('socket-hotel');
        $otherHotel = $this->hotel('socket-other');
        $created = $this->postJson('/api/v1/chat/conversations', ['hotel_id' => (string) $hotel->id])
            ->assertCreated()
            ->json('data');
        $conversationId = $created['conversation']['id'];

        $this->withHeader('X-Conversation-Token', $created['access_token'])
            ->postJson('/api/v1/chat/socket-auth', ['conversation_id' => $conversationId])
            ->assertOk()
            ->assertJsonPath('data.kind', 'guest')
            ->assertJsonPath('data.conversation_id', $conversationId);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'hotel_manager', 'status' => 'active', 'hotel_id' => (string) $otherHotel->id,
        ]));
        $this->withoutHeader('X-Conversation-Token')
            ->postJson('/api/v1/chat/socket-auth', ['conversation_id' => $conversationId])
            ->assertForbidden();
    }

    private function conversation(Hotel $hotel): Conversation
    {
        return Conversation::query()->create([
            'hotel_id' => (string) $hotel->id,
            'token_hash' => hash('sha256', 'test-token'),
            'status' => 'open',
        ]);
    }

    private function hotel(string $slug): Hotel
    {
        return Hotel::query()->create([
            'slug' => $slug,
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'city' => 'Da Nang',
            'address' => '1 Main Street',
        ]);
    }
}
