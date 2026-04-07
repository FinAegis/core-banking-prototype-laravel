<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Services\AlchemyWebhookManager;
use Illuminate\Console\Command;

class EvmWebhookSyncCommand extends Command
{
    protected $signature = 'evm:sync-webhooks
        {--network= : Sync a specific network (ethereum, polygon, arbitrum, base)}
        {--dry-run : Show count without syncing}';

    protected $description = 'Sync all EVM smart account addresses to Alchemy webhooks';

    public function handle(AlchemyWebhookManager $manager): int
    {
        $networks = $this->option('network')
            ? [(string) $this->option('network')]
            : ['ethereum', 'polygon', 'arbitrum', 'base'];

        foreach ($networks as $network) {
            if ($this->option('dry-run')) {
                $count = SmartAccount::where('network', $network)->count();
                $this->info("{$network}: {$count} addresses would be synced");

                continue;
            }

            $count = $manager->syncAllAddresses($network);
            $this->info("{$network}: synced {$count} addresses");
        }

        return self::SUCCESS;
    }
}
