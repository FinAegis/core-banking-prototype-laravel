<?php

namespace Tests\Unit\Services\Cgo;

use App\Models\CgoInvestment;
use App\Services\Cgo\CoinbaseCommerceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CoinbaseCommerceServiceTest extends TestCase
{
    private CoinbaseCommerceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        config([
            'services.coinbase_commerce.api_key' => 'test-api-key',
            'services.coinbase_commerce.webhook_secret' => 'test-webhook-secret',
        ]);
        
        $this->service = new CoinbaseCommerceService();
    }

    public function test_create_charge_sends_correct_request()
    {
        $investment = CgoInvestment::factory()->create([
            'uuid' => 'test-uuid',
            'amount' => 1000,
            'tier' => 'silver',
        ]);

        Http::fake([
            'https://api.commerce.coinbase.com/charges' => Http::response([
                'data' => [
                    'id' => 'test-charge-id',
                    'code' => 'TEST123',
                    'hosted_url' => 'https://commerce.coinbase.com/charges/TEST123',
                    'pricing' => [
                        'local' => [
                            'amount' => '1000.00',
                            'currency' => 'USD',
                        ],
                    ],
                ],
            ], 201),
        ]);

        $charge = $this->service->createCharge($investment);

        Http::assertSent(function ($request) use ($investment) {
            $body = json_decode($request->body(), true);
            return $request->url() === 'https://api.commerce.coinbase.com/charges' &&
                   $request->hasHeader('X-CC-Api-Key', 'test-api-key') &&
                   $request->hasHeader('X-CC-Version', '2018-03-22') &&
                   $body['name'] === 'CGO Investment - Silver' &&
                   $body['pricing_type'] === 'fixed_price' &&
                   $body['local_price']['amount'] === '1000' &&
                   $body['metadata']['investment_uuid'] === $investment->uuid;
        });

        $this->assertEquals('test-charge-id', $charge['id']);
        $this->assertEquals('TEST123', $charge['code']);
        $this->assertEquals('https://commerce.coinbase.com/charges/TEST123', $charge['hosted_url']);
    }

    public function test_create_charge_throws_exception_on_api_error()
    {
        $investment = CgoInvestment::factory()->create();

        Http::fake([
            'https://api.commerce.coinbase.com/charges' => Http::response([
                'error' => [
                    'type' => 'invalid_request',
                    'message' => 'Invalid API key',
                ],
            ], 401),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create Coinbase Commerce charge');
        $this->service->createCharge($investment);
    }

    public function test_verify_webhook_signature_with_valid_signature()
    {
        $payload = '{"event":{"type":"charge:confirmed"}}';
        $secret = 'test-webhook-secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $result = $this->service->verifyWebhookSignature($payload, $signature);
        $this->assertTrue($result);
    }

    public function test_verify_webhook_signature_with_invalid_signature()
    {
        $payload = '{"event":{"type":"charge:confirmed"}}';
        $signature = 'invalid-signature';

        $result = $this->service->verifyWebhookSignature($payload, $signature);
        $this->assertFalse($result);
    }

    public function test_process_webhook_event_handles_charge_confirmed()
    {
        $investment = CgoInvestment::factory()->create([
            'coinbase_charge_id' => 'test-charge-id',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $event = [
            'type' => 'charge:confirmed',
            'data' => [
                'id' => 'test-charge-id',
                'code' => 'TEST123',
                'payments' => [
                    [
                        'status' => 'CONFIRMED',
                        'value' => [
                            'local' => [
                                'amount' => '1000.00',
                                'currency' => 'USD',
                            ],
                            'crypto' => [
                                'amount' => '0.025',
                                'currency' => 'BTC',
                            ],
                        ],
                        'transaction_id' => 'tx_test_123',
                    ],
                ],
            ],
        ];

        $this->service->processWebhookEvent($event);

        $investment->refresh();
        $this->assertEquals('confirmed', $investment->status);
        $this->assertEquals('confirmed', $investment->payment_status);
        $this->assertNotNull($investment->payment_completed_at);
    }

    public function test_process_webhook_event_handles_charge_failed()
    {
        $investment = CgoInvestment::factory()->create([
            'coinbase_charge_id' => 'test-charge-id',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $event = [
            'type' => 'charge:failed',
            'data' => [
                'id' => 'test-charge-id',
                'code' => 'TEST123',
            ],
        ];

        $this->service->processWebhookEvent($event);

        $investment->refresh();
        $this->assertEquals('failed', $investment->payment_status);
        $this->assertNotNull($investment->payment_failed_at);
        $this->assertEquals('Payment expired or cancelled', $investment->payment_failure_reason);
    }

    public function test_process_webhook_event_logs_warning_for_unknown_charge()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Processing Coinbase Commerce webhook', \Mockery::any());
            
        Log::shouldReceive('warning')
            ->once()
            ->with('Coinbase webhook: Investment not found', ['charge_id' => 'unknown-charge-id']);

        $event = [
            'type' => 'charge:confirmed',
            'data' => [
                'id' => 'unknown-charge-id',
            ],
        ];

        $this->service->processWebhookEvent($event);
    }

    public function test_process_webhook_event_handles_charge_pending()
    {
        $investment = CgoInvestment::factory()->create([
            'coinbase_charge_id' => 'test-charge-id',
            'payment_status' => 'pending',
        ]);
        
        Log::shouldReceive('info')
            ->once()
            ->with('Processing Coinbase Commerce webhook', \Mockery::any());
            
        Log::shouldReceive('info')
            ->once()
            ->with('Coinbase charge pending', \Mockery::any());

        $event = [
            'type' => 'charge:pending',
            'data' => [
                'id' => 'test-charge-id',
                'code' => 'TEST123',
                'payments' => [
                    [
                        'value' => [
                            'crypto' => [
                                'amount' => '0.001234',
                                'currency' => 'BTC',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->service->processWebhookEvent($event);

        $investment->refresh();
        $this->assertEquals('pending', $investment->payment_status);
        $this->assertNotNull($investment->payment_pending_at);
        $this->assertEquals('0.001234', $investment->crypto_amount_paid);
        $this->assertEquals('BTC', $investment->crypto_currency_paid);
    }
}