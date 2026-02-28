<?php

declare(strict_types=1);

namespace App\Domain\Rewards\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardQuest extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'xp_reward',
        'points_reward',
        'category',
        'icon',
        'is_repeatable',
        'is_active',
        'sort_order',
        'criteria',
    ];

    protected $casts = [
        'xp_reward'     => 'integer',
        'points_reward' => 'integer',
        'is_repeatable' => 'boolean',
        'is_active'     => 'boolean',
        'sort_order'    => 'integer',
        'criteria'      => 'array',
    ];

    /** @return HasMany<RewardQuestCompletion, $this> */
    public function completions(): HasMany
    {
        return $this->hasMany(RewardQuestCompletion::class, 'quest_id');
    }
}
