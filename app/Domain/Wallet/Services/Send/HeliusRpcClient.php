<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Wallet\Exceptions\SolanaRpcException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin JSON-RPC client over Helius mainnet RPC.
 *
 * Helius requires the API key as a `?api-key=` query parameter — the
 * Authorization header is NOT supported (documented in CLAUDE.md). All
 * methods throw {@see SolanaRpcException} on RPC error envelopes so callers
 * can persist a failure record and surface a user-facing message without
 * leaking PHP runtime warnings.
 */
class HeliusRpcClient
{
    /**
     * @return array{blockhash: string, lastValidBlockHeight: int}
     */
    public function getLatestBlockhash(): array
    {
        $result = $this->call('getLatestBlockhash', [
            ['commitment' => $this->commitment()],
        ]);

        $value = is_array($result) && isset($result['value']) && is_array($result['value'])
            ? $result['value']
            : [];

        return [
            'blockhash'            => (string) ($value['blockhash'] ?? ''),
            'lastValidBlockHeight' => (int) ($value['lastValidBlockHeight'] ?? 0),
        ];
    }

    /**
     * @return array{success: bool, err: array<int|string, mixed>|null, logs: array<int, string>}
     */
    public function simulateTransaction(string $base64Tx): array
    {
        $result = $this->call('simulateTransaction', [
            $base64Tx,
            [
                'encoding'   => 'base64',
                'commitment' => $this->commitment(),
                'sigVerify'  => false,
            ],
        ]);

        $value = is_array($result) && isset($result['value']) && is_array($result['value'])
            ? $result['value']
            : [];
        $err = $value['err'] ?? null;
        $logs = $value['logs'] ?? [];

        return [
            'success' => $err === null,
            'err'     => is_array($err) ? $err : null,
            'logs'    => is_array($logs) ? array_values(array_map('strval', $logs)) : [],
        ];
    }

    /**
     * Submit a fully-signed transaction. Returns the transaction signature.
     *
     * @throws SolanaRpcException
     */
    public function sendTransaction(string $base64Tx): string
    {
        $result = $this->call('sendTransaction', [
            $base64Tx,
            [
                'encoding'            => 'base64',
                'skipPreflight'       => false,
                'preflightCommitment' => $this->commitment(),
            ],
        ]);

        if (! is_string($result)) {
            throw SolanaRpcException::fromRpcError(0, 'sendTransaction returned non-string result');
        }

        return $result;
    }

    /**
     * Look up confirmation status for a batch of signatures.
     *
     * @param  array<int, string> $signatures
     * @return array<string, array{confirmationStatus: string, err: array<int|string, mixed>|null}|null>
     */
    public function getSignatureStatuses(array $signatures): array
    {
        $result = $this->call('getSignatureStatuses', [
            array_values($signatures),
            ['searchTransactionHistory' => true],
        ]);

        $value = is_array($result) && isset($result['value']) && is_array($result['value'])
            ? $result['value']
            : [];

        /** @var array<string, array{confirmationStatus: string, err: array<int|string, mixed>|null}|null> $out */
        $out = [];
        foreach (array_values($signatures) as $i => $sig) {
            $entry = $value[$i] ?? null;
            if (! is_array($entry)) {
                $out[$sig] = null;
                continue;
            }
            $err = $entry['err'] ?? null;
            $out[$sig] = [
                'confirmationStatus' => (string) ($entry['confirmationStatus'] ?? ''),
                'err'                => is_array($err) ? $err : null,
            ];
        }

        return $out;
    }

    /**
     * Fetch on-chain account info, JSON-encoded. Returns null when the account
     * does not exist on-chain (used to detect missing recipient ATAs).
     *
     * @return array{owner: string, lamports: int, data: array<int|string, mixed>}|null
     */
    public function getAccountInfo(string $base58Address): ?array
    {
        $result = $this->call('getAccountInfo', [
            $base58Address,
            [
                'encoding'   => 'jsonParsed',
                'commitment' => $this->commitment(),
            ],
        ]);

        if (! is_array($result) || ! isset($result['value']) || ! is_array($result['value'])) {
            return null;
        }
        $value = $result['value'];

        $data = $value['data'] ?? null;

        return [
            'owner'    => (string) ($value['owner'] ?? ''),
            'lamports' => (int) ($value['lamports'] ?? 0),
            'data'     => is_array($data) ? $data : [],
        ];
    }

    /**
     * Execute a JSON-RPC call. Throws on RPC error envelope or HTTP failure.
     *
     * @param  array<int, mixed> $params
     * @return mixed
     * @throws SolanaRpcException
     */
    private function call(string $method, array $params)
    {
        $endpoint = $this->endpoint();

        try {
            $response = Http::acceptJson()->asJson()
                ->post($endpoint, [
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'method'  => $method,
                    'params'  => $params,
                ])
                ->throw();
        } catch (RequestException $e) {
            throw SolanaRpcException::fromRpcError(
                $e->response->status(),
                "HTTP {$e->response->status()} from Helius RPC ({$method})"
            );
        }

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();

        if (isset($body['error']) && is_array($body['error'])) {
            $code = (int) ($body['error']['code'] ?? 0);
            $message = (string) ($body['error']['message'] ?? 'Unknown RPC error');
            throw SolanaRpcException::fromRpcError($code, $message);
        }

        return $body['result'] ?? null;
    }

    private function endpoint(): string
    {
        $rpcUrl = (string) config('wallet.solana.rpc_url', 'https://mainnet.helius-rpc.com');
        $apiKey = (string) config('services.helius.api_key', '');

        if ($apiKey === '') {
            return $rpcUrl;
        }

        $separator = str_contains($rpcUrl, '?') ? '&' : '?';

        return $rpcUrl . $separator . 'api-key=' . rawurlencode($apiKey);
    }

    private function commitment(): string
    {
        return (string) config('wallet.solana.commitment', 'confirmed');
    }
}
