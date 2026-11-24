<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Constants\ReservedAddresses;
use App\Support\SecretRedactor;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reconcile Solana blockchain_addresses with Helius webhook config.
 *
 * The April 2026 incident traced to silent drift: addresses were
 * created locally but the queued Helius sync (BlockchainAddressObserver)
 * failed at the API boundary for 27 days, leaving Helius watching 1
 * address while our DB had 4. No alarm fired because the failure was
 * caught and only logged as a warning.
 *
 * This command surfaces the drift loudly so it never sits silently
 * again. Schedule it hourly. On drift it logs at ERROR level and
 * exits non-zero so monitoring/cron alerts fire. With --auto-sync it
 * runs solana:sync and checks again: drift that the sync fixed is
 * logged as a WARNING and exits zero; drift that remains still fails.
 */
class SolanaReconcileHeliusCommand extends Command
{
    protected $signature = 'solana:reconcile-helius
        {--auto-sync : Automatically run solana:sync if drift is detected}';

    protected $description = 'Reconcile Solana blockchain_addresses count with Helius webhook config';

    public function handle(): int
    {
        $webhookId = (string) config('services.helius.webhook_id', '');
        $apiKey = (string) config('services.helius.api_key', '');

        if ($webhookId === '' || $apiKey === '') {
            $this->warn('Helius is not configured (HELIUS_WEBHOOK_ID / HELIUS_API_KEY missing).');

            return self::SUCCESS;
        }

        // Reserved addresses are never pushed to Helius (see
        // HeliusWebhookSyncService::syncAllAddresses), so compare the same set
        // — otherwise one such row keeps the drift alarm permanently red.
        $dbAddresses = BlockchainAddress::where('chain', 'solana')
            ->where('is_active', true)
            ->pluck('address')
            ->unique()
            ->reject(fn (string $address): bool => ReservedAddresses::isReservedSolana($address))
            ->values()
            ->all();

        $heliusAddresses = $this->fetchHeliusAddresses($webhookId, $apiKey);

        if ($heliusAddresses === null) {
            return self::FAILURE;
        }

        [$missingFromHelius, $orphanedOnHelius] = $this->drift($dbAddresses, $heliusAddresses);

        $dbCount = count($dbAddresses);
        $heliusCount = count($heliusAddresses);

        $this->info("DB active Solana addresses: {$dbCount}");
        $this->info("Helius watched addresses:   {$heliusCount}");

        if (empty($missingFromHelius) && empty($orphanedOnHelius)) {
            $this->info('In sync — no drift detected.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'Drift detected: %d missing from Helius, %d orphaned on Helius.',
            count($missingFromHelius),
            count($orphanedOnHelius),
        ));

        if (! empty($missingFromHelius)) {
            $this->line('Missing from Helius:');
            foreach ($missingFromHelius as $addr) {
                $this->line("  - {$addr}");
            }
        }

        if (! empty($orphanedOnHelius)) {
            $this->line('Orphaned on Helius (in Helius but not in our DB):');
            foreach ($orphanedOnHelius as $addr) {
                $this->line("  - {$addr}");
            }
        }

        if (! $this->option('auto-sync')) {
            Log::error('Helius reconcile: address drift detected', [
                'db_count'            => $dbCount,
                'helius_count'        => $heliusCount,
                'missing_from_helius' => $missingFromHelius,
                'orphaned_on_helius'  => $orphanedOnHelius,
            ]);
            $this->warn('Re-run with --auto-sync to push the missing addresses, or run `php artisan solana:sync` manually.');

            return self::FAILURE;
        }

        $this->info('--auto-sync set; invoking solana:sync...');
        $this->call('solana:sync');

        // Check again: the drift alarm should only fire when the sync did
        // not fix it, otherwise every self-healed run pages an operator.
        $heliusAfterSync = $this->fetchHeliusAddresses($webhookId, $apiKey);

        if ($heliusAfterSync === null) {
            return self::FAILURE;
        }

        [$stillMissing, $stillOrphaned] = $this->drift($dbAddresses, $heliusAfterSync);

        if (empty($stillMissing) && empty($stillOrphaned)) {
            Log::warning('Helius reconcile: address drift fixed by auto-sync', [
                'db_count'                 => $dbCount,
                'helius_count_before_sync' => $heliusCount,
                'helius_count'             => count($heliusAfterSync),
                'missing_from_helius'      => $missingFromHelius,
                'orphaned_on_helius'       => $orphanedOnHelius,
            ]);
            $this->warn('Drift fixed by solana:sync. Helius now watches the same addresses as our DB.');

            return self::SUCCESS;
        }

        Log::error('Helius reconcile: address drift remains after auto-sync', [
            'db_count'                        => $dbCount,
            'helius_count_before_sync'        => $heliusCount,
            'helius_count'                    => count($heliusAfterSync),
            'missing_from_helius_before_sync' => $missingFromHelius,
            'orphaned_on_helius_before_sync'  => $orphanedOnHelius,
            'missing_from_helius'             => $stillMissing,
            'orphaned_on_helius'              => $stillOrphaned,
        ]);
        $this->error(sprintf(
            'Drift remains after solana:sync: %d missing from Helius, %d orphaned on Helius.',
            count($stillMissing),
            count($stillOrphaned),
        ));

        return self::FAILURE;
    }

    /**
     * Fetch the addresses the Helius webhook watches. Returns null (after
     * logging and printing the error) when the API cannot be read.
     *
     * @return array<int, string>|null
     */
    private function fetchHeliusAddresses(string $webhookId, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withQueryParameters(['api-key' => $apiKey])
                ->get("https://api.helius.xyz/v0/webhooks/{$webhookId}");
        } catch (ConnectionException $e) {
            // The Helius key is the api-key query parameter of the request URL.
            $error = SecretRedactor::message($e);

            Log::error('Helius reconcile: webhook API unreachable', ['error' => $error]);
            $this->error("Helius API unreachable: {$error}");

            return null;
        }

        if (! $response->successful()) {
            Log::error('Helius reconcile: failed to fetch webhook config', [
                'status' => $response->status(),
                'body'   => SecretRedactor::redact($response->body()),
            ]);
            $this->error("Helius API returned {$response->status()}.");

            return null;
        }

        /** @var array<int, string> $addresses */
        $addresses = (array) $response->json('accountAddresses', []);

        return $addresses;
    }

    /**
     * @param  array<int, string>  $dbAddresses
     * @param  array<int, string>  $heliusAddresses
     * @return array{0: array<int, string>, 1: array<int, string>} [missing from Helius, orphaned on Helius]
     */
    private function drift(array $dbAddresses, array $heliusAddresses): array
    {
        return [
            array_values(array_diff($dbAddresses, $heliusAddresses)),
            array_values(array_diff($heliusAddresses, $dbAddresses)),
        ];
    }
}
