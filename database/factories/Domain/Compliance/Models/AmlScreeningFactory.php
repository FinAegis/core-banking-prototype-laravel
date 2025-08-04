<?php

namespace Database\Factories\Domain\Compliance\Models;

use App\Domain\Compliance\Models\AmlScreening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Models\AmlScreening>
 */
class AmlScreeningFactory extends Factory
{
    protected $model = AmlScreening::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        $status = $this->faker->randomElement(['pending', 'in_progress', 'completed', 'failed']);

        return [
            'entity_id'          => $user->uuid,
            'entity_type'        => 'user',
            'screening_number'   => 'AML-' . date('Y') . '-' . str_pad($this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'type'               => $this->faker->randomElement(['sanctions', 'pep', 'adverse_media', 'comprehensive']),
            'status'             => $status,
            'provider'           => $this->faker->randomElement(['dow_jones', 'refinitiv', 'manual']),
            'provider_reference' => $this->faker->uuid(),
            'search_parameters'  => [
                'name'          => $user->name,
                'date_of_birth' => $this->faker->date(),
                'countries'     => [$this->faker->countryCode()],
            ],
            'screening_config'  => [],
            'fuzzy_matching'    => true,
            'match_threshold'   => 85,
            'total_matches'     => $this->faker->numberBetween(0, 5),
            'confirmed_matches' => 0,
            'false_positives'   => 0,
            'overall_risk'      => $this->faker->randomElement(['low', 'medium', 'high']),
        ];
    }

    /**
     * Indicate that the screening is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the screening found no matches (low risk).
     */
    public function lowRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_risk'      => 'low',
            'total_matches'     => 0,
            'confirmed_matches' => 0,
            'false_positives'   => 0,
        ]);
    }

    /**
     * Indicate that the screening found matches (high risk).
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_risk'      => 'high',
            'total_matches'     => $this->faker->numberBetween(1, 3),
            'confirmed_matches' => $this->faker->numberBetween(1, 2),
        ]);
    }

    /**
     * Indicate that the screening is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'pending',
            'completed_at' => null,
        ]);
    }
}
