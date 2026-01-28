<?php

namespace App\Domain\Fraud\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
class FraudScore extends Model
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
        'entity_id',
        'entity_type',
        'score_type',
        'total_score',
        'risk_level',
        'score_breakdown',
        'triggered_rules',
        'entity_snapshot',
        'behavioral_factors',
        'device_factors',
        'network_factors',
        'ml_score',
        'ml_model_version',
        'ml_features',
        'ml_explanation',
        'decision',
        'decision_factors',
        'decision_at',
        'is_override',
        'override_by',
        'override_reason',
        'outcome',
        'outcome_confirmed_at',
        'confirmed_by',
        'outcome_notes',
        'analysis_results',
        'metadata',
    ];

    protected $casts = [
        'score_breakdown'      => 'array',
        'triggered_rules'      => 'array',
        'entity_snapshot'      => 'array',
        'behavioral_factors'   => 'array',
        'device_factors'       => 'array',
        'network_factors'      => 'array',
        'ml_features'          => 'array',
        'ml_explanation'       => 'array',
        'decision_factors'     => 'array',
        'analysis_results'     => 'array',
        'metadata'             => 'array',
        'total_score'          => 'decimal:2',
        'ml_score'             => 'decimal:2',
        'is_override'          => 'boolean',
        'decision_at'          => 'datetime',
        'outcome_confirmed_at' => 'datetime',
    ];

    public const SCORE_TYPE_REAL_TIME = 'real_time';

    public const SCORE_TYPE_BATCH = 'batch';

    public const SCORE_TYPE_ML_PREDICTION = 'ml_prediction';

    public const RISK_LEVEL_VERY_LOW = 'very_low';

    public const RISK_LEVEL_LOW = 'low';

    public const RISK_LEVEL_MEDIUM = 'medium';

    public const RISK_LEVEL_HIGH = 'high';

    public const RISK_LEVEL_VERY_HIGH = 'very_high';

    public const DECISION_ALLOW = 'allow';

    public const DECISION_BLOCK = 'block';

    public const DECISION_CHALLENGE = 'challenge';

    public const DECISION_REVIEW = 'review';

    public const OUTCOME_FRAUD = 'fraud';

    public const OUTCOME_LEGITIMATE = 'legitimate';

    public const OUTCOME_UNKNOWN = 'unknown';

    public const RISK_LEVELS = [
        self::RISK_LEVEL_VERY_LOW  => 'Very Low Risk',
        self::RISK_LEVEL_LOW       => 'Low Risk',
        self::RISK_LEVEL_MEDIUM    => 'Medium Risk',
        self::RISK_LEVEL_HIGH      => 'High Risk',
        self::RISK_LEVEL_VERY_HIGH => 'Very High Risk',
    ];

    public const RISK_THRESHOLDS = [
        self::RISK_LEVEL_VERY_LOW  => 20,
        self::RISK_LEVEL_LOW       => 40,
        self::RISK_LEVEL_MEDIUM    => 60,
        self::RISK_LEVEL_HIGH      => 80,
        self::RISK_LEVEL_VERY_HIGH => 100,
    ];

    // Relationships
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function overrideUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // Helper methods
    public static function calculateRiskLevel(float $score): string
    {
        if ($score < 20) {
            return self::RISK_LEVEL_VERY_LOW;
        }
        if ($score < 40) {
            return self::RISK_LEVEL_LOW;
        }
        if ($score < 60) {
            return self::RISK_LEVEL_MEDIUM;
        }
        if ($score < 80) {
            return self::RISK_LEVEL_HIGH;
        }

        return self::RISK_LEVEL_VERY_HIGH;
    }

    public function isHighRisk(): bool
    {
        return in_array($this->risk_level, [self::RISK_LEVEL_HIGH, self::RISK_LEVEL_VERY_HIGH]);
    }

    public function isBlocked(): bool
    {
        return $this->decision === self::DECISION_BLOCK;
    }

    public function requiresReview(): bool
    {
        return $this->decision === self::DECISION_REVIEW;
    }

    public function requiresChallenge(): bool
    {
        return $this->decision === self::DECISION_CHALLENGE;
    }

    public function wasOverridden(): bool
    {
        return $this->is_override;
    }

    public function hasOutcome(): bool
    {
        return $this->outcome !== null;
    }

    public function isFraud(): bool
    {
        return $this->outcome === self::OUTCOME_FRAUD;
    }

    public function isLegitimate(): bool
    {
        return $this->outcome === self::OUTCOME_LEGITIMATE;
    }

    public function getTopRules(int $limit = 5): array
    {
        if (empty($this->score_breakdown)) {
            return [];
        }

        $rules = collect($this->score_breakdown)
            ->sortByDesc('score')
            ->take($limit)
            ->toArray();

        return array_values($rules);
    }

    public function getMostSignificantFactors(): array
    {
        $factors = [];

        // Add top scoring rules
        $topRules = $this->getTopRules(3);
        foreach ($topRules as $rule) {
            $factors[] = [
                'type'   => 'rule',
                'name'   => $rule['rule_name'] ?? 'Unknown Rule',
                'impact' => $rule['score'] ?? 0,
            ];
        }

        // Add ML factors if present
        if ($this->ml_score && $this->ml_explanation) {
            $topFeatures = collect($this->ml_explanation)
                ->sortByDesc('importance')
                ->take(2)
                ->each(
                    function ($feature) use (&$factors) {
                        $factors[] = [
                            'type'   => 'ml_feature',
                            'name'   => $feature['feature'] ?? 'Unknown Feature',
                            'impact' => $feature['importance'] ?? 0,
                        ];
                    }
                );
        }

        return $factors;
    }

    public function confirmOutcome(string $outcome, User $user, ?string $notes = null): void
    {
        $this->update(
            [
                'outcome'              => $outcome,
                'outcome_confirmed_at' => now(),
                'confirmed_by'         => $user->id,
                'outcome_notes'        => $notes,
            ]
        );

        // Update rule effectiveness based on outcome
        if ($this->triggered_rules) {
            $isPositive = $outcome === self::OUTCOME_FRAUD;

            foreach ($this->triggered_rules as $ruleCode) {
                /** @var Model|null $rule */
                $rule = FraudRule::where('code', $ruleCode)->first();
                $rule?->recordTrigger($isPositive);
            }
        }
    }

    public function override(string $decision, User $user, string $reason): void
    {
        $this->update(
            [
                'decision'        => $decision,
                'is_override'     => true,
                'override_by'     => $user->id,
                'override_reason' => $reason,
                'decision_at'     => now(),
            ]
        );
    }

    public function getDecisionRecommendation(): string
    {
        if ($this->total_score >= 80) {
            return self::DECISION_BLOCK;
        } elseif ($this->total_score >= 60) {
            return self::DECISION_REVIEW;
        } elseif ($this->total_score >= 40) {
            return self::DECISION_CHALLENGE;
        } else {
            return self::DECISION_ALLOW;
        }
    }

    public function toRiskReport(): array
    {
        return [
            'score'           => $this->total_score,
            'risk_level'      => $this->risk_level,
            'decision'        => $this->decision,
            'top_factors'     => $this->getMostSignificantFactors(),
            'rules_triggered' => count($this->triggered_rules ?? []),
            'requires_action' => in_array(
                $this->decision,
                [self::DECISION_BLOCK, self::DECISION_REVIEW, self::DECISION_CHALLENGE]
            ),
        ];
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

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\FraudScoreFactory::new();
    }
}
