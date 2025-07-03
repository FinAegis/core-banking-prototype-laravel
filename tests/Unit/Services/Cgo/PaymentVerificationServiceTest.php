<?php

namespace Tests\Unit\Services\Cgo;

use App\Mail\CgoInvestmentConfirmed;
use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Services\Cgo\CoinbaseCommerceService;
use App\Services\Cgo\PaymentVerificationService;
use App\Services\Cgo\StripePaymentService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PaymentVerificationServiceTest extends TestCase
{
    private PaymentVerificationService $service;
    private $stripeService;
    private $coinbaseService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Mail::fake();
        
        $this->stripeService = Mockery::mock(StripePaymentService::class);
        $this->coinbaseService = Mockery::mock(CoinbaseCommerceService::class);
        
        $this->service = new PaymentVerificationService(
            $this->stripeService,
            $this->coinbaseService
        );
    }

    public function test_verify_payment_returns_true_for_confirmed_investment()
    {
        $investment = CgoInvestment::factory()->confirmed()->create();

        $result = $this->service->verifyPayment($investment);

        $this->assertTrue($result);
        Mail::assertNothingSent();
    }

    public function test_verify_stripe_payment_success()
    {
        $round = CgoPricingRound::factory()->create();
        $investment = CgoInvestment::factory()->withStripePayment()->create([
            'status' => 'pending',
            'round_id' => $round->id,
            'email' => 'investor@example.com',
        ]);

        $this->stripeService->shouldReceive('verifyPayment')
            ->once()
            ->with($investment)
            ->andReturn(true);

        $result = $this->service->verifyPayment($investment);

        $this->assertTrue($result);
        
        $investment->refresh();
        $this->assertEquals('confirmed', $investment->status);
        $this->assertEquals('confirmed', $investment->payment_status);
        $this->assertNotNull($investment->payment_completed_at);
        $this->assertNotNull($investment->certificate_number);
        
        Mail::assertSent(CgoInvestmentConfirmed::class, function ($mail) use ($investment) {
            return $mail->hasTo($investment->email);
        });
    }

    public function test_verify_coinbase_payment_with_charge_id()
    {
        $investment = CgoInvestment::factory()->withCoinbasePayment()->create([
            'status' => 'pending',
            'email' => 'investor@example.com',
        ]);

        $this->coinbaseService->shouldReceive('getCharge')
            ->once()
            ->with($investment->coinbase_charge_id)
            ->andReturn([
                'timeline' => [
                    ['status' => 'PENDING', 'time' => '2025-01-01'],
                    ['status' => 'COMPLETED', 'time' => '2025-01-02'],
                ],
            ]);

        $result = $this->service->verifyPayment($investment);

        $this->assertTrue($result);
        
        $investment->refresh();
        $this->assertEquals('confirmed', $investment->status);
    }

    public function test_verify_manual_crypto_payment_with_tx_hash()
    {
        $investment = CgoInvestment::factory()->create([
            'payment_method' => 'crypto',
            'status' => 'pending',
            'crypto_transaction_hash' => 'tx_123abc',
            'coinbase_charge_id' => null,
            'email' => 'investor@example.com',
        ]);

        $result = $this->service->verifyPayment($investment);

        $this->assertTrue($result);
        
        $investment->refresh();
        $this->assertEquals('confirmed', $investment->status);
    }

    public function test_verify_bank_transfer_requires_manual_confirmation()
    {
        $investment = CgoInvestment::factory()->create([
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
            'bank_transfer_reference' => 'REF123',
            'payment_status' => 'pending',
        ]);

        $result = $this->service->verifyPayment($investment);

        $this->assertFalse($result);
        
        // Now mark as manually confirmed
        $investment->update(['payment_status' => 'confirmed']);
        
        $result = $this->service->verifyPayment($investment);
        
        $this->assertTrue($result);
    }

    public function test_is_payment_expired()
    {
        // Card payment - expires after 1 hour
        $cardInvestment = CgoInvestment::factory()->create([
            'payment_method' => 'card',
            'created_at' => now()->subHours(2),
        ]);
        $this->assertTrue($this->service->isPaymentExpired($cardInvestment));

        // Crypto payment - expires after 24 hours
        $cryptoInvestment = CgoInvestment::factory()->create([
            'payment_method' => 'crypto',
            'created_at' => now()->subHours(25),
        ]);
        $this->assertTrue($this->service->isPaymentExpired($cryptoInvestment));

        // Bank transfer - expires after 72 hours
        $bankInvestment = CgoInvestment::factory()->create([
            'payment_method' => 'bank_transfer',
            'created_at' => now()->subHours(73),
        ]);
        $this->assertTrue($this->service->isPaymentExpired($bankInvestment));

        // Not expired
        $recentInvestment = CgoInvestment::factory()->create([
            'payment_method' => 'card',
            'created_at' => now()->subMinutes(30),
        ]);
        $this->assertFalse($this->service->isPaymentExpired($recentInvestment));
    }

    public function test_handle_expired_payments()
    {
        // Create expired investments
        $expired1 = CgoInvestment::factory()->create([
            'status' => 'pending',
            'payment_method' => 'card',
            'created_at' => now()->subHours(2),
        ]);

        $expired2 = CgoInvestment::factory()->create([
            'status' => 'pending',
            'payment_method' => 'crypto',
            'created_at' => now()->subHours(25),
        ]);

        // Create non-expired investment
        $notExpired = CgoInvestment::factory()->create([
            'status' => 'pending',
            'payment_method' => 'card',
            'created_at' => now()->subMinutes(30),
        ]);

        $count = $this->service->handleExpiredPayments();

        $this->assertEquals(2, $count);

        $expired1->refresh();
        $this->assertEquals('cancelled', $expired1->status);
        $this->assertEquals('expired', $expired1->payment_status);
        $this->assertNotNull($expired1->cancelled_at);

        $notExpired->refresh();
        $this->assertEquals('pending', $notExpired->status);
    }

    public function test_mark_payment_failed()
    {
        $investment = CgoInvestment::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->service->markPaymentFailed($investment, 'Insufficient funds');

        $investment->refresh();
        $this->assertEquals('failed', $investment->payment_status);
        $this->assertNotNull($investment->payment_failed_at);
        $this->assertEquals('Insufficient funds', $investment->payment_failure_reason);
    }

    public function test_verify_pending_payments_batch()
    {
        $round = CgoPricingRound::factory()->create();
        
        // Create pending investments
        $pending1 = CgoInvestment::factory()->withStripePayment()->create([
            'status' => 'pending',
            'round_id' => $round->id,
        ]);

        $pending2 = CgoInvestment::factory()->create([
            'status' => 'pending',
            'payment_method' => 'crypto',
            'crypto_transaction_hash' => 'tx_456',
            'round_id' => $round->id,
            'email' => 'investor2@example.com',
        ]);

        // Create old pending investment (should be ignored)
        $oldPending = CgoInvestment::factory()->create([
            'status' => 'pending',
            'created_at' => now()->subDays(8),
        ]);

        $this->stripeService->shouldReceive('verifyPayment')
            ->once()
            ->andReturn(true);

        $count = $this->service->verifyPendingPayments();

        $this->assertEquals(2, $count);

        $pending1->refresh();
        $pending2->refresh();
        $oldPending->refresh();

        $this->assertEquals('confirmed', $pending1->status);
        $this->assertEquals('confirmed', $pending2->status);
        $this->assertEquals('pending', $oldPending->status);
    }

    public function test_certificate_number_generation()
    {
        $investment = CgoInvestment::factory()->create([
            'id' => 123,
            'tier' => 'gold',
        ]);

        $certificateNumber = $investment->generateCertificateNumber();
        
        $this->assertStringStartsWith('CGO-G-' . date('Y') . '-', $certificateNumber);
        $this->assertStringEndsWith('000123', $certificateNumber);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}