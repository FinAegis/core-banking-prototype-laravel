<?php

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\Services\OracleAggregator;
use App\Domain\Stablecoin\ValueObjects\AggregatedPrice;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class OracleAggregatorTest extends TestCase
{
    private OracleAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = new OracleAggregator();
    }

    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(OracleAggregator::class));
    }

    #[Test]
    public function test_constructor_initializes_oracles_collection(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(0, $constructor->getNumberOfParameters());
    }

    #[Test]
    public function test_has_private_properties(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);

        $this->assertTrue($reflection->hasProperty('oracles'));
        $this->assertTrue($reflection->hasProperty('minOracles'));
        $this->assertTrue($reflection->hasProperty('maxDeviation'));

        $oraclesProperty = $reflection->getProperty('oracles');
        $this->assertTrue($oraclesProperty->isPrivate());
        $this->assertEquals(Collection::class, $oraclesProperty->getType()->getName());

        $minOraclesProperty = $reflection->getProperty('minOracles');
        $this->assertTrue($minOraclesProperty->isPrivate());
        $this->assertEquals('int', $minOraclesProperty->getType()->getName());

        $maxDeviationProperty = $reflection->getProperty('maxDeviation');
        $this->assertTrue($maxDeviationProperty->isPrivate());
        $this->assertEquals('float', $maxDeviationProperty->getType()->getName());
    }

    #[Test]
    public function test_default_property_values(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);

        $minOraclesProperty = $reflection->getProperty('minOracles');
        $minOraclesProperty->setAccessible(true);
        $this->assertEquals(2, $minOraclesProperty->getValue($this->aggregator));

        $maxDeviationProperty = $reflection->getProperty('maxDeviation');
        $maxDeviationProperty->setAccessible(true);
        $this->assertEquals(0.02, $maxDeviationProperty->getValue($this->aggregator));
    }

    #[Test]
    public function test_has_register_oracle_method(): void
    {
        $this->assertTrue(method_exists($this->aggregator, 'registerOracle'));
    }

    #[Test]
    public function test_register_oracle_method_signature(): void
    {
        $reflection = new ReflectionMethod(OracleAggregator::class, 'registerOracle');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('oracle', $parameter->getName());
        $this->assertEquals(OracleConnector::class, $parameter->getType()->getName());

        $this->assertEquals('self', $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_has_get_aggregated_price_method(): void
    {
        $this->assertTrue(method_exists($this->aggregator, 'getAggregatedPrice'));
    }

    #[Test]
    public function test_get_aggregated_price_method_signature(): void
    {
        $reflection = new ReflectionMethod(OracleAggregator::class, 'getAggregatedPrice');

        $this->assertEquals(2, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());

        $parameters = $reflection->getParameters();

        $this->assertEquals('base', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());

        $this->assertEquals('quote', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());

        $this->assertEquals(AggregatedPrice::class, $reflection->getReturnType()->getName());
    }

    #[Test]
    public function test_service_imports(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        $this->assertStringContainsString('use App\Domain\Stablecoin\Contracts\OracleConnector;', $fileContent);
        $this->assertStringContainsString('use App\Domain\Stablecoin\ValueObjects\AggregatedPrice;', $fileContent);
        $this->assertStringContainsString('use Brick\Math\BigDecimal;', $fileContent);
        $this->assertStringContainsString('use Brick\Math\RoundingMode;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Collection;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Cache;', $fileContent);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Log;', $fileContent);
    }

    #[Test]
    public function test_register_oracle_sorts_by_priority(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);
        $method = $reflection->getMethod('registerOracle');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should push oracle to collection
        $this->assertStringContainsString('$this->oracles->push($oracle);', $source);

        // Should sort by priority
        $this->assertStringContainsString('sortBy(fn ($o) => $o->getPriority())', $source);

        // Should return self for chaining
        $this->assertStringContainsString('return $this;', $source);
    }

    #[Test]
    public function test_get_aggregated_price_uses_caching(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);
        $method = $reflection->getMethod('getAggregatedPrice');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should use cache
        $this->assertStringContainsString('Cache::remember', $source);
        $this->assertStringContainsString('$cacheKey = "oracle_price_{$base}_{$quote}"', $source);
        $this->assertStringContainsString('60,', $source); // Cache duration
    }

    #[Test]
    public function test_get_aggregated_price_validates_minimum_oracles(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);
        $method = $reflection->getMethod('getAggregatedPrice');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should check minimum oracle count
        $this->assertStringContainsString('if ($prices->count() < $this->minOracles)', $source);
        $this->assertStringContainsString('throw new RuntimeException', $source);
        $this->assertStringContainsString('Insufficient oracle responses', $source);
    }

    #[Test]
    public function test_has_collect_prices_method(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);

        // Should have collectPrices method (referenced in getAggregatedPrice)
        $method = $reflection->getMethod('getAggregatedPrice');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('$this->collectPrices($base, $quote)', $source);
    }

    #[Test]
    public function test_namespace_is_correct(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);
        $this->assertEquals('App\Domain\Stablecoin\Services', $reflection->getNamespaceName());
    }

    #[Test]
    public function test_constructor_initializes_empty_collection(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);
        $constructor = $reflection->getConstructor();

        $fileName = $reflection->getFileName();
        $startLine = $constructor->getStartLine();
        $endLine = $constructor->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Should initialize oracles as empty collection
        $this->assertStringContainsString('$this->oracles = collect();', $source);
    }

    #[Test]
    public function test_fluent_interface(): void
    {
        // Test that registerOracle returns self
        $oracle = $this->createMock(OracleConnector::class);
        $oracle->method('getPriority')->willReturn(1);

        $result = $this->aggregator->registerOracle($oracle);
        $this->assertSame($this->aggregator, $result);
    }

    #[Test]
    public function test_max_deviation_comment(): void
    {
        $reflection = new ReflectionClass(OracleAggregator::class);
        $property = $reflection->getProperty('maxDeviation');

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Should have comment explaining the percentage
        $this->assertStringContainsString('// 2% max deviation', $fileContent);
    }
}
