<?php

declare(strict_types=1);

namespace App\Domain\Activity\Services;

use App\Domain\Activity\Models\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Activity logging and timeline service.
 *
 * Provides structured activity logging for audit trails, user timelines,
 * and model change tracking across all domains.
 */
class ActivityService
{
    /**
     * Log an activity event.
     *
     * @param array<string, mixed> $properties
     */
    public function log(
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        string $logName = 'default',
        array $properties = [],
    ): Activity {
        return Activity::create([
            'log_name'     => $logName,
            'description'  => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'causer_type'  => $causer?->getMorphClass(),
            'causer_id'    => $causer?->getKey(),
            'properties'   => $properties,
        ]);
    }

    /**
     * Get activity feed for a specific model (subject).
     *
     * @return LengthAwarePaginator
     */
    public function getActivityForSubject(Model $subject, int $perPage = 20): LengthAwarePaginator
    {
        return Activity::where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get activity timeline for a user (as causer).
     *
     * @return LengthAwarePaginator
     */
    public function getActivityForUser(Model $user, int $perPage = 20): LengthAwarePaginator
    {
        return Activity::where('causer_type', $user->getMorphClass())
            ->where('causer_id', $user->getKey())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get recent activity across the entire system.
     *
     * @return LengthAwarePaginator
     */
    public function getRecentActivity(
        ?string $logName = null,
        ?string $subjectType = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Activity::query()->orderByDesc('created_at');

        if ($logName !== null) {
            $query->where('log_name', $logName);
        }

        if ($subjectType !== null) {
            $query->where('subject_type', $subjectType);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get activity statistics.
     *
     * @return array{total: int, today: int, this_week: int, by_log: array<string, int>}
     */
    public function getStats(): array
    {
        return [
            'total'     => Activity::count(),
            'today'     => Activity::whereDate('created_at', today())->count(),
            'this_week' => Activity::where('created_at', '>=', now()->startOfWeek())->count(),
            'by_log'    => Activity::selectRaw('log_name, COUNT(*) as count')
                ->groupBy('log_name')
                ->pluck('count', 'log_name')
                ->toArray(),
        ];
    }

    /**
     * Purge old activity records.
     */
    public function purgeOlderThan(int $days = 90): int
    {
        $deleted = Activity::where('created_at', '<', now()->subDays($days))->delete();

        Log::info("Purged {$deleted} activity records older than {$days} days");

        return $deleted;
    }
}
