<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Log;
use RuntimeException;
use Workflow\Activity;

class AcknowledgeMessageActivity extends Activity
{
    private const ACKNOWLEDGMENT_CHANNEL = 'agent:acknowledgments';

    private const ACK_TIMEOUT = 60; // seconds

    public function execute(string $messageId): array
    {
        $ackKey = "agent:message:{$messageId}:ack";
        $startTime = time();
        $timeout = self::ACK_TIMEOUT;

        // Poll for acknowledgment
        while ((time() - $startTime) < $timeout) {
            // Check if acknowledgment exists in cache
            $acknowledgment = Cache::get($ackKey);

            if ($acknowledgment !== null) {
                return $this->processAcknowledgment($messageId, $acknowledgment);
            }

            // Check Redis for real-time acknowledgment
            $redisAck = Redis::get($ackKey);
            if ($redisAck !== null) {
                $acknowledgment = json_decode($redisAck, true);

                return $this->processAcknowledgment($messageId, $acknowledgment);
            }

            // Check for acknowledgment in pubsub channel
            $pubsubAck = $this->checkPubSubChannel($messageId);
            if ($pubsubAck !== null) {
                return $this->processAcknowledgment($messageId, $pubsubAck);
            }

            // Wait before next poll
            usleep(500000); // 500ms
        }

        // Timeout occurred
        throw new RuntimeException(
            "Acknowledgment timeout for message {$messageId} after {$timeout} seconds"
        );
    }

    private function processAcknowledgment(string $messageId, array $acknowledgment): array
    {
        // Validate acknowledgment structure
        if (! isset($acknowledgment['acknowledgmentId'])) {
            $acknowledgment['acknowledgmentId'] = $this->generateAcknowledgmentId($messageId);
        }

        if (! isset($acknowledgment['acknowledgedAt'])) {
            $acknowledgment['acknowledgedAt'] = now()->toIso8601String();
        }

        // Store acknowledgment for audit trail
        $this->storeAcknowledgment($messageId, $acknowledgment);

        // Update message status
        $this->updateMessageStatus($messageId, 'acknowledged');

        return [
            'acknowledgedAt'     => $acknowledgment['acknowledgedAt'],
            'acknowledgmentId'   => $acknowledgment['acknowledgmentId'],
            'acknowledgedBy'     => $acknowledgment['acknowledgedBy'] ?? null,
            'acknowledgmentType' => $acknowledgment['type'] ?? 'automatic',
            'metadata'           => $acknowledgment['metadata'] ?? [],
        ];
    }

    private function checkPubSubChannel(string $messageId): ?array
    {
        try {
            // Subscribe to acknowledgment channel with timeout
            $redis = Redis::connection('pubsub');
            $channelKey = self::ACKNOWLEDGMENT_CHANNEL . ':' . $messageId;

            // Non-blocking check for message
            $message = $redis->get($channelKey);

            if ($message !== null) {
                return json_decode($message, true);
            }

            return null;
        } catch (Exception $e) {
            // Log error but don't fail the activity
            Log::warning('Failed to check pubsub channel for acknowledgment', [
                'messageId' => $messageId,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function storeAcknowledgment(string $messageId, array $acknowledgment): void
    {
        $key = "agent:message:{$messageId}:ack:history";

        // Store in Redis with TTL
        Redis::setex(
            $key,
            86400, // 24 hours
            json_encode($acknowledgment)
        );

        // Also store in cache for quick access
        Cache::put(
            "agent:message:{$messageId}:ack",
            $acknowledgment,
            now()->addHours(24)
        );
    }

    private function updateMessageStatus(string $messageId, string $status): void
    {
        $key = "agent:message:{$messageId}:metadata";

        Redis::hSet($key, 'status', $status);
        Redis::hSet($key, 'statusUpdatedAt', now()->toIso8601String());
    }

    private function generateAcknowledgmentId(string $messageId): string
    {
        return sprintf(
            'ack_%s_%s',
            substr(md5($messageId), 0, 8),
            time()
        );
    }

    /**
     * Static method to register an acknowledgment (called by receiving agent).
     */
    public static function registerAcknowledgment(
        string $messageId,
        string $acknowledgedBy,
        array $metadata = []
    ): void {
        $acknowledgment = [
            'acknowledgmentId' => sprintf('ack_%s_%s', substr(md5($messageId), 0, 8), time()),
            'acknowledgedAt'   => now()->toIso8601String(),
            'acknowledgedBy'   => $acknowledgedBy,
            'type'             => 'manual',
            'metadata'         => $metadata,
        ];

        // Store in multiple locations for redundancy
        $ackKey = "agent:message:{$messageId}:ack";

        // Store in Redis
        Redis::setex($ackKey, 3600, json_encode($acknowledgment));

        // Store in Cache
        Cache::put($ackKey, $acknowledgment, now()->addHour());

        // Publish to channel
        $channelKey = self::ACKNOWLEDGMENT_CHANNEL . ':' . $messageId;
        Redis::setex($channelKey, 60, json_encode($acknowledgment));

        // Update message metadata
        $metadataKey = "agent:message:{$messageId}:metadata";
        Redis::hSet($metadataKey, 'status', 'acknowledged');
        Redis::hSet($metadataKey, 'acknowledgedAt', $acknowledgment['acknowledgedAt']);
    }
}
