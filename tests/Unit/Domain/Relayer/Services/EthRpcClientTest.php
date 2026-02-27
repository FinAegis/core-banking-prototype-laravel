<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\Services\EthRpcClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EthRpcClientTest extends TestCase
{
    private EthRpcClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'relayer.networks.polygon.rpc_url' => 'https://polygon-rpc.example.com',
            'relayer.pimlico.api_key'          => 'test-api-key',
            'relayer.pimlico.bundler_url'      => 'https://api.pimlico.io/v2/137/rpc',
            'relayer.pimlico.timeout'          => 5,
            'relayer.pimlico.retry_count'      => 3,
        ]);

        $this->client = new EthRpcClient();
    }

    public function test_makes_successful_rpc_call(): void
    {
        Http::fake([
            'polygon-rpc.example.com' => Http::response([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'result'  => '0x1234',
            ]),
        ]);

        $result = $this->client->call(SupportedNetwork::POLYGON, 'eth_blockNumber');

        $this->assertEquals('0x1234', $result);
        Http::assertSentCount(1);
    }

    public function test_retries_on_http_failure(): void
    {
        Http::fake([
            'polygon-rpc.example.com' => Http::sequence()
                ->push(null, 500)
                ->push(null, 500)
                ->push([
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'result'  => '0xabc',
                ]),
        ]);

        $result = $this->client->call(SupportedNetwork::POLYGON, 'eth_gasPrice');

        $this->assertEquals('0xabc', $result);
        Http::assertSentCount(3);
    }

    public function test_throws_rpc_exception_on_rpc_error(): void
    {
        Http::fake([
            'polygon-rpc.example.com' => Http::response([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'error'   => [
                    'code'    => -32601,
                    'message' => 'Method not found',
                ],
            ]),
        ]);

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Method not found');

        $this->client->call(SupportedNetwork::POLYGON, 'invalid_method');
    }

    public function test_does_not_retry_on_rpc_error(): void
    {
        Http::fake([
            'polygon-rpc.example.com' => Http::response([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'error'   => [
                    'code'    => -32602,
                    'message' => 'Invalid params',
                ],
            ]),
        ]);

        try {
            $this->client->call(SupportedNetwork::POLYGON, 'eth_call', []);
        } catch (RpcException $e) {
            $this->assertEquals('eth_call', $e->rpcMethod);
            $this->assertEquals(-32602, $e->rpcErrorCode);
        }

        // Should only have been called once (no retries for RPC errors)
        Http::assertSentCount(1);
    }

    public function test_throws_after_all_retries_exhausted(): void
    {
        Http::fake([
            'polygon-rpc.example.com' => Http::response(null, 500),
        ]);

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('RPC connection failed');

        $this->client->call(SupportedNetwork::POLYGON, 'eth_blockNumber');

        Http::assertSentCount(3);
    }

    public function test_throws_when_no_rpc_url_configured(): void
    {
        config(['relayer.networks.polygon.rpc_url' => '']);

        $client = new EthRpcClient();

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('No RPC URL configured');

        $client->call(SupportedNetwork::POLYGON, 'eth_blockNumber');
    }

    public function test_bundler_call_uses_pimlico_endpoint(): void
    {
        Http::fake([
            'api.pimlico.io/*' => Http::response([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'result'  => '0xuserhash123',
            ]),
        ]);

        $result = $this->client->bundlerCall(
            SupportedNetwork::POLYGON,
            'eth_sendUserOperation',
            [['sender' => '0x123'], '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789']
        );

        $this->assertEquals('0xuserhash123', $result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'apikey=test-api-key');
        });
    }

    public function test_bundler_call_throws_without_api_key(): void
    {
        config(['relayer.pimlico.api_key' => '']);
        $client = new EthRpcClient();

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('PIMLICO_API_KEY not configured');

        $client->bundlerCall(SupportedNetwork::POLYGON, 'eth_sendUserOperation');
    }

    public function test_get_block_number(): void
    {
        Http::fake([
            'polygon-rpc.example.com' => Http::response([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'result'  => '0x3456789',
            ]),
        ]);

        $blockNumber = $this->client->getBlockNumber(SupportedNetwork::POLYGON);

        $this->assertEquals(0x3456789, $blockNumber);
    }

    public function test_get_code(): void
    {
        Http::fake([
            'polygon-rpc.example.com' => Http::response([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'result'  => '0x6080604052',
            ]),
        ]);

        $code = $this->client->getCode(
            SupportedNetwork::POLYGON,
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'
        );

        $this->assertEquals('0x6080604052', $code);
    }

    public function test_get_gas_price(): void
    {
        Http::fake([
            'polygon-rpc.example.com' => Http::response([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'result'  => '0x6fc23ac00',
            ]),
        ]);

        $gasPrice = $this->client->getGasPrice(SupportedNetwork::POLYGON);

        $this->assertEquals('0x6fc23ac00', $gasPrice);
    }

    public function test_rpc_exception_has_structured_context(): void
    {
        $exception = RpcException::fromRpcError('eth_call', [
            'code'    => -32000,
            'message' => 'execution reverted',
            'data'    => '0x08c379a0...',
        ]);

        $this->assertEquals('eth_call', $exception->rpcMethod);
        $this->assertEquals(-32000, $exception->rpcErrorCode);
        $this->assertEquals('0x08c379a0...', $exception->rpcErrorData);

        $context = $exception->context();
        $this->assertArrayHasKey('rpc_method', $context);
        $this->assertArrayHasKey('rpc_error_code', $context);
        $this->assertArrayHasKey('rpc_error_data', $context);
    }
}
