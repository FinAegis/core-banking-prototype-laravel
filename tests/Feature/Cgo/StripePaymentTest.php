<?php

namespace Tests\Feature\Cgo;

use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Models\User;
use App\Services\Cgo\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session;
use Tests\TestCase;
use Mockery;

class StripePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set test Stripe keys
        config([
            'cashier.key' => 'pk_test_' . str_repeat('x', 24),
            'cashier.secret' => 'sk_test_' . str_repeat('x', 24),
            'cashier.currency' => 'eur',
        ]);
    }

    /** @test */
    public function it_creates_stripe_checkout_session_for_investment()
    {
        $user = User::factory()->create();
        $round = CgoPricingRound::factory()->create([
            'is_active' => true,
            'share_price' => 100,
            'max_shares_available' => 10000,
        ]);
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'round_id' => $round->id,
            'amount' => 1000,
            'payment_method' => 'card',
            'status' => 'pending',
        ]);

        // Mock Stripe Session
        $mockSession = Mockery::mock('overload:' . Session::class);
        $mockSession->shouldReceive('create')
            ->once()
            ->andReturn((object)[
                'id' => 'cs_test_' . str_repeat('x', 24),
                'url' => 'https://checkout.stripe.com/pay/cs_test_' . str_repeat('x', 24),
            ]);

        $service = new StripePaymentService();
        $session = $service->createCheckoutSession($investment);

        $this->assertNotNull($session->id);
        $this->assertNotNull($session->url);
        
        $investment->refresh();
        $this->assertEquals('cs_test_' . str_repeat('x', 24), $investment->stripe_session_id);
        $this->assertEquals('checkout_created', $investment->payment_status);
    }

    /** @test */
    public function it_verifies_completed_payment()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 1000,
            'payment_method' => 'card',
            'stripe_session_id' => 'cs_test_' . str_repeat('x', 24),
            'payment_status' => 'checkout_created',
        ]);

        // Mock Stripe Session retrieval
        $mockSession = Mockery::mock('overload:' . Session::class);
        $mockSession->shouldReceive('retrieve')
            ->once()
            ->with($investment->stripe_session_id)
            ->andReturn((object)[
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_' . str_repeat('x', 24),
            ]);

        $service = new StripePaymentService();
        $verified = $service->verifyPayment($investment);

        $this->assertTrue($verified);
        
        $investment->refresh();
        $this->assertEquals('completed', $investment->payment_status);
        $this->assertEquals('pi_test_' . str_repeat('x', 24), $investment->stripe_payment_intent_id);
        $this->assertNotNull($investment->payment_completed_at);
    }

    /** @test */
    public function it_handles_checkout_completed_webhook()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 1000,
            'payment_method' => 'card',
            'payment_status' => 'checkout_created',
        ]);

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'client_reference_id' => $investment->uuid,
                    'payment_intent' => 'pi_test_' . str_repeat('x', 24),
                ],
            ],
        ];

        $service = new StripePaymentService();
        $service->handleWebhook($payload);

        $investment->refresh();
        $this->assertEquals('completed', $investment->payment_status);
        $this->assertEquals('pi_test_' . str_repeat('x', 24), $investment->stripe_payment_intent_id);
        $this->assertNotNull($investment->payment_completed_at);
    }

    /** @test */
    public function it_handles_payment_failed_webhook()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 1000,
            'payment_method' => 'card',
            'payment_status' => 'checkout_created',
        ]);

        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'metadata' => [
                        'investment_id' => $investment->id,
                    ],
                    'last_payment_error' => [
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ];

        $service = new StripePaymentService();
        $service->handleWebhook($payload);

        $investment->refresh();
        $this->assertEquals('failed', $investment->payment_status);
        $this->assertEquals('Your card was declined.', $investment->payment_failure_reason);
        $this->assertNotNull($investment->payment_failed_at);
    }

    /** @test */
    public function user_can_invest_with_card_payment()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $round = CgoPricingRound::factory()->create([
            'is_active' => true,
            'share_price' => 100,
            'max_shares_available' => 10000,
        ]);

        // Mock Stripe Session creation
        $mockSession = Mockery::mock('overload:' . Session::class);
        $mockSession->shouldReceive('create')
            ->once()
            ->andReturn((object)[
                'id' => 'cs_test_' . str_repeat('x', 24),
                'url' => 'https://checkout.stripe.com/pay/cs_test_' . str_repeat('x', 24),
            ]);

        $response = $this->post(route('cgo.invest'), [
            'amount' => 1000,
            'payment_method' => 'card',
            'terms' => true,
        ]);

        $response->assertRedirect();
        $response->assertRedirect('https://checkout.stripe.com/pay/cs_test_' . str_repeat('x', 24));

        $this->assertDatabaseHas('cgo_investments', [
            'user_id' => $user->id,
            'amount' => 1000,
            'payment_method' => 'card',
            'payment_status' => 'checkout_created',
        ]);
    }

    /** @test */
    public function it_requires_stripe_configuration()
    {
        config(['cashier.secret' => null]);

        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'payment_method' => 'card',
        ]);

        $this->expectException(\Exception::class);

        $service = new StripePaymentService();
        $service->createCheckoutSession($investment);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}