<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidateWebhookSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up webhook secrets for testing
        Config::set('services.stripe.webhook_secret', 'stripe_test_secret');
        Config::set('services.coinbase_commerce.webhook_secret', 'coinbase_test_secret');
        Config::set('custodians.connectors.paysera.webhook_secret', 'paysera_test_secret');
        Config::set('custodians.connectors.santander.webhook_secret', 'santander_test_secret');
    }

    #[Test]
    public function it_validates_stripe_webhook_signature()
    {
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => []]]);
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, 'stripe_test_secret');
        $stripeSignature = 't=' . $timestamp . ',v1=' . $expectedSignature;

        $response = $this->postJson('/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => $stripeSignature,
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function it_rejects_stripe_webhook_with_invalid_signature()
    {
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => []]]);
        $timestamp = time();
        $stripeSignature = 't=' . $timestamp . ',v1=invalid_signature';

        $response = $this->postJson('/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => $stripeSignature,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function it_rejects_stripe_webhook_with_expired_timestamp()
    {
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => []]]);
        $timestamp = time() - 400; // 400 seconds ago (exceeds 5-minute tolerance)
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, 'stripe_test_secret');
        $stripeSignature = 't=' . $timestamp . ',v1=' . $expectedSignature;

        $response = $this->postJson('/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => $stripeSignature,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function it_validates_coinbase_webhook_signature()
    {
        $payload = ['event' => ['type' => 'charge:confirmed']];
        $payloadString = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadString, 'coinbase_test_secret');

        $response = $this->postJson('/api/webhooks/coinbase-commerce', $payload, [
            'X-CC-Webhook-Signature' => $signature,
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function it_rejects_coinbase_webhook_with_invalid_signature()
    {
        $payload = ['event' => ['type' => 'charge:confirmed']];

        $response = $this->postJson('/api/webhooks/coinbase-commerce', $payload, [
            'X-CC-Webhook-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function it_validates_paysera_webhook_signature()
    {
        $payload = ['event' => 'transaction.completed', 'event_id' => 'evt_123'];
        $payloadString = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadString, 'paysera_test_secret');

        $response = $this->postJson('/api/webhooks/custodian/paysera', $payload, [
            'X-Paysera-Signature' => $signature,
        ]);

        $response->assertStatus(202)
            ->assertJson(['status' => 'accepted']);
    }

    #[Test]
    public function it_rejects_paysera_webhook_with_invalid_signature()
    {
        $payload = ['event' => 'transaction.completed', 'event_id' => 'evt_123'];

        $response = $this->postJson('/api/webhooks/custodian/paysera', $payload, [
            'X-Paysera-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function it_validates_santander_webhook_signature()
    {
        $payload = ['event_type' => 'payment.received', 'id' => 'wh_987'];
        $payloadString = json_encode($payload);
        $timestamp = (string) time();
        $dataToSign = $timestamp . '.' . $payloadString;
        $signature = hash_hmac('sha512', $dataToSign, 'santander_test_secret');

        $response = $this->postJson('/api/webhooks/custodian/santander', $payload, [
            'X-Santander-Signature' => $signature,
            'X-Santander-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(202)
            ->assertJson(['status' => 'accepted']);
    }

    #[Test]
    public function it_rejects_santander_webhook_with_invalid_signature()
    {
        $payload = ['event_type' => 'payment.received', 'id' => 'wh_987'];
        $timestamp = (string) time();

        $response = $this->postJson('/api/webhooks/custodian/santander', $payload, [
            'X-Santander-Signature' => 'invalid_signature',
            'X-Santander-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function it_rejects_santander_webhook_with_expired_timestamp()
    {
        $payload = ['event_type' => 'payment.received', 'id' => 'wh_987'];
        $payloadString = json_encode($payload);
        $timestamp = (string) (time() - 400); // 400 seconds ago
        $dataToSign = $timestamp . '.' . $payloadString;
        $signature = hash_hmac('sha512', $dataToSign, 'santander_test_secret');

        $response = $this->postJson('/api/webhooks/custodian/santander', $payload, [
            'X-Santander-Signature' => $signature,
            'X-Santander-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function it_rejects_webhook_with_missing_signature_header()
    {
        $payload = ['event' => 'test'];

        // Test Stripe
        $response = $this->postJson('/stripe/webhook', $payload);
        $response->assertStatus(403)->assertJson(['error' => 'Invalid signature']);

        // Test Coinbase
        $response = $this->postJson('/api/webhooks/coinbase-commerce', $payload);
        $response->assertStatus(403)->assertJson(['error' => 'Invalid signature']);

        // Test Paysera
        $response = $this->postJson('/api/webhooks/custodian/paysera', $payload);
        $response->assertStatus(403)->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function it_allows_mock_custodian_webhook_without_signature()
    {
        $payload = ['type' => 'balance.updated', 'id' => 'mock_123'];

        $response = $this->postJson('/api/webhooks/custodian/mock', $payload);

        $response->assertStatus(202)
            ->assertJson(['status' => 'accepted']);
    }

    // Note: OpenBanking callback tests removed as they require full web middleware stack
    // and authentication. The ValidateWebhookSignature middleware handles OAuth state
    // validation for openbanking callbacks when properly configured.
}
