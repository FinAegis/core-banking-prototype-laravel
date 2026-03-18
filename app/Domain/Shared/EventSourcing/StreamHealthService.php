<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class StreamHealthService
{
    private readonly string $prefix;

    private readonly int $warningThreshold;

    private readonly int $criticalThreshold;

    /**
     * @var array<string, string>
     */
    private readonly array $streamMappings;

    public function __construct()
    {
        $this->prefix = (string) config('event-streaming.prefix', 'finaegis:events');
        $this->warningThreshold = (int) config('event-streaming.backpressure.warning_threshold', 1000);
        $this->criticalThreshold = (int) config('event-streaming.backpressure.critical_threshold', 5000);

        /** @var array<string, string> $streams */
        $streams = config('event-streaming.streams', []);
        $this->streamMappings = $streams;
    }

    /**
     * Get a comprehensive health report across all streams.
     *
     * @return array{overall_status: string, streams: array<string, array<string, mixed>>, summary: array<string, int>, generated_at: string}
     */
    public function getHealthReport(): array
    {
        $streams = [];
        $statusCounts = [
            'healthy'     => 0,
            'warning'     => 0,
            'critical'    => 0,
            'unavailable' => 0,
        ];

        foreach ($this->streamMappings as $domain => $streamSuffix) {
            $metrics = $this->getStreamMetrics($domain);
            $streams[$domain] = $metrics;

            $status = $metrics['status'] ?? 'unavailable';
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        $overallStatus = match (true) {
            $statusCounts['critical'] > 0    => 'critical',
            $statusCounts['warning'] > 0     => 'warning',
            $statusCounts['unavailable'] > 0 => 'degraded',
            default                          => 'healthy',
        };

        return [
            'overall_status' => $overallStatus,
            'streams'        => $streams,
            'summary'        => $statusCounts,
            'generated_at'   => now()->toIso8601String(),
        ];
    }

    /**
     * Get detailed metrics for a specific stream.
     *
     * @return array<string, mixed>
     */
    public function getStreamMetrics(string $domain): array
    {
        $streamKey = $this->resolveStreamKey($domain);

        try {
            /** @var int $length */
            $length = Redis::xlen($streamKey);

            /** @var array<string, mixed>|false $info */
            $info = Redis::xinfo('STREAM', $streamKey);

            $consumerMetrics = $this->getConsumerMetrics($domain);
            $pendingCount = $this->getTotalPendingCount($domain);
            $throughput = $this->calculateThroughput($domain, $length);

            $status = match (true) {
                $pendingCount >= $this->criticalThreshold => 'critical',
                $pendingCount >= $this->warningThreshold  => 'warning',
                default                                   => 'healthy',
            };

            return [
                'stream_key'      => $streamKey,
                'length'          => $length,
                'status'          => $status,
                'pending_total'   => $pendingCount,
                'first_entry'     => $info !== false ? ($info['first-entry'] ?? null) : null,
                'last_entry'      => $info !== false ? ($info['last-entry'] ?? null) : null,
                'consumer_groups' => $consumerMetrics,
                'throughput'      => $throughput,
            ];
        } catch (Throwable $e) {
            Log::debug("Stream unavailable: {$streamKey}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'stream_key'      => $streamKey,
                'length'          => 0,
                'status'          => 'unavailable',
                'pending_total'   => 0,
                'first_entry'     => null,
                'last_entry'      => null,
                'consumer_groups' => [],
                'throughput'      => 0.0,
            ];
        }
    }

    /**
     * Get consumer group metrics for a domain.
     *
     * @return array<int, array{name: string, consumers: int, pending: int, last_delivered_id: string, status: string}>
     */
    public function getConsumerMetrics(string $domain): array
    {
        $streamKey = $this->resolveStreamKey($domain);

        try {
            /** @var array<int, array<string, mixed>> $groups */
            $groups = Redis::xinfo('GROUPS', $streamKey);

            $metrics = [];
            foreach ($groups as $group) {
                $pending = (int) ($group['pending'] ?? 0);
                $status = match (true) {
                    $pending >= $this->criticalThreshold => 'critical',
                    $pending >= $this->warningThreshold  => 'warning',
                    default                              => 'healthy',
                };

                $metrics[] = [
                    'name'              => (string) ($group['name'] ?? 'unknown'),
                    'consumers'         => (int) ($group['consumers'] ?? 0),
                    'pending'           => $pending,
                    'last_delivered_id' => (string) ($group['last-delivered-id'] ?? '0-0'),
                    'status'            => $status,
                ];
            }

            return $metrics;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Calculate throughput (messages per second) for a domain stream.
     *
     * Uses a sampling window stored in Redis to track message count changes.
     */
    private function calculateThroughput(string $domain, ?int $knownLength = null): float
    {
        $streamKey = $this->resolveStreamKey($domain);
        $throughputKey = "{$this->prefix}:throughput:{$domain}";

        try {
            $currentLength = $knownLength ?? (int) Redis::xlen($streamKey);
            $currentTime = microtime(true);

            /** @var string|null $previousData */
            $previousData = Redis::get($throughputKey);

            // Store current snapshot
            Redis::setex($throughputKey, 300, json_encode([
                'length' => $currentLength,
                'time'   => $currentTime,
            ], JSON_THROW_ON_ERROR));

            if ($previousData === null) {
                return 0.0;
            }

            /** @var array{length: int, time: float} $previous */
            $previous = json_decode($previousData, true, 512, JSON_THROW_ON_ERROR);
            $timeDiff = $currentTime - $previous['time'];

            if ($timeDiff <= 0) {
                return 0.0;
            }

            $messageDiff = $currentLength - $previous['length'];

            // Messages can be trimmed, so only calculate positive throughput
            if ($messageDiff <= 0) {
                return 0.0;
            }

            return round($messageDiff / $timeDiff, 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * Get total pending count across all consumer groups for a domain.
     */
    private function getTotalPendingCount(string $domain): int
    {
        $streamKey = $this->resolveStreamKey($domain);

        try {
            /** @var array<int, array<string, mixed>> $groups */
            $groups = Redis::xinfo('GROUPS', $streamKey);

            $total = 0;
            foreach ($groups as $group) {
                $total += (int) ($group['pending'] ?? 0);
            }

            return $total;
        } catch (Throwable) {
            return 0;
        }
    }

    private function resolveStreamKey(string $domain): string
    {
        $streamSuffix = $this->streamMappings[strtolower($domain)] ?? "{$domain}-events";

        return "{$this->prefix}:{$streamSuffix}";
    }
}
