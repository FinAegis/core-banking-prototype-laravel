<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Services\HeliusWebhookSyncService;
use Illuminate\Console\Command;

/**
 * Sync all Solana addresses to Helius webhook monitoring.
 *
 * Run periodically or after bulk imports to ensure all user
 * Solana addresses are registered with the Helius webhook.
 */
class SolanaWebhookSyncCommand extends Command
{
    protected $signature = 'solana:sync
        {--dry-run : Show count without updating the webhook}';

    protected $description = 'Sync all Solana blockchain addresses to the Helius webhook provider';

    public function handle(
        HeliusWebhookSyncService $heliusService,
    ): int {
        if ($this->option('dry-run')) {
            $count = BlockchainAddress::where('chain', 'solana')
                ->where('is_active', true)
                ->count();
            $this->info("Would sync {$count} Solana addresses to helius webhook.");

            return self::SUCCESS;
        }

        $this->info('Syncing Solana addresses to helius...');

        $count = $heliusService->syncAllAddresses();

        if ($count === 0) {
            $dbCount = BlockchainAddress::where('chain', 'solana')
                ->where('is_active', true)
                ->count();

            if ($dbCount === 0) {
                $this->warn('No Solana addresses found in blockchain_addresses table. Call /api/v1/wallet/addresses to auto-register user addresses first.');
            } else {
                $this->warn('No addresses synced. Check HELIUS_API_KEY and HELIUS_WEBHOOK_ID are set.');
            }

            return self::FAILURE;
        }

        $this->info("Synced {$count} Solana addresses to helius webhook.");

        return self::SUCCESS;
    }
}
