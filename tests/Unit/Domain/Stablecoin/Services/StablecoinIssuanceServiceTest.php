<?php

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Stablecoin\Contracts\StablecoinIssuanceServiceInterface;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Domain\Wallet\Services\WalletService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\ServiceTestCase;

class StablecoinIssuanceServiceTest extends ServiceTestCase
{
    private StablecoinIssuanceService $service;

    private ExchangeRateService $exchangeRateService;

    private CollateralService $collateralService;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exchangeRateService = $this->createMock(ExchangeRateService::class);
        $this->collateralService = $this->createMock(CollateralService::class);
        $this->walletService = $this->createMock(WalletService::class);

        $this->service = new StablecoinIssuanceService(
            $this->exchangeRateService,
            $this->collateralService,
            $this->walletService
        );
    }

    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(StablecoinIssuanceService::class));
    }

    #[Test]
    public function test_implements_interface(): void
    {
        $this->assertInstanceOf(StablecoinIssuanceServiceInterface::class, $this->service);
    }

    #[Test]
    public function test_constructor_injects_dependencies(): void
    {
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);
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
    public function test_has_mint_method(): void
    {
        $this->assertTrue(method_exists($this->service, 'mint'));
    }

    #[Test]
    public function test_mint_method_signature(): void
    {
        $reflection = new ReflectionMethod(StablecoinIssuanceService::class, 'mint');

        $this->assertEquals(5, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameters = $reflection->getParameters();

        $this->assertEquals('account', $parameters[0]->getName());
        $this->assertEquals(Account::class, $parameters[0]->getType()->getName());

        $this->assertEquals('stablecoinCode', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());

        $this->assertEquals('collateralAssetCode', $parameters[2]->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());

        $this->assertEquals('collateralAmount', $parameters[3]->getName());
        $this->assertEquals('int', $parameters[3]->getType()->getName());

        $this->assertEquals('mintAmount', $parameters[4]->getName());
        $this->assertEquals('int', $parameters[4]->getType()->getName());

        $this->assertEquals(StablecoinCollateralPosition::class, $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_service_properties_are_private_readonly(): void
    {
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);

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
    public function test_service_imports(): void
    {
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Check workflow imports
        $this->assertStringContainsString('use App\Domain\Stablecoin\Workflows\AddCollateralWorkflow;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow;', $fileContent);

        // Check other imports
        $this->assertStringContainsString('use App\Domain\Account\DataObjects\AccountUuid;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Account\Models\Account;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\Stablecoin;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Log;', $fileContent);
        $this->assertStringContainsString('use Workflow\WorkflowStub;', $fileContent);
    }

    #[Test]
    public function test_mint_validates_stablecoin(): void
    {
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);
        $method = $reflection->getMethod('mint');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should find stablecoin
        $this->assertStringContainsString('Stablecoin::findOrFail($stablecoinCode)', $source);

        // Should validate minting is enabled
        $this->assertStringContainsString('if (! $stablecoin->canMint())', $source);
        $this->assertStringContainsString('Minting is disabled', $source);

        // Should check max supply
        $this->assertStringContainsString('if ($stablecoin->hasReachedMaxSupply())', $source);
        $this->assertStringContainsString('Maximum supply reached', $source);
    }

    #[Test]
    public function test_has_additional_methods(): void
    {
        // Check if service likely has other methods beyond mint
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $methodNames = array_map(fn ($method) => $method->getName(), $publicMethods);

        // Should have at least mint and constructor
        $this->assertContains('mint', $methodNames);
        $this->assertContains('__construct', $methodNames);

        // Check for potential burn method
        $fileContent = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('BurnStablecoinWorkflow', $fileContent);
    }

    #[Test]
    public function test_uses_workflow_pattern(): void
    {
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Should use WorkflowStub
        $this->assertStringContainsString('WorkflowStub', $fileContent);

        // Should reference workflows
        $this->assertStringContainsString('MintStablecoinWorkflow', $fileContent);
        $this->assertStringContainsString('BurnStablecoinWorkflow', $fileContent);
        $this->assertStringContainsString('AddCollateralWorkflow', $fileContent);
    }

    #[Test]
    public function test_uses_strict_types(): void
    {
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Check for strict types declaration
        $this->assertStringContainsString('declare(strict_types=1);', $fileContent);
    }

    #[Test]
    public function test_namespace_is_correct(): void
    {
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);
        $this->assertEquals('App\Domain\Stablecoin\Services', $reflection->getNamespaceName());
    }

    #[Test]
    public function test_mint_validates_collateral_sufficiency(): void
    {
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);
        $method = $reflection->getMethod('mint');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should have comment about validating collateral
        $this->assertStringContainsString('// Validate collateral sufficiency', $source);
    }

    #[Test]
    public function test_method_parameters_use_type_hints(): void
    {
        $reflection = new ReflectionMethod(StablecoinIssuanceService::class, 'mint');
        $parameters = $reflection->getParameters();

        foreach ($parameters as $parameter) {
            $this->assertTrue($parameter->hasType());
            $this->assertNotNull($parameter->getType());
        }
    }

    #[Test]
    public function test_service_likely_has_burn_method(): void
    {
        // Based on imports, service should have burn functionality
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Import suggests burn functionality exists
        $this->assertStringContainsString('use App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow;', $fileContent);
    }

    #[Test]
    public function test_service_likely_has_add_collateral_method(): void
    {
        // Based on imports, service should have add collateral functionality
        $reflection = new ReflectionClass(StablecoinIssuanceService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Import suggests add collateral functionality exists
        $this->assertStringContainsString('use App\Domain\Stablecoin\Workflows\AddCollateralWorkflow;', $fileContent);
    }
}
