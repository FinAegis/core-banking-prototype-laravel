<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FraudRule extends Model
{
    use HasUuids;

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
        'is_active' => 'boolean',
        'is_blocking' => 'boolean',
        'ml_enabled' => 'boolean',
        'conditions' => 'array',
        'thresholds' => 'array',
        'actions' => 'array',
        'notification_channels' => 'array',
        'ml_features' => 'array',
        'base_score' => 'integer',
        'weight' => 'decimal:2',
        'precision_rate' => 'decimal:2',
        'ml_confidence_threshold' => 'decimal:2',
        'last_triggered_at' => 'datetime',
    ];

    const CATEGORY_VELOCITY = 'velocity';
    const CATEGORY_PATTERN = 'pattern';
    const CATEGORY_AMOUNT = 'amount';
    const CATEGORY_GEOGRAPHY = 'geography';
    const CATEGORY_DEVICE = 'device';
    const CATEGORY_BEHAVIOR = 'behavior';

    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    const ACTION_BLOCK = 'block';
    const ACTION_FLAG = 'flag';
    const ACTION_REVIEW = 'review';
    const ACTION_NOTIFY = 'notify';
    const ACTION_CHALLENGE = 'challenge';

    const CATEGORIES = [
        self::CATEGORY_VELOCITY => 'Transaction Velocity',
        self::CATEGORY_PATTERN => 'Pattern Detection',
        self::CATEGORY_AMOUNT => 'Amount-based Rules',
        self::CATEGORY_GEOGRAPHY => 'Geographic Rules',
        self::CATEGORY_DEVICE => 'Device-based Rules',
        self::CATEGORY_BEHAVIOR => 'Behavioral Analysis',
    ];

    const SEVERITIES = [
        self::SEVERITY_LOW => 'Low Risk',
        self::SEVERITY_MEDIUM => 'Medium Risk',
        self::SEVERITY_HIGH => 'High Risk',
        self::SEVERITY_CRITICAL => 'Critical Risk',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($rule) {
            if (!$rule->code) {
                $rule->code = static::generateRuleCode($rule->category);
            }
        });
    }

    public static function generateRuleCode(string $category): string
    {
        $prefix = match($category) {
            self::CATEGORY_VELOCITY => 'VEL',
            self::CATEGORY_PATTERN => 'PAT',
            self::CATEGORY_AMOUNT => 'AMT',
            self::CATEGORY_GEOGRAPHY => 'GEO',
            self::CATEGORY_DEVICE => 'DEV',
            self::CATEGORY_BEHAVIOR => 'BEH',
            default => 'FR',
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

    public function recordTrigger(bool $isPositive = null): void
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
        if (!$this->is_active) {
            return false;
        }

        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
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

        if (!$field || !$operator) {
            return false;
        }

        $contextValue = data_get($context, $field);

        return match($operator) {
            'equals' => $contextValue == $value,
            'not_equals' => $contextValue != $value,
            'greater_than' => $contextValue > $value,
            'less_than' => $contextValue < $value,
            'greater_or_equal' => $contextValue >= $value,
            'less_or_equal' => $contextValue <= $value,
            'contains' => str_contains($contextValue, $value),
            'in' => in_array($contextValue, (array)$value),
            'not_in' => !in_array($contextValue, (array)$value),
            'between' => $contextValue >= $value[0] && $contextValue <= $value[1],
            'regex' => preg_match($value, $contextValue),
            default => false,
        };
    }

    public function calculateScore(array $context): float
    {
        $score = $this->base_score;

        // Apply weight based on severity
        $severityMultiplier = match($this->severity) {
            self::SEVERITY_CRITICAL => 2.0,
            self::SEVERITY_HIGH => 1.5,
            self::SEVERITY_MEDIUM => 1.0,
            self::SEVERITY_LOW => 0.5,
            default => 1.0,
        };

        return $score * $this->weight * $severityMultiplier;
    }

    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions ?? []);
    }

    public function shouldNotify(): bool
    {
        return $this->hasAction(self::ACTION_NOTIFY) && !empty($this->notification_channels);
    }

    public function getTimeWindowInSeconds(): int
    {
        if (!$this->time_window) {
            return 0;
        }

        return match($this->time_window) {
            '1h' => 3600,
            '24h' => 86400,
            '7d' => 604800,
            '30d' => 2592000,
            default => 0,
        };
    }
}