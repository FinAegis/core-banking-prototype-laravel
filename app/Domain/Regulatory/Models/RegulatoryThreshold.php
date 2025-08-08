<?php

namespace App\Domain\Regulatory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder whereNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereNotNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder with(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder distinct(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder groupBy(string ...$groups)
 * @method static \Illuminate\Database\Eloquent\Builder having(string $column, string $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 * @method static static firstOrNew(array $attributes, array $values = [])
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static static create(array $attributes = [])
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static bool delete()
 * @method static bool update(array $values)
 * @method static \Illuminate\Database\Eloquent\Builder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder query()
 */
class RegulatoryThreshold extends Model
{
    use HasUuids;

    protected $fillable = [
        'threshold_code',
        'name',
        'description',
        'category',
        'report_type',
        'jurisdiction',
        'regulation_reference',
        'conditions',
        'amount_threshold',
        'currency',
        'count_threshold',
        'time_period',
        'time_period_days',
        'requires_aggregation',
        'aggregation_rules',
        'aggregation_key',
        'actions',
        'auto_report',
        'requires_review',
        'review_priority',
        'is_active',
        'effective_from',
        'effective_to',
        'status',
        'trigger_count',
        'last_triggered_at',
        'false_positive_rate',
    ];

    protected $casts = [
        'conditions'           => 'array',
        'aggregation_rules'    => 'array',
        'actions'              => 'array',
        'amount_threshold'     => 'decimal:2',
        'requires_aggregation' => 'boolean',
        'auto_report'          => 'boolean',
        'requires_review'      => 'boolean',
        'is_active'            => 'boolean',
        'effective_from'       => 'datetime',
        'effective_to'         => 'datetime',
        'last_triggered_at'    => 'datetime',
        'false_positive_rate'  => 'decimal:2',
    ];

    // Categories
    public const CATEGORY_TRANSACTION = 'transaction';

    public const CATEGORY_CUSTOMER = 'customer';

    public const CATEGORY_ACCOUNT = 'account';

    public const CATEGORY_AGGREGATE = 'aggregate';

    // Time periods
    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_QUARTERLY = 'quarterly';

    public const PERIOD_ANNUALLY = 'annually';

    public const PERIOD_ROLLING = 'rolling';

    // Actions
    public const ACTION_REPORT = 'report';

    public const ACTION_FLAG = 'flag';

    public const ACTION_NOTIFY = 'notify';

    public const ACTION_BLOCK = 'block';

    public const ACTION_REVIEW = 'review';

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($threshold) {
                if (empty($threshold->threshold_code)) {
                    $threshold->threshold_code = self::generateThresholdCode(
                        $threshold->report_type,
                        $threshold->jurisdiction,
                        $threshold->category
                    );
                }
            }
        );
    }

    // Helper methods
    public static function generateThresholdCode(string $reportType, string $jurisdiction, string $category): string
    {
        $prefix = "THR-{$reportType}-{$jurisdiction}";
        $categoryAbbr = strtoupper(substr($category, 0, 3));

        $lastThreshold = self::where('threshold_code', 'like', "{$prefix}-%")
            ->orderBy('threshold_code', 'desc')
            ->first();

        if ($lastThreshold) {
            $lastNumber = intval(substr($lastThreshold->threshold_code, -3));
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return "{$prefix}-{$categoryAbbr}-{$newNumber}";
    }

    public function evaluate(array $context): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        // Evaluate all conditions
        foreach ($this->conditions as $condition) {
            if (! $this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;
        $contextValue = data_get($context, $field);

        if ($contextValue === null) {
            return false;
        }

        switch ($operator) {
            case '=':
            case '==':
                return $contextValue == $value;
            case '!=':
            case '<>':
                return $contextValue != $value;
            case '>':
                return $contextValue > $value;
            case '>=':
                return $contextValue >= $value;
            case '<':
                return $contextValue < $value;
            case '<=':
                return $contextValue <= $value;
            case 'in':
                return in_array($contextValue, (array) $value);
            case 'not_in':
                return ! in_array($contextValue, (array) $value);
            case 'contains':
                return str_contains($contextValue, $value);
            case 'starts_with':
                return str_starts_with($contextValue, $value);
            case 'ends_with':
                return str_ends_with($contextValue, $value);
            case 'between':
                return $contextValue >= $value[0] && $contextValue <= $value[1];
            case 'regex':
                return preg_match($value, $contextValue);
            default:
                return false;
        }
    }

    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->effective_from && $now->isBefore($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && $now->isAfter($this->effective_to)) {
            return false;
        }

        return $this->status === 'active';
    }

    public function recordTrigger(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }

    public function shouldAutoReport(): bool
    {
        return $this->auto_report && in_array(self::ACTION_REPORT, $this->actions);
    }

    public function getTimePeriodDays(): int
    {
        if ($this->time_period_days) {
            return $this->time_period_days;
        }

        return match ($this->time_period) {
            self::PERIOD_DAILY     => 1,
            self::PERIOD_WEEKLY    => 7,
            self::PERIOD_MONTHLY   => 30,
            self::PERIOD_QUARTERLY => 90,
            self::PERIOD_ANNUALLY  => 365,
            default                => 30,
        };
    }

    public function getEffectiveAmountThreshold(?string $currency = null): float
    {
        if (! $currency || $currency === $this->currency) {
            return $this->amount_threshold;
        }

        // In production, implement currency conversion
        // For now, return the base amount
        return $this->amount_threshold;
    }

    public function updateFalsePositiveRate(int $totalTriggers, int $falsePositives): void
    {
        if ($totalTriggers > 0) {
            $rate = ($falsePositives / $totalTriggers) * 100;
            $this->update(['false_positive_rate' => round($rate, 2)]);
        }
    }

    public function getPerformanceMetrics(): array
    {
        return [
            'trigger_count'       => $this->trigger_count,
            'last_triggered'      => $this->last_triggered_at?->diffForHumans(),
            'false_positive_rate' => $this->false_positive_rate . '%',
            'effectiveness_score' => $this->calculateEffectivenessScore(),
        ];
    }

    protected function calculateEffectivenessScore(): float
    {
        if ($this->trigger_count === 0) {
            return 0;
        }

        // Simple effectiveness calculation
        // In production, use more sophisticated metrics
        $baseScore = 100;

        // Deduct for high false positive rate
        if ($this->false_positive_rate > 0) {
            $baseScore -= min(50, $this->false_positive_rate);
        }

        // Boost for consistent triggering
        if ($this->last_triggered_at && $this->last_triggered_at->diffInDays(now()) < 30) {
            $baseScore = min(100, $baseScore + 10);
        }

        return max(0, $baseScore);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'active')
            ->where(
                function ($q) {
                    $q->whereNull('effective_from')
                        ->orWhere('effective_from', '<=', now());
                }
            )
                    ->where(
                        function ($q) {
                            $q->whereNull('effective_to')
                                ->orWhere('effective_to', '>=', now());
                        }
                    );
    }

    public function scopeByReportType($query, string $reportType)
    {
        return $query->where('report_type', $reportType);
    }

    public function scopeByJurisdiction($query, string $jurisdiction)
    {
        return $query->where('jurisdiction', $jurisdiction);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeRequiringReview($query)
    {
        return $query->where('requires_review', true);
    }

    public function scopeAutoReporting($query)
    {
        return $query->where('auto_report', true);
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
