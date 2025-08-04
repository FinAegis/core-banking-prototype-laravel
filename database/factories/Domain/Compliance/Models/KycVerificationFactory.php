<?php

namespace Database\Factories\Domain\Compliance\Models;

use App\Domain\Compliance\Models\KycVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Models\KycVerification>
 */
class KycVerificationFactory extends Factory
{
    protected $model = KycVerification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'in_progress', 'completed', 'failed', 'expired']);

        return [
            'user_id'             => User::factory(),
            'verification_number' => 'KYC-' . date('Y') . '-' . str_pad($this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'type'                => $this->faker->randomElement(['identity', 'address', 'income', 'enhanced_due_diligence']),
            'status'              => $status,
            'provider'            => $this->faker->randomElement(['jumio', 'onfido', 'manual']),
            'provider_reference'  => $this->faker->uuid(),
            'verification_data'   => [],
            'extracted_data'      => [],
            'checks_performed'    => [],
            'confidence_score'    => $this->faker->randomFloat(2, 0, 100),
            'document_type'       => $this->faker->randomElement(['passport', 'driving_license', 'national_id']),
            'document_number'     => $this->faker->bothify('??#########'),
            'document_country'    => $this->faker->countryCode(),
            'document_expiry'     => $this->faker->dateTimeBetween('+1 year', '+10 years'),
            'first_name'          => $this->faker->firstName(),
            'last_name'           => $this->faker->lastName(),
            'date_of_birth'       => $this->faker->dateTimeBetween('-60 years', '-18 years'),
            'gender'              => $this->faker->randomElement(['male', 'female', 'other']),
            'nationality'         => $this->faker->countryCode(),
            'address_line1'       => $this->faker->streetAddress(),
            'city'                => $this->faker->city(),
            'state'               => $this->faker->state(),
            'postal_code'         => $this->faker->postcode(),
            'country'             => $this->faker->countryCode(),
            'risk_level'          => $this->faker->randomElement(['low', 'medium', 'high']),
            'risk_factors'        => [],
            'pep_check'           => false,
            'sanctions_check'     => false,
            'adverse_media_check' => false,
            'started_at'          => $status !== 'pending' ? now()->subMinutes($this->faker->numberBetween(10, 60)) : null,
            'completed_at'        => in_array($status, ['completed', 'failed']) ? now() : null,
            'expires_at'          => $status === 'completed' ? now()->addYear() : null,
            'failure_reason'      => $status === 'failed' ? $this->faker->sentence() : null,
            'verification_report' => [],
        ];
    }

    /**
     * Indicate that the verification is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'completed',
            'completed_at'     => now(),
            'expires_at'       => now()->addYear(),
            'failure_reason'   => null,
            'confidence_score' => $this->faker->randomFloat(2, 80, 100),
        ]);
    }

    /**
     * Indicate that the verification is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'            => 'rejected',
            'verified_at'       => null,
            'expires_at'        => null,
            'rejection_reason'  => $this->faker->randomElement(['document_invalid', 'identity_mismatch', 'fraud_suspected']),
            'rejection_details' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the verification is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'expired',
            'verified_at' => now()->subYear()->subDay(),
            'expires_at'  => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the verification is at basic level.
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'basic',
        ]);
    }

    /**
     * Indicate that the verification is at advanced level.
     */
    public function advanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'advanced',
        ]);
    }
}
