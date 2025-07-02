<?php

namespace Database\Factories;

use App\Models\BankAccountModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankAccountModel>
 */
class BankAccountModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BankAccountModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bankCodes = ['HSBC', 'BARCLAYS', 'NATWEST', 'LLOYDS', 'SANTANDER', 'REVOLUT', 'WISE'];
        $accountTypes = ['checking', 'savings', 'business', 'investment'];
        $currencies = ['GBP', 'EUR', 'USD'];
        $statuses = ['pending', 'active', 'suspended', 'closed'];
        
        $accountNumber = fake()->numerify('########');
        $bankCode = fake()->randomElement($bankCodes);
        
        return [
            'id' => Str::uuid()->toString(),
            'user_uuid' => User::factory(),
            'bank_code' => $bankCode,
            'external_id' => Str::uuid()->toString(),
            'account_number' => encrypt($accountNumber),
            'iban' => encrypt($this->generateIBAN($bankCode)),
            'swift' => $this->generateSWIFT($bankCode),
            'currency' => fake()->randomElement($currencies),
            'account_type' => fake()->randomElement($accountTypes),
            'status' => fake()->randomElement($statuses),
            'metadata' => [
                'nickname' => fake()->optional(0.5)->words(2, true),
                'is_primary' => fake()->boolean(20),
                'supported_currencies' => fake()->boolean(30) 
                    ? fake()->randomElements($currencies, fake()->numberBetween(1, 3))
                    : null,
                'opening_date' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
                'last_statement_date' => fake()->optional(0.7)->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
                'overdraft_limit' => fake()->optional(0.3)->randomFloat(2, 100, 5000),
                'holder_name' => fake()->name(),
                'holder_address' => fake()->address(),
            ],
        ];
    }

    /**
     * Generate a realistic IBAN
     *
     * @param string $bankCode
     * @return string
     */
    private function generateIBAN(string $bankCode): string
    {
        // UK IBAN format: GB + 2 check digits + 4 bank code + 6 sort code + 8 account number
        $countryCode = 'GB';
        $checkDigits = fake()->numerify('##');
        $bankCodeIBAN = strtoupper(substr($bankCode, 0, 4));
        $sortCode = fake()->numerify('######');
        $accountNumber = fake()->numerify('########');
        
        return $countryCode . $checkDigits . $bankCodeIBAN . $sortCode . $accountNumber;
    }

    /**
     * Generate a realistic SWIFT/BIC code
     *
     * @param string $bankCode
     * @return string
     */
    private function generateSWIFT(string $bankCode): string
    {
        // SWIFT format: 4 bank code + 2 country code + 2 location + 3 optional branch
        $bankCodeSWIFT = strtoupper(substr($bankCode . 'XX', 0, 4));
        $countryCode = 'GB';
        $locationCode = fake()->randomElement(['2L', '6L', '8M', 'FF']);
        $branchCode = fake()->optional(0.5)->randomElement(['XXX', '001', '002', 'HED']);
        
        return $bankCodeSWIFT . $countryCode . $locationCode . ($branchCode ?? '');
    }

    /**
     * Indicate that the bank account is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'verified_at' => fake()->dateTimeBetween('-1 year', '-1 week')->format('Y-m-d H:i:s'),
                'verification_method' => fake()->randomElement(['micro_deposit', 'instant', 'manual']),
            ]),
        ]);
    }

    /**
     * Indicate that the bank account is pending verification
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the bank account is suspended
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'suspended_at' => fake()->dateTimeBetween('-3 months', '-1 day')->format('Y-m-d H:i:s'),
                'suspension_reason' => fake()->randomElement(['fraud_suspected', 'kyc_failed', 'user_request', 'compliance']),
            ]),
        ]);
    }

    /**
     * Indicate that the bank account is closed
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'closed_at' => fake()->dateTimeBetween('-6 months', '-1 day')->format('Y-m-d H:i:s'),
                'closure_reason' => fake()->randomElement(['user_request', 'bank_decision', 'inactive', 'compliance']),
            ]),
        ]);
    }

    /**
     * Set specific bank
     */
    public function forBank(string $bankCode): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_code' => $bankCode,
            'iban' => encrypt($this->generateIBAN($bankCode)),
            'swift' => $this->generateSWIFT($bankCode),
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
     * Set specific currency
     */
    public function withCurrency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }

    /**
     * Set as primary account
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'is_primary' => true,
            ]),
        ]);
    }

    /**
     * Set specific account type
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => $type,
        ]);
    }

    /**
     * Business account
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'business',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'company_name' => fake()->company(),
                'registration_number' => fake()->numerify('########'),
            ]),
        ]);
    }
}