<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoteFactory extends Factory
{
    protected $model = Vote::class;

    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory()->create()->id,
            'user_uuid' => User::factory()->create()->uuid,
            'selected_options' => $this->generateSelectedOptions(),
            'voting_power' => fake()->numberBetween(1, 100),
            'voted_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'signature' => null, // Will be generated automatically
            'metadata' => [
                'ip_address' => fake()->ipv4,
                'user_agent' => fake()->userAgent,
                'location' => fake()->optional(0.3)->city,
            ],
        ];
    }

    public function forPoll(Poll $poll): static
    {
        return $this->state(function (array $attributes) use ($poll) {
            // Generate realistic selected options based on poll options
            $pollOptions = $poll->options ?? [];
            $selectedOptions = [];

            if (!empty($pollOptions)) {
                // For single choice polls, select one option
                if ($poll->type->value === 'single_choice' || $poll->type->value === 'yes_no') {
                    $selectedOptions = [fake()->randomElement($pollOptions)['id']];
                }
                // For multiple choice, select 1-3 options
                elseif ($poll->type->value === 'multiple_choice') {
                    $count = fake()->numberBetween(1, min(3, count($pollOptions)));
                    $selectedOptions = fake()->randomElements(
                        array_column($pollOptions, 'id'),
                        $count
                    );
                }
                // For ranked choice, select and rank options
                elseif ($poll->type->value === 'ranked_choice') {
                    $count = fake()->numberBetween(2, count($pollOptions));
                    $selectedOptions = fake()->randomElements(
                        array_column($pollOptions, 'id'),
                        $count
                    );
                }
            }

            return [
                'poll_id' => $poll->id,
                'selected_options' => $selectedOptions,
            ];
        });
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_uuid' => $user->uuid,
        ]);
    }

    public function withHighVotingPower(): static
    {
        return $this->state(fn (array $attributes) => [
            'voting_power' => fake()->numberBetween(50, 1000),
        ]);
    }

    public function withLowVotingPower(): static
    {
        return $this->state(fn (array $attributes) => [
            'voting_power' => fake()->numberBetween(1, 10),
        ]);
    }

    public function recentVote(): static
    {
        return $this->state(fn (array $attributes) => [
            'voted_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    public function oldVote(): static
    {
        return $this->state(fn (array $attributes) => [
            'voted_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
        ]);
    }

    public function singleChoice(array $availableOptions = null): static
    {
        return $this->state(function (array $attributes) use ($availableOptions) {
            $options = $availableOptions ?? ['option1', 'option2', 'option3'];
            
            return [
                'selected_options' => [fake()->randomElement($options)],
            ];
        });
    }

    public function multipleChoice(array $availableOptions = null): static
    {
        return $this->state(function (array $attributes) use ($availableOptions) {
            $options = $availableOptions ?? ['option1', 'option2', 'option3', 'option4'];
            $count = fake()->numberBetween(1, min(3, count($options)));
            
            return [
                'selected_options' => fake()->randomElements($options, $count),
            ];
        });
    }

    public function yesVote(): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_options' => ['yes'],
        ]);
    }

    public function noVote(): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_options' => ['no'],
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], $metadata),
        ]);
    }

    private function generateSelectedOptions(): array
    {
        // Generate random options for testing
        $optionCount = fake()->numberBetween(1, 3);
        $options = [];

        for ($i = 0; $i < $optionCount; $i++) {
            $options[] = 'option_' . ($i + 1);
        }

        return $options;
    }
}