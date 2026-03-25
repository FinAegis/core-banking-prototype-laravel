<?php

declare(strict_types=1);

namespace Zelta\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Zelta\Contracts\SignerInterface;
use Zelta\Handlers\X402PaymentHandler;

class X402PaymentHandlerTest extends TestCase
{
    private function createSigner(): SignerInterface
    {
        return new class implements SignerInterface {
            public function sign(string $network, string $to, string $amount, string $asset, int $timeout, array $extra = []): array
            {
                return [
                    'signature' => 'test-signature-' . $network,
                    'authorization' => ['from' => '0xtest', 'to' => $to, 'value' => $amount],
                ];
            }

            public function getAddress(): string
            {
                return '0xTestAddress';
            }
        };
    }

    public function test_can_handle_x402_response(): void
    {
        $handler = new X402PaymentHandler($this->createSigner());

        $response = new Response(402, [
            'X-Payment-Protocol' => 'x402',
            'PAYMENT-REQUIRED' => base64_encode(json_encode([
                'x402Version' => 2,
                'accepts' => [['network' => 'eip155:8453', 'payTo' => '0xrecipient', 'amount' => '1000', 'asset' => '0xUSDC']],
            ])),
        ]);

        $this->assertTrue($handler->canHandle($response));
    }

    public function test_cannot_handle_non_402(): void
    {
        $handler = new X402PaymentHandler($this->createSigner());
        $response = new Response(200);

        $this->assertFalse($handler->canHandle($response));
    }

    public function test_cannot_handle_mpp_response(): void
    {
        $handler = new X402PaymentHandler($this->createSigner());
        $response = new Response(402, ['WWW-Authenticate' => 'Payment base64data']);

        $this->assertFalse($handler->canHandle($response));
    }

    public function test_produces_payment_signature_header(): void
    {
        $handler = new X402PaymentHandler($this->createSigner());

        $paymentRequired = base64_encode((string) json_encode([
            'x402Version' => 2,
            'resource' => ['url' => 'https://api.test/data'],
            'accepts' => [[
                'network' => 'eip155:8453',
                'scheme' => 'exact',
                'payTo' => '0xRecipient',
                'amount' => '100000',
                'asset' => '0xUSDC',
                'maxTimeoutSeconds' => 60,
                'extra' => [],
            ]],
        ]));

        $response = new Response(402, [
            'X-Payment-Protocol' => 'x402',
            'PAYMENT-REQUIRED' => $paymentRequired,
        ]);

        $headers = $handler->handlePaymentRequired($response, '/data');

        $this->assertArrayHasKey('PAYMENT-SIGNATURE', $headers);

        // Decode and verify
        $payload = json_decode(base64_decode($headers['PAYMENT-SIGNATURE']), true);
        $this->assertEquals(2, $payload['x402Version']);
        $this->assertArrayHasKey('payload', $payload);
        $this->assertEquals('test-signature-eip155:8453', $payload['payload']['signature']);
    }

    public function test_prefers_configured_network(): void
    {
        $handler = new X402PaymentHandler(
            $this->createSigner(),
            preferredNetworks: ['solana:mainnet', 'eip155:8453'],
        );

        $paymentRequired = base64_encode((string) json_encode([
            'x402Version' => 2,
            'accepts' => [
                ['network' => 'eip155:8453', 'payTo' => '0xR', 'amount' => '100', 'asset' => '0xU'],
                ['network' => 'solana:mainnet', 'payTo' => 'SolR', 'amount' => '100', 'asset' => 'USDC'],
            ],
        ]));

        $response = new Response(402, [
            'X-Payment-Protocol' => 'x402',
            'PAYMENT-REQUIRED' => $paymentRequired,
        ]);

        $headers = $handler->handlePaymentRequired($response, '/data');
        $payload = json_decode(base64_decode($headers['PAYMENT-SIGNATURE']), true);

        $this->assertEquals('solana:mainnet', $payload['accepted']['network']);
    }

    public function test_returns_empty_for_invalid_header(): void
    {
        $handler = new X402PaymentHandler($this->createSigner());
        $response = new Response(402, [
            'X-Payment-Protocol' => 'x402',
            'PAYMENT-REQUIRED' => 'not-valid-base64!!!',
        ]);

        $headers = $handler->handlePaymentRequired($response, '/data');
        $this->assertEmpty($headers);
    }
}
