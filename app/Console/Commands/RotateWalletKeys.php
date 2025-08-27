<?php

namespace App\Console\Commands;

use App\Domain\Wallet\Models\SecureKeyStorage;
use App\Domain\Wallet\Services\SecureKeyStorageService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RotateWalletKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:rotate-keys 
                            {--wallet=* : Specific wallet IDs to rotate}
                            {--all : Rotate keys for all wallets}
                            {--force : Force rotation without confirmation}
                            {--reason= : Reason for key rotation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate encryption keys for blockchain wallets';

    protected SecureKeyStorageService $keyStorage;

    /**
     * Create a new command instance.
     */
    public function __construct(SecureKeyStorageService $keyStorage)
    {
        parent::__construct();
        $this->keyStorage = $keyStorage;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $walletIds = $this->option('wallet');
        $rotateAll = $this->option('all');
        $force = $this->option('force');
        $reason = $this->option('reason') ?? 'Scheduled key rotation';

        if (empty($walletIds) && ! $rotateAll) {
            $this->error('Please specify wallet IDs with --wallet or use --all flag');

            return self::FAILURE;
        }

        if ($rotateAll) {
            $walletIds = SecureKeyStorage::active()
                ->pluck('wallet_id')
                ->toArray();
        }

        if (empty($walletIds)) {
            $this->info('No wallets found for key rotation');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d wallet(s) for key rotation', count($walletIds)));

        if (! $force) {
            $confirm = $this->confirm(
                sprintf('Are you sure you want to rotate keys for %d wallet(s)?', count($walletIds))
            );

            if (! $confirm) {
                $this->info('Key rotation cancelled');

                return self::SUCCESS;
            }
        }

        $bar = $this->output->createProgressBar(count($walletIds));
        $bar->start();

        $successful = 0;
        $failed = 0;

        foreach ($walletIds as $walletId) {
            try {
                $this->keyStorage->rotateKeys($walletId, 'system', $reason);
                $successful++;

                $this->line(PHP_EOL . "✓ Rotated keys for wallet: {$walletId}");
            } catch (Exception $e) {
                $failed++;
                $this->error(PHP_EOL . "✗ Failed to rotate keys for wallet: {$walletId}");
                $this->error('  Error: ' . $e->getMessage());

                Log::error('Key rotation failed', [
                    'wallet_id' => $walletId,
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Key rotation completed:');
        $this->info("  Successful: {$successful}");

        if ($failed > 0) {
            $this->error("  Failed: {$failed}");
        }

        // Purge any expired temporary keys
        $purged = $this->keyStorage->purgeExpiredKeys();
        if ($purged > 0) {
            $this->info("  Purged {$purged} expired temporary keys");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
