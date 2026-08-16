<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class PublishOutboxEvents extends Command
{
    protected $signature = 'outbox:publish {--watch} {--sleep=1}';

    protected $description = 'Publish committed domain events to the realtime Redis channel';

    public function handle(): int
    {
        do {
            $published = $this->publishBatch();

            if (! $this->option('watch')) {
                $this->info("Published {$published} event(s).");

                return self::SUCCESS;
            }

            if ($published === 0) {
                sleep(max(1, (int) $this->option('sleep')));
            }
        } while (true);
    }

    private function publishBatch(): int
    {
        $events = OutboxEvent::query()
            ->whereNull('published_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($events as $event) {
            $message = $this->realtimeMessage($event);

            if ($message === null) {
                $event->update(['published_at' => now()]);

                continue;
            }

            Redis::publish('staygo.events', json_encode($message, JSON_THROW_ON_ERROR));
            $event->update(['published_at' => now()]);
        }

        return $events->count();
    }

    public function realtimeMessage(OutboxEvent $event): ?array
    {
        if ($event->event_type === 'chat.message') {
            $hotelId = (string) ($event->payload['hotel_id'] ?? '');
            $conversationId = (string) ($event->payload['conversation_id'] ?? '');

            return $hotelId !== '' && $conversationId !== '' ? [
                'id' => (string) $event->event_id,
                'type' => 'chat.message',
                'hotel_id' => $hotelId,
                'conversation_id' => $conversationId,
                'data' => $event->payload,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ] : null;
        }

        if ($event->event_type === 'room.updated') {
            $hotelId = (string) ($event->payload['hotel_id'] ?? '');

            return $hotelId !== '' ? [
                'id' => $event->event_id,
                'type' => 'room.updated',
                'hotel_id' => $hotelId,
                'data' => $event->payload,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ] : null;
        }

        if ($event->aggregate_type !== 'booking') {
            return null;
        }

        $booking = Booking::query()->with('rooms.roomType')->find($event->aggregate_id);
        $hotelId = (string) ($booking?->rooms->first()?->hotel_id ?? $booking?->rooms->first()?->roomType?->hotel_id ?? '');

        return $booking && $hotelId !== '' ? [
            'id' => $event->event_id,
            'type' => 'booking.updated',
            'hotel_id' => $hotelId,
            'data' => [
                ...$event->payload,
                'hotel_id' => $hotelId,
                'status' => $booking->status,
                'room_ids' => $booking->rooms->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ],
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ] : null;
    }
}
