<?php

declare(strict_types=1);

namespace Tests\Feature\AI\ChildWorkflows\Risk;

use App\Domain\AI\Activities\Risk\CalculateCreditScoreActivity;
use App\Domain\AI\Activities\Risk\CalculateDebtRatiosActivity;
use App\Domain\AI\Activities\Risk\EvaluateLoanAffordabilityActivity;
use App\Domain\AI\ChildWorkflows\Risk\CreditRiskWorkflow;
use App\Domain\AI\Events\Risk\CreditAssessedEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreditRiskWorkflowTest extends TestCase
{
    private CreditRiskWorkflow $workflow;

    /** @var MockInterface */
    private MockInterface $mockCreditScoreActivity;

    /** @var MockInterface */
    private MockInterface $mockDebtRatiosActivity;

    /** @var MockInterface */
    private MockInterface $mockAffordabilityActivity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = new CreditRiskWorkflow();

        // Create mocks for activities
        $this->mockCreditScoreActivity = Mockery::mock(CalculateCreditScoreActivity::class);
        $this->mockDebtRatiosActivity = Mockery::mock(CalculateDebtRatiosActivity::class);
        $this->mockAffordabilityActivity = Mockery::mock(EvaluateLoanAffordabilityActivity::class);

        // Bind mocks to container
        $this->app->instance(CalculateCreditScoreActivity::class, $this->mockCreditScoreActivity);
        $this->app->instance(CalculateDebtRatiosActivity::class, $this->mockDebtRatiosActivity);
        $this->app->instance(EvaluateLoanAffordabilityActivity::class, $this->mockAffordabilityActivity);
    }

    /** @test */
    public function it_executes_credit_risk_assessment(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $conversationId = 'test-conversation-456';
        $financialData = [
            'accounts' => collect(),
            'transactions' => collect(),
        ];
        $parameters = [
            'loan_amount' => 10000,
            'loan_term' => 24,
        ];

        // Set up mock expectations
        $this->mockCreditScoreActivity
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                'score' => 700,
                'rating' => 'Good',
            ]);

        $this->mockDebtRatiosActivity
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                'dti_ratio' => 0.25,
                'monthly_income' => 5000,
                'monthly_debt' => 1250,
            ]);

        $this->mockAffordabilityActivity
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                'affordable' => true,
                'monthly_payment' => 450,
                'affordability_ratio' => 0.09,
            ]);

        // Act
        $generator = $this->workflow->execute($conversationId, $user, $financialData, $parameters);
        $result = iterator_to_array($generator, false);
        $assessment = end($result);

        // Assert
        $this->assertIsArray($assessment);
        $this->assertEquals(700, $assessment['credit_score']);
        $this->assertEquals('Good', $assessment['credit_rating']);
        $this->assertEquals(0.25, $assessment['dti_ratio']);
        $this->assertTrue($assessment['approved']);
        $this->assertEquals('low', $assessment['risk_level']);

        Event::assertDispatched(CreditAssessedEvent::class, function ($event) use ($conversationId, $user) {
            return $event->conversationId === $conversationId &&
                   $event->userId === $user->id;
        });
    }

    /** @test */
    public function it_handles_loan_denial(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $conversationId = 'test-conversation-789';
        $financialData = [
            'accounts' => collect(),
            'transactions' => collect(),
        ];
        $parameters = [];

        // Set up mock expectations for poor credit
        $this->mockCreditScoreActivity
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                'score' => 500,
                'rating' => 'Poor',
            ]);

        $this->mockDebtRatiosActivity
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                'dti_ratio' => 0.6,
                'monthly_income' => 3000,
                'monthly_debt' => 1800,
            ]);

        // Act
        $generator = $this->workflow->execute($conversationId, $user, $financialData, $parameters);
        $result = iterator_to_array($generator, false);
        $assessment = end($result);

        // Assert
        $this->assertEquals(500, $assessment['credit_score']);
        $this->assertEquals('Poor', $assessment['credit_rating']);
        $this->assertEquals(0.6, $assessment['dti_ratio']);
        $this->assertFalse($assessment['approved']);
        $this->assertEquals('high', $assessment['risk_level']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}