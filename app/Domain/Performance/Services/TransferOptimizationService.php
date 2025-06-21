<?php

declare(strict_types=1);

namespace App\Domain\Performance\Services;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Domain\Account\DataObjects\AccountUuid;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service for optimizing transfer performance
 * Implements caching strategies and query optimizations
 */
class TransferOptimizationService
{
    private const ACCOUNT_CACHE_TTL = 300; // 5 minutes
    private const BALANCE_CACHE_TTL = 60; // 1 minute
    
    /**
     * Get account with caching
     */
    public function getAccountWithCache(AccountUuid $accountUuid): ?Account
    {
        $uuid = (string) $accountUuid;
        $cacheKey = "account:{$uuid}";
        
        return Cache::remember($cacheKey, self::ACCOUNT_CACHE_TTL, function () use ($uuid) {
            return Account::with(['balances', 'user'])
                ->where('uuid', $uuid)
                ->first();
        });
    }
    
    /**
     * Get account balance with caching
     */
    public function getBalanceWithCache(string $accountUuid, string $assetCode): ?AccountBalance
    {
        $cacheKey = "balance:{$accountUuid}:{$assetCode}";
        
        return Cache::remember($cacheKey, self::BALANCE_CACHE_TTL, function () use ($accountUuid, $assetCode) {
            return AccountBalance::where('account_uuid', $accountUuid)
                ->where('asset_code', $assetCode)
                ->first();
        });
    }
    
    /**
     * Pre-validate transfer in a single query
     */
    public function preValidateTransfer(
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $fromAssetCode,
        int $amount
    ): array {
        $fromUuid = (string) $fromAccountUuid;
        $toUuid = (string) $toAccountUuid;
        
        // Single query to fetch both accounts and balance
        $result = DB::select("
            SELECT 
                a1.uuid as from_uuid,
                a1.frozen as from_frozen,
                a2.uuid as to_uuid,
                a2.frozen as to_frozen,
                ab.balance as from_balance
            FROM accounts a1
            CROSS JOIN accounts a2
            LEFT JOIN account_balances ab ON ab.account_uuid = a1.uuid AND ab.asset_code = ?
            WHERE a1.uuid = ? AND a2.uuid = ?
        ", [$fromAssetCode, $fromUuid, $toUuid]);
        
        if (empty($result)) {
            throw new \Exception("One or both accounts not found");
        }
        
        $data = $result[0];
        
        // Validate accounts
        if (!$data->from_uuid) {
            throw new \Exception("Source account not found: {$fromUuid}");
        }
        
        if (!$data->to_uuid) {
            throw new \Exception("Destination account not found: {$toUuid}");
        }
        
        if ($data->from_frozen) {
            throw new \Exception("Source account is frozen");
        }
        
        if ($data->to_frozen) {
            throw new \Exception("Destination account is frozen");
        }
        
        // Validate balance
        if (!$data->from_balance || $data->from_balance < $amount) {
            $balance = $data->from_balance ?? 0;
            throw new \Exception(
                "Insufficient {$fromAssetCode} balance. Required: {$amount}, Available: {$balance}"
            );
        }
        
        return [
            'from_balance' => $data->from_balance,
            'validation_passed' => true,
        ];
    }
    
    /**
     * Batch validate multiple transfers
     */
    public function batchValidateTransfers(array $transfers): array
    {
        $accountUuids = [];
        $balanceChecks = [];
        
        // Collect all unique account UUIDs and balance checks
        foreach ($transfers as $transfer) {
            $accountUuids[] = $transfer['from_account'];
            $accountUuids[] = $transfer['to_account'];
            $balanceChecks[] = [
                'account_uuid' => $transfer['from_account'],
                'asset_code' => $transfer['from_asset'],
                'amount' => $transfer['amount'],
            ];
        }
        
        $accountUuids = array_unique($accountUuids);
        
        // Fetch all accounts in one query
        $accounts = Account::whereIn('uuid', $accountUuids)
            ->get()
            ->keyBy('uuid');
        
        // Fetch all required balances in one query
        $balanceQuery = AccountBalance::query();
        foreach ($balanceChecks as $check) {
            $balanceQuery->orWhere(function ($q) use ($check) {
                $q->where('account_uuid', $check['account_uuid'])
                  ->where('asset_code', $check['asset_code']);
            });
        }
        
        $balances = $balanceQuery->get()
            ->mapWithKeys(function ($balance) {
                return ["{$balance->account_uuid}:{$balance->asset_code}" => $balance];
            });
        
        // Validate each transfer
        $results = [];
        foreach ($transfers as $index => $transfer) {
            try {
                $fromAccount = $accounts->firstWhere('uuid', $transfer['from_account']);
                $toAccount = $accounts->firstWhere('uuid', $transfer['to_account']);
                
                if (!$fromAccount || !$toAccount) {
                    throw new \Exception("Account not found");
                }
                
                if ($fromAccount->frozen || $toAccount->frozen) {
                    throw new \Exception("Account is frozen");
                }
                
                $balanceKey = "{$transfer['from_account']}:{$transfer['from_asset']}";
                $balance = $balances->get($balanceKey);
                
                if (!$balance || $balance->balance < $transfer['amount']) {
                    throw new \Exception("Insufficient balance");
                }
                
                $results[$index] = ['valid' => true];
            } catch (\Exception $e) {
                $results[$index] = ['valid' => false, 'error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    /**
     * Warm up caches for frequently used accounts
     */
    public function warmUpCaches(array $accountUuids): void
    {
        // Pre-load accounts
        $accounts = Account::with(['balances', 'user'])
            ->whereIn('uuid', $accountUuids)
            ->get();
        
        foreach ($accounts as $account) {
            $cacheKey = "account:{$account->uuid}";
            Cache::put($cacheKey, $account, self::ACCOUNT_CACHE_TTL);
            
            // Cache balances
            foreach ($account->balances as $balance) {
                $balanceCacheKey = "balance:{$account->uuid}:{$balance->asset_code}";
                Cache::put($balanceCacheKey, $balance, self::BALANCE_CACHE_TTL);
            }
        }
    }
    
    /**
     * Clear transfer-related caches
     */
    public function clearTransferCaches(string $accountUuid, string $assetCode): void
    {
        Cache::forget("account:{$accountUuid}");
        Cache::forget("balance:{$accountUuid}:{$assetCode}");
    }
}