<?php

declare(strict_types=1);

namespace App\Domain\User\Aggregates;

use App\Domain\User\Events\UserActivityTracked;
use App\Domain\User\Repositories\UserEventRepository;
use App\Domain\User\Repositories\UserSnapshotRepository;
use DateTimeImmutable;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class UserActivityAggregate extends AggregateRoot
{
    private string $userId = '';

    private array $activities = [];

    private int $activityCount = 0;

    private ?DateTimeImmutable $lastActivityAt = null;

    private array $sessionHistory = [];

    /**
     * Get the stored event repository.
     */
    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(UserEventRepository::class);
    }

    /**
     * Get the snapshot repository.
     */
    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(UserSnapshotRepository::class);
    }

    /**
     * Track a user activity.
     */
    public function trackActivity(
        string $userId,
        string $activity,
        array $context = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null
    ): self {
        $this->recordThat(new UserActivityTracked(
            userId: $userId,
            activity: $activity,
            context: $context,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            sessionId: $sessionId,
            trackedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    /**
     * Apply user activity tracked event.
     */
    protected function applyUserActivityTracked(UserActivityTracked $event): void
    {
        $this->userId = $event->userId;
        $this->activityCount++;
        $this->lastActivityAt = $event->trackedAt;

        // Store last 100 activities in memory
        $this->activities[] = [
            'activity'   => $event->activity,
            'context'    => $event->context,
            'tracked_at' => $event->trackedAt,
            'ip_address' => $event->ipAddress,
            'session_id' => $event->sessionId,
        ];

        if (count($this->activities) > 100) {
            array_shift($this->activities);
        }

        // Track session history
        if ($event->sessionId && ! in_array($event->sessionId, $this->sessionHistory)) {
            $this->sessionHistory[] = $event->sessionId;
            if (count($this->sessionHistory) > 50) {
                array_shift($this->sessionHistory);
            }
        }
    }

    /**
     * Get recent activities.
     */
    public function getRecentActivities(int $limit = 10): array
    {
        return array_slice($this->activities, -$limit);
    }

    /**
     * Get activity count.
     */
    public function getActivityCount(): int
    {
        return $this->activityCount;
    }

    /**
     * Get last activity timestamp.
     */
    public function getLastActivityAt(): ?DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    /**
     * Check if user has performed a specific activity.
     */
    public function hasPerformedActivity(string $activity): bool
    {
        foreach ($this->activities as $activityData) {
            if ($activityData['activity'] === $activity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get activity frequency for a specific activity type.
     */
    public function getActivityFrequency(string $activity): int
    {
        $count = 0;
        foreach ($this->activities as $activityData) {
            if ($activityData['activity'] === $activity) {
                $count++;
            }
        }

        return $count;
    }
}
