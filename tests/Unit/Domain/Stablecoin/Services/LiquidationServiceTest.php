<?php

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Stablecoin\Contracts\LiquidationServiceInterface;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Wallet\Services\WalletService;
use App\Traits\HandlesNestedTransactions;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\ServiceTestCase;

class LiquidationServiceTest extends ServiceTestCase
{
    private LiquidationService $service;

    private ExchangeRateService $exchangeRateService;

    private CollateralService $collateralService;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exchangeRateService = $this->createMock(ExchangeRateService::class);
        $this->collateralService = $this->createMock(CollateralService::class);
        $this->walletService = $this->createMock(WalletService::class);

        $this->service = new LiquidationService(
            $this->exchangeRateService,
            $this->collateralService,
            $this->walletService
        );
    }

    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(LiquidationService::class));
    }

    #[Test]
    public function test_implements_interface(): void
    {
        $this->assertInstanceOf(LiquidationServiceInterface::class, $this->service);
    }

    #[Test]
    public function test_uses_handles_nested_transactions_trait(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains(HandlesNestedTransactions::class, $traits);
    }

    #[Test]
    public function test_constructor_injects_dependencies(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(3, $constructor->getNumberOfParameters());

        $parameters = $constructor->getParameters();

        $this->assertEquals('exchangeRateService', $parameters[0]->getName());
        $this->assertEquals(ExchangeRateService::class, $parameters[0]->getType()->getName());

        $this->assertEquals('collateralService', $parameters[1]->getName());
        $this->assertEquals(CollateralService::class, $parameters[1]->getType()->getName());

        $this->assertEquals('walletService', $parameters[2]->getName());
        $this->assertEquals(WalletService::class, $parameters[2]->getType()->getName());
    }

    #[Test]
    public function test_has_liquidate_position_method(): void
    {
        $this->assertTrue(method_exists($this->service, 'liquidatePosition'));
    }

    #[Test]
    public function test_liquidate_position_method_signature(): void
    {
        $reflection = new ReflectionMethod(LiquidationService::class, 'liquidatePosition');

        $this->assertEquals(2, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameters = $reflection->getParameters();

        $this->assertEquals('position', $parameters[0]->getName());
        $this->assertEquals(StablecoinCollateralPosition::class, $parameters[0]->getType()->getName());
        $this->assertFalse($parameters[0]->allowsNull());

        $this->assertEquals('liquidator', $parameters[1]->getName());
        $this->assertEquals(Account::class, $parameters[1]->getType()->getName());
        $this->assertTrue($parameters[1]->allowsNull());
        $this->assertTrue($parameters[1]->isDefaultValueAvailable());
        $this->assertNull($parameters[1]->getDefaultValue());

        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_service_imports_required_models(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        $this->assertStringContainsString('use App\Domain\Account\Models\Account;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\Stablecoin;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Collection;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Log;', $fileContent);
    }

    #[Test]
    public function test_liquidate_position_validates_eligibility(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);
        $method = $reflection->getMethod('liquidatePosition');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should check if position should be liquidated
        $this->assertStringContainsString('if (! $position->shouldAutoLiquidate())', $source);
        $this->assertStringContainsString('throw new RuntimeException', $source);
        $this->assertStringContainsString('Position is not eligible for liquidation', $source);
    }

    #[Test]
    public function test_liquidate_position_uses_callback_pattern(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);
        $method = $reflection->getMethod('liquidatePosition');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should use callback for transaction handling
        $this->assertStringContainsString('$callback = function () use ($position, $liquidator)', $source);
    }

    #[Test]
    public function test_liquidate_position_calculates_amounts(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);
        $method = $reflection->getMethod('liquidatePosition');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should calculate various amounts
        $this->assertStringContainsString('$debtAmount = $position->debt_amount;', $source);
        $this->assertStringContainsString('$collateralAmount = $position->collateral_amount;', $source);
        $this->assertStringContainsString('$penaltyAmount =', $source);
        $this->assertStringContainsString('$liquidatorReward =', $source);
        $this->assertStringContainsString('$protocolFee =', $source);
        $this->assertStringContainsString('$returnedCollateral =', $source);
    }

    #[Test]
    public function test_liquidate_position_handles_liquidation_penalty(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);
        $method = $reflection->getMethod('liquidatePosition');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should use liquidation penalty from stablecoin
        $this->assertStringContainsString('$liquidationPenalty = $stablecoin->liquidation_penalty;', $source);

        // Should split penalty between liquidator and protocol
        $this->assertStringContainsString('$liquidatorReward = (int) ($penaltyAmount * 0.5)', $source);
        $this->assertStringContainsString('50% of penalty goes to liquidator', $source);
    }

    #[Test]
    public function test_service_uses_dependency_injection(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);

        // Check properties are private readonly
        $this->assertTrue($reflection->hasProperty('exchangeRateService'));
        $this->assertTrue($reflection->hasProperty('collateralService'));
        $this->assertTrue($reflection->hasProperty('walletService'));

        $exchangeRateProperty = $reflection->getProperty('exchangeRateService');
        $this->assertTrue($exchangeRateProperty->isPrivate());
        $this->assertTrue($exchangeRateProperty->isReadOnly());

        $collateralProperty = $reflection->getProperty('collateralService');
        $this->assertTrue($collateralProperty->isPrivate());
        $this->assertTrue($collateralProperty->isReadOnly());

        $walletProperty = $reflection->getProperty('walletService');
        $this->assertTrue($walletProperty->isPrivate());
        $this->assertTrue($walletProperty->isReadOnly());
    }

    #[Test]
    public function test_service_uses_account_data_objects(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Should import AccountUuid
        $this->assertStringContainsString('use App\Domain\Account\DataObjects\AccountUuid;', $fileContent);
    }

    #[Test]
    public function test_has_additional_methods(): void
    {
        // Check if there are other methods beyond liquidatePosition
        $reflection = new ReflectionClass(LiquidationService::class);
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $methodNames = array_map(fn ($method) => $method->getName(), $publicMethods);

        // Should have at least liquidatePosition and constructor
        $this->assertContains('liquidatePosition', $methodNames);
        $this->assertContains('__construct', $methodNames);
    }

    #[Test]
    public function test_uses_strict_types(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Check for strict types declaration
        $this->assertStringContainsString('declare(strict_types=1);', $fileContent);
    }

    #[Test]
    public function test_namespace_is_correct(): void
    {
        $reflection = new ReflectionClass(LiquidationService::class);
        $this->assertEquals('App\Domain\Stablecoin\Services', $reflection->getNamespaceName());
    }
}
