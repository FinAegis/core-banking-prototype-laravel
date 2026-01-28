<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests verifying domain models use the UsesTenantConnection trait.
 *
 * These tests use reflection to verify the trait is applied without
 * instantiating models (which would require Laravel bootstrapping).
 */
class ModelTenantConnectionTest extends TestCase
{
    private const TRAIT_NAME = 'App\Domain\Shared\Traits\UsesTenantConnection';

    /**
     * @param class-string $modelClass
     */
    #[Test]
    #[DataProvider('tenantModelProvider')]
    public function model_uses_tenant_connection_trait(string $modelClass): void
    {
        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($modelClass);
        $traits = $this->getAllTraits($reflection);

        $this->assertContains(
            self::TRAIT_NAME,
            $traits,
            "Model {$modelClass} should use UsesTenantConnection trait"
        );
    }

    /**
     * @param class-string $modelClass
     * @param class-string $expectedBase
     */
    #[Test]
    #[DataProvider('eventSourcingModelProvider')]
    public function event_sourcing_model_extends_tenant_aware_base(string $modelClass, string $expectedBase): void
    {
        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($modelClass);

        $this->assertTrue(
            $reflection->isSubclassOf($expectedBase),
            "Model {$modelClass} should extend {$expectedBase}"
        );
    }

    /**
     * Get all traits used by a class, including those from parent classes.
     *
     * @param  ReflectionClass<object> $reflection
     * @return array<string>
     */
    private function getAllTraits(ReflectionClass $reflection): array
    {
        $traits = [];

        do {
            $traits = array_merge($traits, array_keys($reflection->getTraits()));
        } while ($reflection = $reflection->getParentClass());

        return $traits;
    }

    /**
     * Provide sample of regular Eloquent models that should use the trait.
     *
     * @return array<string, array{string}>
     */
    public static function tenantModelProvider(): array
    {
        return [
            // Account Domain
            'Account'               => ['App\Domain\Account\Models\Account'],
            'AccountBalance'        => ['App\Domain\Account\Models\AccountBalance'],
            'TransactionProjection' => ['App\Domain\Account\Models\TransactionProjection'],
            'Turnover'              => ['App\Domain\Account\Models\Turnover'],

            // AgentProtocol Domain
            'Agent'       => ['App\Domain\AgentProtocol\Models\Agent'],
            'AgentWallet' => ['App\Domain\AgentProtocol\Models\AgentWallet'],
            'Escrow'      => ['App\Domain\AgentProtocol\Models\Escrow'],

            // Banking Domain
            'BankAccountModel'    => ['App\Domain\Banking\Models\BankAccountModel'],
            'BankConnectionModel' => ['App\Domain\Banking\Models\BankConnectionModel'],

            // Compliance Domain
            'AmlScreening'    => ['App\Domain\Compliance\Models\AmlScreening'],
            'ComplianceAlert' => ['App\Domain\Compliance\Models\ComplianceAlert'],
            'KycVerification' => ['App\Domain\Compliance\Models\KycVerification'],

            // Lending Domain
            'Loan'            => ['App\Domain\Lending\Models\Loan'],
            'LoanApplication' => ['App\Domain\Lending\Models\LoanApplication'],

            // Treasury Domain
            'AssetAllocation' => ['App\Domain\Treasury\Models\AssetAllocation'],

            // Stablecoin Domain
            'Stablecoin'        => ['App\Domain\Stablecoin\Models\Stablecoin'],
            'StablecoinReserve' => ['App\Domain\Stablecoin\Models\StablecoinReserve'],

            // Wallet Domain
            'SecureKeyStorage' => ['App\Domain\Wallet\Models\SecureKeyStorage'],
        ];
    }

    /**
     * Provide event sourcing models that should extend tenant-aware base classes.
     *
     * @return array<string, array{string, string}>
     */
    public static function eventSourcingModelProvider(): array
    {
        return [
            // Event models
            'Transaction' => [
                'App\Domain\Account\Models\Transaction',
                'App\Domain\Shared\EventSourcing\TenantAwareStoredEvent',
            ],
            'Transfer' => [
                'App\Domain\Account\Models\Transfer',
                'App\Domain\Shared\EventSourcing\TenantAwareStoredEvent',
            ],
            'TreasuryEvent' => [
                'App\Domain\Treasury\Models\TreasuryEvent',
                'App\Domain\Shared\EventSourcing\TenantAwareStoredEvent',
            ],
            'ComplianceEvent' => [
                'App\Domain\Compliance\Models\ComplianceEvent',
                'App\Domain\Shared\EventSourcing\TenantAwareStoredEvent',
            ],
            'LendingEvent' => [
                'App\Domain\Lending\Models\LendingEvent',
                'App\Domain\Shared\EventSourcing\TenantAwareStoredEvent',
            ],

            // Snapshot models
            'TreasurySnapshot' => [
                'App\Domain\Treasury\Models\TreasurySnapshot',
                'App\Domain\Shared\EventSourcing\TenantAwareSnapshot',
            ],
            'ComplianceSnapshot' => [
                'App\Domain\Compliance\Models\ComplianceSnapshot',
                'App\Domain\Shared\EventSourcing\TenantAwareSnapshot',
            ],
        ];
    }
}
