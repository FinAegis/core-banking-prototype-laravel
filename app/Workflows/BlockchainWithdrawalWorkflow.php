<?php

namespace App\Workflows;

use App\Domain\Wallet\Aggregates\BlockchainWallet;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Services\KeyManagementService;
use App\Domain\Wallet\Contracts\BlockchainConnector;
use App\Domain\Account\Aggregates\Account;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class BlockchainWithdrawalWorkflow extends Workflow
{
    private ActivityStub $activities;
    
    public function __construct()
    {
        $this->activities = WorkflowStub::newActivityStub(
            BlockchainWithdrawalActivities::class,
            [
                'startToCloseTimeout' => 600, // 10 minutes
                'retryAttempts' => 3,
            ]
        );
    }
    
    public function execute(
        string $userId,
        string $walletId,
        string $chain,
        string $toAddress,
        string $amount, // Amount in fiat
        string $asset = 'native',
        ?string $tokenAddress = null,
        ?string $twoFactorCode = null
    ) {
        // Step 1: Validate withdrawal request
        yield $this->activities->validateWithdrawalRequest(
            $userId,
            $walletId,
            $chain,
            $toAddress,
            $amount
        );
        
        // Step 2: Check 2FA if required
        $requires2FA = yield $this->activities->checkTwoFactorRequirement($walletId);
        if ($requires2FA) {
            yield $this->activities->verifyTwoFactorCode($userId, $twoFactorCode);
        }
        
        // Step 3: Check daily withdrawal limit
        yield $this->activities->checkDailyLimit($walletId, $amount);
        
        // Step 4: Check if address is whitelisted (if whitelisting enabled)
        yield $this->activities->checkWhitelistedAddress($walletId, $toAddress);
        
        // Step 5: Get user's fiat account and verify balance
        $accountId = yield $this->activities->getUserFiatAccount($userId);
        yield $this->activities->verifyAccountBalance($accountId, $amount);
        
        // Step 6: Calculate crypto amount based on current rate
        $cryptoAmount = yield $this->activities->calculateCryptoAmount(
            $amount,
            $asset,
            $chain,
            $tokenAddress
        );
        
        // Step 7: Check hot wallet balance
        yield $this->activities->checkHotWalletBalance(
            $chain,
            $asset,
            $cryptoAmount,
            $tokenAddress
        );
        
        // Step 8: Create withdrawal record (pending)
        $withdrawalId = yield $this->activities->createWithdrawalRecord(
            $userId,
            $walletId,
            $chain,
            $toAddress,
            $amount,
            $cryptoAmount,
            $asset,
            $tokenAddress
        );
        
        // Step 9: Debit user's fiat account
        yield $this->activities->debitFiatAccount(
            $accountId,
            $amount,
            "Blockchain withdrawal to {$chain}",
            [
                'withdrawal_id' => $withdrawalId,
                'chain' => $chain,
                'to_address' => $toAddress,
            ]
        );
        
        try {
            // Step 10: Prepare and sign transaction
            $transaction = yield $this->activities->prepareTransaction(
                $chain,
                $toAddress,
                $cryptoAmount,
                $asset,
                $tokenAddress
            );
            
            // Step 11: Broadcast transaction
            $transactionHash = yield $this->activities->broadcastTransaction(
                $chain,
                $transaction
            );
            
            // Step 12: Update withdrawal record with transaction hash
            yield $this->activities->updateWithdrawalRecord(
                $withdrawalId,
                $transactionHash,
                'processing'
            );
            
            // Step 13: Monitor transaction
            yield $this->activities->monitorTransaction(
                $chain,
                $transactionHash,
                $withdrawalId
            );
            
            // Step 14: Send notification
            yield $this->activities->sendWithdrawalNotification(
                $userId,
                $chain,
                $cryptoAmount,
                $asset,
                $amount,
                $transactionHash
            );
            
            return [
                'status' => 'completed',
                'withdrawal_id' => $withdrawalId,
                'transaction_hash' => $transactionHash,
                'amount_fiat' => $amount,
                'amount_crypto' => $cryptoAmount,
                'asset' => $asset,
                'chain' => $chain,
                'to_address' => $toAddress,
            ];
            
        } catch (\Exception $e) {
            // Rollback: Credit the account back
            yield $this->activities->creditFiatAccount(
                $accountId,
                $amount,
                "Withdrawal reversal - {$e->getMessage()}",
                ['withdrawal_id' => $withdrawalId]
            );
            
            // Update withdrawal status
            yield $this->activities->updateWithdrawalRecord(
                $withdrawalId,
                null,
                'failed',
                $e->getMessage()
            );
            
            throw $e;
        }
    }
}

class BlockchainWithdrawalActivities
{
    public function __construct(
        private BlockchainWalletService $walletService,
        private KeyManagementService $keyManager,
        private array $connectors // Injected blockchain connectors
    ) {}
    
    public function validateWithdrawalRequest(
        string $userId,
        string $walletId,
        string $chain,
        string $toAddress,
        string $amount
    ): void {
        // Verify wallet ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $userId)
            ->firstOrFail();
            
        // Check wallet status
        if ($wallet->status !== 'active') {
            throw new \Exception('Wallet is not active');
        }
        
        // Basic address validation
        if (empty($toAddress)) {
            throw new \Exception('Invalid destination address');
        }
        
        // Validate amount
        if (BigDecimal::of($amount)->isLessThanOrEqualTo(0)) {
            throw new \Exception('Invalid withdrawal amount');
        }
    }
    
    public function checkTwoFactorRequirement(string $walletId): bool
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();
            
        $settings = json_decode($wallet->settings, true) ?? [];
        return $settings['requires_2fa'] ?? false;
    }
    
    public function verifyTwoFactorCode(string $userId, ?string $code): void
    {
        if (!$code) {
            throw new \Exception('Two-factor authentication code required');
        }
        
        // Verify 2FA code (placeholder - would integrate with actual 2FA service)
        $user = User::find($userId);
        // Verification logic here
    }
    
    public function checkDailyLimit(string $walletId, string $amount): void
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();
            
        $settings = json_decode($wallet->settings, true) ?? [];
        $dailyLimit = BigDecimal::of($settings['daily_limit'] ?? '10000');
        
        // Calculate today's withdrawals
        $todayWithdrawals = DB::table('blockchain_withdrawals')
            ->where('wallet_id', $walletId)
            ->whereDate('created_at', today())
            ->whereIn('status', ['completed', 'processing'])
            ->sum('amount_fiat');
            
        $totalToday = BigDecimal::of($todayWithdrawals ?: '0')->plus($amount);
        
        if ($totalToday->isGreaterThan($dailyLimit)) {
            throw new \Exception('Daily withdrawal limit exceeded');
        }
    }
    
    public function checkWhitelistedAddress(string $walletId, string $toAddress): void
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();
            
        $settings = json_decode($wallet->settings, true) ?? [];
        $whitelistedAddresses = $settings['whitelisted_addresses'] ?? [];
        
        if (!empty($whitelistedAddresses) && !in_array($toAddress, $whitelistedAddresses)) {
            throw new \Exception('Address not whitelisted');
        }
    }
    
    public function getUserFiatAccount(string $userId): string
    {
        $account = DB::table('accounts')
            ->where('user_id', $userId)
            ->where('currency', 'USD')
            ->where('status', 'active')
            ->firstOrFail();
            
        return $account->account_id;
    }
    
    public function verifyAccountBalance(string $accountId, string $amount): void
    {
        $account = Account::retrieve($accountId);
        $balance = $account->getBalance();
        
        if ($balance->isLessThan($amount)) {
            throw new \Exception('Insufficient balance');
        }
    }
    
    public function calculateCryptoAmount(
        string $fiatAmount,
        string $asset,
        string $chain,
        ?string $tokenAddress
    ): string {
        // This would integrate with price oracles
        // For now, using placeholder rates
        $rates = [
            'ethereum' => ['native' => '2500.00'],
            'polygon' => ['native' => '0.75'],
            'bsc' => ['native' => '300.00'],
            'bitcoin' => ['native' => '45000.00'],
        ];
        
        if ($asset === 'native') {
            $rate = BigDecimal::of($rates[$chain]['native'] ?? '1');
            $cryptoAmount = BigDecimal::of($fiatAmount)->dividedBy(
                $rate,
                18,
                \Brick\Math\RoundingMode::DOWN
            );
            
            // Convert to wei/satoshi
            return $cryptoAmount->multipliedBy(
                BigDecimal::of('10')->power(18)
            )->toScale(0, \Brick\Math\RoundingMode::DOWN)->__toString();
        }
        
        // For tokens, would need to fetch token price and decimals
        return '0';
    }
    
    public function checkHotWalletBalance(
        string $chain,
        string $asset,
        string $amount,
        ?string $tokenAddress
    ): void {
        // Get hot wallet address for this chain
        $hotWallet = $this->getHotWalletAddress($chain);
        
        $connector = $this->getConnector($chain);
        
        if ($asset === 'native') {
            $balanceData = $connector->getBalance($hotWallet);
            $balance = $balanceData->balance;
        } else {
            // Token balances would be fetched from getTokenBalances
            $tokenBalances = $connector->getTokenBalances($hotWallet);
            $balance = '0';
            foreach ($tokenBalances as $token) {
                if (isset($token['address']) && $token['address'] === $tokenAddress) {
                    $balance = $token['balance'] ?? '0';
                    break;
                }
            }
        }
        
        if (BigDecimal::of($balance)->isLessThan($amount)) {
            throw new \Exception('Insufficient hot wallet balance');
        }
    }
    
    public function createWithdrawalRecord(
        string $userId,
        string $walletId,
        string $chain,
        string $toAddress,
        string $amountFiat,
        string $amountCrypto,
        string $asset,
        ?string $tokenAddress
    ): string {
        $withdrawalId = 'wd_' . uniqid();
        
        DB::table('blockchain_withdrawals')->insert([
            'withdrawal_id' => $withdrawalId,
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'chain' => $chain,
            'to_address' => $toAddress,
            'amount_fiat' => $amountFiat,
            'amount_crypto' => $amountCrypto,
            'asset' => $asset,
            'token_address' => $tokenAddress,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $withdrawalId;
    }
    
    public function debitFiatAccount(
        string $accountId,
        string $amount,
        string $description,
        array $metadata
    ): void {
        $account = Account::retrieve($accountId);
        $account->debit(
            BigDecimal::of($amount),
            $description,
            $metadata
        );
        $account->persist();
    }
    
    public function creditFiatAccount(
        string $accountId,
        string $amount,
        string $description,
        array $metadata
    ): void {
        $account = Account::retrieve($accountId);
        $account->credit(
            BigDecimal::of($amount),
            $description,
            $metadata
        );
        $account->persist();
    }
    
    public function prepareTransaction(
        string $chain,
        string $toAddress,
        string $amount,
        string $asset,
        ?string $tokenAddress
    ): array {
        // Simplified transaction preparation
        // In production, this would use proper blockchain libraries
        $hotWallet = $this->getHotWalletAddress($chain);
        
        return [
            'from' => $hotWallet,
            'to' => $toAddress,
            'amount' => $amount,
            'asset' => $asset,
            'tokenAddress' => $tokenAddress,
            'chain' => $chain,
            'nonce' => time(), // Placeholder
            'gasPrice' => '20000000000', // Placeholder
            'gasLimit' => '21000', // Placeholder
        ];
    }
    
    public function broadcastTransaction(string $chain, array $signedTransaction): string
    {
        // Simplified broadcasting
        // In production, this would broadcast to the actual blockchain
        return '0x' . hash('sha256', json_encode($signedTransaction));
    }
    
    public function updateWithdrawalRecord(
        string $withdrawalId,
        ?string $transactionHash,
        string $status,
        ?string $error = null
    ): void {
        $updates = [
            'status' => $status,
            'updated_at' => now(),
        ];
        
        if ($transactionHash) {
            $updates['transaction_hash'] = $transactionHash;
        }
        
        if ($error) {
            $updates['error_message'] = $error;
        }
        
        if ($status === 'completed') {
            $updates['completed_at'] = now();
        }
        
        DB::table('blockchain_withdrawals')
            ->where('withdrawal_id', $withdrawalId)
            ->update($updates);
    }
    
    public function monitorTransaction(
        string $chain,
        string $transactionHash,
        string $withdrawalId
    ): void {
        $connector = $this->getConnector($chain);
        $confirmed = false;
        $attempts = 0;
        $maxAttempts = 60; // 30 minutes with 30-second intervals
        
        while (!$confirmed && $attempts < $maxAttempts) {
            $tx = $connector->getTransaction($transactionHash);
            
            if ($tx && $tx['confirmations'] >= 6) {
                $confirmed = true;
                $this->updateWithdrawalRecord($withdrawalId, null, 'completed');
            }
            
            if (!$confirmed) {
                sleep(30);
                $attempts++;
            }
        }
        
        if (!$confirmed) {
            throw new \Exception('Transaction confirmation timeout');
        }
    }
    
    public function sendWithdrawalNotification(
        string $userId,
        string $chain,
        string $cryptoAmount,
        string $asset,
        string $fiatAmount,
        string $transactionHash
    ): void {
        $user = User::find($userId);
        
        // Send notification (placeholder)
        // In production, this would send email/push notification
    }
    
    private function getConnector(string $chain): BlockchainConnector
    {
        if (!isset($this->connectors[$chain])) {
            throw new \Exception("Unsupported blockchain: {$chain}");
        }
        
        return $this->connectors[$chain];
    }
    
    private function getHotWalletAddress(string $chain): string
    {
        // In production, this would retrieve from secure configuration
        return config("blockchain.hot_wallets.{$chain}.address");
    }
    
    private function getHotWalletPrivateKey(string $chain): string
    {
        // In production, this would retrieve from HSM or secure key storage
        $encryptedKey = config("blockchain.hot_wallets.{$chain}.encrypted_key");
        return $this->keyManager->decryptPrivateKey($encryptedKey, 'system');
    }
}