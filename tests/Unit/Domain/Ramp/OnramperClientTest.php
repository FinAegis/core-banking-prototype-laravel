<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ramp;

use App\Domain\Ramp\Clients\OnramperClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OnramperClientTest extends TestCase
{
    private OnramperClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ramp.providers.onramper.api_key'    => 'pk_test_12345',
            'ramp.providers.onramper.secret_key' => 'sk_test_secret',
            'ramp.providers.onramper.base_url'   => 'https://api.onramper.com',
        ]);

        $this->client = new OnramperClient();
    }

    public function test_constructor_throws_without_api_key(): void
    {
        config(['ramp.providers.onramper.api_key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API key is not configured');

        new OnramperClient();
    }

    public function test_get_quotes_calls_api(): void
    {
        Http::fake([
            'api.onramper.com/quotes/usd/btc*' => Http::response([
                ['provider' => 'Simplex', 'cryptoAmount' => 0.0025, 'fee' => ['fiatFee' => 3.5]],
            ]),
        ]);

        $quotes = $this->client->getQuotes('usd', 'btc', '100.0');

        $this->assertCount(1, $quotes);
        $this->assertEquals('Simplex', $quotes[0]['provider']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/quotes/usd/btc')
                && $request->hasHeader('Authorization', 'pk_test_12345');
        });
    }

    public function test_get_quotes_with_optional_params(): void
    {
        Http::fake([
            'api.onramper.com/quotes/*' => Http::response([]),
        ]);

        $this->client->getQuotes('eur', 'eth', '200.0', 'creditCard', 'DE');

        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'paymentMethod=creditCard')
                && str_contains($url, 'country=DE');
        });
    }

    public function test_get_quotes_throws_on_failure(): void
    {
        Http::fake([
            'api.onramper.com/quotes/*' => Http::response(['message' => 'Invalid pair'], 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Onramper quote request failed');

        $this->client->getQuotes('xxx', 'yyy', '100.0');
    }

    public function test_create_checkout_intent(): void
    {
        Http::fake([
            'api.onramper.com/checkout/intent' => Http::response([
                'transactionId' => 'tx_789',
                'checkoutUrl'   => 'https://onramper.com/checkout?id=tx_789',
            ]),
        ]);

        $result = $this->client->createCheckoutIntent([
            'quoteId'       => 'q_12345',
            'redirectURL'   => 'https://finaegis.org/ramp/complete',
            'walletAddress' => '0xabc',
        ]);

        $this->assertEquals('tx_789', $result['transactionId']);
        $this->assertStringContainsString('checkout', $result['checkoutUrl']);
    }

    public function test_get_transaction(): void
    {
        Http::fake([
            'api.onramper.com/transaction/tx_789' => Http::response([
                'id'           => 'tx_789',
                'status'       => 'completed',
                'fiatAmount'   => '100.0',
                'cryptoAmount' => 0.0025,
            ]),
        ]);

        $tx = $this->client->getTransaction('tx_789');

        $this->assertEquals('completed', $tx['status']);
        $this->assertEquals(0.0025, $tx['cryptoAmount']);
    }

    public function test_get_supported_assets(): void
    {
        Http::fake([
            'api.onramper.com/supported*' => Http::response([
                'crypto' => ['BTC', 'ETH', 'USDC'],
                'fiat'   => ['USD', 'EUR'],
            ]),
        ]);

        $assets = $this->client->getSupportedAssets();

        $this->assertArrayHasKey('crypto', $assets);
        $this->assertArrayHasKey('fiat', $assets);
    }

    public function test_sign_payload(): void
    {
        $payload = 'apiKey=pk_test_12345&walletAddress=0xabc';
        $signature = $this->client->signPayload($payload);

        $expected = hash_hmac('sha256', $payload, 'sk_test_secret');
        $this->assertEquals($expected, $signature);
    }

    public function test_verify_webhook_signature_valid(): void
    {
        $payload = '{"id":"tx_1","status":"completed"}';
        $signature = hash_hmac('sha256', $payload, 'sk_test_secret');

        $this->assertTrue($this->client->verifyWebhookSignature($payload, $signature));
    }

    public function test_verify_webhook_signature_invalid(): void
    {
        $payload = '{"id":"tx_1","status":"completed"}';

        $this->assertFalse($this->client->verifyWebhookSignature($payload, 'invalid_signature'));
    }

    public function test_verify_webhook_signature_rejects_empty_secret(): void
    {
        config(['ramp.providers.onramper.secret_key' => '']);
        $client = new OnramperClient();

        $this->assertFalse($client->verifyWebhookSignature('payload', 'sig'));
    }

    public function test_get_api_key(): void
    {
        $this->assertEquals('pk_test_12345', $this->client->getApiKey());
    }
}
