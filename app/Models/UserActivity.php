<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    protected $fillable = [
        'user_id',
        'activity',
        'context',
        'tracked_at',
        'ip_address',
        'user_agent',
        'session_id',
    ];

    protected $casts = [
        'context'    => 'array',
        'tracked_at' => 'datetime',
    ];

    /**
     * Get the user that owns the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by activity type.
     */
    public function scopeByActivity($query, string $activity)
    {
        return $query->where('activity', $activity);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('tracked_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get recent activities.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('tracked_at', '>=', now()->subDays($days));
    }
}
