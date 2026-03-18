<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class BackpressureHandler
{
    private readonly string $prefix;

    private readonly string $consumerGroup;

    private readonly int $warningThreshold;

    private readonly int $criticalThreshold;

    private readonly int $resumeThreshold;

    public function __construct()
    {
        $this->prefix = (string) config('event-streaming.prefix', 'finaegis:events');
        $this->consumerGroup = (string) config('event-streaming.consumer_group', 'finaegis-consumers');
        $this->warningThreshold = (int) config('event-streaming.backpressure.warning_threshold', 1000);
        $this->criticalThreshold = (int) config('event-streaming.backpressure.critical_threshold', 5000);
        $this->resumeThreshold = (int) config('event-streaming.backpressure.resume_threshold', 500);
    }

    /**
     * Check backpressure status for a domain stream.
     *
     * @return array{status: string, pending_count: int, threshold_warning: int, threshold_critical: int, should_pause: bool}
     */
    public function checkBackpressure(string $domain): array
    {
        $pendingCount = $this->getPendingCount($domain);
        $isPaused = $this->isConsumerPaused($domain);

        $status = match (true) {
            $pendingCount >= $this->criticalThreshold => 'critical',
            $pendingCount >= $this->warningThreshold  => 'warning',
            default                                   => 'healthy',
        };

        $shouldPause = $status === 'critical' && ! $isPaused;

        if ($shouldPause) {
            Log::critical("Backpressure critical on domain: {$domain}", [
                'pending_count'      => $pendingCount,
                'critical_threshold' => $this->criticalThreshold,
            ]);
        } elseif ($status === 'warning') {
            Log::warning("Backpressure warning on domain: {$domain}", [
                'pending_count'     => $pendingCount,
                'warning_threshold' => $this->warningThreshold,
            ]);
        }

        return [
            'status'             => $status,
            'pending_count'      => $pendingCount,
            'threshold_warning'  => $this->warningThreshold,
            'threshold_critical' => $this->criticalThreshold,
            'should_pause'       => $shouldPause,
        ];
    }

    /**
     * Pause a consumer group for a domain.
     */
    public function pauseConsumer(string $domain, string $reason = 'backpressure'): bool
    {
        $cacheKey = $this->getPauseCacheKey($domain);

        try {
            Cache::put($cacheKey, [
                'paused_at' => now()->toIso8601String(),
                'reason'    => $reason,
                'domain'    => $domain,
            ], now()->addHours(24));

            Log::warning("Consumer paused for domain: {$domain}", [
                'reason' => $reason,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error("Failed to pause consumer for domain: {$domain}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resume a consumer group for a domain.
     */
    public function resumeConsumer(string $domain): bool
    {
        $cacheKey = $this->getPauseCacheKey($domain);

        try {
            $pendingCount = $this->getPendingCount($domain);

            if ($pendingCount > $this->resumeThreshold) {
                Log::info("Cannot resume consumer for domain: {$domain} - pending count ({$pendingCount}) still above resume threshold ({$this->resumeThreshold})");

                return false;
            }

            Cache::forget($cacheKey);

            Log::info("Consumer resumed for domain: {$domain}", [
                'pending_count' => $pendingCount,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error("Failed to resume consumer for domain: {$domain}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get health status for a consumer on a specific domain.
     *
     * @return array{domain: string, status: string, pending_count: int, is_paused: bool, pause_info: array<string, string>|null, consumer_group: string}
     */
    public function getConsumerHealth(string $domain): array
    {
        $pendingCount = $this->getPendingCount($domain);
        $isPaused = $this->isConsumerPaused($domain);
        $pauseInfo = $this->getPauseInfo($domain);

        $status = match (true) {
            $isPaused                                 => 'paused',
            $pendingCount >= $this->criticalThreshold => 'critical',
            $pendingCount >= $this->warningThreshold  => 'warning',
            default                                   => 'healthy',
        };

        return [
            'domain'         => $domain,
            'status'         => $status,
            'pending_count'  => $pendingCount,
            'is_paused'      => $isPaused,
            'pause_info'     => $pauseInfo,
            'consumer_group' => $this->consumerGroup,
        ];
    }

    /**
     * Check if a consumer is currently paused.
     */
    public function isConsumerPaused(string $domain): bool
    {
        return Cache::has($this->getPauseCacheKey($domain));
    }

    /**
     * Get the number of pending messages for a domain's consumer group.
     */
    private function getPendingCount(string $domain): int
    {
        $streamKey = $this->resolveStreamKey($domain);

        try {
            /** @var array<int, array<string, mixed>> $groups */
            $groups = Redis::xinfo('GROUPS', $streamKey);

            foreach ($groups as $group) {
                if (($group['name'] ?? '') === $this->consumerGroup) {
                    return (int) ($group['pending'] ?? 0);
                }
            }

            return 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Get pause information for a domain.
     *
     * @return array<string, string>|null
     */
    private function getPauseInfo(string $domain): ?array
    {
        $cacheKey = $this->getPauseCacheKey($domain);

        /** @var array<string, string>|null $info */
        $info = Cache::get($cacheKey);

        return $info;
    }

    private function getPauseCacheKey(string $domain): string
    {
        return "event-streaming:paused:{$domain}";
    }

    private function resolveStreamKey(string $domain): string
    {
        /** @var array<string, string> $streams */
        $streams = config('event-streaming.streams', []);
        $streamSuffix = $streams[strtolower($domain)] ?? "{$domain}-events";

        return "{$this->prefix}:{$streamSuffix}";
    }
}
