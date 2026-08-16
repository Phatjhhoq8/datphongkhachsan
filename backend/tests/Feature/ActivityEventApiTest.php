<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\Hotel;
use App\Models\RoomType;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class ActivityEventApiTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_public_client_can_store_an_allowlisted_activity_event(): void
    {
        $hotel = Hotel::query()->firstOrFail();
        $roomType = RoomType::query()->where('hotel_id', $hotel->id)->firstOrFail();
        $hotelId = $hotel->id;
        $roomTypeId = $roomType->id;

        $this->postJson('/api/v1/activity-events', [
            'event' => 'voice_search',
            'session_id' => 'session-demo-123456',
            'path' => '/hotel/search',
            'hotel_id' => $hotelId,
            'room_type_id' => $roomTypeId,
            'duration_seconds' => 12,
            'metadata' => ['transcript' => 'Tìm phòng Deluxe cho 2 người ngày mai', 'source' => 'web_speech'],
        ])->assertCreated()->assertJsonPath('data.accepted', true);

        $event = ActivityEvent::query()->firstOrFail();
        $this->assertSame('voice_search', $event->event);
        $this->assertSame('session-demo-123456', $event->session_id);
        $this->assertSame('/hotel/search', $event->path);
        $this->assertSame((int) $hotelId, (int) $event->hotel_id);
        $this->assertSame((int) $roomTypeId, (int) $event->room_type_id);
        $this->assertSame(12, $event->duration_seconds);
        $this->assertSame('Tìm phòng Deluxe cho 2 người ngày mai', $event->metadata['transcript']);
        $this->assertNotNull($event->expires_at);
        $this->assertTrue($event->expires_at->isBetween(now()->addDays(179), now()->addDays(181)));
    }

    public function test_event_type_object_ids_duration_and_required_fields_are_validated(): void
    {
        $this->postJson('/api/v1/activity-events', [
            'event' => 'booking_created',
            'session_id' => '',
            'path' => str_repeat('x', 501),
            'hotel_id' => 'not-an-integer',
            'room_type_id' => 'not-an-integer',
            'duration_seconds' => 86401,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'event', 'session_id', 'path', 'hotel_id', 'room_type_id', 'duration_seconds',
        ]);
    }

    public function test_metadata_is_sanitized_and_never_persists_audio_or_pii(): void
    {
        $this->postJson('/api/v1/activity-events', [
            'event' => 'search',
            'session_id' => 'privacy-session-123',
            'path' => '/hotel/search',
            'metadata' => [
                'keyword' => '<b>Deluxe</b>',
                'transcript' => str_repeat('a', 400),
                'email' => 'guest@example.com',
                'phone' => '0901234567',
                'raw_audio' => 'base64-audio',
                'audio' => ['bytes' => [1, 2, 3]],
                'nested' => ['name' => 'Guest Name', 'safe' => '<i>tomorrow</i>'],
            ],
        ])->assertCreated();

        $event = ActivityEvent::query()->firstOrFail();
        $metadata = $event->metadata;
        $this->assertSame('Deluxe', $metadata['keyword']);
        $this->assertSame(300, strlen($metadata['transcript']));
        $this->assertSame(['safe' => 'tomorrow'], $metadata['nested']);
        $stored = json_encode($metadata);
        $this->assertStringNotContainsString('guest@example.com', $stored);
        $this->assertStringNotContainsString('0901234567', $stored);
        $this->assertStringNotContainsString('base64-audio', $stored);
        $this->assertStringNotContainsString('Guest Name', $stored);
    }

    public function test_all_supported_event_types_are_accepted(): void
    {
        foreach (['page_view', 'search', 'room_view', 'voice_search'] as $event) {
            $this->postJson('/api/v1/activity-events', [
                'event' => $event,
                'session_id' => 'allowlist-session-123',
                'path' => '/hotel',
            ])->assertCreated();
        }

        $this->assertDatabaseCount('activity_events', 4, 'mongodb');
    }
}
