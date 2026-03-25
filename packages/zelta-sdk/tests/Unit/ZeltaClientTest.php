<?php

declare(strict_types=1);

namespace Zelta\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Zelta\Contracts\SignerInterface;
use Zelta\DataObjects\PaymentConfig;
use Zelta\Exceptions\PaymentRequiredException;
use Zelta\Handlers\X402PaymentHandler;
use Zelta\ZeltaClient;

class ZeltaClientTest extends TestCase
{
    private function createSigner(): SignerInterface
    {
        return new class implements SignerInterface {
            public function sign(string $network, string $to, string $amount, string $asset, int $timeout, array $extra = []): array
            {
                return [
                    'signature' => 'test-sig',
                    'authorization' => ['from' => '0xtest', 'to' => $to, 'value' => $amount],
                ];
            }

            public function getAddress(): string
            {
                return '0xTestAddress';
            }
        };
    }

    private function createClientWithMock(MockHandler $mock, ?X402PaymentHandler $handler = null, bool $autoPay = true): ZeltaClient
    {
        $handlerStack = HandlerStack::create($mock);
        $config = new PaymentConfig(
            baseUrl: 'https://api.test',
            apiKey: 'zk_test_123',
            autoPay: $autoPay,
        );

        $client = new ZeltaClient(config: $config, payment: $handler);

        // Inject mock HTTP client via reflection
        $reflection = new \ReflectionClass($client);
        $prop = $reflection->getProperty('http');
        $prop->setValue($client, new Client(['handler' => $handlerStack, 'http_errors' => false]));

        return $client;
    }

    public function test_successful_request_without_payment(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => 'success'])),
        ]);

        $client = $this->createClientWithMock($mock);
        $result = $client->get('/v1/test');

        $this->assertEquals(200, $result->statusCode);
        $this->assertEquals(['data' => 'success'], $result->body);
        $this->assertFalse($result->paid);
    }

    public function test_402_without_handler_throws(): void
    {
        $mock = new MockHandler([
            new Response(402, [], json_encode(['error' => 'payment_required'])),
        ]);

        $client = $this->createClientWithMock($mock);

        $this->expectException(PaymentRequiredException::class);
        $client->get('/v1/premium');
    }

    public function test_402_with_auto_pay_disabled_throws(): void
    {
        $paymentRequired = base64_encode((string) json_encode([
            'x402Version' => 2,
            'accepts' => [['network' => 'eip155:8453', 'payTo' => '0xR', 'amount' => '100', 'asset' => '0xU']],
        ]));

        $mock = new MockHandler([
            new Response(402, [
                'X-Payment-Protocol' => 'x402',
                'PAYMENT-REQUIRED' => $paymentRequired,
            ]),
        ]);

        $handler = new X402PaymentHandler($this->createSigner());
        $client = $this->createClientWithMock($mock, $handler, autoPay: false);

        $this->expectException(PaymentRequiredException::class);
        $client->get('/v1/premium');
    }

    public function test_402_auto_pay_retries_and_succeeds(): void
    {
        $paymentRequired = base64_encode((string) json_encode([
            'x402Version' => 2,
            'resource' => ['url' => '/v1/premium'],
            'accepts' => [[
                'network' => 'eip155:8453',
                'payTo' => '0xRecipient',
                'amount' => '100000',
                'asset' => '0xUSDC',
                'maxTimeoutSeconds' => 60,
            ]],
        ]));

        $mock = new MockHandler([
            // First request: 402
            new Response(402, [
                'X-Payment-Protocol' => 'x402',
                'PAYMENT-REQUIRED' => $paymentRequired,
            ]),
            // Retry with payment: 200
            new Response(200, [], json_encode(['data' => 'premium_content'])),
        ]);

        $handler = new X402PaymentHandler($this->createSigner());
        $client = $this->createClientWithMock($mock, $handler);

        $result = $client->get('/v1/premium');

        $this->assertEquals(200, $result->statusCode);
        $this->assertEquals(['data' => 'premium_content'], $result->body);
        $this->assertTrue($result->paid);
        $this->assertArrayHasKey('PAYMENT-SIGNATURE', $result->paymentMeta);
    }

    public function test_post_request_works(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => '123'])),
        ]);

        $client = $this->createClientWithMock($mock);
        $result = $client->post('/v1/resource', ['json' => ['name' => 'test']]);

        $this->assertEquals(201, $result->statusCode);
        $this->assertEquals(['id' => '123'], $result->body);
    }
}
