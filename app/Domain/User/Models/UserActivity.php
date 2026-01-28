<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    use UsesTenantConnection;

    protected $table = 'user_activities';

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'user_id', 'user_id');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('tracked_at', '>=', now()->subDays($days));
    }

    public function scopeByActivity($query, string $activity)
    {
        return $query->where('activity', $activity);
    }
}
