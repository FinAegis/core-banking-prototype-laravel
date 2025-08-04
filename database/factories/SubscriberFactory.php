<?php

namespace Database\Factories;

use App\Domain\Newsletter\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Newsletter\Models\Subscriber>
 */
class SubscriberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscriber::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sources = [
            Subscriber::SOURCE_BLOG,
            Subscriber::SOURCE_CGO,
            Subscriber::SOURCE_INVESTMENT,
            Subscriber::SOURCE_FOOTER,
            Subscriber::SOURCE_CONTACT,
            Subscriber::SOURCE_PARTNER,
        ];

        $tags = [
            'newsletter',
            'product_updates',
            'marketing',
            'investor',
            'partner',
            'early_adopter',
            'blog_updates',
            'cgo_early_access',
        ];

        return [
            'email'       => fake()->unique()->safeEmail(),
            'source'      => fake()->randomElement($sources),
            'status'      => Subscriber::STATUS_ACTIVE,
            'preferences' => fake()->boolean(30) ? [
                'frequency' => fake()->randomElement(['daily', 'weekly', 'monthly']),
                'topics'    => fake()->randomElements(['news', 'updates', 'offers'], 2),
            ] : null,
            'tags'               => fake()->randomElements($tags, fake()->numberBetween(0, 3)),
            'ip_address'         => fake()->ipv4(),
            'user_agent'         => fake()->userAgent(),
            'confirmed_at'       => fake()->boolean(90) ? fake()->dateTimeBetween('-6 months', 'now') : null,
            'unsubscribed_at'    => null,
            'unsubscribe_reason' => null,
        ];
    }

    /**
     * Indicate that the subscriber is unsubscribed.
     */
    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'             => Subscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at'    => fake()->dateTimeBetween('-3 months', 'now'),
            'unsubscribe_reason' => fake()->randomElement([
                'Too many emails',
                'No longer interested',
                'User requested unsubscribe',
                'Other',
            ]),
        ]);
    }

    /**
     * Indicate that the subscriber email has bounced.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'             => Subscriber::STATUS_BOUNCED,
            'unsubscribed_at'    => fake()->dateTimeBetween('-1 month', 'now'),
            'unsubscribe_reason' => 'Email bounced',
        ]);
    }
}
