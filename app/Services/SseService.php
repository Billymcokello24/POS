<?php

namespace App\Services;

use Predis\Client as PredisClient;

class SseService
{
    /**
     * Push an event for a business using Redis for sequence and pub/sub.
     *
     * @param int|string $businessId
     * @param string $type
     * @param array $payload
     * @return int sequence
     */
    public static function pushBusinessEvent($businessId, string $type, array $payload = []): int
    {
        // Create a Predis client. It will read REDIS_* env or default to 127.0.0.1:6379
        $redis = new PredisClient();

        $seqKey = "business_sse_seq:{$businessId}";

        // Atomically increment sequence
        try {
            $seq = $redis->incr($seqKey);
        } catch (\Throwable $e) {
            // Fallback: if Redis is unavailable, return 0 so caller doesn't rely on it
            return 0;
        }

        $eventKey = "business_sse_event:{$businessId}:{$seq}";

        $data = [
            'type' => $type,
            'payload' => $payload,
            'seq' => $seq,
            'time' => now()->toDateTimeString(),
        ];

        $json = json_encode($data);

        // Store the event for short time so new clients can catch up
        try {
            // Use SETEX - TTL 30 minutes
            $redis->setex($eventKey, 1800, $json);

            // Publish to channel for immediate delivery
            $channel = "business_sse_channel:{$businessId}";
            $redis->publish($channel, $json);
        } catch (\Throwable $e) {
            // ignore write failures
        }

        return (int) $seq;
    }
}
