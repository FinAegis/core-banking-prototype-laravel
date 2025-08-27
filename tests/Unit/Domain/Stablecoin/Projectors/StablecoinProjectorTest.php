<?php

namespace Tests\Unit\Domain\Stablecoin\Projectors;

use App\Domain\Stablecoin\Events\CollateralLocked;
use App\Domain\Stablecoin\Events\CollateralPositionClosed;
use App\Domain\Stablecoin\Events\CollateralPositionCreated;
use App\Domain\Stablecoin\Events\CollateralPositionLiquidated;
use App\Domain\Stablecoin\Events\CollateralPositionUpdated;
use App\Domain\Stablecoin\Events\CollateralReleased;
use App\Domain\Stablecoin\Events\StablecoinBurned;
use App\Domain\Stablecoin\Events\StablecoinMinted;
use App\Domain\Stablecoin\Projectors\StablecoinProjector;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;
use Tests\TestCase;

class StablecoinProjectorTest extends TestCase
{
    private StablecoinProjector $projector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projector = new StablecoinProjector();
    }

    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(StablecoinProjector::class));
    }

    #[Test]
    public function test_extends_projector(): void
    {
        $this->assertInstanceOf(Projector::class, $this->projector);
    }

    #[Test]
    public function test_has_event_handler_methods(): void
    {
        $expectedMethods = [
            'onCollateralPositionCreated',
            'onCollateralLocked',
            'onStablecoinMinted',
            'onStablecoinBurned',
            'onCollateralReleased',
            'onCollateralPositionUpdated',
            'onCollateralPositionClosed',
            'onCollateralPositionLiquidated',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(method_exists($this->projector, $method));
        }
    }

    #[Test]
    public function test_on_collateral_position_created_method_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onCollateralPositionCreated');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameter = $method->getParameters()[0];
        $this->assertEquals('event', $parameter->getName());
        $this->assertEquals(CollateralPositionCreated::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_on_collateral_locked_method_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onCollateralLocked');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameter = $method->getParameters()[0];
        $this->assertEquals('event', $parameter->getName());
        $this->assertEquals(CollateralLocked::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_on_stablecoin_minted_method_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onStablecoinMinted');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameter = $method->getParameters()[0];
        $this->assertEquals('event', $parameter->getName());
        $this->assertEquals(StablecoinMinted::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_on_stablecoin_burned_method_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onStablecoinBurned');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameter = $method->getParameters()[0];
        $this->assertEquals('event', $parameter->getName());
        $this->assertEquals(StablecoinBurned::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_on_collateral_released_method_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onCollateralReleased');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameter = $method->getParameters()[0];
        $this->assertEquals('event', $parameter->getName());
        $this->assertEquals(CollateralReleased::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_on_collateral_position_updated_method_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onCollateralPositionUpdated');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameter = $method->getParameters()[0];
        $this->assertEquals('event', $parameter->getName());
        $this->assertEquals(CollateralPositionUpdated::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_on_collateral_position_closed_method_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onCollateralPositionClosed');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameter = $method->getParameters()[0];
        $this->assertEquals('event', $parameter->getName());
        $this->assertEquals(CollateralPositionClosed::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_on_collateral_position_liquidated_method_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onCollateralPositionLiquidated');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameter = $method->getParameters()[0];
        $this->assertEquals('event', $parameter->getName());
        $this->assertEquals(CollateralPositionLiquidated::class, $parameter->getType()->getName());
    }

    #[Test]
    public function test_projector_uses_model(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);

        // Check that the projector references the model
        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;', $fileContent);
        $this->assertStringContainsString('StablecoinCollateralPosition::', $fileContent);
    }

    #[Test]
    public function test_projector_handles_create_operations(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onCollateralPositionCreated');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify it calls create on the model
        $this->assertStringContainsString('StablecoinCollateralPosition::create', $source);
    }

    #[Test]
    public function test_projector_handles_increment_operations(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);

        // Check onCollateralLocked method
        $method = $reflection->getMethod('onCollateralLocked');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('increment(\'collateral_amount\'', $source);

        // Check onStablecoinMinted method
        $method = $reflection->getMethod('onStablecoinMinted');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('increment(\'debt_amount\'', $source);
    }

    #[Test]
    public function test_projector_handles_decrement_operations(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);

        // Check onStablecoinBurned method
        $method = $reflection->getMethod('onStablecoinBurned');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('decrement(\'debt_amount\'', $source);

        // Check onCollateralReleased method
        $method = $reflection->getMethod('onCollateralReleased');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('decrement(\'collateral_amount\'', $source);
    }

    #[Test]
    public function test_projector_handles_update_operations(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);

        // Check onCollateralPositionUpdated method
        $method = $reflection->getMethod('onCollateralPositionUpdated');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('->fill(', $source);
        $this->assertStringContainsString('\'collateral_amount\'', $source);
        $this->assertStringContainsString('\'debt_amount\'', $source);
        $this->assertStringContainsString('\'collateral_ratio\'', $source);
        $this->assertStringContainsString('\'status\'', $source);
    }

    #[Test]
    public function test_projector_handles_status_changes(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);

        // Check onCollateralPositionClosed method
        $method = $reflection->getMethod('onCollateralPositionClosed');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('\'status\'    => \'closed\'', $source);
        $this->assertStringContainsString('\'closed_at\' => now()', $source);

        // Check onCollateralPositionLiquidated method
        $method = $reflection->getMethod('onCollateralPositionLiquidated');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('\'status\'            => \'liquidated\'', $source);
        $this->assertStringContainsString('\'liquidated_at\'     => now()', $source);
        $this->assertStringContainsString('\'collateral_amount\' => 0', $source);
        $this->assertStringContainsString('\'debt_amount\'       => 0', $source);
    }

    #[Test]
    public function test_projector_finds_positions_by_uuid(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);

        $methodsToCheck = [
            'onCollateralLocked',
            'onStablecoinMinted',
            'onStablecoinBurned',
            'onCollateralReleased',
            'onCollateralPositionUpdated',
            'onCollateralPositionClosed',
            'onCollateralPositionLiquidated',
        ];

        $fileName = $reflection->getFileName();

        foreach ($methodsToCheck as $methodName) {
            $method = $reflection->getMethod($methodName);
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();
            $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

            // Each method should find position by UUID
            $this->assertStringContainsString('where(\'uuid\', $event->position_uuid)', $source);
            $this->assertStringContainsString('firstOrFail()', $source);
        }
    }

    #[Test]
    public function test_projector_field_mappings(): void
    {
        $reflection = new ReflectionClass(StablecoinProjector::class);
        $method = $reflection->getMethod('onCollateralPositionCreated');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify field mappings in create method
        $expectedMappings = [
            '\'uuid\'                  => $event->position_uuid',
            '\'account_uuid\'          => $event->account_uuid',
            '\'stablecoin_code\'       => $event->stablecoin_code',
            '\'collateral_asset_code\' => $event->collateral_asset_code',
            '\'collateral_amount\'     => $event->collateral_amount',
            '\'debt_amount\'           => $event->debt_amount',
            '\'collateral_ratio\'      => $event->collateral_ratio',
            '\'status\'                => $event->status',
        ];

        foreach ($expectedMappings as $mapping) {
            $this->assertStringContainsString($mapping, $source);
        }
    }
}
