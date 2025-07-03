<?php

namespace Tests\Feature\Cgo;

use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Models\CgoRefund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CgoPricingRound $round;
    protected CgoInvestment $investment;

    protected function setUp(): void
    {
        parent::setUp();
        
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

    public function test_investment_cannot_be_refunded_if_not_confirmed()
    {
        $pendingInvestment = CgoInvestment::create([
            'uuid' => \Str::uuid(),
            'user_id' => $this->user->id,
            'round_id' => $this->round->id,
            'amount' => 10000,
            'currency' => 'USD',
            'share_price' => 100,
            'shares_purchased' => 100,
            'ownership_percentage' => 0.01,
            'tier' => 'bronze',
            'status' => 'pending',
            'payment_method' => 'stripe',
            'payment_status' => 'pending',
        ]);
        
        $this->assertFalse($pendingInvestment->canBeRefunded());
    }

    public function test_investment_cannot_be_refunded_after_90_days()
    {
        $oldInvestment = CgoInvestment::create([
            'uuid' => \Str::uuid(),
            'user_id' => $this->user->id,
            'round_id' => $this->round->id,
            'amount' => 10000,
            'currency' => 'USD',
            'share_price' => 100,
            'shares_purchased' => 100,
            'ownership_percentage' => 0.01,
            'tier' => 'bronze',
            'status' => 'confirmed',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_test789',
            'payment_status' => 'completed',
            'payment_completed_at' => now()->subDays(91),
        ]);
        
        $this->assertFalse($oldInvestment->canBeRefunded());
    }

    public function test_refund_formatted_amount()
    {
        $refund = CgoRefund::create([
            'investment_id' => $this->investment->id,
            'user_id' => $this->user->id,
            'amount' => 12345, // $123.45
            'currency' => 'USD',
            'reason' => 'customer_request',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
            'requested_at' => now(),
        ]);
        
        $this->assertEquals('$123.45', $refund->formatted_amount);
    }

    public function test_refund_status_color()
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
        
        $this->assertEquals('warning', $refund->status_color);
        
        $refund->update(['status' => 'approved']);
        $this->assertEquals('primary', $refund->status_color);
        
        $refund->update(['status' => 'rejected']);
        $this->assertEquals('danger', $refund->status_color);
        
        $refund->update(['status' => 'processing']);
        $this->assertEquals('info', $refund->status_color);
        
        $refund->update(['status' => 'completed']);
        $this->assertEquals('success', $refund->status_color);
        
        $refund->update(['status' => 'failed']);
        $this->assertEquals('danger', $refund->status_color);
        
        $refund->update(['status' => 'cancelled']);
        $this->assertEquals('gray', $refund->status_color);
    }
}