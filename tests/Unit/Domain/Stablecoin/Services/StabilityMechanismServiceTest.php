<?php

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Stablecoin\Contracts\StabilityMechanismServiceInterface;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Stablecoin\Services\StabilityMechanismService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\ServiceTestCase;

class StabilityMechanismServiceTest extends ServiceTestCase
{
    private StabilityMechanismService $service;

    private ExchangeRateService $exchangeRateService;

    private CollateralService $collateralService;

    private LiquidationService $liquidationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exchangeRateService = $this->createMock(ExchangeRateService::class);
        $this->collateralService = $this->createMock(CollateralService::class);
        $this->liquidationService = $this->createMock(LiquidationService::class);

        $this->service = new StabilityMechanismService(
            $this->exchangeRateService,
            $this->collateralService,
            $this->liquidationService
        );
    }

    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(StabilityMechanismService::class));
    }

    #[Test]
    public function test_implements_interface(): void
    {
        $this->assertInstanceOf(StabilityMechanismServiceInterface::class, $this->service);
    }

    #[Test]
    public function test_constructor_injects_dependencies(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(3, $constructor->getNumberOfParameters());

        $parameters = $constructor->getParameters();

        $this->assertEquals('exchangeRateService', $parameters[0]->getName());
        $this->assertEquals(ExchangeRateService::class, $parameters[0]->getType()->getName());
        $this->assertFalse($parameters[0]->allowsNull());

        $this->assertEquals('collateralService', $parameters[1]->getName());
        $this->assertEquals(CollateralService::class, $parameters[1]->getType()->getName());
        $this->assertFalse($parameters[1]->allowsNull());

        $this->assertEquals('liquidationService', $parameters[2]->getName());
        $this->assertEquals(LiquidationService::class, $parameters[2]->getType()->getName());
        $this->assertTrue($parameters[2]->allowsNull());
        $this->assertTrue($parameters[2]->isDefaultValueAvailable());
        $this->assertNull($parameters[2]->getDefaultValue());
    }

    #[Test]
    public function test_has_execute_stability_mechanisms_method(): void
    {
        $this->assertTrue(method_exists($this->service, 'executeStabilityMechanisms'));
    }

    #[Test]
    public function test_execute_stability_mechanisms_method_signature(): void
    {
        $reflection = new ReflectionMethod(StabilityMechanismService::class, 'executeStabilityMechanisms');

        $this->assertEquals(0, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_service_properties_are_private_readonly(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);

        $exchangeRateProperty = $reflection->getProperty('exchangeRateService');
        $this->assertTrue($exchangeRateProperty->isPrivate());
        $this->assertTrue($exchangeRateProperty->isReadOnly());

        $collateralProperty = $reflection->getProperty('collateralService');
        $this->assertTrue($collateralProperty->isPrivate());
        $this->assertTrue($collateralProperty->isReadOnly());

        $liquidationProperty = $reflection->getProperty('liquidationService');
        $this->assertTrue($liquidationProperty->isPrivate());
        $this->assertTrue($liquidationProperty->isReadOnly());
    }

    #[Test]
    public function test_service_imports(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        $this->assertStringContainsString('use App\Domain\Asset\Services\ExchangeRateService;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Contracts\StabilityMechanismServiceInterface;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\Stablecoin;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Cache;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Log;', $fileContent);
    }

    #[Test]
    public function test_execute_stability_mechanisms_handles_errors(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);
        $method = $reflection->getMethod('executeStabilityMechanisms');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should handle errors with try-catch
        $this->assertStringContainsString('try {', $source);
        $this->assertStringContainsString('} catch (Exception $e)', $source);

        // Error logging is handled internally

        // Should include error in results
        $this->assertStringContainsString('\'success\' => false', $source);
        $this->assertStringContainsString('\'error\'   => $e->getMessage()', $source);
    }

    #[Test]
    public function test_execute_stability_mechanisms_processes_active_stablecoins(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);
        $method = $reflection->getMethod('executeStabilityMechanisms');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should get active stablecoins
        $this->assertStringContainsString('Stablecoin::active()->get()', $source);

        // Should iterate through stablecoins
        $this->assertStringContainsString('foreach ($stablecoins as $stablecoin)', $source);

        // Should call executeStabilityMechanismForStablecoin
        $this->assertStringContainsString('executeStabilityMechanismForStablecoin($stablecoin)', $source);
    }

    #[Test]
    public function test_has_execute_stability_mechanism_for_stablecoin_method(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);

        // Check if method is referenced in executeStabilityMechanisms
        $method = $reflection->getMethod('executeStabilityMechanisms');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('$this->executeStabilityMechanismForStablecoin', $source);
    }

    #[Test]
    public function test_execute_stability_mechanisms_returns_results_array(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);
        $method = $reflection->getMethod('executeStabilityMechanisms');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should initialize results array
        $this->assertStringContainsString('$results = [];', $source);

        // Should store results by stablecoin code
        $this->assertStringContainsString('$results[$stablecoin->code] = $result;', $source);

        // Should return results
        $this->assertStringContainsString('return $results;', $source);
    }

    #[Test]
    public function test_uses_strict_types(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Check for strict types declaration
        $this->assertStringContainsString('declare(strict_types=1);', $fileContent);
    }

    #[Test]
    public function test_namespace_is_correct(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);
        $this->assertEquals('App\Domain\Stablecoin\Services', $reflection->getNamespaceName());
    }

    #[Test]
    public function test_log_error_includes_context(): void
    {
        $reflection = new ReflectionClass(StabilityMechanismService::class);
        $method = $reflection->getMethod('executeStabilityMechanisms');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should include stablecoin_code in log context
        $this->assertStringContainsString("'stablecoin_code' => \$stablecoin->code", $source);
        $this->assertStringContainsString("'error'           => \$e->getMessage()", $source);
    }
}
