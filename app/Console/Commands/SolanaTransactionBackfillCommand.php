<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Services\HeliusTransactionProcessor;
use App\Support\SecretRedactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backfill Solana transaction history from Helius API.
 *
 * Fetches historical transactions for all registered Solana addresses
 * using Helius Enhanced Transactions API and stores them in both
 * blockchain_address_transactions and activity_feed_items tables.
 */
class SolanaTransactionBackfillCommand extends Command
{
    protected $signature = 'solana:backfill-transactions
        {--address= : Backfill only this specific address}
        {--limit=50 : Maximum transactions per address}
        {--dry-run : Show count without storing}';

    protected $description = 'Backfill Solana transaction history from Helius Enhanced Transactions API';

    public function handle(): int
    {
        $apiKey = (string) config('services.helius.api_key');

        if ($apiKey === '') {
            $this->error('HELIUS_API_KEY is not set.');

            return self::FAILURE;
        }

        $specificAddress = $this->option('address');
        $limit = (int) $this->option('limit');

        $query = BlockchainAddress::where('chain', 'solana')->where('is_active', true)->with('user');

        if ($specificAddress) {
            $query->where('address', $specificAddress);
        }

        $addresses = $query->get();

        if ($addresses->isEmpty()) {
            $this->warn('No Solana addresses found in blockchain_addresses table.');

            return self::FAILURE;
        }

        $this->info("Backfilling transactions for {$addresses->count()} Solana address(es)...");

        $totalCreated = 0;
        $totalSkipped = 0;

        $processor = app(HeliusTransactionProcessor::class);

        foreach ($addresses as $blockchainAddress) {
            $user = $blockchainAddress->user;

            if ($user === null) {
                $this->warn("  Skipping {$blockchainAddress->address} — no user found");

                continue;
            }

            $this->info("  Fetching history for {$blockchainAddress->address}...");

            $transactions = $this->fetchEnhancedTransactions($apiKey, $blockchainAddress->address, $limit);

            if ($transactions === null) {
                $this->warn("  Failed to fetch transactions for {$blockchainAddress->address}");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->info("  Would store {$transactions->count()} transactions (dry-run)");
                $totalCreated += $transactions->count();

                continue;
            }

            foreach ($transactions as $tx) {
                $timestamp = isset($tx['timestamp']) ? date('Y-m-d H:i:s', (int) $tx['timestamp']) : null;
                $wasCreated = $processor->processTransaction(
                    $blockchainAddress->address,
                    $blockchainAddress,
                    $user->id,
                    $tx,
                    $timestamp,
                );

                if ($wasCreated) {
                    $totalCreated++;
                } else {
                    $totalSkipped++;
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run complete. Would store {$totalCreated} transactions.");
        } else {
            $this->info("Done. Created: {$totalCreated}, Already existed: {$totalSkipped}.");
        }

        return self::SUCCESS;
    }

    /**
     * Fetch enhanced transaction history from Helius API.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>|null
     */
    private function fetchEnhancedTransactions(string $apiKey, string $address, int $limit): ?\Illuminate\Support\Collection
    {
        $url = "https://api.helius.xyz/v0/addresses/{$address}/transactions";

        try {
            // Helius API requires api-key as query param (no header auth support)
            $response = Http::timeout(30)
                ->withoutRedirecting()
                ->get($url, [
                    'api-key' => $apiKey,
                    'limit'   => min($limit, 100),
                ]);

            if (! $response->successful()) {
                Log::warning('Helius: Failed to fetch transaction history', [
                    'address' => $address,
                    'status'  => $response->status(),
                ]);

                return null;
            }

            return collect($response->json());
        } catch (Throwable $e) {
            // The Helius key is the api-key query parameter of the request URL.
            Log::error('Helius: Transaction history fetch error', [
                'address' => $address,
                'error'   => SecretRedactor::message($e),
            ]);

            return null;
        }
    }
}
