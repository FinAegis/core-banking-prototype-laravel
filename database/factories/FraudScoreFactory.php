<?php

namespace Database\Factories;

use App\Domain\Account\Models\Transaction;
use App\Domain\Fraud\Models\FraudScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Fraud\Models\FraudScore>
 */
class FraudScoreFactory extends Factory
{
    protected $model = FraudScore::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalScore = $this->faker->randomFloat(2, 0, 100);
        $riskLevel = FraudScore::calculateRiskLevel($totalScore);
        $decision = $this->getDecisionBasedOnScore($totalScore);

        return [
            'entity_id'   => $this->faker->uuid(),
            'entity_type' => Transaction::class,
            'score_type'  => $this->faker->randomElement([
                FraudScore::SCORE_TYPE_REAL_TIME,
                FraudScore::SCORE_TYPE_BATCH,
                FraudScore::SCORE_TYPE_ML_PREDICTION,
            ]),
            'total_score'          => $totalScore,
            'risk_level'           => $riskLevel,
            'score_breakdown'      => $this->generateScoreBreakdown(),
            'triggered_rules'      => $this->generateTriggeredRules(),
            'entity_snapshot'      => $this->generateEntitySnapshot(),
            'behavioral_factors'   => $this->generateBehavioralFactors(),
            'device_factors'       => $this->generateDeviceFactors(),
            'network_factors'      => $this->generateNetworkFactors(),
            'ml_score'             => $this->faker->optional(0.5)->randomFloat(2, 0, 100),
            'ml_model_version'     => $this->faker->optional(0.5)->numerify('v#.#.#'),
            'ml_features'          => $this->faker->optional(0.5)->passthrough($this->generateMLFeatures()),
            'ml_explanation'       => $this->faker->optional(0.5)->passthrough($this->generateMLExplanation()),
            'decision'             => $decision,
            'decision_factors'     => $this->generateDecisionFactors($decision),
            'decision_at'          => now(),
            'is_override'          => false,
            'override_by'          => null,
            'override_reason'      => null,
            'outcome'              => null,
            'outcome_confirmed_at' => null,
            'confirmed_by'         => null,
            'outcome_notes'        => null,
        ];
    }

    /**
     * Generate score breakdown.
     */
    private function generateScoreBreakdown(): array
    {
        return [
            [
                'rule_code' => 'VEL-001',
                'rule_name' => 'High Velocity Transactions',
                'score'     => $this->faker->randomFloat(2, 10, 30),
            ],
            [
                'rule_code' => 'AMT-002',
                'rule_name' => 'Unusual Amount Pattern',
                'score'     => $this->faker->randomFloat(2, 5, 25),
            ],
            [
                'rule_code' => 'GEO-003',
                'rule_name' => 'Geographic Anomaly',
                'score'     => $this->faker->randomFloat(2, 5, 20),
            ],
        ];
    }

    /**
     * Generate triggered rules.
     */
    private function generateTriggeredRules(): array
    {
        return $this->faker->randomElements(['VEL-001', 'AMT-002', 'GEO-003', 'PAT-004', 'DEV-005'], 3);
    }

    /**
     * Generate entity snapshot.
     */
    private function generateEntitySnapshot(): array
    {
        return [
            'amount'     => $this->faker->randomFloat(2, 100, 10000),
            'currency'   => 'USD',
            'type'       => $this->faker->randomElement(['transfer', 'purchase', 'withdrawal']),
            'timestamp'  => now()->toIso8601String(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    /**
     * Generate behavioral factors.
     */
    private function generateBehavioralFactors(): array
    {
        return [
            'transaction_velocity'     => $this->faker->numberBetween(1, 50),
            'average_transaction_size' => $this->faker->randomFloat(2, 100, 1000),
            'unusual_time_activity'    => $this->faker->boolean(),
            'account_age_days'         => $this->faker->numberBetween(30, 1000),
        ];
    }

    /**
     * Generate device factors.
     */
    private function generateDeviceFactors(): array
    {
        return [
            'device_id'          => $this->faker->uuid(),
            'device_trust_score' => $this->faker->numberBetween(0, 100),
            'is_rooted'          => $this->faker->boolean(10),
            'is_vpn'             => $this->faker->boolean(20),
            'location_mismatch'  => $this->faker->boolean(15),
        ];
    }

    /**
     * Generate network factors.
     */
    private function generateNetworkFactors(): array
    {
        return [
            'ip_reputation' => $this->faker->randomElement(['good', 'neutral', 'bad']),
            'is_proxy'      => $this->faker->boolean(10),
            'is_tor'        => $this->faker->boolean(5),
            'country_code'  => $this->faker->countryCode(),
            'risk_country'  => $this->faker->boolean(15),
        ];
    }

    /**
     * Generate ML features.
     */
    private function generateMLFeatures(): array
    {
        return [
            'transaction_amount_zscore' => $this->faker->randomFloat(2, -3, 3),
            'velocity_score'            => $this->faker->randomFloat(2, 0, 1),
            'merchant_risk_score'       => $this->faker->randomFloat(2, 0, 1),
            'time_since_last_txn'       => $this->faker->numberBetween(60, 86400),
        ];
    }

    /**
     * Generate ML explanation.
     */
    private function generateMLExplanation(): array
    {
        return [
            [
                'feature'    => 'transaction_amount_zscore',
                'importance' => $this->faker->randomFloat(2, 0.1, 0.8),
                'value'      => $this->faker->randomFloat(2, -3, 3),
            ],
            [
                'feature'    => 'velocity_score',
                'importance' => $this->faker->randomFloat(2, 0.1, 0.5),
                'value'      => $this->faker->randomFloat(2, 0, 1),
            ],
        ];
    }

    /**
     * Generate decision factors.
     */
    private function generateDecisionFactors(string $decision): array
    {
        $factors = ['risk_score' => 'primary'];

        if ($decision === FraudScore::DECISION_BLOCK) {
            $factors['blocked_rules'] = ['VEL-001', 'AMT-002'];
        }

        if ($decision === FraudScore::DECISION_REVIEW) {
            $factors['review_reasons'] = ['unusual_pattern', 'high_amount'];
        }

        return $factors;
    }

    /**
     * Get decision based on score.
     */
    private function getDecisionBasedOnScore(float $score): string
    {
        if ($score >= 80) {
            return FraudScore::DECISION_BLOCK;
        } elseif ($score >= 60) {
            return FraudScore::DECISION_REVIEW;
        } elseif ($score >= 40) {
            return FraudScore::DECISION_CHALLENGE;
        } else {
            return FraudScore::DECISION_ALLOW;
        }
    }

    /**
     * Indicate high risk score.
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_score' => $this->faker->randomFloat(2, 80, 100),
            'risk_level'  => FraudScore::RISK_LEVEL_VERY_HIGH,
            'decision'    => FraudScore::DECISION_BLOCK,
        ]);
    }

    /**
     * Indicate low risk score.
     */
    public function lowRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_score' => $this->faker->randomFloat(2, 0, 20),
            'risk_level'  => FraudScore::RISK_LEVEL_VERY_LOW,
            'decision'    => FraudScore::DECISION_ALLOW,
        ]);
    }

    /**
     * With confirmed outcome.
     */
    public function withOutcome(string $outcome = FraudScore::OUTCOME_LEGITIMATE): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome'              => $outcome,
            'outcome_confirmed_at' => now(),
            'confirmed_by'         => User::factory(),
            'outcome_notes'        => $this->faker->sentence(),
        ]);
    }

    /**
     * With override.
     */
    public function overridden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_override'     => true,
            'override_by'     => User::factory(),
            'override_reason' => $this->faker->sentence(),
        ]);
    }
}
