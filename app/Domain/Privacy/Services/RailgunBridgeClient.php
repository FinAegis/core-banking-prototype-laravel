<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * HTTP client for the Node.js RAILGUN Bridge Service.
 *
 * Communicates with the bridge via bearer-token-authenticated JSON API
 * at the configured base URL. All privacy pool operations (shield, unshield,
 * transfer, wallet, merkle) are delegated to the bridge which wraps the
 * RAILGUN SDK.
 */
class RailgunBridgeClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $secret,
        private readonly int $timeout,
    ) {
    }

    /**
     * Create a RAILGUN wallet via the bridge.
     *
     * @return array<string, mixed>
     */
    public function createWallet(string $walletId, string $mnemonic, string $encryptionKey): array
    {
        return $this->post('/wallet/create', [
            'walletId'      => $walletId,
            'mnemonic'      => $mnemonic,
            'encryptionKey' => $encryptionKey,
        ]);
    }

    /**
     * Get shielded token balances for a wallet on a network.
     *
     * @return array<string, mixed>
     */
    public function getBalances(string $walletId, string $network): array
    {
        return $this->get("/wallet/{$walletId}/balances", [
            'network' => $network,
        ]);
    }

    /**
     * Trigger a wallet balance rescan.
     *
     * @return array<string, mixed>
     */
    public function scanWallet(string $walletId, ?string $network = null): array
    {
        return $this->post('/wallet/scan', array_filter([
            'walletId' => $walletId,
            'network'  => $network,
        ]));
    }

    /**
     * Build a shield (deposit) transaction.
     *
     * @return array<string, mixed>
     */
    public function shield(string $walletId, string $tokenAddress, string $amount, string $network): array
    {
        return $this->post('/shield', [
            'walletId'     => $walletId,
            'tokenAddress' => $tokenAddress,
            'amount'       => $amount,
            'network'      => $network,
        ]);
    }

    /**
     * Build an unshield (withdraw) transaction.
     *
     * @return array<string, mixed>
     */
    public function unshield(
        string $walletId,
        string $encryptionKey,
        string $recipientAddress,
        string $tokenAddress,
        string $amount,
        string $network,
    ): array {
        return $this->post('/unshield', [
            'walletId'         => $walletId,
            'encryptionKey'    => $encryptionKey,
            'recipientAddress' => $recipientAddress,
            'tokenAddress'     => $tokenAddress,
            'amount'           => $amount,
            'network'          => $network,
        ]);
    }

    /**
     * Build a private transfer between two 0zk addresses.
     *
     * @return array<string, mixed>
     */
    public function privateTransfer(
        string $walletId,
        string $encryptionKey,
        string $recipientRailgunAddress,
        string $tokenAddress,
        string $amount,
        string $network,
    ): array {
        return $this->post('/transfer', [
            'walletId'                => $walletId,
            'encryptionKey'           => $encryptionKey,
            'recipientRailgunAddress' => $recipientRailgunAddress,
            'tokenAddress'            => $tokenAddress,
            'amount'                  => $amount,
            'network'                 => $network,
        ]);
    }

    /**
     * Get the current Merkle root for a network.
     *
     * @return array<string, mixed>
     */
    public function getMerkleRoot(string $network): array
    {
        return $this->get("/merkle/root/{$network}");
    }

    /**
     * Get a Merkle proof for a commitment.
     *
     * @return array<string, mixed>
     */
    public function getMerkleProof(string $commitment, string $network): array
    {
        return $this->get("/merkle/proof/{$commitment}", [
            'network' => $network,
        ]);
    }

    /**
     * Check bridge health status.
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->get('/health', [], false);
    }

    /**
     * Check if the bridge is reachable and the engine is ready.
     */
    public function isHealthy(): bool
    {
        try {
            $health = $this->health();

            return ($health['engine_ready'] ?? false) === true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Send a GET request to the bridge.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = [], bool $authenticated = true): array
    {
        try {
            $request = Http::timeout($this->timeout)
                ->acceptJson();

            if ($authenticated) {
                $request = $request->withToken($this->secret);
            }

            $response = $request
                ->get($this->baseUrl . $path, $query)
                ->throw();

            $data = $response->json();

            if (! isset($data['success']) || $data['success'] !== true) {
                $errorMsg = $data['error']['message'] ?? 'Unknown bridge error';

                throw new RuntimeException("RAILGUN bridge error: {$errorMsg}");
            }

            return $data['data'] ?? [];
        } catch (ConnectionException $e) {
            Log::error('RAILGUN bridge connection failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('RAILGUN bridge is unreachable: ' . $e->getMessage());
        } catch (RequestException $e) {
            Log::error('RAILGUN bridge request failed', [
                'path'   => $path,
                'status' => $e->response->status(),
                'body'   => $e->response->body(),
            ]);

            throw new RuntimeException('RAILGUN bridge request failed: ' . $e->getMessage());
        }
    }

    /**
     * Send a POST request to the bridge.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function post(string $path, array $data = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->withToken($this->secret)
                ->post($this->baseUrl . $path, $data)
                ->throw();

            $responseData = $response->json();

            if (! isset($responseData['success']) || $responseData['success'] !== true) {
                $errorMsg = $responseData['error']['message'] ?? 'Unknown bridge error';

                throw new RuntimeException("RAILGUN bridge error: {$errorMsg}");
            }

            return $responseData['data'] ?? [];
        } catch (ConnectionException $e) {
            Log::error('RAILGUN bridge connection failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('RAILGUN bridge is unreachable: ' . $e->getMessage());
        } catch (RequestException $e) {
            Log::error('RAILGUN bridge request failed', [
                'path'   => $path,
                'status' => $e->response->status(),
                'body'   => $e->response->body(),
            ]);

            throw new RuntimeException('RAILGUN bridge request failed: ' . $e->getMessage());
        }
    }
}
