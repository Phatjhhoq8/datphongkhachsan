<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityEventRequest;
use App\Models\ActivityEvent;
use Illuminate\Http\JsonResponse;

class ActivityEventController extends Controller
{
    private const SENSITIVE_KEYS = [
        'audio', 'raw_audio', 'audio_data', 'blob', 'base64', 'email', 'phone',
        'guest_email', 'guest_phone', 'name', 'guest_name', 'address', 'password', 'token',
    ];

    public function __invoke(StoreActivityEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['metadata'] = $this->sanitizeMetadata($data['metadata'] ?? []);
        $data['expires_at'] = now()->addDays(180);

        ActivityEvent::query()->create($data);

        return response()->json(['data' => ['accepted' => true]], 201);
    }

    private function sanitizeMetadata(array $metadata, int $depth = 0): array
    {
        if ($depth >= 3) {
            return [];
        }

        $sanitized = [];
        foreach (array_slice($metadata, 0, 20, true) as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);
            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value, $depth + 1);
            } elseif (is_string($value)) {
                $limit = $normalizedKey === 'transcript' ? 300 : 500;
                $clean = trim(strip_tags($value));
                $clean = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', '[redacted]', $clean);
                $clean = preg_replace('/(?<!\d)(?:\+?84|0)\d{8,10}(?!\d)/u', '[redacted]', $clean);
                $sanitized[$key] = mb_substr($clean, 0, $limit);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
