<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Workflow\Activity;

class QueueMessageActivity extends Activity
{
    private const DEFAULT_QUEUE = 'agent-messages';

    private const PRIORITY_QUEUES = [
        0   => 'agent-messages-low',
        25  => 'agent-messages-normal',
        50  => 'agent-messages-high',
        75  => 'agent-messages-urgent',
        100 => 'agent-messages-critical',
    ];

    public function execute(string $messageId, int $priority = 50, ?string $queueName = null): array
    {
        $effectiveQueueName = $this->determineQueueName($priority, $queueName);
        $queuedAt = now()->toIso8601String();

        // Store message metadata in Redis for tracking
        $this->storeMessageMetadata($messageId, $effectiveQueueName, $priority, $queuedAt);

        // Add message to priority-based sorted set in Redis
        $this->addToPriorityQueue($messageId, $priority, $effectiveQueueName);

        // Increment queue statistics
        $this->updateQueueStatistics($effectiveQueueName);

        return [
            'queuedAt'      => $queuedAt,
            'queueName'     => $effectiveQueueName,
            'priority'      => $priority,
            'position'      => $this->getQueuePosition($messageId, $effectiveQueueName),
            'queueLength'   => $this->getQueueLength($effectiveQueueName),
            'estimatedWait' => $this->estimateWaitTime($effectiveQueueName),
        ];
    }

    private function determineQueueName(int $priority, ?string $customQueue): string
    {
        if ($customQueue !== null) {
            return $customQueue;
        }

        // Map priority to appropriate queue
        foreach (self::PRIORITY_QUEUES as $threshold => $queueName) {
            if ($priority >= $threshold) {
                $selectedQueue = $queueName;
            }
        }

        return $selectedQueue ?? self::DEFAULT_QUEUE;
    }

    private function storeMessageMetadata(
        string $messageId,
        string $queueName,
        int $priority,
        string $queuedAt
    ): void {
        $key = "agent:message:{$messageId}:metadata";
        $metadata = [
            'messageId' => $messageId,
            'queueName' => $queueName,
            'priority'  => $priority,
            'queuedAt'  => $queuedAt,
            'status'    => 'queued',
        ];

        Redis::hMSet($key, $metadata);
        Redis::expire($key, 86400); // TTL of 24 hours
    }

    private function addToPriorityQueue(string $messageId, int $priority, string $queueName): void
    {
        $queueKey = "agent:queue:{$queueName}:messages";

        // Use negative priority for score so higher priorities are processed first
        // Add timestamp to ensure FIFO for same priority
        $score = -$priority * 1000000 + microtime(true);

        Redis::zAdd($queueKey, $score, $messageId);
    }

    private function updateQueueStatistics(string $queueName): void
    {
        $statsKey = "agent:queue:{$queueName}:stats";
        $date = now()->format('Y-m-d');

        Redis::hIncrBy($statsKey, 'total_messages', 1);
        Redis::hIncrBy($statsKey, "messages_{$date}", 1);
        Redis::hSet($statsKey, 'last_queued_at', now()->toIso8601String());

        // Set TTL for daily stats
        Redis::expire($statsKey, 604800); // 7 days
    }

    private function getQueuePosition(string $messageId, string $queueName): int
    {
        $queueKey = "agent:queue:{$queueName}:messages";
        $rank = Redis::zRevRank($queueKey, $messageId);

        return $rank !== false ? $rank + 1 : 0;
    }

    private function getQueueLength(string $queueName): int
    {
        $queueKey = "agent:queue:{$queueName}:messages";

        return (int) Redis::zCard($queueKey);
    }

    private function estimateWaitTime(string $queueName): int
    {
        $statsKey = "agent:queue:{$queueName}:stats";
        $rateValue = Redis::hGet($statsKey, 'avg_processing_rate');
        $processingRate = is_numeric($rateValue) ? (float) $rateValue : 10.0; // Default 10 msg/sec
        $queueLength = $this->getQueueLength($queueName);

        // Estimate wait time in seconds
        $estimatedSeconds = (int) ceil($queueLength / $processingRate);

        return min($estimatedSeconds, 3600); // Cap at 1 hour
    }
}
