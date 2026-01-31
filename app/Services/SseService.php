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

            // If this is a subscription activation/finalization, also write a quick cache
            if (is_string($type) && str_starts_with($type, 'subscription.')) {
                try {
                    // If payload includes enough subscription details, prefer that
                    $cached = null;
                    if (! empty($payload['plan_name']) || ! empty($payload['id'])) {
                        // attempt to build cache from payload
                        $cached = [
                            'id' => $payload['id'] ?? null,
                            'plan_name' => $payload['plan_name'] ?? ($payload['plan'] ?? null),
                            'starts_at' => $payload['starts_at'] ?? null,
                            'ends_at' => $payload['ends_at'] ?? null,
                            'status' => $payload['status'] ?? null,
                        ];
                    }

                    // If we still don't have plan_name/ends_at, try to hydrate from DB using subscription id
                    if (empty($cached['plan_name']) && ! empty($payload['id'])) {
                        try {
                            $sub = \App\Models\Subscription::find($payload['id']);
                            if ($sub) {
                                $cached = [
                                    'id' => $sub->id,
                                    'plan_name' => $sub->plan_name ?? null,
                                    'starts_at' => $sub->starts_at?->toDateTimeString() ?? null,
                                    'ends_at' => $sub->ends_at?->toDateTimeString() ?? null,
                                    'status' => $sub->status ?? null,
                                ];
                            }
                        } catch (\Throwable $_) {
                            // ignore DB lookup failures
                        }
                    }

                    if (! empty($cached) && is_array($cached)) {
                        $cacheKey = "business_current_subscription:{$businessId}";
                        // set shorter TTL (30 minutes) to allow quick retrieval
                        $redis->setex($cacheKey, 1800, json_encode($cached));
                    }
                } catch (\Throwable $_) {
                    // Don't fail the main push if caching fails
                }
            }
        } catch (\Throwable $e) {
            // ignore write failures
        }

        return (int) $seq;
    }
}
