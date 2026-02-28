<?php

declare(strict_types=1);

namespace App\Domain\Rewards\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    use HasUuids;

    protected $fillable = [
        'reward_profile_id',
        'shop_item_id',
        'points_spent',
        'status',
        'metadata',
    ];

    protected $casts = [
        'points_spent' => 'integer',
        'metadata'     => 'array',
    ];

    /** @return BelongsTo<RewardProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(RewardProfile::class, 'reward_profile_id');
    }

    /** @return BelongsTo<RewardShopItem, $this> */
    public function shopItem(): BelongsTo
    {
        return $this->belongsTo(RewardShopItem::class, 'shop_item_id');
    }
}
