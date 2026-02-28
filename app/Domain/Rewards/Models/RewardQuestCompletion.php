<?php

declare(strict_types=1);

namespace App\Domain\Rewards\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardQuestCompletion extends Model
{
    use HasUuids;

    protected $fillable = [
        'reward_profile_id',
        'quest_id',
        'completed_at',
        'xp_earned',
        'points_earned',
    ];

    protected $casts = [
        'completed_at'  => 'datetime',
        'xp_earned'     => 'integer',
        'points_earned' => 'integer',
    ];

    /** @return BelongsTo<RewardProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(RewardProfile::class, 'reward_profile_id');
    }

    /** @return BelongsTo<RewardQuest, $this> */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(RewardQuest::class, 'quest_id');
    }
}
