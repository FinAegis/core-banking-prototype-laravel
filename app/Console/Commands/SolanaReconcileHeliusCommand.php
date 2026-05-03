<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\BlockchainAddress;
use Illuminate\Console\Command;
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
 * exits non-zero so monitoring/cron alerts fire.
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

        $dbAddresses = BlockchainAddress::where('chain', 'solana')
            ->where('is_active', true)
            ->pluck('address')
            ->unique()
            ->values()
            ->all();

        $response = Http::timeout(15)
            ->withQueryParameters(['api-key' => $apiKey])
            ->get("https://api.helius.xyz/v0/webhooks/{$webhookId}");

        if (! $response->successful()) {
            Log::error('Helius reconcile: failed to fetch webhook config', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            $this->error("Helius API returned {$response->status()}.");

            return self::FAILURE;
        }

        /** @var array<int, string> $heliusAddresses */
        $heliusAddresses = (array) $response->json('accountAddresses', []);

        $missingFromHelius = array_values(array_diff($dbAddresses, $heliusAddresses));
        $orphanedOnHelius = array_values(array_diff($heliusAddresses, $dbAddresses));

        $dbCount = count($dbAddresses);
        $heliusCount = count($heliusAddresses);

        $this->info("DB active Solana addresses: {$dbCount}");
        $this->info("Helius watched addresses:   {$heliusCount}");

        if (empty($missingFromHelius) && empty($orphanedOnHelius)) {
            $this->info('In sync — no drift detected.');

            return self::SUCCESS;
        }

        Log::error('Helius reconcile: address drift detected', [
            'db_count'            => $dbCount,
            'helius_count'        => $heliusCount,
            'missing_from_helius' => $missingFromHelius,
            'orphaned_on_helius'  => $orphanedOnHelius,
        ]);

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

        if ($this->option('auto-sync')) {
            $this->info('--auto-sync set; invoking solana:sync...');
            $this->call('solana:sync');
        } else {
            $this->warn('Re-run with --auto-sync to push the missing addresses, or run `php artisan solana:sync` manually.');
        }

        return self::FAILURE;
    }
}
