<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FraudScore extends Model
{
    use HasUuids;

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
    ];

    protected $casts = [
        'score_breakdown' => 'array',
        'triggered_rules' => 'array',
        'entity_snapshot' => 'array',
        'behavioral_factors' => 'array',
        'device_factors' => 'array',
        'network_factors' => 'array',
        'ml_features' => 'array',
        'ml_explanation' => 'array',
        'decision_factors' => 'array',
        'total_score' => 'decimal:2',
        'ml_score' => 'decimal:2',
        'is_override' => 'boolean',
        'decision_at' => 'datetime',
        'outcome_confirmed_at' => 'datetime',
    ];

    const SCORE_TYPE_REAL_TIME = 'real_time';
    const SCORE_TYPE_BATCH = 'batch';
    const SCORE_TYPE_ML_PREDICTION = 'ml_prediction';

    const RISK_LEVEL_VERY_LOW = 'very_low';
    const RISK_LEVEL_LOW = 'low';
    const RISK_LEVEL_MEDIUM = 'medium';
    const RISK_LEVEL_HIGH = 'high';
    const RISK_LEVEL_VERY_HIGH = 'very_high';

    const DECISION_ALLOW = 'allow';
    const DECISION_BLOCK = 'block';
    const DECISION_CHALLENGE = 'challenge';
    const DECISION_REVIEW = 'review';

    const OUTCOME_FRAUD = 'fraud';
    const OUTCOME_LEGITIMATE = 'legitimate';
    const OUTCOME_UNKNOWN = 'unknown';

    const RISK_LEVELS = [
        self::RISK_LEVEL_VERY_LOW => 'Very Low Risk',
        self::RISK_LEVEL_LOW => 'Low Risk',
        self::RISK_LEVEL_MEDIUM => 'Medium Risk',
        self::RISK_LEVEL_HIGH => 'High Risk',
        self::RISK_LEVEL_VERY_HIGH => 'Very High Risk',
    ];

    const RISK_THRESHOLDS = [
        self::RISK_LEVEL_VERY_LOW => 20,
        self::RISK_LEVEL_LOW => 40,
        self::RISK_LEVEL_MEDIUM => 60,
        self::RISK_LEVEL_HIGH => 80,
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
        if ($score < 20) return self::RISK_LEVEL_VERY_LOW;
        if ($score < 40) return self::RISK_LEVEL_LOW;
        if ($score < 60) return self::RISK_LEVEL_MEDIUM;
        if ($score < 80) return self::RISK_LEVEL_HIGH;
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
                'type' => 'rule',
                'name' => $rule['rule_name'] ?? 'Unknown Rule',
                'impact' => $rule['score'] ?? 0,
            ];
        }

        // Add ML factors if present
        if ($this->ml_score && $this->ml_explanation) {
            $topFeatures = collect($this->ml_explanation)
                ->sortByDesc('importance')
                ->take(2)
                ->each(function ($feature) use (&$factors) {
                    $factors[] = [
                        'type' => 'ml_feature',
                        'name' => $feature['feature'] ?? 'Unknown Feature',
                        'impact' => $feature['importance'] ?? 0,
                    ];
                });
        }

        return $factors;
    }

    public function confirmOutcome(string $outcome, User $user, string $notes = null): void
    {
        $this->update([
            'outcome' => $outcome,
            'outcome_confirmed_at' => now(),
            'confirmed_by' => $user->id,
            'outcome_notes' => $notes,
        ]);

        // Update rule effectiveness based on outcome
        if ($this->triggered_rules) {
            $isPositive = $outcome === self::OUTCOME_FRAUD;
            
            foreach ($this->triggered_rules as $ruleCode) {
                $rule = FraudRule::where('code', $ruleCode)->first();
                $rule?->recordTrigger($isPositive);
            }
        }
    }

    public function override(string $decision, User $user, string $reason): void
    {
        $this->update([
            'decision' => $decision,
            'is_override' => true,
            'override_by' => $user->id,
            'override_reason' => $reason,
            'decision_at' => now(),
        ]);
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
            'score' => $this->total_score,
            'risk_level' => $this->risk_level,
            'decision' => $this->decision,
            'top_factors' => $this->getMostSignificantFactors(),
            'rules_triggered' => count($this->triggered_rules ?? []),
            'requires_action' => in_array($this->decision, [self::DECISION_BLOCK, self::DECISION_REVIEW, self::DECISION_CHALLENGE]),
        ];
    }
}