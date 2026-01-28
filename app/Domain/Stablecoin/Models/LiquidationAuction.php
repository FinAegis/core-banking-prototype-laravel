<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $auction_id
 * @property string $position_id
 * @property float $collateral_value
 * @property float $minimum_bid
 * @property string $status
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property string|null $winner_id
 * @property float|null $winning_bid
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property array $collateral
 */
class LiquidationAuction extends Model
{
    use UsesTenantConnection;

    protected $table = 'liquidation_auctions';

    protected $fillable = [
        'auction_id',
        'position_id',
        'collateral_value',
        'minimum_bid',
        'status',
        'started_at',
        'expires_at',
        'winner_id',
        'winning_bid',
        'completed_at',
        'collateral',
    ];

    protected $casts = [
        'collateral_value' => 'float',
        'minimum_bid'      => 'float',
        'winning_bid'      => 'float',
        'started_at'       => 'datetime',
        'expires_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'collateral'       => 'array',
    ];

    /**
     * @return HasMany<LiquidationBid, covariant $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(LiquidationBid::class, 'auction_id', 'auction_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && now()->lessThan($this->expires_at);
    }

    public function isExpired(): bool
    {
        return $this->status === 'active' && now()->greaterThanOrEqualTo($this->expires_at);
    }

    public function hasWinner(): bool
    {
        return $this->winner_id !== null;
    }
}
