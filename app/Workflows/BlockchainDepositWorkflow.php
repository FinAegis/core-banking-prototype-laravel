<?php

namespace App\Workflows;

use App\Domain\Wallet\Aggregates\BlockchainWallet;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Contracts\BlockchainConnector;
use App\Domain\Account\Aggregates\Account;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class BlockchainDepositWorkflow extends Workflow
{
    private ActivityStub $activities;
    
    public function __construct()
    {
        $this->activities = WorkflowStub::newActivityStub(
            BlockchainDepositActivities::class,
            [
                'startToCloseTimeout' => 300, // 5 minutes
                'retryAttempts' => 3,
            ]
        );
    }
    
    public function execute(
        string $walletId,
        string $chain,
        string $transactionHash,
        string $fromAddress,
        string $toAddress,
        string $amount,
        string $asset = 'native',
        ?string $tokenAddress = null
    ) {
        // Step 1: Verify transaction on blockchain
        $transactionData = yield $this->activities->verifyTransaction(
            $chain,
            $transactionHash
        );
        
        if (!$transactionData['confirmed']) {
            // Wait for confirmations
            yield $this->activities->waitForConfirmations(
                $chain,
                $transactionHash,
                6 // Required confirmations
            );
            
            // Re-verify after confirmations
            $transactionData = yield $this->activities->verifyTransaction(
                $chain,
                $transactionHash
            );
        }
        
        // Step 2: Validate transaction details match
        yield $this->activities->validateTransactionDetails(
            $transactionData,
            $toAddress,
            $amount,
            $asset,
            $tokenAddress
        );
        
        // Step 3: Check for duplicate deposits
        $isDuplicate = yield $this->activities->checkDuplicateDeposit(
            $walletId,
            $transactionHash
        );
        
        if ($isDuplicate) {
            return [
                'status' => 'duplicate',
                'message' => 'This transaction has already been processed',
                'transaction_hash' => $transactionHash,
            ];
        }
        
        // Step 4: Record blockchain transaction
        yield $this->activities->recordBlockchainTransaction(
            $walletId,
            $chain,
            $transactionHash,
            $fromAddress,
            $toAddress,
            $amount,
            $asset,
            $transactionData
        );
        
        // Step 5: Get user's fiat account
        $userId = yield $this->activities->getUserIdFromWallet($walletId);
        $accountId = yield $this->activities->getUserFiatAccount($userId, $chain);
        
        // Step 6: Calculate fiat value
        $fiatValue = yield $this->activities->calculateFiatValue(
            $amount,
            $asset,
            $chain,
            $tokenAddress
        );
        
        // Step 7: Credit user's fiat account
        yield $this->activities->creditFiatAccount(
            $accountId,
            $fiatValue,
            "Blockchain deposit from {$chain}",
            [
                'transaction_hash' => $transactionHash,
                'chain' => $chain,
                'asset' => $asset,
                'amount' => $amount,
            ]
        );
        
        // Step 8: Update token balance if ERC20/BEP20
        if ($asset !== 'native' && $tokenAddress) {
            yield $this->activities->updateTokenBalance(
                $walletId,
                $toAddress,
                $chain,
                $tokenAddress,
                $amount
            );
        }
        
        // Step 9: Send notification
        yield $this->activities->sendDepositNotification(
            $userId,
            $chain,
            $amount,
            $asset,
            $fiatValue,
            $transactionHash
        );
        
        return [
            'status' => 'completed',
            'transaction_hash' => $transactionHash,
            'amount' => $amount,
            'asset' => $asset,
            'fiat_value' => $fiatValue,
            'chain' => $chain,
        ];
    }
}

class BlockchainDepositActivities
{
    public function __construct(
        private BlockchainWalletService $walletService,
        private array $connectors // Injected blockchain connectors
    ) {}
    
    public function verifyTransaction(string $chain, string $transactionHash): array
    {
        $connector = $this->getConnector($chain);
        $tx = $connector->getTransaction($transactionHash);
        
        if (!$tx) {
            throw new \Exception('Transaction not found');
        }
        
        return [
            'hash' => $tx->hash,
            'from' => $tx->from,
            'to' => $tx->to,
            'value' => (string) $tx->value,
            'confirmations' => $tx->metadata['confirmations'] ?? 0,
            'blockNumber' => $tx->blockNumber,
            'gasUsed' => (string) $tx->gasLimit,
            'gasPrice' => (string) $tx->gasPrice,
            'confirmed' => $tx->status === 'confirmed',
        ];
    }
    
    public function waitForConfirmations(
        string $chain,
        string $transactionHash,
        int $requiredConfirmations
    ): void {
        $connector = $this->getConnector($chain);
        $currentConfirmations = 0;
        
        while ($currentConfirmations < $requiredConfirmations) {
            sleep(30); // Wait 30 seconds between checks
            $tx = $connector->getTransaction($transactionHash);
            if ($tx) {
                $currentConfirmations = $tx->metadata['confirmations'] ?? 0;
            }
        }
    }
    
    public function validateTransactionDetails(
        array $transactionData,
        string $expectedToAddress,
        string $expectedAmount,
        string $expectedAsset,
        ?string $expectedTokenAddress
    ): void {
        if (strtolower($transactionData['to']) !== strtolower($expectedToAddress)) {
            throw new \Exception('Transaction recipient does not match');
        }
        
        if ($expectedAsset === 'native') {
            if ($transactionData['value'] !== $expectedAmount) {
                throw new \Exception('Transaction amount does not match');
            }
        } else {
            // For tokens, verify the token transfer event
            $tokenTransfer = $this->parseTokenTransfer($transactionData);
            if (strtolower($tokenTransfer['token']) !== strtolower($expectedTokenAddress)) {
                throw new \Exception('Token address does not match');
            }
            if ($tokenTransfer['amount'] !== $expectedAmount) {
                throw new \Exception('Token amount does not match');
            }
        }
    }
    
    public function checkDuplicateDeposit(string $walletId, string $transactionHash): bool
    {
        return DB::table('blockchain_transactions')
            ->where('wallet_id', $walletId)
            ->where('transaction_hash', $transactionHash)
            ->exists();
    }
    
    public function recordBlockchainTransaction(
        string $walletId,
        string $chain,
        string $transactionHash,
        string $fromAddress,
        string $toAddress,
        string $amount,
        string $asset,
        array $transactionData
    ): void {
        DB::table('blockchain_transactions')->insert([
            'wallet_id' => $walletId,
            'chain' => $chain,
            'transaction_hash' => $transactionHash,
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
            'amount' => $amount,
            'asset' => $asset,
            'gas_used' => $transactionData['gasUsed'] ?? null,
            'gas_price' => $transactionData['gasPrice'] ?? null,
            'status' => 'confirmed',
            'confirmations' => $transactionData['confirmations'] ?? 0,
            'block_number' => $transactionData['blockNumber'] ?? null,
            'metadata' => json_encode($transactionData),
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    public function getUserIdFromWallet(string $walletId): string
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->firstOrFail();
            
        return $wallet->user_id;
    }
    
    public function getUserFiatAccount(string $userId, string $chain): string
    {
        // Get user's primary USD account
        $account = DB::table('accounts')
            ->where('user_id', $userId)
            ->where('currency', 'USD')
            ->where('status', 'active')
            ->first();
            
        if (!$account) {
            // Create account if doesn't exist
            $accountId = 'acc_' . uniqid();
            $accountAggregate = Account::create(
                $accountId,
                $userId,
                'savings',
                'USD'
            );
            $accountAggregate->persist();
            
            return $accountId;
        }
        
        return $account->account_id;
    }
    
    public function calculateFiatValue(
        string $amount,
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
            $rate = $rates[$chain]['native'] ?? '0';
            $cryptoAmount = BigDecimal::of($amount)->dividedBy(
                BigDecimal::of('10')->power(18), // Most chains use 18 decimals
                18,
                \Brick\Math\RoundingMode::DOWN
            );
            
            return $cryptoAmount->multipliedBy($rate)->toScale(2, \Brick\Math\RoundingMode::DOWN)->__toString();
        }
        
        // For tokens, would need to fetch token price
        return '0.00';
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
    
    public function updateTokenBalance(
        string $walletId,
        string $address,
        string $chain,
        string $tokenAddress,
        string $amount
    ): void {
        // For now, we'll use placeholder token info
        // In production, this would fetch from blockchain or cache
        DB::table('token_balances')->updateOrInsert(
            [
                'address' => $address,
                'chain' => $chain,
                'token_address' => $tokenAddress,
            ],
            [
                'wallet_id' => $walletId,
                'symbol' => 'TOKEN',
                'name' => 'Token',
                'decimals' => 18,
                'balance' => DB::raw("balance + {$amount}"),
                'updated_at' => now(),
            ]
        );
    }
    
    public function sendDepositNotification(
        string $userId,
        string $chain,
        string $amount,
        string $asset,
        string $fiatValue,
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
    
    private function parseTokenTransfer(array $transactionData): array
    {
        // Parse ERC20/BEP20 transfer from transaction logs
        // This is a simplified version
        return [
            'token' => $transactionData['to'],
            'amount' => $transactionData['input'] ?? '0',
        ];
    }
}