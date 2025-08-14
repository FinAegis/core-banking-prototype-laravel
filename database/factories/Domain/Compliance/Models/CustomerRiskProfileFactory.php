<?php

namespace Database\Factories\Domain\Compliance\Models;

use App\Domain\Compliance\Models\CustomerRiskProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Models\CustomerRiskProfile>
 */
class CustomerRiskProfileFactory extends Factory
{
    protected $model = CustomerRiskProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $riskRating = $this->faker->randomElement(['low', 'medium', 'high']);
        $riskScore = match ($riskRating) {
            'high'   => $this->faker->numberBetween(70, 100),
            'medium' => $this->faker->numberBetween(40, 69),
            'low'    => $this->faker->numberBetween(0, 39),
        };

        return [
            'user_id'            => User::factory(),
            'profile_number'     => 'CRP-' . date('Y') . '-' . str_pad($this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'risk_rating'        => $riskRating,
            'risk_score'         => $riskScore,
            'last_assessment_at' => now(),
            'next_review_at'     => now()->addMonths(6),
            'geographic_risk'    => [
                'countries' => [$this->faker->countryCode()],
                'score'     => $this->faker->numberBetween(0, 100),
            ],
            'product_risk' => [
                'products' => ['standard_transfer', 'domestic_payment'],
                'score'    => $this->faker->numberBetween(0, 100),
            ],
            'channel_risk' => [
                'onboarding_channel' => 'online_verified',
                'score'              => $this->faker->numberBetween(0, 100),
            ],
            'customer_risk' => [
                'type'  => 'individual',
                'score' => $this->faker->numberBetween(0, 100),
            ],
            'behavioral_risk' => [
                'patterns' => [],
                'score'    => $this->faker->numberBetween(0, 100),
            ],
            'cdd_level'                   => 'standard',
            'cdd_measures'                => [],
            'cdd_completed_at'            => now(),
            'cdd_expires_at'              => now()->addYear(),
            'is_pep'                      => false,
            'pep_type'                    => null,
            'pep_position'                => null,
            'pep_details'                 => null,
            'pep_verified_at'             => null,
            'is_sanctioned'               => false,
            'sanctions_details'           => null,
            'sanctions_verified_at'       => now(),
            'has_adverse_media'           => false,
            'adverse_media_details'       => null,
            'adverse_media_checked_at'    => now(),
            'daily_transaction_limit'     => 10000,
            'monthly_transaction_limit'   => 100000,
            'single_transaction_limit'    => 5000,
            'restricted_countries'        => [],
            'restricted_currencies'       => [],
            'enhanced_monitoring'         => false,
            'monitoring_rules'            => [],
            'monitoring_frequency'        => 30,
            'risk_history'                => [],
            'screening_history'           => [],
            'suspicious_activities_count' => 0,
            'last_suspicious_activity_at' => null,
            'source_of_wealth'            => $this->faker->randomElement(['employment', 'business', 'investment']),
            'source_of_funds'             => $this->faker->randomElement(['salary', 'business_income', 'savings']),
            'sow_verified'                => true,
            'sof_verified'                => true,
            'business_type'               => null,
            'industry_code'               => null,
            'beneficial_owners'           => [],
            'complex_structure'           => false,
            'approved_by'                 => null,
            'approved_at'                 => now(),
            'approval_notes'              => null,
            'override_reasons'            => null,
        ];
    }

    /**
     * Indicate that the profile is low risk.
     */
    public function lowRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_rating'               => 'low',
            'risk_score'                => $this->faker->numberBetween(0, 39),
            'cdd_level'                 => 'simplified',
            'daily_transaction_limit'   => 50000,
            'monthly_transaction_limit' => 500000,
            'single_transaction_limit'  => 25000,
        ]);
    }

    /**
     * Indicate that the profile is medium risk.
     */
    public function mediumRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_rating'               => 'medium',
            'risk_score'                => $this->faker->numberBetween(40, 69),
            'cdd_level'                 => 'standard',
            'daily_transaction_limit'   => 10000,
            'monthly_transaction_limit' => 100000,
            'single_transaction_limit'  => 5000,
        ]);
    }

    /**
     * Indicate that the profile is high risk.
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_rating'               => 'high',
            'risk_score'                => $this->faker->numberBetween(70, 100),
            'cdd_level'                 => 'enhanced',
            'enhanced_monitoring'       => true,
            'daily_transaction_limit'   => 5000,
            'monthly_transaction_limit' => 50000,
            'single_transaction_limit'  => 2500,
        ]);
    }

    /**
     * Indicate that the profile is PEP.
     */
    public function pep(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pep'       => true,
            'pep_type'     => $this->faker->randomElement(['domestic', 'foreign', 'international_org']),
            'pep_position' => $this->faker->randomElement(['minister', 'senator', 'ambassador']),
            'pep_details'  => [
                'position' => 'Government Official',
                'country'  => $this->faker->countryCode(),
            ],
            'pep_verified_at' => now(),
            'risk_rating'     => 'high',
            'risk_score'      => $this->faker->numberBetween(80, 100),
            'cdd_level'       => 'enhanced',
        ]);
    }

    /**
     * Indicate that the profile is sanctioned.
     */
    public function sanctioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sanctioned'     => true,
            'sanctions_details' => [
                'list'             => 'OFAC SDN',
                'match_percentage' => 95,
                'date_added'       => now()->subMonths(6)->toIso8601String(),
            ],
            'sanctions_verified_at'     => now(),
            'risk_rating'               => 'prohibited',
            'risk_score'                => 100,
            'daily_transaction_limit'   => 0,
            'monthly_transaction_limit' => 0,
            'single_transaction_limit'  => 0,
        ]);
    }
}
