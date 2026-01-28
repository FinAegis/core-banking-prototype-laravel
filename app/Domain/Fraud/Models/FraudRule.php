<?php

namespace App\Domain\Fraud\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values)
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 */
class FraudRule extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'severity',
        'is_active',
        'is_blocking',
        'conditions',
        'thresholds',
        'time_window',
        'min_occurrences',
        'base_score',
        'weight',
        'actions',
        'notification_channels',
        'triggers_count',
        'true_positives',
        'false_positives',
        'precision_rate',
        'last_triggered_at',
        'ml_enabled',
        'ml_model_id',
        'ml_features',
        'ml_confidence_threshold',
    ];

    protected $casts = [
        'is_active'               => 'boolean',
        'is_blocking'             => 'boolean',
        'ml_enabled'              => 'boolean',
        'conditions'              => 'array',
        'thresholds'              => 'array',
        'actions'                 => 'array',
        'notification_channels'   => 'array',
        'ml_features'             => 'array',
        'base_score'              => 'integer',
        'weight'                  => 'decimal:2',
        'precision_rate'          => 'decimal:2',
        'ml_confidence_threshold' => 'decimal:2',
        'last_triggered_at'       => 'datetime',
    ];

    public const CATEGORY_VELOCITY = 'velocity';

    public const CATEGORY_PATTERN = 'pattern';

    public const CATEGORY_AMOUNT = 'amount';

    public const CATEGORY_GEOGRAPHY = 'geography';

    public const CATEGORY_DEVICE = 'device';

    public const CATEGORY_BEHAVIOR = 'behavior';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const ACTION_BLOCK = 'block';

    public const ACTION_FLAG = 'flag';

    public const ACTION_REVIEW = 'review';

    public const ACTION_NOTIFY = 'notify';

    public const ACTION_CHALLENGE = 'challenge';

    public const CATEGORIES = [
        self::CATEGORY_VELOCITY  => 'Transaction Velocity',
        self::CATEGORY_PATTERN   => 'Pattern Detection',
        self::CATEGORY_AMOUNT    => 'Amount-based Rules',
        self::CATEGORY_GEOGRAPHY => 'Geographic Rules',
        self::CATEGORY_DEVICE    => 'Device-based Rules',
        self::CATEGORY_BEHAVIOR  => 'Behavioral Analysis',
    ];

    public const SEVERITIES = [
        self::SEVERITY_LOW      => 'Low Risk',
        self::SEVERITY_MEDIUM   => 'Medium Risk',
        self::SEVERITY_HIGH     => 'High Risk',
        self::SEVERITY_CRITICAL => 'Critical Risk',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($rule) {
                if (! $rule->code) {
                    $rule->code = static::generateRuleCode($rule->category);
                }
            }
        );
    }

    public static function generateRuleCode(string $category): string
    {
        $prefix = match ($category) {
            self::CATEGORY_VELOCITY  => 'VEL',
            self::CATEGORY_PATTERN   => 'PAT',
            self::CATEGORY_AMOUNT    => 'AMT',
            self::CATEGORY_GEOGRAPHY => 'GEO',
            self::CATEGORY_DEVICE    => 'DEV',
            self::CATEGORY_BEHAVIOR  => 'BEH',
            default                  => 'FR',
        };

        $count = static::where('code', 'like', $prefix . '-%')->count();

        return sprintf('%s-%03d', $prefix, $count + 1);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isBlocking(): bool
    {
        return $this->is_blocking;
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    public function isHighRisk(): bool
    {
        return in_array($this->severity, [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    public function recordTrigger(?bool $isPositive = null): void
    {
        $this->increment('triggers_count');
        $this->update(['last_triggered_at' => now()]);

        if ($isPositive !== null) {
            if ($isPositive) {
                $this->increment('true_positives');
            } else {
                $this->increment('false_positives');
            }
            $this->updatePrecisionRate();
        }
    }

    public function updatePrecisionRate(): void
    {
        $total = $this->true_positives + $this->false_positives;
        if ($total > 0) {
            $this->precision_rate = round(($this->true_positives / $total) * 100, 2);
            $this->save();
        }
    }

    public function getEffectiveness(): string
    {
        if ($this->precision_rate >= 90) {
            return 'Excellent';
        } elseif ($this->precision_rate >= 75) {
            return 'Good';
        } elseif ($this->precision_rate >= 50) {
            return 'Fair';
        } else {
            return 'Needs Improvement';
        }
    }

    public function evaluate(array $context): bool
    {
        if (! $this->is_active) {
            return false;
        }

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
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (! $field || ! $operator) {
            return false;
        }

        $contextValue = data_get($context, $field);

        return match ($operator) {
            'equals'           => $contextValue == $value,
            'not_equals'       => $contextValue != $value,
            'greater_than'     => $contextValue > $value,
            'less_than'        => $contextValue < $value,
            'greater_or_equal' => $contextValue >= $value,
            'less_or_equal'    => $contextValue <= $value,
            'contains'         => str_contains($contextValue, $value),
            'in'               => in_array($contextValue, (array) $value),
            'not_in'           => ! in_array($contextValue, (array) $value),
            'between'          => $contextValue >= $value[0] && $contextValue <= $value[1],
            'regex'            => preg_match($value, $contextValue),
            default            => false,
        };
    }

    public function calculateScore(array $context): float
    {
        $score = $this->base_score;

        // Apply weight based on severity
        $severityMultiplier = match ($this->severity) {
            self::SEVERITY_CRITICAL => 2.0,
            self::SEVERITY_HIGH     => 1.5,
            self::SEVERITY_MEDIUM   => 1.0,
            self::SEVERITY_LOW      => 0.5,
            default                 => 1.0,
        };

        return $score * $this->weight * $severityMultiplier;
    }

    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions ?? []);
    }

    public function shouldNotify(): bool
    {
        return $this->hasAction(self::ACTION_NOTIFY) && ! empty($this->notification_channels);
    }

    public function getTimeWindowInSeconds(): int
    {
        if (! $this->time_window) {
            return 0;
        }

        return match ($this->time_window) {
            '1h'    => 3600,
            '24h'   => 86400,
            '7d'    => 604800,
            '30d'   => 2592000,
            default => 0,
        };
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
