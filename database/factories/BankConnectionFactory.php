<?php

namespace Database\Factories;

use App\Models\BankConnectionModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankConnectionModel>
 */
class BankConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BankConnectionModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bankCodes = ['HSBC', 'BARCLAYS', 'NATWEST', 'LLOYDS', 'SANTANDER', 'REVOLUT', 'WISE'];
        $statuses = ['active', 'inactive', 'pending', 'expired'];
        
        return [
            'id' => Str::uuid()->toString(),
            'user_uuid' => User::factory(),
            'bank_code' => fake()->randomElement($bankCodes),
            'status' => fake()->randomElement($statuses),
            'credentials' => $this->generateCredentials(),
            'permissions' => $this->generatePermissions(),
            'last_sync_at' => fake()->optional(0.7)->dateTimeBetween('-1 week', 'now'),
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('now', '+1 year'),
            'metadata' => [
                'sync_interval' => fake()->randomElement([3600, 7200, 14400, 28800]),
                'connection_type' => fake()->randomElement(['oauth', 'api_key', 'username_password']),
                'consent_version' => '1.0.0',
                'last_error' => null,
            ],
        ];
    }

    /**
     * Generate fake encrypted credentials
     *
     * @return array
     */
    private function generateCredentials(): array
    {
        return [
            'username' => encrypt(fake()->userName()),
            'password' => encrypt(fake()->password()),
            'api_key' => encrypt(Str::random(32)),
            'customer_id' => fake()->numerify('CUST-#########'),
        ];
    }

    /**
     * Generate permissions array
     *
     * @return array
     */
    private function generatePermissions(): array
    {
        $allPermissions = [
            'accounts.read',
            'accounts.details',
            'balances.read',
            'transactions.read',
            'transactions.details',
            'standing-orders.read',
            'direct-debits.read',
            'beneficiaries.read',
            'payments.initiate',
        ];

        // Sometimes give all permissions
        if (fake()->boolean(20)) {
            return ['*'];
        }

        // Otherwise give a random subset
        return fake()->randomElements($allPermissions, fake()->numberBetween(3, 7));
    }

    /**
     * Indicate that the bank connection is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'last_sync_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Indicate that the bank connection is expired
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the bank connection is pending
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'last_sync_at' => null,
            'permissions' => [],
        ]);
    }

    /**
     * Set specific bank
     */
    public function forBank(string $bankCode): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_code' => $bankCode,
        ]);
    }

    /**
     * Set specific user
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_uuid' => $user->uuid,
        ]);
    }

    /**
     * With all permissions
     */
    public function withAllPermissions(): static
    {
        return $this->state(fn (array $attributes) => [
            'permissions' => ['*'],
        ]);
    }

    /**
     * With specific permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn (array $attributes) => [
            'permissions' => $permissions,
        ]);
    }
}