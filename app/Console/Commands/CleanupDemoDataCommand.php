<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupDemoDataCommand extends Command
{
    protected $signature = 'demo:cleanup 
                            {--days=1 : Number of days to keep demo data}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up old demo data';

    public function handle(): int
    {
        if (! config('demo.mode')) {
            $this->error('This command can only be run in demo mode');

            return Command::FAILURE;
        }

        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Cleaning up demo data older than {$days} day(s)...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        DB::beginTransaction();

        try {
            // Clean up demo transactions
            $transactionsQuery = TransactionProjection::where('created_at', '<', $cutoffDate)
                ->where(function ($query) {
                    $query->whereJsonContains('metadata->demo', true)
                        ->orWhere('reference', 'like', 'demo_%')
                        ->orWhere('external_reference', 'like', 'demo_%');
                });

            $transactionCount = $transactionsQuery->count();
            if ($transactionCount > 0) {
                $this->info("Found {$transactionCount} demo transactions to delete");
                if (! $dryRun) {
                    $transactionsQuery->delete();
                }
            }

            // Clean up demo accounts
            $accountsQuery = Account::where('created_at', '<', $cutoffDate)
                ->whereHas('user', function ($query) {
                    /** @phpstan-ignore-next-line */
                    $query->where('email', 'like', '%@demo.finaegis.com');
                });

            $accountCount = $accountsQuery->count();
            if ($accountCount > 0) {
                $this->info("Found {$accountCount} demo accounts to delete");
                if (! $dryRun) {
                    $accountsQuery->delete();
                }
            }

            // Clean up demo users
            $usersQuery = User::where('created_at', '<', $cutoffDate)
                ->where('email', 'like', '%@demo.finaegis.com');

            $userCount = $usersQuery->count();
            if ($userCount > 0) {
                $this->info("Found {$userCount} demo users to delete");
                if (! $dryRun) {
                    $usersQuery->delete();
                }
            }

            // Clean up event sourcing tables for demo data
            $eventTables = [
                'account_events',
                'exchange_events',
                'stablecoin_events',
                'lending_events',
                'wallet_events',
            ];

            foreach ($eventTables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $eventsQuery = DB::table($table)
                        ->where('created_at', '<', $cutoffDate)
                        ->whereJsonContains('event_properties->demo', true);

                    $eventCount = $eventsQuery->count();
                    if ($eventCount > 0) {
                        $this->info("Found {$eventCount} demo events in {$table} to delete");
                        if (! $dryRun) {
                            $eventsQuery->delete();
                        }
                    }
                }
            }

            // Clean up demo blockchain records
            $blockchainQuery = DB::table('blockchain_addresses')
                ->where('created_at', '<', $cutoffDate)
                ->where('address', 'like', 'demo_%');

            $blockchainCount = $blockchainQuery->count();
            if ($blockchainCount > 0) {
                $this->info("Found {$blockchainCount} demo blockchain addresses to delete");
                if (! $dryRun) {
                    $blockchainQuery->delete();
                }
            }

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry run completed - no data was deleted');
            } else {
                DB::commit();
                $this->info('Demo data cleanup completed successfully');

                // Log the cleanup
                Log::info('Demo data cleanup completed', [
                    'cutoff_date'          => $cutoffDate->toDateTimeString(),
                    'transactions_deleted' => $transactionCount,
                    'accounts_deleted'     => $accountCount,
                    'users_deleted'        => $userCount,
                ]);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during cleanup: ' . $e->getMessage());
            Log::error('Demo data cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
