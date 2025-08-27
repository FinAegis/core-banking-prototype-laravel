<?php

namespace App\Domain\Wallet\Workflows\Activities;

use App\Domain\Account\Models\Account;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Services\KeyManagementService;
use App\Models\User;
use Brick\Math\BigDecimal;
use Exception;
use Illuminate\Support\Facades\DB;

class BlockchainWithdrawalActivities
{
    public function __construct(
        private BlockchainWalletService $walletService,
        private KeyManagementService $keyManager,
        private array $connectors // Injected blockchain connectors
    ) {
    }

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
            throw new Exception('Wallet is not active');
        }

        // Basic address validation
        if (empty($toAddress)) {
            throw new Exception('Invalid destination address');
        }

        // Validate amount
        if (BigDecimal::of($amount)->isLessThanOrEqualTo(0)) {
            throw new Exception('Invalid withdrawal amount');
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
        if (! $code) {
            throw new Exception('Two-factor authentication code required');
        }

        // Verify 2FA code (placeholder - would integrate with actual 2FA service)
        /** @var User|null $$user */
        $$user = User::find($userId);
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
            throw new Exception('Daily withdrawal limit exceeded');
        }
    }

    public function checkWhitelistedAddress(string $walletId, string $toAddress): void
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();

        $settings = json_decode($wallet->settings, true) ?? [];
        $whitelistedAddresses = $settings['whitelisted_addresses'] ?? [];

        if (! empty($whitelistedAddresses) && ! in_array($toAddress, $whitelistedAddresses)) {
            throw new Exception('Address not whitelisted');
        }
    }

    public function getUserFiatAccount(string $userId): string
    {
        $account = DB::table('accounts')
            ->where('user_id', $userId)
            ->where('currency', 'USD')
            ->where('status', 'active')
            ->first();

        if (! $account) {
            throw new Exception('No active USD account found');
        }

        return $account->id;
    }

    public function calculateFees(string $chain, string $amount): array
    {
        // Fee calculation based on chain
        $networkFeeMultiplier = match ($chain) {
            'ethereum' => '0.002',
            'bitcoin'  => '0.0015',
            'polygon'  => '0.0005',
            default    => '0.001'
        };

        $platformFeeMultiplier = '0.001'; // 0.1%

        $networkFee = BigDecimal::of($amount)->multipliedBy($networkFeeMultiplier);
        $platformFee = BigDecimal::of($amount)->multipliedBy($platformFeeMultiplier);
        $totalFee = $networkFee->plus($platformFee);

        return [
            'network_fee'  => $networkFee->toScale(8)->__toString(),
            'platform_fee' => $platformFee->toScale(8)->__toString(),
            'total_fee'    => $totalFee->toScale(8)->__toString(),
        ];
    }

    public function estimateGasPrice(string $chain): string
    {
        // Placeholder for gas price estimation
        // In production, this would query the blockchain
        return match ($chain) {
            'ethereum' => '50', // Gwei
            'polygon'  => '30',
            default    => '1'
        };
    }

    public function getExchangeRate(string $symbol, string $currency = 'USD'): string
    {
        // Placeholder for exchange rate service
        // In production, this would query a price oracle
        $rates = [
            'BTC'   => '43000',
            'ETH'   => '2200',
            'MATIC' => '0.65',
        ];

        return $rates[$symbol] ?? '1';
    }

    public function lockAccountBalance(string $accountId, string $amount): void
    {
        /** @var Account|null $account */
        $account = Account::find($accountId);
        if (! $account) {
            throw new Exception('Account not found');
        }

        // This would interact with the Account aggregate to lock funds
        // For now, we'll use a simple DB update
        DB::table('account_balance_locks')->insert(
            [
                'account_id' => $accountId,
                'amount'     => $amount,
                'reason'     => 'blockchain_withdrawal',
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
            ]
        );
    }

    private function getNextNonce(string $address, string $chain): int
    {
        // In production, this would query the blockchain
        // For now, return a placeholder
        return rand(1, 1000);
    }

    public function signTransaction(string $walletId, array $transactionData): string
    {
        // Get wallet's encrypted private key
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();

        // Decrypt the private key
        $privateKey = $this->keyManager->decrypt($wallet->encrypted_private_key);

        // Sign the transaction (placeholder)
        // In production, this would use the appropriate blockchain library
        $signature = hash('sha256', json_encode($transactionData) . $privateKey);

        // Clear the private key from memory
        unset($privateKey);

        return $signature;
    }

    public function waitForConfirmations(string $txHash, string $chain): array
    {
        // In production, this would poll the blockchain for confirmations
        // For now, we'll simulate waiting
        sleep(2);

        return [
            'confirmations' => 6,
            'status'        => 'confirmed',
            'block_number'  => rand(1000000, 2000000),
        ];
    }

    public function updateWithdrawalStatus(
        string $withdrawalId,
        string $status,
        array $confirmationData
    ): void {
        DB::table('blockchain_withdrawals')
            ->where('withdrawal_id', $withdrawalId)
            ->update(
                [
                    'status'        => $status,
                    'confirmations' => $confirmationData['confirmations'],
                    'confirmed_at'  => $status === 'completed' ? now() : null,
                    'updated_at'    => now(),
                ]
            );
    }

    public function processAccountingEntries(
        string $accountId,
        string $walletId,
        string $amount,
        array $fees
    ): void {
        // Debit user's fiat account
        /** @var Account|null $$account */
        $$account = Account::find($accountId);
        if ($account) {
            $totalAmount = BigDecimal::of($amount)->plus($fees['total_fee']);

            // This would normally use the Account aggregate's methods
            DB::table('transactions')->insert(
                [
                    'account_id'  => $accountId,
                    'type'        => 'debit',
                    'amount'      => $totalAmount->toScale(2)->__toString(),
                    'description' => 'Blockchain withdrawal',
                    'reference'   => $walletId,
                    'created_at'  => now(),
                ]
            );
        }

        // Record fees as revenue
        if (BigDecimal::of($fees['platform_fee'])->isGreaterThan(0)) {
            DB::table('transactions')->insert(
                [
                    'account_id'  => 'revenue_account', // Placeholder
                    'type'        => 'credit',
                    'amount'      => $fees['platform_fee'],
                    'description' => 'Withdrawal platform fee',
                    'created_at'  => now(),
                ]
            );
        }
    }

    public function notifyUser(string $userId, string $withdrawalId, string $status): void
    {
        // Send notification to user
        /** @var User|null $user */
        $user = User::find($userId);
        if ($user) {
            // This would trigger a notification
            // For now, just log it
            DB::table('notifications')->insert(
                [
                    'user_id' => $userId,
                    'type'    => 'blockchain_withdrawal',
                    'data'    => json_encode(
                        [
                            'withdrawal_id' => $withdrawalId,
                            'status'        => $status,
                        ]
                    ),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function compensateFailedWithdrawal(string $withdrawalId): void
    {
        $withdrawal = DB::table('blockchain_withdrawals')
            ->where('withdrawal_id', $withdrawalId)
            ->first();

        if (! $withdrawal) {
            return;
        }

        // Unlock account balance
        DB::table('account_balance_locks')
            ->where('account_id', $withdrawal->account_id)
            ->where('reason', 'blockchain_withdrawal')
            ->delete();

        // Update withdrawal status
        DB::table('blockchain_withdrawals')
            ->where('withdrawal_id', $withdrawalId)
            ->update(
                [
                    'status'     => 'failed',
                    'failed_at'  => now(),
                    'updated_at' => now(),
                ]
            );

        // Reverse any accounting entries
        DB::table('transactions')
            ->where('reference', $withdrawal->wallet_id)
            ->where('created_at', '>=', $withdrawal->created_at)
            ->delete();
    }

    public function verifyAccountBalance(string $accountId, string $amount): void
    {
        $account = DB::table('accounts')
            ->where('id', $accountId)
            ->first();

        if (! $account) {
            throw new Exception('Account not found');
        }

        if (BigDecimal::of($account->balance)->isLessThan($amount)) {
            throw new Exception('Insufficient balance');
        }
    }

    public function calculateCryptoAmount(
        string $fiatAmount,
        string $asset,
        string $chain,
        ?string $tokenAddress
    ): string {
        $symbol = match ($chain) {
            'bitcoin'  => 'BTC',
            'ethereum' => 'ETH',
            'polygon'  => 'MATIC',
            default    => 'USD'
        };

        $rate = $this->getExchangeRate($symbol);

        // Convert fiat to crypto
        return BigDecimal::of($fiatAmount)->dividedBy($rate, 8)->__toString();
    }

    public function checkHotWalletBalance(
        string $chain,
        string $asset,
        string $cryptoAmount,
        ?string $tokenAddress
    ): void {
        // Check hot wallet has sufficient balance
        // This is a placeholder - in production would check actual wallet balance
        $hotWalletBalance = DB::table('hot_wallet_balances')
            ->where('chain', $chain)
            ->where('asset', $asset)
            ->first();

        if (! $hotWalletBalance || BigDecimal::of($hotWalletBalance->balance)->isLessThan($cryptoAmount)) {
            throw new Exception('Insufficient hot wallet balance');
        }
    }

    public function createWithdrawalRecord(
        string $userId,
        string $walletId,
        string $chain,
        string $toAddress,
        string $fiatAmount,
        string $cryptoAmount,
        string $asset,
        ?string $tokenAddress
    ): string {
        $withdrawalId = uniqid('withdrawal_');

        DB::table('blockchain_withdrawals')->insert([
            'withdrawal_id' => $withdrawalId,
            'user_id'       => $userId,
            'wallet_id'     => $walletId,
            'chain'         => $chain,
            'to_address'    => $toAddress,
            'amount_fiat'   => $fiatAmount,
            'amount_crypto' => $cryptoAmount,
            'asset'         => $asset,
            'token_address' => $tokenAddress,
            'status'        => 'pending',
            'created_at'    => now(),
        ]);

        return $withdrawalId;
    }

    public function debitFiatAccount(
        string $accountId,
        string $amount,
        string $description,
        array $metadata
    ): void {
        DB::table('transactions')->insert([
            'account_id'  => $accountId,
            'type'        => 'debit',
            'amount'      => $amount,
            'description' => $description,
            'metadata'    => json_encode($metadata),
            'created_at'  => now(),
        ]);

        DB::table('accounts')
            ->where('id', $accountId)
            ->decrement('balance', (int) $amount);
    }

    public function prepareTransaction(
        string $chain,
        string $toAddress,
        string $cryptoAmount,
        string $asset,
        ?string $tokenAddress
    ): array {
        // Get hot wallet for this chain
        $hotWallet = DB::table('hot_wallets')
            ->where('chain', $chain)
            ->where('status', 'active')
            ->first();

        if (! $hotWallet) {
            throw new Exception('No active hot wallet for chain');
        }

        $fees = $this->calculateFees($chain, $cryptoAmount);

        // Use the original method with full parameters
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $hotWallet->wallet_id)
            ->first();

        // Calculate total amount including fees
        $totalAmount = BigDecimal::of($cryptoAmount)->plus($fees['total_fee']);

        // Prepare transaction data
        return [
            'wallet_id'     => $hotWallet->wallet_id,
            'from_address'  => $wallet->address,
            'to_address'    => $toAddress,
            'amount'        => $cryptoAmount,
            'fee'           => $fees['total_fee'],
            'gas_price'     => $this->estimateGasPrice($chain),
            'nonce'         => $this->getNextNonce($wallet->address, $chain),
            'chain'         => $chain,
            'asset'         => $asset,
            'token_address' => $tokenAddress,
        ];
    }

    public function broadcastTransaction(string $chain, array $transaction): string
    {
        // Sign the transaction
        $signature = $this->signTransaction($transaction['wallet_id'] ?? '', $transaction);

        // Get the appropriate connector
        $connector = $this->connectors[$chain] ?? null;

        if (! $connector) {
            throw new Exception("No connector available for chain: $chain");
        }

        // Broadcast the transaction (placeholder)
        // In production, this would actually broadcast to the blockchain
        $txHash = '0x' . hash('sha256', $signature . time());

        // Store transaction record
        DB::table('blockchain_transactions')->insert(
            [
                'chain'        => $chain,
                'type'         => 'withdrawal',
                'tx_hash'      => $txHash,
                'from_address' => $transaction['from_address'],
                'to_address'   => $transaction['to_address'],
                'amount'       => $transaction['amount'],
                'fee'          => $transaction['fee'],
                'status'       => 'pending',
                'created_at'   => now(),
            ]
        );

        return $txHash;
    }

    public function updateWithdrawalRecord(
        string $withdrawalId,
        ?string $transactionHash,
        string $status,
        ?string $errorMessage = null
    ): void {
        $update = [
            'status'     => $status,
            'updated_at' => now(),
        ];

        if ($transactionHash) {
            $update['tx_hash'] = $transactionHash;
        }

        if ($errorMessage) {
            $update['error_message'] = $errorMessage;
        }

        if ($status === 'processing') {
            $update['processed_at'] = now();
        } elseif ($status === 'completed') {
            $update['confirmed_at'] = now();
        } elseif ($status === 'failed') {
            $update['failed_at'] = now();
        }

        DB::table('blockchain_withdrawals')
            ->where('withdrawal_id', $withdrawalId)
            ->update($update);
    }

    public function monitorTransaction(
        string $chain,
        string $transactionHash,
        string $withdrawalId
    ): void {
        // Wait for confirmations
        $confirmationData = $this->waitForConfirmations($transactionHash, $chain);

        // Update withdrawal with confirmation data
        $this->updateWithdrawalStatus($withdrawalId, 'completed', $confirmationData);
    }

    public function sendWithdrawalNotification(
        string $userId,
        string $chain,
        string $cryptoAmount,
        string $asset,
        string $fiatAmount,
        string $transactionHash
    ): void {
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type'    => 'blockchain_withdrawal_completed',
            'data'    => json_encode([
                'chain'            => $chain,
                'amount_crypto'    => $cryptoAmount,
                'amount_fiat'      => $fiatAmount,
                'asset'            => $asset,
                'transaction_hash' => $transactionHash,
            ]),
            'created_at' => now(),
        ]);
    }

    public function creditFiatAccount(
        string $accountId,
        string $amount,
        string $description,
        array $metadata
    ): void {
        DB::table('transactions')->insert([
            'account_id'  => $accountId,
            'type'        => 'credit',
            'amount'      => $amount,
            'description' => $description,
            'metadata'    => json_encode($metadata),
            'created_at'  => now(),
        ]);

        DB::table('accounts')
            ->where('id', $accountId)
            ->increment('balance', $amount);
    }
}
