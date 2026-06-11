<?php

namespace Database\Factories\Domain\Subscription\Models;

use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Subscription\Models\ProcessedWebhookEvent>
 */
class ProcessedWebhookEventFactory extends Factory
{
    protected $model = ProcessedWebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider'     => $this->faker->randomElement(['hyperswitch', 'bridge', 'apple', 'google']),
            'event_id'     => 'evt_' . $this->faker->unique()->lexify('????????????????'),
            'event_type'   => 'payment_succeeded',
            'processed_at' => now(),
        ];
    }
}
