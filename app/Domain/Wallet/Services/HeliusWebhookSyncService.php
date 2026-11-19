<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Constants\ReservedAddresses;
use App\Support\SecretRedactor;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Syncs Solana user addresses with Helius webhook monitoring.
 *
 * Unlike Alchemy (which monitors token contract addresses catching all transfers),
 * Helius requires explicit account addresses. This service automatically adds/removes
 * user Solana addresses to the Helius webhook via their API.
 *
 * Uses a cache lock to prevent concurrent modifications from losing addresses.
 * Single-address adds/removes are read-modify-write against ONE snapshot of
 * the webhook config and fail closed: if that read fails or carries no
 * address list, nothing is written (a PUT replaces the whole list, so writing
 * from a failed read would wipe every other user's address).
 * Helius API: PUT /v0/webhooks/{id}?api-key={key} with accountAddresses in body.
 * Max 100,000 addresses per webhook. Costs 100 credits per API call.
 */
class HeliusWebhookSyncService
{
    private const CACHE_KEY = 'helius_webhook_addresses';

    private const LOCK_KEY = 'helius_webhook_update_lock';

    private const CACHE_TTL = 3600;

    /**
     * Lock TTL. Must exceed the worst case of one GET plus one PUT at the
     * 15s HTTP timeout, so the lock cannot expire mid-update and let two
     * read-modify-writes drop each other's addresses.
     */
    private const LOCK_TIMEOUT = 45;

    /**
     * Helius caps a webhook at 100,000 addresses; past it every PUT (and so
     * the hourly full resync) is rejected. Alert well before that.
     */
    private const ADDRESS_ALERT_THRESHOLD = 90_000;

    /**
     * Fields Helius accepts on PUT /v0/webhooks/{id}.
     *
     * The GET response includes server-managed fields (`webhookID`, `project`,
     * `wallet`, `lastEnabledAt`, etc.) that Helius rejects with 400 on PUT.
     * We whitelist instead of blacklist so future-added server fields don't
     * silently break sync.
     */
    private const PUT_ALLOWED_FIELDS = [
        'webhookURL',
        'transactionTypes',
        'accountAddresses',
        'webhookType',
        'authHeader',
        'txnStatus',
        'encoding',
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
            return $this->mutateWebhookAddresses($webhookId, $apiKey, $address, add: true);
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
            return $this->mutateWebhookAddresses($webhookId, $apiKey, $address, add: false);
        });
    }

    /**
     * Sync all Solana addresses from the database to Helius.
     *
     * Holds the same lock as the single-address updates and reads the
     * database inside it, so an address added while the sync runs is never
     * dropped by the full-list PUT.
     *
     * Returns the count of addresses pushed on success, 0 on failure
     * (including when the lock cannot be taken).
     */
    public function syncAllAddresses(): int
    {
        $webhookId = $this->getWebhookId();
        $apiKey = $this->getApiKey();

        if ($webhookId === '' || $apiKey === '') {
            Log::warning('Helius: Cannot sync — HELIUS_WEBHOOK_ID or HELIUS_API_KEY not set');

            return 0;
        }

        try {
            return (int) Cache::lock(self::LOCK_KEY, self::LOCK_TIMEOUT)->block(10, function () use ($webhookId, $apiKey): int {
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
            });
        } catch (LockTimeoutException) {
            Log::warning('Helius: Full address sync skipped — another webhook update holds the lock');

            return 0;
        }
    }

    /**
     * Add or remove ONE address against a single fresh snapshot of the
     * webhook config (the same GET supplies the current list and the config
     * that is PUT back). Fails closed: a failed GET, or a config without an
     * `accountAddresses` list, writes nothing; and a PUT that would change
     * the list by anything other than the one address is refused.
     */
    private function mutateWebhookAddresses(string $webhookId, string $apiKey, string $address, bool $add): bool
    {
        $response = $this->send('GET', $webhookId, $apiKey);

        if (! $response->successful()) {
            Log::error('Helius: Failed to fetch webhook, address list left unchanged', [
                'status'    => $response->status(),
                'operation' => $add ? 'add' : 'remove',
            ]);

            return false;
        }

        $config = $response->json();

        if (! is_array($config) || ! isset($config['accountAddresses']) || ! is_array($config['accountAddresses'])) {
            Log::error('Helius: Webhook config has no accountAddresses list, address list left unchanged', [
                'operation' => $add ? 'add' : 'remove',
            ]);

            return false;
        }

        /** @var array<int, string> $current */
        $current = array_values(array_unique(array_filter($config['accountAddresses'], 'is_string')));

        Cache::put(self::CACHE_KEY, $current, self::CACHE_TTL);

        if ($add) {
            if (in_array($address, $current, true)) {
                return true;
            }

            $updated = [...$current, $address];
        } else {
            $updated = array_values(array_diff($current, [$address]));

            if (count($updated) === count($current)) {
                return true;
            }
        }

        // Safety net: a single add/remove changes the list by exactly one.
        if (abs(count($updated) - count($current)) !== 1) {
            Log::error('Helius: Refusing webhook update that changes more than one address', [
                'current_count' => count($current),
                'updated_count' => count($updated),
            ]);

            return false;
        }

        /** @var array<string, mixed> $config */
        return $this->putWebhookAddresses($webhookId, $apiKey, $config, $updated);
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

        $current = $this->send('GET', $webhookId, $apiKey);

        if (! $current->successful()) {
            Log::error('Helius: Failed to fetch current webhook config for update', [
                'status' => $current->status(),
                'body'   => SecretRedactor::redact($current->body()),
            ]);

            return false;
        }

        /** @var array<string, mixed> $config */
        $config = (array) $current->json();

        return $this->putWebhookAddresses($webhookId, $apiKey, $config, $uniqueAddresses);
    }

    /**
     * PUT the webhook config back with a new address list.
     *
     * @param array<string, mixed> $config    config as returned by GET
     * @param array<int, string>   $addresses
     */
    private function putWebhookAddresses(string $webhookId, string $apiKey, array $config, array $addresses): bool
    {
        $uniqueAddresses = array_values(array_unique($addresses));

        // Helius rejects unknown/server-managed fields on PUT. Whitelist only
        // the fields documented as accepted, plus our updated address list.
        $config = array_intersect_key($config, array_flip(self::PUT_ALLOWED_FIELDS));
        $config['accountAddresses'] = $uniqueAddresses;

        $response = $this->send('PUT', $webhookId, $apiKey, $config);

        if (! $response->successful()) {
            Log::error('Helius: Failed to update webhook addresses', [
                'status' => $response->status(),
                'body'   => SecretRedactor::redact($response->body()),
                'count'  => count($uniqueAddresses),
            ]);

            return false;
        }

        Cache::put(self::CACHE_KEY, $uniqueAddresses, self::CACHE_TTL);

        Log::info('Helius: Webhook addresses updated', ['count' => count($uniqueAddresses)]);

        if (count($uniqueAddresses) >= self::ADDRESS_ALERT_THRESHOLD) {
            Log::critical('Helius: webhook address list is close to the 100,000-address cap', [
                'count'     => count($uniqueAddresses),
                'threshold' => self::ADDRESS_ALERT_THRESHOLD,
            ]);
        }

        return true;
    }

    /**
     * Call the Helius webhook API. Helius only accepts the key as the
     * `api-key` query parameter, so transport errors (which embed the request
     * URL) are rethrown with the key redacted and without the original
     * exception attached.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws RuntimeException when Helius cannot be reached
     */
    private function send(string $method, string $webhookId, string $apiKey, array $body = []): Response
    {
        $request = Http::timeout(15)->withQueryParameters(['api-key' => $apiKey]);
        $url = "https://api.helius.xyz/v0/webhooks/{$webhookId}";

        try {
            return $method === 'PUT' ? $request->put($url, $body) : $request->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Helius webhook API unreachable: ' . SecretRedactor::message($e));
        }
    }

    private function isReservedAddress(string $address): bool
    {
        return ReservedAddresses::isReservedSolana($address);
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
