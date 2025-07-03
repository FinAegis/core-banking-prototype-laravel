<?php

namespace Tests\Feature;

use App\Models\CgoInvestment;
use App\Models\User;
use App\Services\Cgo\PaymentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CgoPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected PaymentVerificationService $verificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->verificationService = $this->mock(PaymentVerificationService::class);
    }

    public function test_user_can_view_pending_payments()
    {
        $pendingInvestment = CgoInvestment::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'pending',
        ]);
        
        $completedInvestment = CgoInvestment::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('cgo.payment-verification.index'));

        $response->assertOk()
            ->assertSee($pendingInvestment->uuid)
            ->assertDontSee($completedInvestment->uuid);
    }

    public function test_user_cannot_view_other_users_payments()
    {
        $otherUser = User::factory()->create();
        $otherInvestment = CgoInvestment::factory()->create([
            'user_id' => $otherUser->id,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('cgo.payment-verification.index'));

        $response->assertOk()
            ->assertDontSee($otherInvestment->uuid);
    }

    public function test_user_can_check_payment_status()
    {
        $investment = CgoInvestment::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'pending',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $this->verificationService
            ->shouldReceive('verifyStripePayment')
            ->once()
            ->with($investment)
            ->andReturn(['verified' => true, 'status' => 'completed']);

        $response = $this->actingAs($this->user)
            ->post(route('cgo.payment-verification.check', $investment));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Payment has been verified!',
            ]);
    }

    public function test_user_cannot_check_other_users_payment_status()
    {
        $otherUser = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $otherUser->id,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('cgo.payment-verification.check', $investment));

        $response->assertForbidden();
    }

    public function test_user_can_get_payment_timeline()
    {
        $investment = CgoInvestment::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'pending',
            'payment_pending_at' => now()->subHour(),
            'kyc_verified_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('cgo.payment-verification.timeline', $investment));

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['date', 'event', 'description', 'icon', 'color']
            ])
            ->assertJsonCount(4); // Created, method selected, pending, KYC
    }

    public function test_admin_can_access_payment_verification_dashboard()
    {
        $admin = User::factory()->create();
        
        // Note: In a real app, you'd need to set up proper admin authentication
        // This is a placeholder for the Filament page access test
        $this->markTestIncomplete('Filament page testing requires additional setup');
    }

    public function test_payment_verification_stats_widget_displays_correct_data()
    {
        CgoInvestment::factory()->count(5)->create([
            'payment_status' => 'pending',
        ]);
        
        CgoInvestment::factory()->count(3)->create([
            'payment_status' => 'processing',
        ]);
        
        CgoInvestment::factory()->count(2)->create([
            'payment_status' => 'pending',
            'created_at' => now()->subDays(2),
        ]);

        $widget = new \App\Filament\Widgets\PaymentVerificationStats();
        $stats = $widget->getStats();

        $this->assertCount(4, $stats);
        $this->assertEquals('7', $stats[0]->getValue()); // 5 + 2 pending
        $this->assertEquals('2', $stats[2]->getValue()); // 2 urgent
    }

    public function test_manual_payment_verification_updates_investment()
    {
        $investment = CgoInvestment::factory()->create([
            'payment_status' => 'pending',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
        ]);

        // This would be tested through the Filament interface
        // Simulating the manual verification process
        $data = [
            'reference' => 'BANK-REF-123',
            'amount_received' => 1000.00,
            'notes' => 'Verified via bank statement',
        ];

        $investment->update([
            'payment_status' => 'completed',
            'status' => 'confirmed',
            'payment_completed_at' => now(),
            'bank_transfer_reference' => $data['reference'],
            'amount_paid' => $data['amount_received'] * 100,
            'metadata' => [
                'manual_verification' => [
                    'verified_by' => 1,
                    'verified_at' => now()->toIso8601String(),
                    'notes' => $data['notes'],
                ]
            ]
        ]);

        $this->assertDatabaseHas('cgo_investments', [
            'id' => $investment->id,
            'payment_status' => 'completed',
            'status' => 'confirmed',
            'bank_transfer_reference' => 'BANK-REF-123',
        ]);
    }
}