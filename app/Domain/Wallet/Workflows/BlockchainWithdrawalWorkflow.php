<?php

namespace App\Domain\Wallet\Workflows;

use App\Domain\Account\Aggregates\Account;
use App\Domain\Wallet\Workflows\Activities\BlockchainWithdrawalActivities;
use App\Models\User;
use Exception;
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
                'retryAttempts'       => 3,
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
                'chain'         => $chain,
                'to_address'    => $toAddress,
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
                'status'           => 'completed',
                'withdrawal_id'    => $withdrawalId,
                'transaction_hash' => $transactionHash,
                'amount_fiat'      => $amount,
                'amount_crypto'    => $cryptoAmount,
                'asset'            => $asset,
                'chain'            => $chain,
                'to_address'       => $toAddress,
            ];
        } catch (Exception $e) {
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
