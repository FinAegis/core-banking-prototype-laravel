<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\Exceptions\RpcOutcomeUnknownException;
use App\Support\SecretRedactor;
use App\Support\TransportFailure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reusable JSON-RPC client for Ethereum nodes and ERC-4337 bundlers.
 *
 * Supports both standard Ethereum RPC and bundler-specific endpoints (Pimlico v2).
 * Includes retry logic with configurable attempts and delay.
 * Read-only RPC calls (blockNumber, gasPrice) are cached to reduce provider usage.
 *
 * @deprecated Use {@see \App\Infrastructure\Web3\EthRpcClient} for CrossChain/DeFi adapters.
 *             This Relayer-specific client remains for ERC-4337 bundler integration
 *             (SupportedNetwork enum, Pimlico endpoints) until full migration in v7.1.
 */
class EthRpcClient
{
    /**
     * Calls that change chain state. When one of them fails in a way the
     * server may have processed, the caller gets RpcOutcomeUnknownException
     * instead of a plain failure. Reads keep the plain failure.
     */
    private const array WRITE_METHODS = ['eth_sendUserOperation', 'eth_sendRawTransaction'];

    private int $timeout;

    private int $retryCount;

    private int $retryDelayMs;

    private int $rpcCacheTtl;

    public function __construct()
    {
        $this->timeout = (int) config('relayer.pimlico.timeout', 15);
        $this->retryCount = (int) config('relayer.pimlico.retry_count', 3);
        $this->retryDelayMs = 200;
        $this->rpcCacheTtl = (int) config('relayer.rpc_cache_ttl_seconds', 15);
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

        // PIMLICO_BUNDLER_URL is optional; an empty value (the config key is
        // always defined, so its default never applies) falls back to the
        // per-chain Pimlico v2 endpoint.
        $chainId = $network->getChainId();
        $configuredUrl = config('relayer.pimlico.bundler_url');
        $baseUrl = is_string($configuredUrl) && trim($configuredUrl) !== ''
            ? trim($configuredUrl)
            : "https://api.pimlico.io/v2/{$chainId}/rpc";
        $url = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'apikey=' . rawurlencode((string) $apiKey);

        return $this->executeWithRetry($url, $method, $params);
    }

    /**
     * Get the current block number for a network (cached).
     *
     * @throws RpcException
     */
    public function getBlockNumber(SupportedNetwork $network): int
    {
        $cacheKey = "rpc:block_number:{$network->value}";

        return (int) Cache::remember($cacheKey, $this->rpcCacheTtl, function () use ($network): int {
            $result = $this->call($network, 'eth_blockNumber');

            return (int) hexdec((string) $result);
        });
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
     * Get the current gas price in wei (hex, cached).
     *
     * @throws RpcException
     */
    public function getGasPrice(SupportedNetwork $network): string
    {
        $cacheKey = "rpc:gas_price:{$network->value}";

        return (string) Cache::remember($cacheKey, $this->rpcCacheTtl, function () use ($network): string {
            return (string) $this->call($network, 'eth_gasPrice');
        });
    }

    /**
     * Get the max priority fee per gas (EIP-1559, cached).
     *
     * @throws RpcException
     */
    public function getMaxPriorityFeePerGas(SupportedNetwork $network): string
    {
        $cacheKey = "rpc:max_priority_fee:{$network->value}";

        return (string) Cache::remember($cacheKey, $this->rpcCacheTtl, function () use ($network): string {
            return (string) $this->call($network, 'eth_maxPriorityFeePerGas');
        });
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
        $mayHaveBeenProcessed = false;

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
                // Don't retry RPC-level errors (invalid params, etc.). After an
                // attempt the server may have processed, this answer is about
                // the repeat, not the original, so it settles nothing.
                if ($mayHaveBeenProcessed) {
                    throw RpcOutcomeUnknownException::after(
                        $method,
                        'an earlier attempt may have been processed; the retry answered: ' . $e->getMessage()
                    );
                }

                throw $e;
            } catch (Throwable $e) {
                $lastException = $e;
                $mayHaveBeenProcessed = $mayHaveBeenProcessed
                    || (in_array($method, self::WRITE_METHODS, true) && TransportFailure::mayHaveBeenProcessed($e));

                Log::warning('RPC call failed, retrying', [
                    'method'  => $method,
                    'attempt' => $attempt,
                    'max'     => $this->retryCount,
                    'error'   => SecretRedactor::message($e),
                ]);

                if ($attempt < $this->retryCount) {
                    usleep($this->retryDelayMs * 1000);
                }
            }
        }

        // Transport errors embed the request URL (and with it the provider
        // key); RpcException redacts its message, this keeps the intent explicit.
        $reason = $lastException !== null ? SecretRedactor::message($lastException) : 'All retry attempts exhausted';

        if ($mayHaveBeenProcessed) {
            throw RpcOutcomeUnknownException::after($method, $reason);
        }

        throw RpcException::connectionFailed($method, $reason);
    }
}
