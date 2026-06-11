<?php

namespace Database\Factories\Domain\Compliance\Kyc\Models;

use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Kyc\Models\BridgeCustomer>
 */
class BridgeCustomerFactory extends Factory
{
    protected $model = BridgeCustomer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'            => User::factory(),
            'bridge_customer_id' => 'cust_' . $this->faker->unique()->lexify('????????????'),
            'kyc_status'         => BridgeCustomer::KYC_NOT_STARTED,
            'developer_fee_bps'  => BridgeCustomer::DEV_FEE_BPS_FREE,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kyc_status' => BridgeCustomer::KYC_APPROVED,
        ]);
    }

    public function withVirtualAccount(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kyc_status'              => BridgeCustomer::KYC_APPROVED,
            'virtual_account_id'      => 'va_' . $this->faker->unique()->lexify('????????'),
            'virtual_account_details' => ['iban' => 'GB29NWBK60161331926819'],
            'supported_rails'         => ['ach', 'sepa'],
        ]);
    }
}
