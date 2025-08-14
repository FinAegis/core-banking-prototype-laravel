<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidationBid extends Model
{
    protected $table = 'liquidation_bids';

    protected $fillable = [
        'auction_id',
        'bidder_id',
        'amount',
        'placed_at',
    ];

    protected $casts = [
        'amount'    => 'float',
        'placed_at' => 'datetime',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(LiquidationAuction::class, 'auction_id', 'auction_id');
    }
}
