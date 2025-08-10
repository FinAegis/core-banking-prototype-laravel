<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Activities\Trading;

use App\Domain\AI\Activities\Trading\CalculateRSIActivity;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

class CalculateRSIActivityTest extends TestCase
{
    private CalculateRSIActivity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a dummy workflow stub for testing
        $workflow = WorkflowStub::make(\App\Domain\AI\ChildWorkflows\Trading\MarketAnalysisWorkflow::class);
        /** @var StoredWorkflow $storedWorkflow */
        $storedWorkflow = StoredWorkflow::query()->findOrFail($workflow->id());

        // Create activity with required constructor parameters
        $this->activity = new CalculateRSIActivity(
            index: 0,
            now: now()->toDateTimeString(),
            storedWorkflow: $storedWorkflow
        );
    }

    /** @test */
    public function it_calculates_rsi_with_valid_data(): void
    {
        // Arrange
        $prices = [
            44.34, 44.09, 44.15, 43.61, 44.33, 44.83, 45.10,
            45.42, 45.84, 46.08, 45.89, 46.03, 45.61, 46.28,
            46.28, 46.00, 46.03, 46.41, 46.22, 45.64,
        ];

        // Act
        $result = $this->activity->execute([
            'prices' => $prices,
            'period' => 14,
        ]);

        // Assert
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('signal', $result);
        $this->assertArrayHasKey('strength', $result);
        $this->assertGreaterThanOrEqual(0, $result['value']);
        $this->assertLessThanOrEqual(100, $result['value']);
    }

    /** @test */
    public function it_returns_neutral_for_insufficient_data(): void
    {
        // Arrange
        $prices = [50, 51, 52]; // Only 3 prices

        // Act
        $result = $this->activity->execute([
            'prices' => $prices,
            'period' => 14,
        ]);

        // Assert
        $this->assertEquals(50.0, $result['value']);
        $this->assertEquals('neutral', $result['signal']);
        $this->assertEquals(0.0, $result['strength']);
    }

    /** @test */
    public function it_identifies_overbought_condition(): void
    {
        // Arrange - Create prices that will generate high RSI
        $prices = [];
        $price = 100;
        for ($i = 0; $i < 20; $i++) {
            $price *= 1.02; // Consistent 2% gains
            $prices[] = $price;
        }

        // Act
        $result = $this->activity->execute([
            'prices' => $prices,
            'period' => 14,
        ]);

        // Assert
        $this->assertGreaterThan(70, $result['value']);
        $this->assertEquals('overbought', $result['signal']);
    }

    /** @test */
    public function it_identifies_oversold_condition(): void
    {
        // Arrange - Create prices that will generate low RSI
        $prices = [];
        $price = 100;
        for ($i = 0; $i < 20; $i++) {
            $price *= 0.98; // Consistent 2% losses
            $prices[] = $price;
        }

        // Act
        $result = $this->activity->execute([
            'prices' => $prices,
            'period' => 14,
        ]);

        // Assert
        $this->assertLessThan(30, $result['value']);
        $this->assertEquals('oversold', $result['signal']);
    }

    /** @test */
    public function it_calculates_signal_strength_correctly(): void
    {
        // Arrange
        $prices = [
            50, 52, 51, 53, 54, 52, 51, 50, 48, 47,
            46, 45, 44, 43, 42, 41, 40, 39, 38, 37,
        ];

        // Act
        $result = $this->activity->execute([
            'prices' => $prices,
            'period' => 14,
        ]);

        // Assert
        $this->assertGreaterThan(0, $result['strength']);
        $this->assertLessThanOrEqual(1, $result['strength']);
    }
}
