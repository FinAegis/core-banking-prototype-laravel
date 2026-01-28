<?php

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * @method static int count(string $columns = '*')
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 */
class TransactionMonitoringRule extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\Domain\Compliance\TransactionMonitoringRuleFactory::new();
    }

    protected $fillable = [
        'rule_code',
        'name',
        'description',
        'category',
        'risk_level',
        'is_active',
        'conditions',
        'parameters',
        'time_window',
        'threshold_amount',
        'threshold_count',
        'actions',
        'auto_escalate',
        'escalation_level',
        'applies_to_customer_types',
        'applies_to_risk_levels',
        'applies_to_countries',
        'applies_to_currencies',
        'applies_to_transaction_types',
        'triggers_count',
        'true_positives',
        'false_positives',
        'accuracy_rate',
        'last_triggered_at',
        'created_by',
        'last_modified_by',
        'last_reviewed_at',
        'tuning_history',
    ];

    protected $casts = [
        'is_active'                    => 'boolean',
        'auto_escalate'                => 'boolean',
        'conditions'                   => 'array',
        'parameters'                   => 'array',
        'actions'                      => 'array',
        'applies_to_customer_types'    => 'array',
        'applies_to_risk_levels'       => 'array',
        'applies_to_countries'         => 'array',
        'applies_to_currencies'        => 'array',
        'applies_to_transaction_types' => 'array',
        'tuning_history'               => 'array',
        'threshold_amount'             => 'decimal:2',
        'accuracy_rate'                => 'decimal:2',
        'last_triggered_at'            => 'datetime',
        'last_reviewed_at'             => 'datetime',
    ];

    public const CATEGORY_VELOCITY = 'velocity';

    public const CATEGORY_PATTERN = 'pattern';

    public const CATEGORY_THRESHOLD = 'threshold';

    public const CATEGORY_GEOGRAPHY = 'geography';

    public const CATEGORY_BEHAVIOR = 'behavior';

    public const RISK_LEVEL_LOW = 'low';

    public const RISK_LEVEL_MEDIUM = 'medium';

    public const RISK_LEVEL_HIGH = 'high';

    public const ACTION_ALERT = 'alert';

    public const ACTION_BLOCK = 'block';

    public const ACTION_REVIEW = 'review';

    public const ACTION_REPORT = 'report';

    public const CATEGORIES = [
        self::CATEGORY_VELOCITY  => 'Velocity Rules',
        self::CATEGORY_PATTERN   => 'Pattern Detection',
        self::CATEGORY_THRESHOLD => 'Threshold Monitoring',
        self::CATEGORY_GEOGRAPHY => 'Geographic Rules',
        self::CATEGORY_BEHAVIOR  => 'Behavioral Analysis',
    ];

    // Common rule templates
    public const RULE_TEMPLATES = [
        'rapid_movement' => [
            'name'        => 'Rapid Movement of Funds',
            'category'    => self::CATEGORY_VELOCITY,
            'description' => 'Detects rapid movement of funds through accounts',
            'risk_level'  => self::RISK_LEVEL_HIGH,
        ],
        'structuring' => [
            'name'        => 'Potential Structuring',
            'category'    => self::CATEGORY_PATTERN,
            'description' => 'Detects transactions structured to avoid reporting thresholds',
            'risk_level'  => self::RISK_LEVEL_HIGH,
        ],
        'high_risk_geography' => [
            'name'        => 'High-Risk Geography',
            'category'    => self::CATEGORY_GEOGRAPHY,
            'description' => 'Transactions involving high-risk countries',
            'risk_level'  => self::RISK_LEVEL_MEDIUM,
        ],
        'unusual_pattern' => [
            'name'        => 'Unusual Transaction Pattern',
            'category'    => self::CATEGORY_BEHAVIOR,
            'description' => 'Deviation from established customer behavior',
            'risk_level'  => self::RISK_LEVEL_MEDIUM,
        ],
        'large_cash' => [
            'name'        => 'Large Cash Transaction',
            'category'    => self::CATEGORY_THRESHOLD,
            'description' => 'Cash transactions exceeding threshold',
            'risk_level'  => self::RISK_LEVEL_MEDIUM,
        ],
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isHighRisk(): bool
    {
        return $this->risk_level === self::RISK_LEVEL_HIGH;
    }

    public function shouldAutoEscalate(): bool
    {
        return $this->auto_escalate;
    }

    public function appliesTo(string $customerType, string $riskLevel, ?string $country = null, ?string $currency = null): bool
    {
        // Check customer type
        if ($this->applies_to_customer_types && ! in_array($customerType, $this->applies_to_customer_types)) {
            return false;
        }

        // Check risk level
        if ($this->applies_to_risk_levels && ! in_array($riskLevel, $this->applies_to_risk_levels)) {
            return false;
        }

        // Check country
        if ($country && $this->applies_to_countries && ! in_array($country, $this->applies_to_countries)) {
            return false;
        }

        // Check currency
        if ($currency && $this->applies_to_currencies && ! in_array($currency, $this->applies_to_currencies)) {
            return false;
        }

        return true;
    }

    public function recordTrigger(?bool $isTruePositive = null): void
    {
        $this->increment('triggers_count');
        $this->update(['last_triggered_at' => now()]);

        if ($isTruePositive !== null) {
            if ($isTruePositive) {
                $this->increment('true_positives');
            } else {
                $this->increment('false_positives');
            }
            $this->updateAccuracyRate();
        }
    }

    public function updateAccuracyRate(): void
    {
        $total = $this->true_positives + $this->false_positives;
        if ($total > 0) {
            $this->accuracy_rate = round(($this->true_positives / $total) * 100, 2);
            $this->save();
        }
    }

    public function getEffectiveness(): string
    {
        if ($this->accuracy_rate >= 80) {
            return 'Highly Effective';
        } elseif ($this->accuracy_rate >= 60) {
            return 'Effective';
        } elseif ($this->accuracy_rate >= 40) {
            return 'Moderately Effective';
        } else {
            return 'Needs Tuning';
        }
    }

    public function evaluateTransaction(array $transaction): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Evaluate conditions
        foreach ($this->conditions as $condition) {
            if (! $this->evaluateCondition($condition, $transaction)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateCondition(array $condition, array $transaction): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (! $field || ! $operator) {
            return false;
        }

        $transactionValue = $transaction[$field] ?? null;

        return match ($operator) {
            'equals'           => $transactionValue == $value,
            'not_equals'       => $transactionValue != $value,
            'greater_than'     => $transactionValue > $value,
            'less_than'        => $transactionValue < $value,
            'greater_or_equal' => $transactionValue >= $value,
            'less_or_equal'    => $transactionValue <= $value,
            'contains'         => str_contains($transactionValue, $value),
            'in'               => in_array($transactionValue, (array) $value),
            'not_in'           => ! in_array($transactionValue, (array) $value),
            default            => false,
        };
    }

    public function getActions(): array
    {
        return $this->actions ?? [];
    }

    public function addTuningNote(string $note, User $user): void
    {
        $history = $this->tuning_history ?? [];
        $history[] = [
            'date'            => now()->toIso8601String(),
            'user_id'         => $user->id,
            'user_name'       => $user->name,
            'note'            => $note,
            'accuracy_before' => $this->accuracy_rate,
        ];

        $this->update(
            [
            'tuning_history'   => $history,
            'last_modified_by' => $user->id,
            'last_reviewed_at' => now(),
            ]
        );
    }

    public static function createFromTemplate(string $template, array $overrides = []): self
    {
        if (! isset(self::RULE_TEMPLATES[$template])) {
            throw new InvalidArgumentException("Unknown rule template: {$template}");
        }

        $templateData = self::RULE_TEMPLATES[$template];
        $ruleCode = 'TMR-' . str_pad(self::count() + 1, 3, '0', STR_PAD_LEFT);

        return self::create(
            array_merge(
                $templateData,
                [
                'rule_code'       => $ruleCode,
                'is_active'       => true,
                'triggers_count'  => 0,
                'true_positives'  => 0,
                'false_positives' => 0,
                ],
                $overrides
            )
        );
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
