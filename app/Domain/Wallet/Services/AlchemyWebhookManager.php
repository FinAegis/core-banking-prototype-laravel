<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages per-user EVM address monitoring via Alchemy Notify API.
 *
 * Auto-provisions ADDRESS_ACTIVITY webhooks when the first EVM address for a
 * network is registered, and stores webhook metadata (id, signing_key) in the
 * webhook_endpoints table. Supports sharding when a webhook reaches capacity.
 *
 * Alchemy APIs:
 * - POST https://dashboard.alchemy.com/api/create-webhook   (provision new webhook)
 * - PATCH https://dashboard.alchemy.com/api/update-webhook-addresses (add/remove addresses)
 */
class AlchemyWebhookManager
{
    private const CREATE_URL = 'https://dashboard.alchemy.com/api/create-webhook';

    private const PATCH_URL = 'https://dashboard.alchemy.com/api/update-webhook-addresses';

    private const WEBHOOK_URL = 'https://zelta.app/api/webhooks/alchemy/address-activity';

    private const PROVIDER = 'alchemy';

    /** @var array<string, string> Internal network name → Alchemy enum */
    private const NETWORK_MAP = [
        'ethereum' => 'ETH_MAINNET',
        'polygon'  => 'MATIC_MAINNET',
        'arbitrum' => 'ARB_MAINNET',
        'base'     => 'BASE_MAINNET',
    ];

    /**
     * Add an EVM address to the Alchemy webhook for the given network.
     *
     * Gets or creates a webhook with capacity, PATCHes the address, and
     * increments the address_count.
     */
    public function addAddress(string $address, string $network): bool
    {
        $token = $this->getNotifyToken();

        if ($token === '') {
            Log::debug('AlchemyWebhookManager: Skipped — notify_token not configured');

            return false;
        }

        $address = strtolower($address);

        $endpoint = $this->getOrCreateWebhook($network, $token);

        if ($endpoint === null) {
            return false;
        }

        $success = $this->patchAddresses($token, $endpoint->external_webhook_id, [$address], []);

        if ($success) {
            $endpoint->incrementAddressCount();
        }

        return $success;
    }

    /**
     * Remove an EVM address from the Alchemy webhook for the given network.
     *
     * Finds the webhook for the network and PATCHes to remove the address.
     */
    public function removeAddress(string $address, string $network): bool
    {
        $token = $this->getNotifyToken();

        if ($token === '') {
            Log::debug('AlchemyWebhookManager: Skipped — notify_token not configured');

            return false;
        }

        $address = strtolower($address);

        $endpoint = WebhookEndpoint::where('provider', self::PROVIDER)
            ->where('network', $network)
            ->where('is_active', true)
            ->first();

        if (! $endpoint instanceof WebhookEndpoint) {
            Log::warning('AlchemyWebhookManager: No active webhook for network', ['network' => $network]);

            return false;
        }

        // address_count is not decremented on remove because we cannot determine
        // which shard the address belongs to. The count serves as a high watermark.
        return $this->patchAddresses($token, $endpoint->external_webhook_id, [], [$address]);
    }

    /**
     * Sync all SmartAccount addresses for a network to the Alchemy webhook.
     *
     * Queries all SmartAccount records for the network, gets or creates a webhook,
     * PATCHes all addresses in one call, and updates the address_count.
     *
     * @return int Number of addresses synced
     */
    public function syncAllAddresses(string $network): int
    {
        $token = $this->getNotifyToken();

        if ($token === '') {
            Log::warning('AlchemyWebhookManager: Cannot sync — notify_token not configured');

            return 0;
        }

        $addresses = SmartAccount::where('network', $network)
            ->pluck('account_address')
            ->map(fn (string $addr): string => strtolower($addr))
            ->unique()
            ->values()
            ->all();

        if ($addresses === []) {
            Log::info('AlchemyWebhookManager: No SmartAccount addresses to sync', ['network' => $network]);

            return 0;
        }

        $synced = 0;

        $chunks = collect($addresses)->chunk(WebhookEndpoint::MAX_ADDRESSES_PER_WEBHOOK);

        foreach ($chunks as $chunk) {
            /** @var array<string> $batch */
            $batch = array_values($chunk->all());

            $endpoint = $this->getOrCreateWebhook($network, $token);

            if ($endpoint === null) {
                return $synced;
            }

            $success = $this->patchAddresses($token, $endpoint->external_webhook_id, $batch, []);

            if ($success) {
                $endpoint->update(['address_count' => count($batch)]);
                $synced += count($batch);
            } else {
                return $synced;
            }
        }

        Log::info('AlchemyWebhookManager: Synced addresses', [
            'network' => $network,
            'count'   => $synced,
        ]);

        return $synced;
    }

    /**
     * Get all signing keys from active Alchemy webhooks.
     *
     * Used by the webhook controller for HMAC signature verification.
     *
     * @return array<string>
     */
    public function getSigningKeys(): array
    {
        return WebhookEndpoint::where('provider', self::PROVIDER)
            ->where('is_active', true)
            ->pluck('signing_key')
            ->all();
    }

    /**
     * Find an existing webhook with capacity, or create a new one via the Alchemy API.
     */
    private function getOrCreateWebhook(string $network, string $token): ?WebhookEndpoint
    {
        $endpoint = WebhookEndpoint::where('provider', self::PROVIDER)
            ->where('network', $network)
            ->where('is_active', true)
            ->orderBy('shard')
            ->get()
            ->first(fn (WebhookEndpoint $ep): bool => $ep->hasCapacity());

        if ($endpoint instanceof WebhookEndpoint) {
            return $endpoint;
        }

        return $this->createWebhook($network, $token);
    }

    /**
     * Provision a new ADDRESS_ACTIVITY webhook via Alchemy Notify API.
     *
     * Stores the returned webhook_id and signing_key in the webhook_endpoints table.
     */
    private function createWebhook(string $network, string $token): ?WebhookEndpoint
    {
        $alchemyNetwork = self::NETWORK_MAP[$network] ?? null;

        if ($alchemyNetwork === null) {
            Log::error('AlchemyWebhookManager: Unsupported network', ['network' => $network]);

            return null;
        }

        $nextShard = (int) WebhookEndpoint::where('provider', self::PROVIDER)
            ->where('network', $network)
            ->max('shard') + 1;

        $response = Http::timeout(15)
            ->withHeaders(['X-Alchemy-Token' => $token])
            ->post(self::CREATE_URL, [
                'network'      => $alchemyNetwork,
                'webhook_type' => 'ADDRESS_ACTIVITY',
                'webhook_url'  => self::WEBHOOK_URL,
                'addresses'    => [],
            ]);

        if (! $response->successful()) {
            Log::error('AlchemyWebhookManager: Failed to create webhook', [
                'network' => $network,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return null;
        }

        /** @var array{id: string, signing_key: string, is_active: bool} $data */
        $data = $response->json('data');

        return DB::transaction(fn (): WebhookEndpoint => WebhookEndpoint::create([
            'provider'            => self::PROVIDER,
            'network'             => $network,
            'shard'               => $nextShard,
            'external_webhook_id' => $data['id'],
            'signing_key'         => $data['signing_key'],
            'webhook_url'         => self::WEBHOOK_URL,
            'is_active'           => $data['is_active'],
            'address_count'       => 0,
        ]));
    }

    /**
     * PATCH addresses to add/remove on an existing Alchemy webhook.
     *
     * @param array<string> $toAdd
     * @param array<string> $toRemove
     */
    private function patchAddresses(string $token, string $webhookId, array $toAdd, array $toRemove): bool
    {
        $response = Http::timeout(15)
            ->withHeaders(['X-Alchemy-Token' => $token])
            ->patch(self::PATCH_URL, [
                'webhook_id'          => $webhookId,
                'addresses_to_add'    => array_values($toAdd),
                'addresses_to_remove' => array_values($toRemove),
            ]);

        if (! $response->successful()) {
            Log::error('AlchemyWebhookManager: Failed to patch addresses', [
                'webhook_id' => $webhookId,
                'status'     => $response->status(),
                'body'       => $response->body(),
                'add_count'  => count($toAdd),
                'rm_count'   => count($toRemove),
            ]);

            return false;
        }

        return true;
    }

    private function getNotifyToken(): string
    {
        return (string) config('services.alchemy.notify_token', '');
    }
}
