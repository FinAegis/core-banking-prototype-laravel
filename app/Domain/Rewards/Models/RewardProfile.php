<?php

declare(strict_types=1);

namespace App\Domain\Rewards\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'xp',
        'level',
        'current_streak',
        'longest_streak',
        'last_activity_date',
        'points_balance',
    ];

    protected $casts = [
        'xp'                 => 'integer',
        'level'              => 'integer',
        'current_streak'     => 'integer',
        'longest_streak'     => 'integer',
        'last_activity_date' => 'date',
        'points_balance'     => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RewardQuestCompletion, $this> */
    public function questCompletions(): HasMany
    {
        return $this->hasMany(RewardQuestCompletion::class);
    }

    /** @return HasMany<RewardRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    public function xpForNextLevel(): int
    {
        return $this->level * 100;
    }

    public function xpProgress(): float
    {
        $needed = $this->xpForNextLevel();

        return $needed > 0 ? min(1.0, (float) $this->xp / $needed) : 0.0;
    }
}
