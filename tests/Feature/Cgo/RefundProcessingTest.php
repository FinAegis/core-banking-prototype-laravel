<?php

namespace Tests\Feature\Cgo;

use App\Domain\Cgo\Actions\RequestRefundAction;
use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Models\CgoRefund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Temporal\Client\WorkflowClient;
use Temporal\Internal\Workflow\WorkflowContext;

class RefundProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CgoPricingRound $round;
    protected CgoInvestment $investment;

    protected function setUp(): void
    {
        parent::setUp();
        
        Queue::fake();
        
        $this->user = User::factory()->create();
        
        $this->round = CgoPricingRound::create([
            'name' => 'Round 1',
            'round_number' => 1,
            'share_price' => 100,
            'max_shares_available' => 10000,
            'shares_sold' => 0,
            'total_raised' => 0,
            'started_at' => now()->subDays(5),
            'ended_at' => now()->addDays(25),
            'is_active' => true,
        ]);
        
        $this->investment = CgoInvestment::create([
            'uuid' => \Str::uuid(),
            'user_id' => $this->user->id,
            'round_id' => $this->round->id,
            'amount' => 10000, // $100
            'currency' => 'USD',
            'share_price' => 100,
            'shares_purchased' => 100,
            'ownership_percentage' => 0.01,
            'tier' => 'bronze',
            'status' => 'confirmed',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_test123',
            'payment_status' => 'completed',
            'payment_completed_at' => now()->subDays(2),
        ]);
    }

    public function test_can_request_refund_for_eligible_investment()
    {
        // Mock the WorkflowClient
        $mockWorkflowExecution = $this->createMock(\Temporal\Client\WorkflowExecution::class);
        $mockWorkflowExecution->method('getID')->willReturn('test-workflow-id');
        
        $mockWorkflowRun = $this->createMock(\Temporal\Client\WorkflowRun::class);
        $mockWorkflowRun->method('getExecution')->willReturn($mockWorkflowExecution);
        
        $mockWorkflowProxy = $this->createMock(\Temporal\Client\WorkflowProxy::class);
        
        $mockWorkflowClient = $this->createMock(WorkflowClient::class);
        $mockWorkflowClient->expects($this->once())
            ->method('newWorkflow')
            ->willReturn($mockWorkflowProxy);
        
        $mockWorkflowClient->expects($this->once())
            ->method('start')
            ->willReturn($mockWorkflowRun);
        
        $this->app->instance(WorkflowClient::class, $mockWorkflowClient);
        
        $action = new RequestRefundAction($mockWorkflowClient);
        
        $result = $action->execute(
            investment: $this->investment,
            initiator: $this->user,
            reason: 'customer_request',
            reasonDetails: 'Changed my mind'
        );
        
        $this->assertArrayHasKey('workflow_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('initiated', $result['status']);
    }

    public function test_cannot_request_refund_for_ineligible_investment()
    {
        // Make investment ineligible
        $this->investment->update([
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        
        $mockWorkflowClient = $this->createMock(WorkflowClient::class);
        $this->app->instance(WorkflowClient::class, $mockWorkflowClient);
        
        $action = new RequestRefundAction($mockWorkflowClient);
        
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This investment cannot be refunded');
        
        $action->execute(
            investment: $this->investment,
            initiator: $this->user,
            reason: 'customer_request',
            reasonDetails: 'Changed my mind'
        );
    }

    public function test_cannot_request_refund_when_one_already_exists()
    {
        // Create an existing refund
        CgoRefund::create([
            'investment_id' => $this->investment->id,
            'user_id' => $this->user->id,
            'amount' => $this->investment->amount,
            'currency' => 'USD',
            'reason' => 'customer_request',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
            'requested_at' => now(),
        ]);
        
        $mockWorkflowClient = $this->createMock(WorkflowClient::class);
        $this->app->instance(WorkflowClient::class, $mockWorkflowClient);
        
        $action = new RequestRefundAction($mockWorkflowClient);
        
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('A refund is already in progress for this investment');
        
        $action->execute(
            investment: $this->investment,
            initiator: $this->user,
            reason: 'customer_request',
            reasonDetails: 'Trying again'
        );
    }

    public function test_auto_approves_small_refunds()
    {
        // Create a small investment
        $smallInvestment = CgoInvestment::create([
            'uuid' => \Str::uuid(),
            'user_id' => $this->user->id,
            'round_id' => $this->round->id,
            'amount' => 5000, // $50
            'currency' => 'USD',
            'share_price' => 100,
            'shares_purchased' => 50,
            'ownership_percentage' => 0.005,
            'tier' => 'bronze',
            'status' => 'confirmed',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_test456',
            'payment_status' => 'completed',
            'payment_completed_at' => now()->subDays(1),
        ]);
        
        // Mock the WorkflowClient
        $mockWorkflowExecution = $this->createMock(\Temporal\Client\WorkflowExecution::class);
        $mockWorkflowExecution->method('getID')->willReturn('test-workflow-id');
        
        $mockWorkflowRun = $this->createMock(\Temporal\Client\WorkflowRun::class);
        $mockWorkflowRun->method('getExecution')->willReturn($mockWorkflowExecution);
        
        $mockWorkflowProxy = $this->createMock(\Temporal\Client\WorkflowProxy::class);
        
        $mockWorkflowClient = $this->createMock(WorkflowClient::class);
        $mockWorkflowClient->expects($this->once())
            ->method('newWorkflow')
            ->willReturn($mockWorkflowProxy);
        
        $mockWorkflowClient->expects($this->once())
            ->method('start')
            ->willReturn($mockWorkflowRun);
        
        $this->app->instance(WorkflowClient::class, $mockWorkflowClient);
        
        $action = new RequestRefundAction($mockWorkflowClient);
        
        $result = $action->execute(
            investment: $smallInvestment,
            initiator: $this->user,
            reason: 'customer_request',
            reasonDetails: 'Small amount'
        );
        
        $this->assertTrue($result['auto_approved']);
    }

    public function test_auto_approves_within_grace_period()
    {
        // Update investment to be within grace period
        $this->investment->update([
            'payment_completed_at' => now()->subDays(3), // Within 7-day grace period
        ]);
        
        // Mock the WorkflowClient
        $mockWorkflowExecution = $this->createMock(\Temporal\Client\WorkflowExecution::class);
        $mockWorkflowExecution->method('getID')->willReturn('test-workflow-id');
        
        $mockWorkflowRun = $this->createMock(\Temporal\Client\WorkflowRun::class);
        $mockWorkflowRun->method('getExecution')->willReturn($mockWorkflowExecution);
        
        $mockWorkflowProxy = $this->createMock(\Temporal\Client\WorkflowProxy::class);
        
        $mockWorkflowClient = $this->createMock(WorkflowClient::class);
        $mockWorkflowClient->expects($this->once())
            ->method('newWorkflow')
            ->willReturn($mockWorkflowProxy);
        
        $mockWorkflowClient->expects($this->once())
            ->method('start')
            ->willReturn($mockWorkflowRun);
        
        $this->app->instance(WorkflowClient::class, $mockWorkflowClient);
        
        $action = new RequestRefundAction($mockWorkflowClient);
        
        $result = $action->execute(
            investment: $this->investment,
            initiator: $this->user,
            reason: 'customer_request',
            reasonDetails: 'Within grace period'
        );
        
        $this->assertTrue($result['auto_approved']);
    }

    public function test_refund_model_relationships()
    {
        $refund = CgoRefund::create([
            'investment_id' => $this->investment->id,
            'user_id' => $this->user->id,
            'amount' => $this->investment->amount,
            'currency' => 'USD',
            'reason' => 'customer_request',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
            'requested_at' => now(),
        ]);
        
        $this->assertInstanceOf(CgoInvestment::class, $refund->investment);
        $this->assertEquals($this->investment->id, $refund->investment->id);
        
        $this->assertInstanceOf(User::class, $refund->user);
        $this->assertEquals($this->user->id, $refund->user->id);
        
        $this->assertInstanceOf(User::class, $refund->initiator);
        $this->assertEquals($this->user->id, $refund->initiator->id);
    }

    public function test_refund_status_methods()
    {
        $refund = CgoRefund::create([
            'investment_id' => $this->investment->id,
            'user_id' => $this->user->id,
            'amount' => $this->investment->amount,
            'currency' => 'USD',
            'reason' => 'customer_request',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
            'requested_at' => now(),
        ]);
        
        $this->assertTrue($refund->isPending());
        $this->assertTrue($refund->canBeApproved());
        $this->assertTrue($refund->canBeRejected());
        $this->assertTrue($refund->canBeCancelled());
        $this->assertFalse($refund->canBeProcessed());
        
        $refund->update(['status' => 'approved']);
        
        $this->assertTrue($refund->isApproved());
        $this->assertFalse($refund->canBeApproved());
        $this->assertFalse($refund->canBeRejected());
        $this->assertTrue($refund->canBeProcessed());
        $this->assertTrue($refund->canBeCancelled());
        
        $refund->update(['status' => 'completed']);
        
        $this->assertTrue($refund->isCompleted());
        $this->assertFalse($refund->canBeCancelled());
    }

    public function test_investment_refund_methods()
    {
        $this->assertTrue($this->investment->canBeRefunded());
        $this->assertEquals(0, $this->investment->getTotalRefundedAmount());
        $this->assertEquals($this->investment->amount, $this->investment->getRefundableAmount());
        $this->assertFalse($this->investment->hasActiveRefund());
        
        // Create a completed refund
        CgoRefund::create([
            'investment_id' => $this->investment->id,
            'user_id' => $this->user->id,
            'amount' => 5000,
            'amount_refunded' => 5000,
            'currency' => 'USD',
            'reason' => 'customer_request',
            'status' => 'completed',
            'initiated_by' => $this->user->id,
            'requested_at' => now()->subDays(1),
            'completed_at' => now(),
        ]);
        
        $this->assertEquals(5000, $this->investment->getTotalRefundedAmount());
        $this->assertEquals(5000, $this->investment->getRefundableAmount());
        
        // Create an active refund
        CgoRefund::create([
            'investment_id' => $this->investment->id,
            'user_id' => $this->user->id,
            'amount' => 5000,
            'currency' => 'USD',
            'reason' => 'customer_request',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
            'requested_at' => now(),
        ]);
        
        $this->assertTrue($this->investment->hasActiveRefund());
    }
}