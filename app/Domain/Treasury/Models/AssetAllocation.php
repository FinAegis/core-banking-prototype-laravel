<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AssetAllocation extends Model
{
    use UsesTenantConnection;
    use HasUuids;

    protected $fillable = [
        'portfolio_id',
        'asset_class',
        'target_weight',
        'current_weight',
        'drift',
        'target_amount',
        'current_amount',
        'metadata',
    ];

    protected $casts = [
        'target_weight'  => 'decimal:4',
        'current_weight' => 'decimal:4',
        'drift'          => 'decimal:4',
        'target_amount'  => 'decimal:2',
        'current_amount' => 'decimal:2',
        'metadata'       => 'array',
    ];

    public function getDriftAttribute($value): float
    {
        return (float) $value;
    }

    public function setDriftAttribute($value): void
    {
        $this->attributes['drift'] = (float) $value;
    }

    public function needsRebalancing(float $threshold = 5.0): bool
    {
        return abs($this->drift) > $threshold;
    }

    public function isOverweight(): bool
    {
        return $this->current_weight > $this->target_weight;
    }

    public function isUnderweight(): bool
    {
        return $this->current_weight < $this->target_weight;
    }

    public function getDriftPercentage(): float
    {
        return $this->target_weight > 0 ? ($this->drift / $this->target_weight) * 100 : 0.0;
    }

    public function calculateRequiredRebalancing(float $totalValue): array
    {
        $currentValue = $this->current_amount ?? ($this->current_weight / 100) * $totalValue;
        $targetValue = ($this->target_weight / 100) * $totalValue;
        $rebalanceAmount = $targetValue - $currentValue;

        return [
            'current_value'    => $currentValue,
            'target_value'     => $targetValue,
            'rebalance_amount' => $rebalanceAmount,
            'action'           => $rebalanceAmount > 0 ? 'buy' : 'sell',
            'amount'           => abs($rebalanceAmount),
        ];
    }
}
