<?php

declare(strict_types=1);

namespace Tests\Feature\AI\Activities\Risk;

use App\Domain\AI\Activities\Risk\CalculateDebtRatiosActivity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

class CalculateDebtRatiosActivityTest extends TestCase
{
    private CalculateDebtRatiosActivity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a dummy workflow stub for testing
        $workflow = WorkflowStub::make(\App\Domain\AI\ChildWorkflows\Risk\CreditRiskWorkflow::class);
        /** @var StoredWorkflow $storedWorkflow */
        $storedWorkflow = StoredWorkflow::query()->findOrFail($workflow->id());

        // Create activity with required constructor parameters
        $this->activity = new CalculateDebtRatiosActivity(
            index: 0,
            now: now()->toDateTimeString(),
            storedWorkflow: $storedWorkflow
        );
    }

    #[Test]
    public function it_calculates_debt_ratios_correctly(): void
    {
        // Arrange
        $financialData = [
            'transactions' => collect([
                (object) ['type' => 'credit', 'amount' => 5000, 'created_at' => now()->subDays(10)],
                (object) ['type' => 'credit', 'amount' => 3000, 'created_at' => now()->subDays(15)],
                (object) ['type' => 'debit', 'amount' => 1000, 'created_at' => now()->subDays(5)],
            ]),
            'loans' => collect([
                (object) ['monthly_payment' => 500],
                (object) ['monthly_payment' => 300],
            ]),
        ];

        // Act
        $result = $this->activity->execute(['financial_data' => $financialData]);

        // Assert
        $this->assertArrayHasKey('dti_ratio', $result);
        $this->assertArrayHasKey('monthly_income', $result);
        $this->assertArrayHasKey('monthly_debt', $result);
        $this->assertEquals(8000, $result['monthly_income']);
        $this->assertEquals(800, $result['monthly_debt']);
        $this->assertEquals(0.1, $result['dti_ratio']);
    }

    #[Test]
    public function it_handles_zero_income(): void
    {
        // Arrange
        $financialData = [
            'transactions' => collect(),
            'loans'        => collect([
                (object) ['monthly_payment' => 500],
            ]),
        ];

        // Act
        $result = $this->activity->execute(['financial_data' => $financialData]);

        // Assert
        $this->assertEquals(0, $result['monthly_income']);
        $this->assertEquals(500, $result['monthly_debt']);
        $this->assertEquals(1.0, $result['dti_ratio']); // Max ratio when income is 0
    }

    #[Test]
    public function it_handles_empty_financial_data(): void
    {
        // Act
        $result = $this->activity->execute(['financial_data' => []]);

        // Assert
        $this->assertEquals(0, $result['monthly_income']);
        $this->assertEquals(0, $result['monthly_debt']);
        $this->assertEquals(1.0, $result['dti_ratio']);
    }
}
