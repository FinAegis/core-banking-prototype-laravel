<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Services;

use App\Domain\Privacy\Services\RailgunBridgeClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RailgunBridgeClientTest extends TestCase
{
    private RailgunBridgeClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new RailgunBridgeClient(
            baseUrl: 'http://127.0.0.1:3100',
            secret: 'test-secret',
            timeout: 10,
        );
    }

    public function test_create_wallet(): void
    {
        Http::fake([
            '127.0.0.1:3100/wallet/create' => Http::response([
                'success' => true,
                'data'    => [
                    'wallet_id'       => 'wallet-123',
                    'railgun_address' => '0zk1234567890abcdef',
                ],
            ]),
        ]);

        $result = $this->client->createWallet('wallet-123', 'test-mnemonic', 'enc-key');

        $this->assertEquals('wallet-123', $result['wallet_id']);
        $this->assertEquals('0zk1234567890abcdef', $result['railgun_address']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-secret')
                && $request->url() === 'http://127.0.0.1:3100/wallet/create'
                && $request['walletId'] === 'wallet-123';
        });
    }

    public function test_get_balances(): void
    {
        Http::fake([
            '127.0.0.1:3100/wallet/w1/balances*' => Http::response([
                'success' => true,
                'data'    => [
                    'wallet_id' => 'w1',
                    'network'   => 'polygon',
                    'balances'  => [
                        '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359' => '100000000',
                    ],
                ],
            ]),
        ]);

        $result = $this->client->getBalances('w1', 'polygon');

        $this->assertEquals('polygon', $result['network']);
        $this->assertArrayHasKey('balances', $result);
    }

    public function test_scan_wallet(): void
    {
        Http::fake([
            '127.0.0.1:3100/wallet/scan' => Http::response([
                'success' => true,
                'data'    => [
                    'wallet_id' => 'w1',
                    'network'   => 'polygon',
                    'status'    => 'scan_initiated',
                ],
            ]),
        ]);

        $result = $this->client->scanWallet('w1', 'polygon');

        $this->assertEquals('scan_initiated', $result['status']);
    }

    public function test_shield(): void
    {
        Http::fake([
            '127.0.0.1:3100/shield' => Http::response([
                'success' => true,
                'data'    => [
                    'transaction'  => ['to' => '0xabc', 'data' => '0x123', 'value' => '0'],
                    'gas_estimate' => '150000',
                    'nullifiers'   => [],
                    'network'      => 'polygon',
                ],
            ]),
        ]);

        $result = $this->client->shield('w1', '0xtoken', '1000000', 'polygon');

        $this->assertArrayHasKey('transaction', $result);
        $this->assertEquals('polygon', $result['network']);
        $this->assertEquals('150000', $result['gas_estimate']);
    }

    public function test_unshield(): void
    {
        Http::fake([
            '127.0.0.1:3100/unshield' => Http::response([
                'success' => true,
                'data'    => [
                    'transaction' => ['to' => '0xabc', 'data' => '0x456', 'value' => '0'],
                    'nullifiers'  => ['0xnull1'],
                    'network'     => 'arbitrum',
                ],
            ]),
        ]);

        $result = $this->client->unshield('w1', 'enc-key', '0xrecipient', '0xtoken', '500000', 'arbitrum');

        $this->assertArrayHasKey('transaction', $result);
        $this->assertEquals('arbitrum', $result['network']);
    }

    public function test_private_transfer(): void
    {
        Http::fake([
            '127.0.0.1:3100/transfer' => Http::response([
                'success' => true,
                'data'    => [
                    'transaction' => ['to' => '0xabc', 'data' => '0x789', 'value' => '0'],
                    'nullifiers'  => ['0xnull2'],
                    'network'     => 'ethereum',
                ],
            ]),
        ]);

        $result = $this->client->privateTransfer('w1', 'enc-key', '0zk_recipient', '0xtoken', '250000', 'ethereum');

        $this->assertArrayHasKey('transaction', $result);
        $this->assertEquals('ethereum', $result['network']);
    }

    public function test_get_merkle_root(): void
    {
        Http::fake([
            '127.0.0.1:3100/merkle/root/polygon' => Http::response([
                'success' => true,
                'data'    => [
                    'root'       => '0xabc123',
                    'network'    => 'polygon',
                    'leaf_count' => 42000,
                    'tree_depth' => 32,
                    'synced_at'  => '2026-02-27T12:00:00Z',
                ],
            ]),
        ]);

        $result = $this->client->getMerkleRoot('polygon');

        $this->assertEquals('0xabc123', $result['root']);
        $this->assertEquals(42000, $result['leaf_count']);
    }

    public function test_get_merkle_proof(): void
    {
        Http::fake([
            '127.0.0.1:3100/merkle/proof/0xcommit*' => Http::response([
                'success' => true,
                'data'    => [
                    'commitment' => '0xcommit',
                    'root'       => '0xroot',
                    'network'    => 'polygon',
                    'tree_depth' => 32,
                    'verified'   => true,
                ],
            ]),
        ]);

        $result = $this->client->getMerkleProof('0xcommit', 'polygon');

        $this->assertTrue($result['verified']);
        $this->assertEquals('0xcommit', $result['commitment']);
    }

    public function test_health(): void
    {
        Http::fake([
            '127.0.0.1:3100/health' => Http::response([
                'success' => true,
                'data'    => [
                    'status'             => 'healthy',
                    'engine_ready'       => true,
                    'supported_networks' => ['ethereum', 'polygon', 'arbitrum', 'bsc'],
                    'loaded_networks'    => ['polygon', 'arbitrum'],
                ],
            ]),
        ]);

        $result = $this->client->health();

        $this->assertEquals('healthy', $result['status']);
        $this->assertTrue($result['engine_ready']);
    }

    public function test_is_healthy_returns_true_when_engine_ready(): void
    {
        Http::fake([
            '127.0.0.1:3100/health' => Http::response([
                'success' => true,
                'data'    => ['engine_ready' => true],
            ]),
        ]);

        $this->assertTrue($this->client->isHealthy());
    }

    public function test_is_healthy_returns_false_on_connection_failure(): void
    {
        Http::fake([
            '127.0.0.1:3100/health' => Http::response([], 500),
        ]);

        $this->assertFalse($this->client->isHealthy());
    }

    public function test_throws_on_bridge_error_response(): void
    {
        Http::fake([
            '127.0.0.1:3100/wallet/create' => Http::response([
                'success' => false,
                'error'   => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid mnemonic'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RAILGUN bridge error: Invalid mnemonic');

        $this->client->createWallet('w1', 'bad-mnemonic', 'enc-key');
    }

    public function test_throws_on_http_error(): void
    {
        Http::fake([
            '127.0.0.1:3100/wallet/create' => Http::response('Server Error', 500),
        ]);

        $this->expectException(RuntimeException::class);

        $this->client->createWallet('w1', 'test', 'enc-key');
    }
}
