<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Models\CustodianAccount;
use App\Models\CustodianTransfer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FallbackService
{
    /**
     * Cache configuration
     */
    private const BALANCE_CACHE_TTL = 300; // 5 minutes
    private const ACCOUNT_INFO_CACHE_TTL = 3600; // 1 hour
    private const TRANSFER_STATUS_CACHE_TTL = 600; // 10 minutes
    
    /**
     * Get fallback balance from cache or database
     */
    public function getFallbackBalance(string $custodian, string $accountId, string $assetCode): ?Money
    {
        $cacheKey = $this->getBalanceCacheKey($custodian, $accountId, $assetCode);
        
        // Try cache first
        $cachedBalance = Cache::get($cacheKey);
        if ($cachedBalance !== null) {
            Log::info("Using cached balance for fallback", [
                'custodian' => $custodian,
                'account' => $accountId,
                'asset' => $assetCode,
                'balance' => $cachedBalance,
            ]);
            
            return new Money((int) $cachedBalance);
        }
        
        // Try database
        $custodianAccount = CustodianAccount::where('custodian_account_id', $accountId)
            ->where('custodian_name', $custodian)
            ->first();
            
        if ($custodianAccount && $custodianAccount->last_known_balance !== null) {
            Log::info("Using database balance for fallback", [
                'custodian' => $custodian,
                'account' => $accountId,
                'asset' => $assetCode,
                'balance' => $custodianAccount->last_known_balance,
                'last_synced' => $custodianAccount->last_synced_at,
            ]);
            
            // Note: This assumes single currency per account for simplicity
            // In production, we'd need to store multi-currency balances
            return new Money($custodianAccount->last_known_balance);
        }
        
        return null;
    }
    
    /**
     * Cache balance for future fallback use
     */
    public function cacheBalance(string $custodian, string $accountId, string $assetCode, Money $balance): void
    {
        $cacheKey = $this->getBalanceCacheKey($custodian, $accountId, $assetCode);
        Cache::put($cacheKey, $balance->getAmount(), self::BALANCE_CACHE_TTL);
        
        // Also update database if custodian account exists
        $custodianAccount = CustodianAccount::where('custodian_account_id', $accountId)
            ->where('custodian_name', $custodian)
            ->first();
            
        if ($custodianAccount) {
            $custodianAccount->update([
                'last_known_balance' => $balance->getAmount(),
                'last_synced_at' => now(),
            ]);
        }
    }
    
    /**
     * Get fallback account info from cache
     */
    public function getFallbackAccountInfo(string $custodian, string $accountId): ?AccountInfo
    {
        $cacheKey = $this->getAccountInfoCacheKey($custodian, $accountId);
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("Using cached account info for fallback", [
                'custodian' => $custodian,
                'account' => $accountId,
            ]);
            
            return unserialize($cached);
        }
        
        return null;
    }
    
    /**
     * Cache account info for future fallback use
     */
    public function cacheAccountInfo(string $custodian, string $accountId, AccountInfo $info): void
    {
        $cacheKey = $this->getAccountInfoCacheKey($custodian, $accountId);
        Cache::put($cacheKey, serialize($info), self::ACCOUNT_INFO_CACHE_TTL);
    }
    
    /**
     * Get fallback transfer status from database
     */
    public function getFallbackTransferStatus(string $custodian, string $transferId): ?TransactionReceipt
    {
        $transfer = CustodianTransfer::where('id', $transferId)
            ->first();
            
        if ($transfer) {
            Log::info("Using database transfer status for fallback", [
                'custodian' => $custodian,
                'transfer' => $transferId,
                'status' => $transfer->status,
            ]);
            
            return new TransactionReceipt(
                id: $transfer->id,
                status: $transfer->status,
                fromAccount: $transfer->from_account_uuid,
                toAccount: $transfer->to_account_uuid,
                assetCode: $transfer->asset_code,
                amount: $transfer->amount,
                fee: null,
                reference: $transfer->reference,
                createdAt: $transfer->created_at,
                completedAt: $transfer->completed_at,
                metadata: $transfer->metadata ?? [],
                failureReason: $transfer->failure_reason
            );
        }
        
        return null;
    }
    
    /**
     * Queue transfer for retry when custodian is available
     */
    public function queueTransferForRetry(
        string $custodian,
        string $fromAccount,
        string $toAccount,
        Money $amount,
        string $assetCode,
        string $reference,
        string $description
    ): TransactionReceipt {
        // We need custodian account IDs, for now use dummy values
        // In production, these would be resolved from accounts to custodian accounts
        $fromCustodianAccountId = 1;
        $toCustodianAccountId = 2;
        
        // Create a pending transfer record
        $transferId = 'QUEUED_' . \Str::uuid()->toString();
        $transfer = CustodianTransfer::create([
            'id' => $transferId,
            'from_account_uuid' => $fromAccount,
            'to_account_uuid' => $toAccount,
            'from_custodian_account_id' => $fromCustodianAccountId,
            'to_custodian_account_id' => $toCustodianAccountId,
            'amount' => $amount->getAmount(),
            'asset_code' => $assetCode,
            'reference' => $reference,
            'status' => 'pending',
            'transfer_type' => 'external',
            'metadata' => [
                'queued_at' => now()->toIso8601String(),
                'reason' => 'Custodian unavailable',
                'custodian' => $custodian,
                'description' => $description,
            ],
        ]);
        
        Log::warning("Transfer queued for retry", [
            'custodian' => $custodian,
            'transfer_id' => $transfer->id,
            'amount' => $amount->getAmount(),
            'asset' => $assetCode,
        ]);
        
        // Return a receipt indicating the transfer is queued
        return new TransactionReceipt(
            id: $transfer->id,
            status: 'pending',
            fromAccount: $fromAccount,
            toAccount: $toAccount,
            assetCode: $assetCode,
            amount: $amount->getAmount(),
            fee: null,
            reference: $reference,
            createdAt: now(),
            completedAt: null,
            metadata: [
                'queued' => true,
                'retry_after' => now()->addMinutes(5)->toIso8601String(),
                'custodian' => $custodian,
            ]
        );
    }
    
    /**
     * Get alternative custodian for fallback routing
     */
    public function getAlternativeCustodian(string $failedCustodian, string $assetCode): ?string
    {
        // This would be configured based on business rules
        // For now, simple hardcoded fallback routing
        $fallbackRoutes = [
            'paysera' => ['deutsche_bank', 'santander'],
            'deutsche_bank' => ['santander', 'paysera'],
            'santander' => ['paysera', 'deutsche_bank'],
        ];
        
        $alternatives = $fallbackRoutes[strtolower($failedCustodian)] ?? [];
        
        // Check which alternatives are available
        foreach ($alternatives as $alternative) {
            $registry = app(\App\Domain\Custodian\Services\CustodianRegistry::class);
            
            try {
                $connector = $registry->getConnector($alternative);
                if ($connector->isAvailable()) {
                    Log::info("Found alternative custodian", [
                        'failed' => $failedCustodian,
                        'alternative' => $alternative,
                        'asset' => $assetCode,
                    ]);
                    
                    return $alternative;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return null;
    }
    
    // Cache key helpers
    private function getBalanceCacheKey(string $custodian, string $accountId, string $assetCode): string
    {
        return "custodian:fallback:{$custodian}:{$accountId}:{$assetCode}:balance";
    }
    
    private function getAccountInfoCacheKey(string $custodian, string $accountId): string
    {
        return "custodian:fallback:{$custodian}:{$accountId}:info";
    }
}