<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Constants\EvmTokens;
use App\Domain\Wallet\Services\EvmTransactionProcessor;
use App\Support\SecretRedactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Backfill EVM transaction history from the Alchemy Transfers API.
 *
 * Fetches historical USDC/USDT transfers for every registered EVM address
 * (Polygon/Base/Arbitrum/Ethereum) and stores them via
 * {@see EvmTransactionProcessor} into `blockchain_address_transactions` and
 * `activity_feed_items` — the EVM counterpart of `solana:backfill-transactions`.
 *
 * Use it to seed history that predates the live Alchemy webhook mirror.
 */
class EvmTransactionBackfillCommand extends Command
{
    protected $signature = 'evm:backfill-transactions
        {--address= : Backfill only this specific address}
        {--network= : Backfill only this network (polygon|base|arbitrum|ethereum)}
        {--limit=50 : Maximum transfers per address per direction}
        {--dry-run : Show counts without storing}';

    protected $description = 'Backfill EVM USDC/USDT transaction history from the Alchemy Transfers API';

    /**
     * Network key → Alchemy RPC subdomain.
     */
    private const array NETWORK_SUBDOMAINS = [
        'polygon'  => 'polygon-mainnet',
        'base'     => 'base-mainnet',
        'arbitrum' => 'arb-mainnet',
        'ethereum' => 'eth-mainnet',
    ];

    public function handle(EvmTransactionProcessor $processor): int
    {
        $apiKey = (string) config('relayer.balance_checking.alchemy_api_key');

        if ($apiKey === '') {
            $this->error('ALCHEMY_API_KEY is not set.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $networkOption = $this->option('network');
        $networkFilter = is_string($networkOption) && $networkOption !== '' ? strtolower($networkOption) : null;

        if ($networkFilter !== null && ! isset(self::NETWORK_SUBDOMAINS[$networkFilter])) {
            $this->error("Unknown network: {$networkFilter}");

            return self::FAILURE;
        }

        $query = BlockchainAddress::query()
            ->whereIn('chain', array_keys(self::NETWORK_SUBDOMAINS))
            ->where('is_active', true)
            ->with('user');

        if ($networkFilter !== null) {
            $query->where('chain', $networkFilter);
        }

        $addressOption = $this->option('address');
        if (is_string($addressOption) && $addressOption !== '') {
            $query->where('address', strtolower($addressOption));
        }

        $addresses = $query->get();

        if ($addresses->isEmpty()) {
            $this->warn('No matching active EVM addresses to backfill.');

            return self::SUCCESS;
        }

        $stored = 0;
        $scanned = 0;

        foreach ($addresses as $blockchainAddress) {
            $user = $blockchainAddress->user;

            if ($user === null) {
                continue;
            }

            $chain = $blockchainAddress->chain;
            $address = strtolower($blockchainAddress->address);

            // Two directions: inbound deposits (toAddress) and outbound sends (fromAddress).
            foreach (['toAddress', 'fromAddress'] as $direction) {
                try {
                    $transfers = $this->fetchTransfers($apiKey, $chain, $address, $direction, $limit);
                } catch (Throwable $e) {
                    // The Alchemy key is part of the request URL path.
                    $error = SecretRedactor::message($e);

                    $this->error("  {$chain} {$address} ({$direction}): {$error}");
                    Log::warning('EVM backfill: transfer fetch failed', [
                        'chain'   => $chain,
                        'address' => $address,
                        'error'   => $error,
                    ]);

                    continue;
                }

                foreach ($transfers as $transfer) {
                    $scanned++;

                    $activity = $this->normalizeTransfer($transfer);

                    if ($activity === null) {
                        continue;
                    }

                    if ($dryRun) {
                        continue;
                    }

                    $created = $processor->processActivity(
                        $address,
                        $blockchainAddress,
                        $user->id,
                        $chain,
                        $activity,
                        $this->blockTimestamp($transfer),
                    );

                    if ($created) {
                        $stored++;
                    }
                }
            }
        }

        $this->info($dryRun
            ? "Dry run: {$scanned} transfers found across {$addresses->count()} address rows."
            : "Backfill complete: {$stored} new transactions stored ({$scanned} transfers scanned).");

        return self::SUCCESS;
    }

    /**
     * Fetch USDC/USDT transfers for one address in one direction from Alchemy.
     *
     * @return array<int, mixed>
     */
    private function fetchTransfers(
        string $apiKey,
        string $chain,
        string $address,
        string $direction,
        int $limit
    ): array {
        $subdomain = self::NETWORK_SUBDOMAINS[$chain] ?? null;
        $contracts = $this->contractsFor($chain);

        if ($subdomain === null || $contracts === []) {
            return [];
        }

        $response = Http::timeout(30)->post("https://{$subdomain}.g.alchemy.com/v2/{$apiKey}", [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'alchemy_getAssetTransfers',
            'params'  => [[
                'fromBlock'         => '0x0',
                'toBlock'           => 'latest',
                'category'          => ['erc20'],
                'contractAddresses' => $contracts,
                'withMetadata'      => true,
                'excludeZeroValue'  => true,
                'order'             => 'desc',
                'maxCount'          => '0x' . dechex($limit),
                $direction          => $address,
            ]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Alchemy API returned HTTP {$response->status()}");
        }

        $transfers = $response->json('result.transfers');

        return is_array($transfers) ? $transfers : [];
    }

    /**
     * Monitored USDC/USDT contract addresses for a chain.
     *
     * @return array<int, string>
     */
    private function contractsFor(string $chain): array
    {
        return array_values(array_filter([
            EvmTokens::USDC[$chain] ?? null,
            EvmTokens::USDT[$chain] ?? null,
        ]));
    }

    /**
     * Adapt an Alchemy `getAssetTransfers` transfer to the address-activity
     * shape {@see EvmTransactionProcessor::processActivity()} expects.
     *
     * @return array<string, mixed>|null
     */
    private function normalizeTransfer(mixed $transfer): ?array
    {
        if (! is_array($transfer)) {
            return null;
        }

        $hash = $transfer['hash'] ?? null;

        if (! is_string($hash) || $hash === '') {
            return null;
        }

        $rawContract = is_array($transfer['rawContract'] ?? null) ? $transfer['rawContract'] : [];

        return [
            'hash'        => $hash,
            'fromAddress' => $transfer['from'] ?? '',
            'toAddress'   => $transfer['to'] ?? '',
            'value'       => $transfer['value'] ?? null,
            'asset'       => $transfer['asset'] ?? '',
            'category'    => $transfer['category'] ?? 'erc20',
            'blockNum'    => $transfer['blockNum'] ?? null,
            'rawContract' => [
                // getAssetTransfers names it `value`; the webhook names it `rawValue`.
                'rawValue' => $rawContract['value'] ?? null,
                'address'  => $rawContract['address'] ?? null,
            ],
        ];
    }

    /**
     * Pull the ISO block timestamp from a transfer's metadata, if present.
     */
    private function blockTimestamp(mixed $transfer): ?string
    {
        if (! is_array($transfer)) {
            return null;
        }

        $metadata = $transfer['metadata'] ?? null;
        $timestamp = is_array($metadata) ? ($metadata['blockTimestamp'] ?? null) : null;

        return is_string($timestamp) && $timestamp !== '' ? $timestamp : null;
    }
}
