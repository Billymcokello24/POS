<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Predis\Client as PredisClient;

class SseController extends Controller
{
    public function businessStream(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response('Unauthorized', 401);
        }

        $businessId = $user->current_business_id;
        if (! $businessId) {
            return response('No business context', 400);
        }

        // Streamed response that subscribes to Redis channel and also sends missed events
        $response = response()->stream(function () use ($businessId) {
            // open a Redis client
            $redis = new PredisClient();
            $channel = "business_sse_channel:{$businessId}";
            $seqKey = "business_sse_seq:{$businessId}";
            $lastServedKey = "business_sse_last_served:{$businessId}";

            // Determine starting sequence (current seq). New clients start from current and only receive new events
            try {
                $currentSeq = (int) $redis->get($seqKey) ?? 0;
            } catch (\Throwable $e) {
                $currentSeq = 0;
            }

            $lastSeq = (int) (Cache::get($lastServedKey, $currentSeq) ?? $currentSeq);

            // Flush any missed events between lastSeq and currentSeq
            try {
                for ($s = $lastSeq + 1; $s <= $currentSeq; $s++) {
                    $eventKey = "business_sse_event:{$businessId}:{$s}";
                    $item = $redis->get($eventKey);
                    if ($item) {
                        echo "event: business:update\n";
                        echo "data: {$item}\n\n";
                        @ob_flush(); @flush();
                        $lastSeq = $s;
                        Cache::put($lastServedKey, $lastSeq, now()->addHours(6));
                        // Optionally delete if you want to free space
                        // $redis->del($eventKey);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // Subscribe to channel for live events
            try {
                $pubsub = $redis->pubSubLoop();
                $pubsub->subscribe($channel);

                foreach ($pubsub as $message) {
                    if ($message->kind === 'message') {
                        $payload = $message->payload;

                        // forward to client
                        echo "event: business:update\n";
                        echo "data: {$payload}\n\n";
                        @ob_flush(); @flush();

                        // Update last served seq if payload includes seq
                        try {
                            $obj = json_decode($payload, true);
                            if (isset($obj['seq'])) {
                                $lastSeq = (int) $obj['seq'];
                                Cache::put($lastServedKey, $lastSeq, now()->addHours(6));
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }

                    // If client disconnected, stop loop
                    if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
                        $pubsub->unsubscribe();
                        break;
                    }
                }

                // cleanup
                $pubsub->close();
            } catch (\Throwable $e) {
                // On Redis errors, fallback to short polling
                while (! connection_aborted() && connection_status() === CONNECTION_NORMAL) {
                    try {
                        $currentSeq = (int) ($redis->get($seqKey) ?? 0);
                        if ($currentSeq > $lastSeq) {
                            for ($s = $lastSeq + 1; $s <= $currentSeq; $s++) {
                                $eventKey = "business_sse_event:{$businessId}:{$s}";
                                $item = $redis->get($eventKey);
                                if ($item) {
                                    echo "event: business:update\n";
                                    echo "data: {$item}\n\n";
                                    @ob_flush(); @flush();
                                    $lastSeq = $s;
                                    Cache::put($lastServedKey, $lastSeq, now()->addHours(6));
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    sleep(1);
                    if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) break;
                }
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

        return $response;
    }
}
