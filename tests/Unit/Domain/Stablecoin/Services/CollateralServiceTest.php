<?php

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Stablecoin\Contracts\CollateralServiceInterface;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Services\CollateralService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\ServiceTestCase;

class CollateralServiceTest extends ServiceTestCase
{
    private CollateralService $service;

    private ExchangeRateService $exchangeRateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exchangeRateService = $this->createMock(ExchangeRateService::class);
        $this->service = new CollateralService($this->exchangeRateService);
    }

    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(CollateralService::class));
    }

    #[Test]
    public function test_implements_interface(): void
    {
        $this->assertInstanceOf(CollateralServiceInterface::class, $this->service);
    }

    #[Test]
    public function test_constructor_injects_dependencies(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());

        $parameter = $constructor->getParameters()[0];
        $this->assertEquals('exchangeRateService', $parameter->getName());
        $this->assertEquals(ExchangeRateService::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_has_required_methods(): void
    {
        $expectedMethods = [
            'convertToPegAsset',
            'calculateTotalCollateralValue',
            'getPositionsAtRisk',
            'getPositionsForLiquidation',
            'updatePositionCollateralRatio',
            'calculatePositionHealthScore',
            'getCollateralDistribution',
            'getSystemCollateralizationMetrics',
            'calculateLiquidationPriority',
            'getPositionRecommendations',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(method_exists($this->service, $method));
        }
    }

    #[Test]
    public function test_convert_to_peg_asset_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'convertToPegAsset');

        $this->assertEquals(3, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameters = $reflection->getParameters();
        $this->assertEquals('fromAsset', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());

        $this->assertEquals('amount', $parameters[1]->getName());
        $this->assertEquals('float', $parameters[1]->getType()->getName());

        $this->assertEquals('pegAsset', $parameters[2]->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());

        $this->assertEquals('float', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_calculate_total_collateral_value_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'calculateTotalCollateralValue');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('stablecoinCode', $parameter->getName());
        $this->assertEquals('string', $parameter->getType()->getName());

        $this->assertEquals('float', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_get_positions_at_risk_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'getPositionsAtRisk');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('bufferRatio', $parameter->getName());
        $this->assertEquals('float', $parameter->getType()->getName());
        $this->assertTrue($parameter->isDefaultValueAvailable());
        $this->assertEquals(0.05, $parameter->getDefaultValue());

        $this->assertEquals(Collection::class, $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_get_positions_for_liquidation_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'getPositionsForLiquidation');

        $this->assertEquals(0, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals(Collection::class, $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_update_position_collateral_ratio_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'updatePositionCollateralRatio');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('position', $parameter->getName());
        $this->assertEquals(StablecoinCollateralPosition::class, $parameter->getType()->getName());

        $this->assertEquals('void', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_calculate_position_health_score_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'calculatePositionHealthScore');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('position', $parameter->getName());
        $this->assertEquals(StablecoinCollateralPosition::class, $parameter->getType()->getName());

        $this->assertEquals('float', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_get_collateral_distribution_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'getCollateralDistribution');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('stablecoinCode', $parameter->getName());
        $this->assertEquals('string', $parameter->getType()->getName());

        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_get_system_collateralization_metrics_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'getSystemCollateralizationMetrics');

        $this->assertEquals(0, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_calculate_liquidation_priority_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'calculateLiquidationPriority');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('position', $parameter->getName());
        $this->assertEquals(StablecoinCollateralPosition::class, $parameter->getType()->getName());

        $this->assertEquals('float', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_get_position_recommendations_method_signature(): void
    {
        $reflection = new ReflectionMethod(CollateralService::class, 'getPositionRecommendations');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('position', $parameter->getName());
        $this->assertEquals(StablecoinCollateralPosition::class, $parameter->getType()->getName());

        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_has_private_calculate_suggested_collateral_amount_method(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);
        $this->assertTrue($reflection->hasMethod('calculateSuggestedCollateralAmount'));

        $method = $reflection->getMethod('calculateSuggestedCollateralAmount');
        $this->assertTrue($method->isPrivate());
    }

    #[Test]
    public function test_service_uses_models(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);

        // Check that the service imports required models
        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\Stablecoin;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Collection;', $fileContent);
    }

    #[Test]
    public function test_service_handles_exchange_rates(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);

        // Check convertToPegAsset implementation
        $method = $reflection->getMethod('convertToPegAsset');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should have same asset shortcut
        $this->assertStringContainsString('if ($fromAsset === $pegAsset)', $source);
        $this->assertStringContainsString('return $amount;', $source);

        // Should use exchange rate service
        $this->assertStringContainsString('$this->exchangeRateService->getRate', $source);
        $this->assertStringContainsString('throw new RuntimeException', $source);
    }

    #[Test]
    public function test_service_handles_position_filtering(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);

        // Check getPositionsAtRisk implementation
        $method = $reflection->getMethod('getPositionsAtRisk');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should filter active positions
        $this->assertStringContainsString('->active()', $source);
        $this->assertStringContainsString('->filter(', $source);
        $this->assertStringContainsString('updatePositionCollateralRatio', $source);
    }

    #[Test]
    public function test_service_calculates_health_metrics(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);

        // Check calculatePositionHealthScore implementation
        $method = $reflection->getMethod('calculatePositionHealthScore');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should handle zero debt case
        $this->assertStringContainsString('if ($position->debt_amount == 0)', $source);
        $this->assertStringContainsString('return 1.0;', $source);

        // Should normalize score between 0 and 1
        $this->assertStringContainsString('min(1.0, max(0.0,', $source);
    }

    #[Test]
    public function test_service_provides_recommendations(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);

        // Check getPositionRecommendations implementation
        $method = $reflection->getMethod('getPositionRecommendations');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should have different urgency levels
        $this->assertStringContainsString("'urgency' => 'critical'", $source);
        $this->assertStringContainsString("'urgency'          => 'high'", $source);
        $this->assertStringContainsString("'urgency' => 'medium'", $source);
        $this->assertStringContainsString("'urgency'         => 'low'", $source);

        // Should have different actions
        $this->assertStringContainsString("'action'  => 'liquidate'", $source);
        $this->assertStringContainsString("'action'           => 'add_collateral'", $source);
        $this->assertStringContainsString("'action'  => 'monitor'", $source);
        $this->assertStringContainsString("'action'          => 'mint_more'", $source);
    }

    #[Test]
    public function test_service_handles_system_metrics(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);

        // Check getSystemCollateralizationMetrics implementation
        $method = $reflection->getMethod('getSystemCollateralizationMetrics');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should iterate through active stablecoins
        $this->assertStringContainsString('Stablecoin::active()->get()', $source);

        // Should include various metrics
        $this->assertStringContainsString("'total_supply'", $source);
        $this->assertStringContainsString("'total_collateral_value'", $source);
        $this->assertStringContainsString("'global_ratio'", $source);
        $this->assertStringContainsString("'is_healthy'", $source);
        $this->assertStringContainsString("'collateral_distribution'", $source);
    }

    #[Test]
    public function test_liquidation_priority_calculation(): void
    {
        $reflection = new ReflectionClass(CollateralService::class);

        // Check calculateLiquidationPriority implementation
        $method = $reflection->getMethod('calculateLiquidationPriority');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should use multiple factors
        $this->assertStringContainsString('calculatePositionHealthScore', $source);
        $this->assertStringContainsString('debt_amount', $source);
        $this->assertStringContainsString('last_interaction_at', $source);

        // Should have weighted factors
        $this->assertStringContainsString('* 0.6', $source);
        $this->assertStringContainsString('* 0.3', $source);
        $this->assertStringContainsString('* 0.1', $source);
    }
}
