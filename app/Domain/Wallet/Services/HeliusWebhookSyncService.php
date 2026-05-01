<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Account\Models\BlockchainAddress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Syncs Solana user addresses with Helius webhook monitoring.
 *
 * Unlike Alchemy (which monitors token contract addresses catching all transfers),
 * Helius requires explicit account addresses. This service automatically adds/removes
 * user Solana addresses to the Helius webhook via their API.
 *
 * Uses a cache lock to prevent concurrent modifications from losing addresses.
 * Helius API: PUT /v0/webhooks/{id}?api-key={key} with accountAddresses in body.
 * Max 100,000 addresses per webhook. Costs 100 credits per API call.
 */
class HeliusWebhookSyncService
{
    private const CACHE_KEY = 'helius_webhook_addresses';

    private const LOCK_KEY = 'helius_webhook_update_lock';

    private const CACHE_TTL = 3600;

    private const LOCK_TIMEOUT = 15;

    /** @var array<string> Solana system/program addresses that must never be monitored */
    private const RESERVED_ADDRESSES = [
        '11111111111111111111111111111111',
        '11111111111111111111111111111112',
        'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
        'ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL',
        'SysvarC1ock11111111111111111111111111111111',
        'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', // USDC mint — too noisy
    ];

    /**
     * Add a Solana address to the Helius webhook.
     *
     * Uses a cache lock to prevent concurrent adds from overwriting each other.
     */
    public function addAddress(string $address): bool
    {
        if ($this->isReservedAddress($address)) {
            Log::warning('Helius: Rejected reserved Solana address', ['address' => $address]);

            return false;
        }

        $webhookId = $this->getWebhookId();
        $apiKey = $this->getApiKey();

        if ($webhookId === '' || $apiKey === '') {
            Log::debug('Helius: Webhook sync skipped — not configured');

            return false;
        }

        // Lock to prevent concurrent read-modify-write race condition
        return (bool) Cache::lock(self::LOCK_KEY, self::LOCK_TIMEOUT)->block(10, function () use ($address, $webhookId, $apiKey): bool {
            // Read fresh (bypass cache to get latest after lock acquisition)
            $currentAddresses = $this->fetchAddressesFromApi($webhookId, $apiKey);

            if (in_array($address, $currentAddresses, true)) {
                return true;
            }

            $currentAddresses[] = $address;

            return $this->updateWebhookAddresses($webhookId, $apiKey, $currentAddresses);
        });
    }

    /**
     * Remove a Solana address from the Helius webhook.
     */
    public function removeAddress(string $address): bool
    {
        $webhookId = $this->getWebhookId();
        $apiKey = $this->getApiKey();

        if ($webhookId === '' || $apiKey === '') {
            return false;
        }

        return (bool) Cache::lock(self::LOCK_KEY, self::LOCK_TIMEOUT)->block(10, function () use ($address, $webhookId, $apiKey): bool {
            $currentAddresses = $this->fetchAddressesFromApi($webhookId, $apiKey);
            $updated = array_values(array_diff($currentAddresses, [$address]));

            if (count($updated) === count($currentAddresses)) {
                return true;
            }

            return $this->updateWebhookAddresses($webhookId, $apiKey, $updated);
        });
    }

    /**
     * Sync all Solana addresses from the database to Helius.
     *
     * Returns the count of addresses pushed on success, 0 on failure.
     */
    public function syncAllAddresses(): int
    {
        $webhookId = $this->getWebhookId();
        $apiKey = $this->getApiKey();

        if ($webhookId === '' || $apiKey === '') {
            Log::warning('Helius: Cannot sync — HELIUS_WEBHOOK_ID or HELIUS_API_KEY not set');

            return 0;
        }

        $addresses = BlockchainAddress::where('chain', 'solana')
            ->where('is_active', true)
            ->pluck('address')
            ->unique()
            ->reject(fn (string $addr): bool => $this->isReservedAddress($addr))
            ->values()
            ->all();

        if (! $this->updateWebhookAddresses($webhookId, $apiKey, $addresses)) {
            return 0;
        }

        Log::info('Helius: Synced all Solana addresses', ['count' => count($addresses)]);

        return count($addresses);
    }

    /**
     * Fetch addresses directly from Helius API (bypasses cache).
     *
     * @return array<string>
     */
    private function fetchAddressesFromApi(string $webhookId, string $apiKey): array
    {
        $response = Http::timeout(15)
            ->get("https://api.helius.xyz/v0/webhooks/{$webhookId}", [
                'api-key' => $apiKey,
            ]);

        if (! $response->successful()) {
            Log::error('Helius: Failed to fetch webhook', [
                'status' => $response->status(),
            ]);

            return [];
        }

        /** @var array<string> $addresses */
        $addresses = $response->json('accountAddresses', []);

        Cache::put(self::CACHE_KEY, $addresses, self::CACHE_TTL);

        return $addresses;
    }

    /**
     * Update the webhook with a new address list.
     *
     * Helius's PUT /v0/webhooks/{id} replaces the entire webhook config — it
     * does NOT support partial updates. Sending only accountAddresses fails
     * with 400 ("Webhook URL is required", "Transaction types must be an
     * array", etc.). So we GET the current config, swap accountAddresses,
     * and PUT the whole object back.
     *
     * Helius requires `api-key` as a query parameter — it does not accept
     * the key in the JSON body or in an Authorization header.
     *
     * @param array<string> $addresses
     */
    private function updateWebhookAddresses(string $webhookId, string $apiKey, array $addresses): bool
    {
        $uniqueAddresses = array_values(array_unique($addresses));

        $current = Http::timeout(15)
            ->withQueryParameters(['api-key' => $apiKey])
            ->get("https://api.helius.xyz/v0/webhooks/{$webhookId}");

        if (! $current->successful()) {
            Log::error('Helius: Failed to fetch current webhook config for update', [
                'status' => $current->status(),
                'body'   => $current->body(),
            ]);

            return false;
        }

        /** @var array<string, mixed> $config */
        $config = (array) $current->json();
        $config['accountAddresses'] = $uniqueAddresses;

        // Strip server-managed fields that Helius rejects on PUT.
        unset($config['webhookID'], $config['project']);

        $response = Http::timeout(15)
            ->withQueryParameters(['api-key' => $apiKey])
            ->put("https://api.helius.xyz/v0/webhooks/{$webhookId}", $config);

        if (! $response->successful()) {
            Log::error('Helius: Failed to update webhook addresses', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'count'  => count($uniqueAddresses),
            ]);

            return false;
        }

        Cache::put(self::CACHE_KEY, $uniqueAddresses, self::CACHE_TTL);

        Log::info('Helius: Webhook addresses updated', ['count' => count($uniqueAddresses)]);

        return true;
    }

    private function isReservedAddress(string $address): bool
    {
        return in_array($address, self::RESERVED_ADDRESSES, true);
    }

    private function getWebhookId(): string
    {
        return (string) config('services.helius.webhook_id', '');
    }

    private function getApiKey(): string
    {
        return (string) config('services.helius.api_key', '');
    }
}
