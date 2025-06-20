<?php

namespace App\Console\Commands;

use App\Domain\Custodian\Services\BalanceSynchronizationService;
use App\Models\CustodianAccount;
use Illuminate\Console\Command;

class SynchronizeCustodianBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'custodian:sync-balances 
                            {--account= : Sync specific internal account UUID}
                            {--custodian= : Sync specific custodian ID}
                            {--force : Force sync even if recently synchronized}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize account balances with external custodians';

    private BalanceSynchronizationService $syncService;

    /**
     * Create a new command instance.
     */
    public function __construct(BalanceSynchronizationService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting custodian balance synchronization...');
        
        $accountUuid = $this->option('account');
        $custodianId = $this->option('custodian');
        $force = $this->option('force');
        
        if ($accountUuid) {
            return $this->syncSpecificAccount($accountUuid);
        }
        
        if ($custodianId) {
            return $this->syncSpecificCustodian($custodianId, $force);
        }
        
        return $this->syncAllAccounts();
    }
    
    /**
     * Sync balances for a specific internal account
     */
    private function syncSpecificAccount(string $accountUuid): int
    {
        $this->info("Synchronizing balances for account: {$accountUuid}");
        
        $results = $this->syncService->synchronizeAccountBalancesByInternalAccount($accountUuid);
        
        foreach ($results as $custodianId => $success) {
            if ($success) {
                $this->info("✅ Successfully synchronized with {$custodianId}");
            } else {
                $this->error("❌ Failed to synchronize with {$custodianId}");
            }
        }
        
        return empty(array_filter($results, fn($result) => !$result)) ? 0 : 1;
    }
    
    /**
     * Sync balances for a specific custodian
     */
    private function syncSpecificCustodian(string $custodianId, bool $force): int
    {
        $this->info("Synchronizing balances for custodian: {$custodianId}");
        
        $custodianAccounts = CustodianAccount::active()
            ->forCustodian($custodianId);
            
        if (!$force) {
            $custodianAccounts->needsSynchronization();
        }
        
        $custodianAccounts = $custodianAccounts->get();
        
        if ($custodianAccounts->isEmpty()) {
            $this->warn('No accounts found for synchronization.');
            return 0;
        }
        
        $successCount = 0;
        $failCount = 0;
        
        $bar = $this->output->createProgressBar($custodianAccounts->count());
        $bar->start();
        
        foreach ($custodianAccounts as $account) {
            if ($this->syncService->synchronizeAccountBalance($account)) {
                $successCount++;
            } else {
                $failCount++;
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("✅ Synchronized: {$successCount}");
        $this->error("❌ Failed: {$failCount}");
        
        return $failCount > 0 ? 1 : 0;
    }
    
    /**
     * Sync all active custodian accounts
     */
    private function syncAllAccounts(): int
    {
        $results = $this->syncService->synchronizeAllBalances();
        
        $this->newLine();
        $this->info('📊 Synchronization Summary:');
        $this->info("✅ Synchronized: {$results['synchronized']}");
        $this->warn("⏭️  Skipped: {$results['skipped']}");
        $this->error("❌ Failed: {$results['failed']}");
        $this->info("⏱️  Duration: {$results['duration']} seconds");
        
        if (!empty($results['details'])) {
            $this->newLine();
            
            // Show failed accounts if any
            $failures = array_filter($results['details'], fn($detail) => $detail['status'] === 'failed');
            if (!empty($failures)) {
                $this->error('Failed accounts:');
                foreach ($failures as $failure) {
                    $this->error("  - {$failure['custodian_id']}/{$failure['external_account_id']}: {$failure['message']}");
                }
            }
        }
        
        // Show statistics
        $this->newLine();
        $this->info('📈 Current Statistics:');
        $stats = $this->syncService->getSynchronizationStats();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Active Accounts', $stats['total_accounts']],
                ['Synced in Last Hour', $stats['synced_last_hour']],
                ['Failed in Last Hour', $stats['failed_last_hour']],
                ['Never Synced', $stats['never_synced']],
                ['Sync Rate', "{$stats['sync_rate']}%"],
                ['Failure Rate', "{$stats['failure_rate']}%"],
            ]
        );
        
        return $results['failed'] > 0 ? 1 : 0;
    }
}
