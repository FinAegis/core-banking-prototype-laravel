<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class DeadLetterQueueService
{
    private readonly string $prefix;

    private readonly int $maxRetries;

    private readonly int $maxDlqLength;

    public function __construct()
    {
        $this->prefix = (string) config('event-streaming.prefix', 'finaegis:events');
        $this->maxRetries = (int) config('event-streaming.dead_letter.max_retries', 3);
        $this->maxDlqLength = (int) config('event-streaming.dead_letter.max_length', 10000);
    }

    /**
     * Send a failed message to the dead letter queue.
     *
     * @param  array<string, mixed>  $originalMessage
     */
    public function sendToDeadLetter(
        string $domain,
        string $messageId,
        array $originalMessage,
        string $consumerName,
        string $errorMessage,
    ): ?string {
        $dlqKey = $this->resolveDlqKey($domain);

        try {
            $retryCount = $this->getRetryCount($domain, $messageId);

            $fields = [
                'original_stream'     => $this->resolveStreamKey($domain),
                'original_message_id' => $messageId,
                'consumer'            => $consumerName,
                'error_message'       => $errorMessage,
                'retry_count'         => (string) ($retryCount + 1),
                'payload'             => json_encode($originalMessage, JSON_THROW_ON_ERROR),
                'failed_at'           => now()->toIso8601String(),
                'domain'              => $domain,
            ];

            /** @var string $dlqMessageId */
            $dlqMessageId = Redis::xadd($dlqKey, '*', $fields);

            if ($this->maxDlqLength > 0) {
                /** @phpstan-ignore argument.type */
                Redis::xtrim($dlqKey, 'MAXLEN', $this->maxDlqLength);
            }

            Log::warning("Message sent to DLQ: {$dlqKey}", [
                'original_message_id' => $messageId,
                'dlq_message_id'      => $dlqMessageId,
                'domain'              => $domain,
                'retry_count'         => $retryCount + 1,
                'error'               => $errorMessage,
            ]);

            return $dlqMessageId;
        } catch (Throwable $e) {
            Log::error("Failed to send message to DLQ: {$dlqKey}", [
                'message_id' => $messageId,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Retry a message from the dead letter queue by re-publishing to the original stream.
     */
    public function retryFromDeadLetter(string $domain, string $dlqMessageId): bool
    {
        $dlqKey = $this->resolveDlqKey($domain);

        try {
            /** @var array<string, array<string, string>>|false $messages */
            $messages = Redis::xrange($dlqKey, $dlqMessageId, $dlqMessageId);

            if ($messages === false || $messages === []) {
                Log::warning("DLQ message not found: {$dlqMessageId}");

                return false;
            }

            $message = reset($messages);
            $retryCount = (int) ($message['retry_count'] ?? 0);

            if ($retryCount >= $this->maxRetries) {
                Log::error("Max retries ({$this->maxRetries}) exceeded for DLQ message: {$dlqMessageId}", [
                    'domain'      => $domain,
                    'retry_count' => $retryCount,
                ]);

                return false;
            }

            $originalStream = $message['original_stream'] ?? $this->resolveStreamKey($domain);

            /** @var array<string, mixed> $payload */
            $payload = json_decode($message['payload'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

            $fields = [
                'domain'         => $payload['domain'] ?? $domain,
                'event_class'    => $payload['event_class'] ?? 'unknown',
                'aggregate_uuid' => $payload['aggregate_uuid'] ?? '',
                'payload'        => $message['payload'] ?? '{}',
                'published_at'   => now()->toIso8601String(),
                'retry_count'    => (string) $retryCount,
                'retried_from'   => $dlqMessageId,
            ];

            /** @var string $newMessageId */
            $newMessageId = Redis::xadd($originalStream, '*', $fields);

            // Remove from DLQ after successful retry
            Redis::xdel($dlqKey, [$dlqMessageId]);

            Log::info("Message retried from DLQ: {$dlqKey}", [
                'dlq_message_id' => $dlqMessageId,
                'new_message_id' => $newMessageId,
                'domain'         => $domain,
                'retry_count'    => $retryCount,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error("Failed to retry message from DLQ: {$dlqKey}", [
                'dlq_message_id' => $dlqMessageId,
                'error'          => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * List messages in the dead letter queue for a domain.
     *
     * @return array<string, array<string, string>>
     */
    public function listDeadLetters(string $domain, int $count = 100): array
    {
        $dlqKey = $this->resolveDlqKey($domain);

        try {
            /** @var array<string, array<string, string>>|false $messages */
            $messages = Redis::xrange($dlqKey, '-', '+', $count);

            if ($messages === false) {
                return [];
            }

            return $messages;
        } catch (Throwable $e) {
            Log::error("Failed to list DLQ messages: {$dlqKey}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Purge all messages from the dead letter queue for a domain.
     */
    public function purgeDeadLetters(string $domain): bool
    {
        $dlqKey = $this->resolveDlqKey($domain);

        try {
            Redis::del([$dlqKey]);

            Log::info("DLQ purged: {$dlqKey}", [
                'domain' => $domain,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error("Failed to purge DLQ: {$dlqKey}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the count of messages in the dead letter queue for a domain.
     */
    public function getDeadLetterCount(string $domain): int
    {
        $dlqKey = $this->resolveDlqKey($domain);

        try {
            /** @var int $count */
            $count = Redis::xlen($dlqKey);

            return $count;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Check if a message has exceeded maximum retries.
     */
    public function hasExceededMaxRetries(string $domain, string $messageId): bool
    {
        return $this->getRetryCount($domain, $messageId) >= $this->maxRetries;
    }

    /**
     * Get the current retry count for a message.
     */
    private function getRetryCount(string $domain, string $messageId): int
    {
        $dlqKey = $this->resolveDlqKey($domain);

        try {
            /** @var array<string, array<string, string>>|false $messages */
            $messages = Redis::xrange($dlqKey, '-', '+');

            if ($messages === false) {
                return 0;
            }

            foreach ($messages as $message) {
                if (($message['original_message_id'] ?? '') === $messageId) {
                    return (int) ($message['retry_count'] ?? 0);
                }
            }

            return 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private function resolveDlqKey(string $domain): string
    {
        return "{$this->prefix}:dlq:{$domain}";
    }

    private function resolveStreamKey(string $domain): string
    {
        /** @var array<string, string> $streams */
        $streams = config('event-streaming.streams', []);
        $streamSuffix = $streams[strtolower($domain)] ?? "{$domain}-events";

        return "{$this->prefix}:{$streamSuffix}";
    }
}
