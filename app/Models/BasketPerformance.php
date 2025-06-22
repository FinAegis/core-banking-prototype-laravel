<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Asset\Models\Asset;

class BasketPerformance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'basket_asset_code',
        'period_type',
        'period_start',
        'period_end',
        'start_value',
        'end_value',
        'high_value',
        'low_value',
        'average_value',
        'return_value',
        'return_percentage',
        'volatility',
        'sharpe_ratio',
        'max_drawdown',
        'value_count',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'start_value' => 'float',
        'end_value' => 'float',
        'high_value' => 'float',
        'low_value' => 'float',
        'average_value' => 'float',
        'return_value' => 'float',
        'return_percentage' => 'float',
        'volatility' => 'float',
        'sharpe_ratio' => 'float',
        'max_drawdown' => 'float',
        'value_count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the basket associated with this performance record.
     */
    public function basket(): BelongsTo
    {
        return $this->belongsTo(BasketAsset::class, 'basket_asset_code', 'code');
    }
    
    /**
     * Get the component performances for this record.
     */
    public function componentPerformances(): HasMany
    {
        return $this->hasMany(ComponentPerformance::class);
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
     * Get the performance rating based on return percentage.
     */
    public function getPerformanceRatingAttribute(): string
    {
        if ($this->return_percentage >= 10) {
            return 'excellent';
        } elseif ($this->return_percentage >= 5) {
            return 'good';
        } elseif ($this->return_percentage >= 0) {
            return 'neutral';
        } elseif ($this->return_percentage >= -5) {
            return 'poor';
        } else {
            return 'very_poor';
        }
    }

    /**
     * Get the risk rating based on volatility.
     */
    public function getRiskRatingAttribute(): string
    {
        if ($this->volatility <= 5) {
            return 'very_low';
        } elseif ($this->volatility <= 10) {
            return 'low';
        } elseif ($this->volatility <= 20) {
            return 'moderate';
        } elseif ($this->volatility <= 30) {
            return 'high';
        } else {
            return 'very_high';
        }
    }

    /**
     * Check if this performance period is complete.
     */
    public function isComplete(): bool
    {
        return $this->period_end->isPast() && $this->value_count > 0;
    }

    /**
     * Get annualized return based on period type.
     */
    public function getAnnualizedReturn(): float
    {
        if ($this->return_percentage === 0.0) {
            return 0.0;
        }

        $daysInPeriod = $this->period_start->diffInDays($this->period_end);
        if ($daysInPeriod === 0) {
            return 0.0;
        }

        $periodsPerYear = 365.25 / $daysInPeriod;
        
        // Compound annual growth rate formula
        $annualizedReturn = (pow(1 + ($this->return_percentage / 100), $periodsPerYear) - 1) * 100;
        
        return round($annualizedReturn, 2);
    }

    /**
     * Scope a query to only include complete performance records.
     */
    public function scopeComplete($query)
    {
        return $query->where('period_end', '<', now())
            ->where('value_count', '>', 0);
    }

    /**
     * Scope a query to filter by period type.
     */
    public function scopeByPeriodType($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    /**
     * Scope a query to get performance for a specific basket.
     */
    public function scopeForBasket($query, string $basketCode)
    {
        return $query->where('basket_asset_code', $basketCode);
    }

    /**
     * Scope a query to order by period start date.
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('period_start', 'asc');
    }

    /**
     * Scope a query to order by period start date descending.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('period_start', 'desc');
    }
}