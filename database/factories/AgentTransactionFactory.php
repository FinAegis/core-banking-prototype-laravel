<?php

namespace Database\Factories;

use App\Models\AgentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgentTransaction>
 */
class AgentTransactionFactory extends Factory
{
    protected $model = AgentTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['initiated', 'validated', 'processing', 'completed', 'failed'];
        $types = ['direct', 'escrow', 'split'];
        $feeTypes = ['domestic', 'international', 'crypto', 'escrow'];

        $amount = $this->faker->randomFloat(2, 10, 10000);
        $feeAmount = $amount * $this->faker->randomFloat(4, 0.001, 0.05); // 0.1% to 5% fee

        return [
            'transaction_id' => 'txn_' . $this->faker->unique()->uuid,
            'from_agent_id'  => function () {
                // Create agent identity if not provided
                return \App\Domain\AgentProtocol\Models\AgentIdentity::factory()->create()->agent_id;
            },
            'to_agent_id' => function () {
                // Create agent identity if not provided
                return \App\Domain\AgentProtocol\Models\AgentIdentity::factory()->create()->agent_id;
            },
            'amount'     => $amount,
            'currency'   => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'fee_amount' => round($feeAmount, 2),
            'fee_type'   => $this->faker->randomElement($feeTypes),
            'status'     => $this->faker->randomElement($statuses),
            'type'       => $this->faker->randomElement($types),
            'escrow_id'  => null,
            'metadata'   => [
                'source'     => $this->faker->randomElement(['api', 'web', 'mobile']),
                'ip_address' => $this->faker->ipv4,
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the transaction failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Indicate that the transaction is in escrow.
     */
    public function inEscrow(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'      => 'escrow',
            'escrow_id' => 'escrow_' . $this->faker->uuid,
        ]);
    }

    /**
     * Indicate that the transaction is a direct transfer.
     */
    public function direct(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'      => 'direct',
            'escrow_id' => null,
        ]);
    }

    /**
     * Indicate that the transaction is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }
}
