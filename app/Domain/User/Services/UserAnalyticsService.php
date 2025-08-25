<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Models\UserActivity;
use App\Domain\User\Models\UserProfile;
use Illuminate\Support\Facades\DB;

class UserAnalyticsService
{
    /**
     * Get user activity analytics.
     */
    public function getUserAnalytics(string $userId, int $days = 30): array
    {
        $profile = UserProfile::where('user_id', $userId)->first();

        if (! $profile) {
            return [];
        }

        $activities = UserActivity::forUser($userId)
            ->recent($days)
            ->get();

        return [
            'profile'    => $this->getProfileAnalytics($profile),
            'activity'   => $this->getActivityAnalytics($activities),
            'engagement' => $this->getEngagementMetrics($userId, $days),
            'behavior'   => $this->getBehaviorPatterns($activities),
        ];
    }

    /**
     * Get profile completeness analytics.
     */
    private function getProfileAnalytics(UserProfile $profile): array
    {
        $fields = [
            'first_name'               => 10,
            'last_name'                => 10,
            'phone_number'             => 15,
            'date_of_birth'            => 10,
            'country'                  => 10,
            'city'                     => 5,
            'address'                  => 5,
            'postal_code'              => 5,
            'is_verified'              => 20,
            'preferences'              => 5,
            'notification_preferences' => 5,
        ];

        $completeness = 0;
        $missingFields = [];

        foreach ($fields as $field => $weight) {
            if (empty($profile->$field)) {
                $missingFields[] = $field;
            } else {
                $completeness += $weight;
            }
        }

        return [
            'completeness_percentage' => min(100, $completeness),
            'missing_fields'          => $missingFields,
            'verification_status'     => $profile->is_verified,
            'account_age_days'        => $profile->created_at->diffInDays(now()),
        ];
    }

    /**
     * Get activity analytics.
     */
    private function getActivityAnalytics($activities): array
    {
        $activityCounts = $activities->groupBy('activity')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $hourlyDistribution = $activities->groupBy(function ($activity) {
            return $activity->tracked_at->format('H');
        })->map(fn ($group) => $group->count())->toArray();

        return [
            'total_activities'    => $activities->count(),
            'activity_types'      => $activityCounts,
            'hourly_distribution' => $hourlyDistribution,
            'most_active_hour'    => $this->getMostActiveHour($hourlyDistribution),
            'last_activity'       => $activities->first()?->tracked_at,
        ];
    }

    /**
     * Get engagement metrics.
     */
    private function getEngagementMetrics(string $userId, int $days): array
    {
        $loginCount = UserActivity::forUser($userId)
            ->recent($days)
            ->byActivity('login')
            ->count();

        $dailyActiveCount = UserActivity::forUser($userId)
            ->recent($days)
            ->select(DB::raw('DATE(tracked_at) as date'))
            ->distinct('date')
            ->count('date');

        $avgActivitiesPerDay = UserActivity::forUser($userId)
            ->recent($days)
            ->count() / max(1, $days);

        return [
            'login_count'      => $loginCount,
            'days_active'      => $dailyActiveCount,
            'activity_rate'    => round($avgActivitiesPerDay, 2),
            'engagement_score' => $this->calculateEngagementScore($loginCount, $dailyActiveCount, $days),
        ];
    }

    /**
     * Get behavior patterns.
     */
    private function getBehaviorPatterns($activities): array
    {
        $patterns = [];

        // Device usage
        $devices = $activities->pluck('context.device')->filter()->countBy();
        $patterns['preferred_device'] = $devices->sortDesc()->keys()->first() ?? 'unknown';

        // Time patterns
        $morningActivities = $activities->filter(function ($activity) {
            $hour = $activity->tracked_at->hour;

            return $hour >= 6 && $hour < 12;
        })->count();

        $afternoonActivities = $activities->filter(function ($activity) {
            $hour = $activity->tracked_at->hour;

            return $hour >= 12 && $hour < 18;
        })->count();

        $eveningActivities = $activities->filter(function ($activity) {
            $hour = $activity->tracked_at->hour;

            return $hour >= 18 && $hour < 24;
        })->count();

        $maxTime = max($morningActivities, $afternoonActivities, $eveningActivities);

        if ($maxTime === $morningActivities) {
            $patterns['active_period'] = 'morning';
        } elseif ($maxTime === $afternoonActivities) {
            $patterns['active_period'] = 'afternoon';
        } else {
            $patterns['active_period'] = 'evening';
        }

        // Activity patterns
        $patterns['frequent_activities'] = $activities->groupBy('activity')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->take(5)
            ->toArray();

        return $patterns;
    }

    /**
     * Calculate engagement score.
     */
    private function calculateEngagementScore(int $loginCount, int $daysActive, int $totalDays): int
    {
        $loginScore = min(30, $loginCount * 2);
        $activeScore = min(40, ($daysActive / max(1, $totalDays)) * 40);
        $consistencyScore = min(30, ($loginCount / max(1, $daysActive)) * 15);

        return (int) round($loginScore + $activeScore + $consistencyScore);
    }

    /**
     * Get most active hour.
     */
    private function getMostActiveHour(array $hourlyDistribution): ?string
    {
        if (empty($hourlyDistribution)) {
            return null;
        }

        $maxHour = array_search(max($hourlyDistribution), $hourlyDistribution);

        return sprintf('%02d:00', $maxHour);
    }

    /**
     * Get user segment.
     */
    public function getUserSegment(string $userId): string
    {
        $analytics = $this->getUserAnalytics($userId, 30);

        $engagementScore = $analytics['engagement']['engagement_score'] ?? 0;
        $completeness = $analytics['profile']['completeness_percentage'] ?? 0;

        if ($engagementScore >= 80 && $completeness >= 90) {
            return 'power_user';
        } elseif ($engagementScore >= 60 && $completeness >= 70) {
            return 'active_user';
        } elseif ($engagementScore >= 40) {
            return 'regular_user';
        } elseif ($engagementScore >= 20) {
            return 'occasional_user';
        } else {
            return 'dormant_user';
        }
    }
}
