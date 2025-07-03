<?php

namespace Database\Factories;

use App\Models\BatchJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BatchJobFactory extends Factory
{
    protected $model = BatchJob::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'user_uuid' => Str::uuid(),
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['transfer', 'payment', 'conversion']),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed', 'cancelled']),
            'total_items' => $this->faker->numberBetween(1, 100),
            'processed_items' => 0,
            'failed_items' => 0,
            'scheduled_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'metadata' => [],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_items' => 0,
            'failed_items' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'processed_items' => $attributes['total_items'] ?? 10,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failed_items' => $attributes['total_items'] ?? 10,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }
}