<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Events\AccountBalanceUpdated;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Wallet\Services\WalletService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalanceSynchronizationService
{
    private CustodianRegistry $custodianRegistry;

    private WalletService $walletService;

    private array $syncResults = [];

    public function __construct(CustodianRegistry $custodianRegistry, WalletService $walletService)
    {
        $this->custodianRegistry = $custodianRegistry;
        $this->walletService = $walletService;
    }

    /**
     * Synchronize balances for all active custodian accounts.
     */
    public function synchronizeAllBalances(): array
    {
        $this->syncResults = [
            'synchronized' => 0,
            'failed'       => 0,
            'skipped'      => 0,
            'start_time'   => now(),
            'details'      => [],
        ];

        $custodianAccounts = $this->getActiveCustodianAccounts();

        Log::info(
            'Starting balance synchronization',
            [
                'total_accounts' => $custodianAccounts->count(),
            ]
        );

        foreach ($custodianAccounts as $custodianAccount) {
            $this->synchronizeAccountBalance($custodianAccount);
        }

        $this->syncResults['end_time'] = now();
        $this->syncResults['duration'] = $this->syncResults['end_time']->diffInSeconds($this->syncResults['start_time']);

        Log::info('Balance synchronization completed', $this->syncResults);

        return $this->syncResults;
    }

    /**
     * Synchronize balance for a specific account.
     */
    public function synchronizeAccountBalance(CustodianAccount $custodianAccount): bool
    {
        try {
            // Skip if recently synchronized
            if ($this->isRecentlySynchronized($custodianAccount)) {
                $this->recordSyncResult($custodianAccount, 'skipped', 'Recently synchronized');

                return true;
            }

            // Get custodian connector
            $connector = $this->custodianRegistry->getConnector($custodianAccount->custodian_id);

            if (! $connector->isAvailable()) {
                $this->recordSyncResult($custodianAccount, 'failed', 'Custodian not available');

                return false;
            }

            // Get account info from custodian
            $accountInfo = $connector->getAccountInfo($custodianAccount->external_account_id);

            // Update balances
            $this->updateAccountBalances($custodianAccount, $accountInfo);

            // Update sync timestamp
            $custodianAccount->update(
                [
                    'last_synced_at' => now(),
                    'sync_status'    => 'success',
                    'sync_error'     => null,
                ]
            );

            $this->recordSyncResult($custodianAccount, 'synchronized', 'Success');

            return true;
        } catch (Exception $e) {
            Log::error(
                'Balance synchronization failed',
                [
                    'custodian_account_id' => $custodianAccount->id,
                    'error'                => $e->getMessage(),
                ]
            );

            // Update sync status
            $custodianAccount->update(
                [
                    'last_synced_at' => now(),
                    'sync_status'    => 'failed',
                    'sync_error'     => $e->getMessage(),
                ]
            );

            $this->recordSyncResult($custodianAccount, 'failed', $e->getMessage());

            return false;
        }
    }

    /**
     * Synchronize balances for a specific internal account.
     */
    public function synchronizeAccountBalancesByInternalAccount(string $accountUuid): array
    {
        $results = [];

        $custodianAccounts = CustodianAccount::where('account_uuid', $accountUuid)
            ->where('status', 'active')
            ->get();

        foreach ($custodianAccounts as $custodianAccount) {
            $results[$custodianAccount->custodian_id] = $this->synchronizeAccountBalance($custodianAccount);
        }

        return $results;
    }

    /**
     * Get active custodian accounts that need synchronization.
     */
    private function getActiveCustodianAccounts(): Collection
    {
        return CustodianAccount::where('status', 'active')
            ->where(
                function ($query) {
                    $query->whereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<', now()->subMinutes(5));
                }
            )
            ->orderBy('last_synced_at', 'asc')
            ->get();
    }

    /**
     * Check if account was recently synchronized.
     */
    private function isRecentlySynchronized(CustodianAccount $custodianAccount): bool
    {
        if (! $custodianAccount->last_synced_at) {
            return false;
        }

        // Skip if synchronized within last minute
        return $custodianAccount->last_synced_at->isAfter(now()->subMinute());
    }

    /**
     * Update account balances from custodian data.
     */
    private function updateAccountBalances(CustodianAccount $custodianAccount, AccountInfo $accountInfo): void
    {
        DB::transaction(
            function () use ($custodianAccount, $accountInfo) {
                $account = Account::findOrFail($custodianAccount->account_uuid);

                foreach ($accountInfo->balances as $assetCode => $amountInCents) {
                    $currentBalance = $account->getBalance($assetCode);

                    if ($currentBalance !== $amountInCents) {
                        // Calculate the difference
                        $difference = $amountInCents - $currentBalance;

                        // Update the balance using WalletService
                        $accountUuid = AccountUuid::fromString($account->uuid);
                        if ($difference > 0) {
                            $this->walletService->deposit($accountUuid, $assetCode, $difference);
                        } else {
                            $this->walletService->withdraw($accountUuid, $assetCode, abs($difference));
                        }

                        // Fire balance updated event
                        event(
                            new AccountBalanceUpdated(
                                accountUuid: $account->uuid,
                                custodianId: $custodianAccount->custodian_id,
                                assetCode: $assetCode,
                                previousBalance: $currentBalance,
                                newBalance: $amountInCents,
                                source: 'synchronization'
                            )
                        );

                        Log::info(
                            'Account balance updated',
                            [
                                'account_uuid'     => $account->uuid,
                                'custodian_id'     => $custodianAccount->custodian_id,
                                'asset_code'       => $assetCode,
                                'previous_balance' => $currentBalance,
                                'new_balance'      => $amountInCents,
                                'difference'       => $difference,
                            ]
                        );
                    }
                }

                // Update custodian account metadata
                $custodianAccount->update(
                    [
                        'metadata' => array_merge(
                            $custodianAccount->metadata ?? [],
                            [
                                'last_known_balances' => $accountInfo->balances,
                                'account_status'      => $accountInfo->status,
                                'synchronized_at'     => now()->toISOString(),
                            ]
                        ),
                    ]
                );
            }
        );
    }

    /**
     * Record sync result for reporting.
     */
    private function recordSyncResult(CustodianAccount $custodianAccount, string $status, string $message): void
    {
        $this->syncResults[$status]++;
        $this->syncResults['details'][] = [
            'custodian_account_id' => $custodianAccount->id,
            'account_uuid'         => $custodianAccount->account_uuid,
            'custodian_id'         => $custodianAccount->custodian_id,
            'external_account_id'  => $custodianAccount->external_account_id,
            'status'               => $status,
            'message'              => $message,
            'timestamp'            => now()->toISOString(),
        ];
    }

    /**
     * Get synchronization statistics.
     */
    public function getSynchronizationStats(): array
    {
        $totalAccounts = CustodianAccount::where('status', 'active')->count();
        $syncedLastHour = CustodianAccount::where('status', 'active')
            ->where('last_synced_at', '>=', now()->subHour())
            ->count();
        $failedLastHour = CustodianAccount::where('status', 'active')
            ->where('sync_status', 'failed')
            ->where('last_synced_at', '>=', now()->subHour())
            ->count();
        $neverSynced = CustodianAccount::where('status', 'active')
            ->whereNull('last_synced_at')
            ->count();

        return [
            'total_accounts'   => $totalAccounts,
            'synced_last_hour' => $syncedLastHour,
            'failed_last_hour' => $failedLastHour,
            'never_synced'     => $neverSynced,
            'sync_rate'        => $totalAccounts > 0 ? round(($syncedLastHour / $totalAccounts) * 100, 2) : 0,
            'failure_rate'     => $syncedLastHour > 0 ? round(($failedLastHour / $syncedLastHour) * 100, 2) : 0,
            'last_sync_run'    => $this->syncResults['end_time'] ?? null,
        ];
    }
}
