<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reusable JSON-RPC client for Ethereum nodes and ERC-4337 bundlers.
 *
 * Supports both standard Ethereum RPC and bundler-specific endpoints (Pimlico v2).
 * Includes retry logic with configurable attempts and delay.
 */
class EthRpcClient
{
    private int $timeout;

    private int $retryCount;

    private int $retryDelayMs;

    public function __construct()
    {
        $this->timeout = (int) config('relayer.pimlico.timeout', 15);
        $this->retryCount = (int) config('relayer.pimlico.retry_count', 3);
        $this->retryDelayMs = 200;
    }

    /**
     * Make a JSON-RPC call to an Ethereum node.
     *
     * @param  array<int, mixed>  $params
     * @return mixed The 'result' field from the JSON-RPC response
     *
     * @throws RpcException
     */
    public function call(SupportedNetwork $network, string $method, array $params = []): mixed
    {
        $rpcUrl = $network->getRpcUrl();
        if (empty($rpcUrl)) {
            throw RpcException::connectionFailed($method, "No RPC URL configured for {$network->value}");
        }

        return $this->executeWithRetry($rpcUrl, $method, $params);
    }

    /**
     * Make a JSON-RPC call to a bundler endpoint (Pimlico v2).
     *
     * @param  array<int, mixed>  $params
     * @return mixed The 'result' field from the JSON-RPC response
     *
     * @throws RpcException
     */
    public function bundlerCall(SupportedNetwork $network, string $method, array $params = []): mixed
    {
        $apiKey = config('relayer.pimlico.api_key');
        if (empty($apiKey)) {
            throw RpcException::connectionFailed($method, 'PIMLICO_API_KEY not configured');
        }

        $chainId = $network->getChainId();
        $baseUrl = config('relayer.pimlico.bundler_url', "https://api.pimlico.io/v2/{$chainId}/rpc");
        $url = $baseUrl . '?apikey=' . $apiKey;

        return $this->executeWithRetry($url, $method, $params);
    }

    /**
     * Get the current block number for a network.
     *
     * @throws RpcException
     */
    public function getBlockNumber(SupportedNetwork $network): int
    {
        $result = $this->call($network, 'eth_blockNumber');

        return (int) hexdec((string) $result);
    }

    /**
     * Get the deployed bytecode at an address (empty = not deployed).
     *
     * @throws RpcException
     */
    public function getCode(SupportedNetwork $network, string $address): string
    {
        return (string) $this->call($network, 'eth_getCode', [$address, 'latest']);
    }

    /**
     * Get the current gas price in wei (hex).
     *
     * @throws RpcException
     */
    public function getGasPrice(SupportedNetwork $network): string
    {
        return (string) $this->call($network, 'eth_gasPrice');
    }

    /**
     * Get the max priority fee per gas (EIP-1559).
     *
     * @throws RpcException
     */
    public function getMaxPriorityFeePerGas(SupportedNetwork $network): string
    {
        return (string) $this->call($network, 'eth_maxPriorityFeePerGas');
    }

    /**
     * Make an eth_call to a contract.
     *
     * @param  array<string, string>  $callObject  {to, data, from?}
     *
     * @throws RpcException
     */
    public function ethCall(SupportedNetwork $network, array $callObject, string $block = 'latest'): string
    {
        return (string) $this->call($network, 'eth_call', [$callObject, $block]);
    }

    /**
     * Execute a JSON-RPC call with retry logic.
     *
     * @param  array<int, mixed>  $params
     *
     * @throws RpcException
     */
    private function executeWithRetry(string $url, string $method, array $params): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => 1,
        ];

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryCount; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->post($url, $payload)
                    ->throw()
                    ->json();

                if (isset($response['error'])) {
                    throw RpcException::fromRpcError($method, $response['error']);
                }

                return $response['result'] ?? null;
            } catch (RpcException $e) {
                // Don't retry RPC-level errors (invalid params, etc.)
                throw $e;
            } catch (Throwable $e) {
                $lastException = $e;

                Log::warning('RPC call failed, retrying', [
                    'method'  => $method,
                    'attempt' => $attempt,
                    'max'     => $this->retryCount,
                    'error'   => $e->getMessage(),
                ]);

                if ($attempt < $this->retryCount) {
                    usleep($this->retryDelayMs * 1000);
                }
            }
        }

        throw RpcException::connectionFailed(
            $method,
            $lastException?->getMessage() ?? 'All retry attempts exhausted'
        );
    }
}
