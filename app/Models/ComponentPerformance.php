<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Asset\Models\Asset;

class ComponentPerformance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'basket_performance_id',
        'asset_code',
        'start_weight',
        'end_weight',
        'average_weight',
        'contribution_value',
        'contribution_percentage',
        'return_value',
        'return_percentage',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_weight' => 'float',
        'end_weight' => 'float',
        'average_weight' => 'float',
        'contribution_value' => 'float',
        'contribution_percentage' => 'float',
        'return_value' => 'float',
        'return_percentage' => 'float',
    ];

    /**
     * Get the basket performance record this belongs to.
     */
    public function basketPerformance(): BelongsTo
    {
        return $this->belongsTo(BasketPerformance::class);
    }

    /**
     * Get the asset associated with this component.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_code', 'code');
    }

    /**
     * Get a formatted contribution percentage.
     */
    public function getFormattedContributionAttribute(): string
    {
        $prefix = $this->contribution_percentage >= 0 ? '+' : '';
        return $prefix . number_format($this->contribution_percentage, 2) . '%';
    }

    /**
     * Get a formatted return percentage.
     */
    public function getFormattedReturnAttribute(): string
    {
        $prefix = $this->return_percentage >= 0 ? '+' : '';
        return $prefix . number_format($this->return_percentage, 2) . '%';
    }

    /**
     * Check if this component had a positive contribution.
     */
    public function hasPositiveContribution(): bool
    {
        return $this->contribution_percentage > 0;
    }

    /**
     * Get the weight change during the period.
     */
    public function getWeightChangeAttribute(): float
    {
        return $this->end_weight - $this->start_weight;
    }

    /**
     * Check if the component weight changed significantly.
     */
    public function hasSignificantWeightChange(float $threshold = 1.0): bool
    {
        return abs($this->weight_change) >= $threshold;
    }

    /**
     * Scope a query to only include positive contributors.
     */
    public function scopePositiveContributors($query)
    {
        return $query->where('contribution_percentage', '>', 0);
    }

    /**
     * Scope a query to only include negative contributors.
     */
    public function scopeNegativeContributors($query)
    {
        return $query->where('contribution_percentage', '<', 0);
    }

    /**
     * Scope a query to order by contribution percentage.
     */
    public function scopeOrderByContribution($query, $direction = 'desc')
    {
        return $query->orderBy('contribution_percentage', $direction);
    }
}