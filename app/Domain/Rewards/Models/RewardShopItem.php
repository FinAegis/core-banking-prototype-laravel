<?php

declare(strict_types=1);

namespace App\Domain\Rewards\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardShopItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'points_cost',
        'category',
        'icon',
        'stock',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'stock'       => 'integer',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
        'metadata'    => 'array',
    ];

    /** @return HasMany<RewardRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class, 'shop_item_id');
    }

    public function isAvailable(): bool
    {
        return $this->is_active && ($this->stock === null || $this->stock > 0);
    }
}
