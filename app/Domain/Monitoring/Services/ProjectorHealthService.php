<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\EventSourcing\Facades\Projectionist;

class ProjectorHealthService
{
    private const CACHE_KEY = 'projector_health_status';

    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get health status for all registered projectors.
     *
     * @return array<string, mixed>
     */
    public function getAllProjectorStatus(): array
    {
        $projectors = Projectionist::getProjectors();
        $statuses = [];

        foreach ($projectors as $projector) {
            $statuses[] = $this->getProjectorStatus($projector);
        }

        return [
            'total_projectors' => count($statuses),
            'healthy'          => collect($statuses)->where('status', 'healthy')->count(),
            'stale'            => collect($statuses)->where('status', 'stale')->count(),
            'failed'           => collect($statuses)->where('status', 'failed')->count(),
            'projectors'       => $statuses,
            'checked_at'       => now()->toIso8601String(),
        ];
    }

    /**
     * Get health status for a single projector.
     *
     * @return array<string, mixed>
     */
    public function getProjectorStatus(object $projector): array
    {
        $className = $projector::class;
        $lastProcessedAt = $this->getLastProcessedAt($className);
        $lag = $this->calculateLag($className);
        $status = $this->determineStatus($lastProcessedAt, $lag);

        return [
            'class'             => $className,
            'name'              => class_basename($className),
            'status'            => $status,
            'last_processed_at' => $lastProcessedAt?->toIso8601String(),
            'lag'               => $lag,
            'domain'            => $this->extractDomain($className),
        ];
    }

    /**
     * Get cached health status or refresh.
     *
     * @return array<string, mixed>
     */
    public function getCachedStatus(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->getAllProjectorStatus();
        });
    }

    /**
     * Detect stale projectors (no activity for > 1 hour with pending events).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function detectStaleProjectors(): Collection
    {
        $status = $this->getAllProjectorStatus();

        return collect($status['projectors'])->filter(
            fn (array $p) => $p['status'] === 'stale' || $p['status'] === 'failed'
        )->values();
    }

    /**
     * Get the last time a projector processed an event.
     */
    private function getLastProcessedAt(string $projectorClass): ?\Illuminate\Support\Carbon
    {
        try {
            $record = DB::table('projector_statuses')
                ->where('projector_name', $projectorClass)
                ->first();
        } catch (\Illuminate\Database\QueryException) {
            return null;
        }

        if (! $record) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($record->updated_at);
    }

    /**
     * Calculate the number of unprocessed events for a projector.
     */
    private function calculateLag(string $projectorClass): int
    {
        try {
            $lastProcessedId = DB::table('projector_statuses')
                ->where('projector_name', $projectorClass)
                ->value('last_processed_event_id');
        } catch (\Illuminate\Database\QueryException) {
            return 0;
        }

        if ($lastProcessedId === null) {
            return 0;
        }

        return (int) DB::table('stored_events')
            ->where('id', '>', $lastProcessedId)
            ->count();
    }

    /**
     * Determine projector health status.
     */
    private function determineStatus(?\Illuminate\Support\Carbon $lastProcessedAt, int $lag): string
    {
        if ($lastProcessedAt === null) {
            return 'unknown';
        }

        $minutesSinceLastActivity = $lastProcessedAt->diffInMinutes(now());

        if ($lag > 1000) {
            return 'failed';
        }

        if ($minutesSinceLastActivity > 60 && $lag > 0) {
            return 'stale';
        }

        return 'healthy';
    }

    /**
     * Extract domain name from projector class.
     */
    private function extractDomain(string $className): string
    {
        if (preg_match('/App\\\\Domain\\\\([^\\\\]+)\\\\/', $className, $matches)) {
            return $matches[1];
        }

        return 'Unknown';
    }
}
