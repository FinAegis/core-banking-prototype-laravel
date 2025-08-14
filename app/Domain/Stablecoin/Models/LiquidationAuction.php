<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquidationAuction extends Model
{
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
