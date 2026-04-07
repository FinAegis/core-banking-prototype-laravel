<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ramp;

use App\Domain\Ramp\Clients\OnramperClient;
use App\Domain\Ramp\Providers\OnramperProvider;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class OnramperProviderTest extends TestCase
{
    private OnramperClient&MockInterface $client;

    private OnramperProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var OnramperClient&MockInterface $client */
        $client = Mockery::mock(OnramperClient::class);
        $this->client = $client;
        $this->provider = new OnramperProvider($this->client);
    }

    public function test_get_name_returns_onramper(): void
    {
        $this->assertEquals('onramper', $this->provider->getName());
    }

    public function test_get_quotes_returns_all_provider_quotes(): void
    {
        $this->client->shouldReceive('getQuotes')
            ->with('usd', 'btc', '100.0')
            ->once()
            ->andReturn([
                [
                    'provider'     => 'Simplex',
                    'quoteId'      => 'q_12345',
                    'fiatAmount'   => 100,
                    'cryptoAmount' => 0.0025,
                    'fee'          => ['fiatFee' => 3.5, 'networkFee' => 0.5],
                ],
                [
                    'provider'     => 'MoonPay',
                    'quoteId'      => 'q_67890',
                    'fiatAmount'   => 100,
                    'cryptoAmount' => 0.0024,
                    'fee'          => ['fiatFee' => 4.0, 'networkFee' => 0.5],
                ],
            ]);

        $quotes = $this->provider->getQuotes('on', 'USD', '100.0', 'BTC');

        $this->assertCount(2, $quotes);
        $this->assertEquals('Simplex', $quotes[0]['provider_name']);
        $this->assertEquals('q_12345', $quotes[0]['quote_id']);
        $this->assertEquals(100.0, $quotes[0]['fiat_amount']);
        $this->assertEquals(0.0025, $quotes[0]['crypto_amount']);
        $this->assertEquals(3.5, $quotes[0]['fee']);
        $this->assertEquals(0.5, $quotes[0]['network_fee']);
        $this->assertEquals('USD', $quotes[0]['fee_currency']);

        $this->assertEquals('MoonPay', $quotes[1]['provider_name']);
        $this->assertEquals('q_67890', $quotes[1]['quote_id']);
    }

    public function test_get_quotes_off_ramp_swaps_currencies(): void
    {
        $this->client->shouldReceive('getQuotes')
            ->with('btc', 'usd', '0.005')
            ->once()
            ->andReturn([
                [
                    'provider'     => 'Transak',
                    'cryptoAmount' => 195.0,
                    'fee'          => ['fiatFee' => 2.0, 'networkFee' => 0.5],
                ],
            ]);

        $quotes = $this->provider->getQuotes('off', 'USD', '0.005', 'BTC');

        $this->assertCount(1, $quotes);
        $this->assertEquals('Transak', $quotes[0]['provider_name']);
    }

    public function test_get_quotes_returns_empty_when_no_quotes(): void
    {
        $this->client->shouldReceive('getQuotes')
            ->once()
            ->andReturn([]);

        $quotes = $this->provider->getQuotes('on', 'USD', '100.0', 'BTC');

        $this->assertEmpty($quotes);
    }

    public function test_create_session_calls_checkout_intent(): void
    {
        $this->client->shouldReceive('createCheckoutIntent')
            ->once()
            ->withArgs(function (array $params) {
                return $params['quoteId'] === 'q_12345'
                    && $params['walletAddress'] === '0x1234';
            })
            ->andReturn([
                'transactionId' => 'tx_789',
                'checkoutUrl'   => 'https://onramper.com/checkout?id=tx_789',
            ]);

        $result = $this->provider->createSession([
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => '100.0',
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x1234',
            'quote_id'        => 'q_12345',
        ]);

        $this->assertEquals('tx_789', $result['session_id']);
        $this->assertEquals('https://onramper.com/checkout?id=tx_789', $result['checkout_url']);
        $this->assertEquals('onramper', $result['metadata']['provider']);
        $this->assertEquals('q_12345', $result['metadata']['quote_id']);
    }

    public function test_create_session_throws_without_quote_id(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('quote_id is required');

        $this->provider->createSession([
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => '100.0',
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x1234',
            'quote_id'        => null,
        ]);
    }

    public function test_get_session_status_maps_completed(): void
    {
        $this->client->shouldReceive('getTransaction')
            ->with('tx_123')
            ->once()
            ->andReturn([
                'id'            => 'tx_123',
                'status'        => 'completed',
                'fiatAmount'    => 100.0,
                'cryptoAmount'  => 98.5,
                'paymentMethod' => 'creditCard',
                'provider'      => 'Simplex',
            ]);

        $result = $this->provider->getSessionStatus('tx_123');

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(100.0, $result['fiat_amount']);
        $this->assertEquals(98.5, $result['crypto_amount']);
        $this->assertEquals('Simplex', $result['metadata']['onramp_provider']);
    }

    public function test_get_session_status_maps_failed(): void
    {
        $this->client->shouldReceive('getTransaction')
            ->with('tx_456')
            ->once()
            ->andReturn(['status' => 'failed']);

        $result = $this->provider->getSessionStatus('tx_456');

        $this->assertEquals('failed', $result['status']);
    }

    public function test_get_session_status_returns_pending_on_error(): void
    {
        $this->client->shouldReceive('getTransaction')
            ->with('tx_notfound')
            ->once()
            ->andThrow(new RuntimeException('Transaction not found'));

        $result = $this->provider->getSessionStatus('tx_notfound');

        $this->assertEquals('pending', $result['status']);
    }

    public function test_get_supported_currencies_returns_pairs(): void
    {
        $currencies = $this->provider->getSupportedCurrencies();

        $this->assertNotEmpty($currencies);
        $this->assertArrayHasKey('fiat', $currencies[0]);
        $this->assertArrayHasKey('crypto', $currencies[0]);
    }

    public function test_webhook_validator_delegates_to_client(): void
    {
        $this->client->shouldReceive('verifyWebhookSignature')
            ->with('{"id":"tx_1"}', 'valid_sig')
            ->once()
            ->andReturn(true);

        $validator = $this->provider->getWebhookValidator();

        $this->assertTrue($validator('{"id":"tx_1"}', 'valid_sig'));
    }

    public function test_webhook_validator_rejects_invalid_signature(): void
    {
        $this->client->shouldReceive('verifyWebhookSignature')
            ->with('{"id":"tx_1"}', 'bad_sig')
            ->once()
            ->andReturn(false);

        $validator = $this->provider->getWebhookValidator();

        $this->assertFalse($validator('{"id":"tx_1"}', 'bad_sig'));
    }
}
