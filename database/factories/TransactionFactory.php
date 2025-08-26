<?php

namespace Database\Factories;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Account\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     * @phpstan-ignore property.phpDocType
     */
    protected $model = Transaction::class;

    /**
     * Track aggregate versions for each account UUID to ensure they're sequential.
     */
    private static array $aggregateVersions = [];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     * @phpstan-ignore method.childReturnType
     */
    public function definition(): array
    {
        $types = ['deposit', 'withdrawal', 'transfer_in', 'transfer_out'];
        $type = fake()->randomElement($types);

        // Generate amount based on type (deposits and transfers in are positive, withdrawals and transfers out are negative)
        $amount = match($type) { // @phpstan-ignore match.unhandled
            'deposit', 'transfer_in' => fake()->numberBetween(100, 100000), // $1 to $1000
            'withdrawal', 'transfer_out' => -fake()->numberBetween(100, 50000), // -$1 to -$500
        };

        $accountUuid = Account::factory()->create()->uuid;

        return [
            'aggregate_uuid'    => $accountUuid,
            'aggregate_version' => $this->getNextVersionForAggregate($accountUuid),
            'event_version'     => 1,
            'event_class'       => 'App\\Domain\\Account\\Events\\MoneyAdded',
            'event_properties'  => [
                'amount'    => $amount,
                'assetCode' => 'USD',
                'metadata'  => [],
            ],
            'meta_data' => [
                'type'        => $type,
                'reference'   => fake()->optional()->bothify('REF-####-????'),
                'description' => fake()->optional()->sentence(),
            ],
            'created_at' => now(),
        ];
    }

    /**
     * Get the next version for an aggregate, ensuring sequential versions.
     */
    private function getNextVersionForAggregate(string $aggregateUuid): int
    {
        if (! isset(self::$aggregateVersions[$aggregateUuid])) {
            // Check if there are existing transactions for this aggregate
            $lastVersion = Transaction::where('aggregate_uuid', $aggregateUuid)
                ->orderBy('aggregate_version', 'desc')
                ->value('aggregate_version') ?? 0;

            self::$aggregateVersions[$aggregateUuid] = $lastVersion;
        }

        return ++self::$aggregateVersions[$aggregateUuid];
    }

    /**
     * Indicate that the transaction is a deposit.
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_data' => array_merge($attributes['meta_data'] ?? [], [
                'type' => 'deposit',
            ]),
            'event_properties' => array_merge($attributes['event_properties'] ?? [], [
                'amount' => fake()->numberBetween(100, 100000),
            ]),
        ]);
    }

    /**
     * Indicate that the transaction is a withdrawal.
     */
    public function withdrawal(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_data' => array_merge($attributes['meta_data'] ?? [], [
                'type' => 'withdrawal',
            ]),
            'event_properties' => array_merge($attributes['event_properties'] ?? [], [
                'amount' => -fake()->numberBetween(100, 50000),
            ]),
        ]);
    }

    /**
     * Indicate that the transaction is a transfer in.
     */
    public function transferIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_data' => array_merge($attributes['meta_data'] ?? [], [
                'type' => 'transfer_in',
            ]),
            'event_properties' => array_merge($attributes['event_properties'] ?? [], [
                'amount' => fake()->numberBetween(100, 100000),
            ]),
        ]);
    }

    /**
     * Indicate that the transaction is a transfer out.
     */
    public function transferOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_data' => array_merge($attributes['meta_data'] ?? [], [
                'type' => 'transfer_out',
            ]),
            'event_properties' => array_merge($attributes['event_properties'] ?? [], [
                'amount' => -fake()->numberBetween(100, 50000),
            ]),
        ]);
    }

    /**
     * Set a specific account for the transaction.
     */
    public function forAccount(Account $account): static
    {
        return $this->state(function (array $attributes) use ($account) {
            return [
                'aggregate_uuid'    => $account->uuid,
                'aggregate_version' => $this->getNextVersionForAggregate($account->uuid),
            ];
        });
    }

    /**
     * Create a new instance of the model and filter out invalid attributes.
     *
     * Override parent create to handle amount attribute properly for event sourcing.
     *
     * @param array $attributes
     * @param Model|null $parent
     * @return Transaction|Collection
     * @phpstan-return Transaction|Collection<int, Transaction>
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        // Transaction extends EloquentStoredEvent so it only has event sourcing columns
        // If amount is passed as a direct attribute, move it to event_properties
        if (is_array($attributes) && isset($attributes['amount'])) {
            $amount = $attributes['amount'];
            unset($attributes['amount']);

            // Ensure event_properties exists in attributes
            if (! isset($attributes['event_properties'])) {
                $attributes['event_properties'] = [];
            }

            // Add amount to event_properties
            $attributes['event_properties']['amount'] = $amount;
        }

        return parent::create($attributes, $parent);
    }

    /**
     * Clear the version cache (useful for tests).
     */
    public static function clearVersionCache(): void
    {
        self::$aggregateVersions = [];
    }
}
