<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Domain\AgentProtocol\Aggregates\AgentTransactionAggregate;
use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\Events\AgentFundedFromMainAccount;
use App\Domain\AgentProtocol\Events\AgentWithdrewToMainAccount;
use App\Domain\AgentProtocol\Models\AgentTransaction;
use App\Domain\AgentProtocol\Models\AgentWallet;
use App\Domain\Asset\Services\ExchangeRateService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Workflow\WorkflowStub;

/**
 * Service that integrates agent wallets with the main payment system.
 *
 * This service bridges the Agent Protocol domain with the core Account domain,
 * allowing seamless fund transfers between agent wallets and main accounts.
 */
class AgentPaymentIntegrationService
{
    public function __construct(
        private readonly ?ExchangeRateService $exchangeRateService = null
    ) {
    }

    /**
     * Fund an agent wallet from a main account.
     *
     * @param string $agentWalletId The agent wallet ID to fund
     * @param string $mainAccountUuid The main account UUID to withdraw from
     * @param float $amount The amount to transfer
     * @param string $currency The currency of the transfer
     * @param array $metadata Additional metadata for the transaction
     * @return AgentTransaction The created transaction record
     * @throws InvalidArgumentException|Exception
     */
    public function fundAgentWallet(
        string $agentWalletId,
        string $mainAccountUuid,
        float $amount,
        string $currency = 'USD',
        array $metadata = []
    ): AgentTransaction {
        DB::beginTransaction();

        try {
            // Validate agent wallet exists
            $agentWallet = AgentWallet::where('wallet_id', $agentWalletId)->firstOrFail();

            // Validate main account exists
            $mainAccount = Account::where('uuid', $mainAccountUuid)->firstOrFail();

            // Validate amount
            if ($amount <= 0) {
                throw new InvalidArgumentException('Amount must be greater than zero');
            }

            // Check main account has sufficient balance
            $mainAccountBalance = $mainAccount->balance ?? 0;
            if ($mainAccountBalance < $amount) {
                throw new InvalidArgumentException('Insufficient balance in main account');
            }

            // Calculate exchange rate if currencies differ
            $convertedAmount = $amount;
            $exchangeRate = 1.0;

            if ($agentWallet->currency !== $currency && $this->exchangeRateService) {
                $exchangeRate = $this->getExchangeRate($currency, $agentWallet->currency);
                $convertedAmount = round($amount * $exchangeRate, 2);
            }

            // Generate transaction ID
            $transactionId = 'agent_fund_' . Str::uuid()->toString();

            // Withdraw from main account using the main payment workflow
            $workflow = WorkflowStub::make(WithdrawAccountWorkflow::class);
            $workflow->start(
                new AccountUuid($mainAccountUuid),
                new Money((int) ($amount * 100)) // Money expects cents
            );

            // Credit agent wallet
            $walletAggregate = AgentWalletAggregate::retrieve($agentWalletId);
            $walletAggregate->receivePayment(
                transactionId: $transactionId,
                fromAgentId: 'main_account_' . $mainAccountUuid,
                amount: $convertedAmount,
                metadata: array_merge($metadata, [
                    'source'            => 'main_account',
                    'main_account_uuid' => $mainAccountUuid,
                    'original_amount'   => $amount,
                    'original_currency' => $currency,
                    'exchange_rate'     => $exchangeRate,
                ])
            );
            $walletAggregate->persist();

            // Update agent wallet read model
            $agentWallet->update([
                'available_balance' => $agentWallet->available_balance + $convertedAmount,
                'total_balance'     => $agentWallet->total_balance + $convertedAmount,
            ]);

            // Create transaction aggregate for audit trail
            $transactionAggregate = AgentTransactionAggregate::initiate(
                transactionId: $transactionId,
                fromAgentId: 'main_account_' . $mainAccountUuid,
                toAgentId: $agentWallet->agent_id,
                amount: $amount,
                currency: $currency,
                type: 'funding',
                metadata: array_merge($metadata, [
                    'integration_type'  => 'main_to_agent',
                    'main_account_uuid' => $mainAccountUuid,
                    'agent_wallet_id'   => $agentWalletId,
                ])
            );
            $transactionAggregate->validate()->complete('success');
            $transactionAggregate->persist();

            // Record event
            event(new AgentFundedFromMainAccount(
                agentWalletId: $agentWalletId,
                mainAccountUuid: $mainAccountUuid,
                amount: $amount,
                currency: $currency,
                convertedAmount: $convertedAmount,
                transactionId: $transactionId
            ));

            // Create transaction record
            $transaction = AgentTransaction::create([
                'transaction_id' => $transactionId,
                'from_agent_id'  => 'main_account_' . $mainAccountUuid,
                'to_agent_id'    => $agentWallet->agent_id,
                'amount'         => $amount,
                'currency'       => $currency,
                'fee_amount'     => 0,
                'fee_type'       => 'none',
                'status'         => 'completed',
                'type'           => 'funding',
                'metadata'       => array_merge($metadata, [
                    'integration_type'  => 'main_to_agent',
                    'main_account_uuid' => $mainAccountUuid,
                    'converted_amount'  => $convertedAmount,
                    'exchange_rate'     => $exchangeRate,
                ]),
            ]);

            DB::commit();

            Log::info('Agent wallet funded from main account', [
                'transaction_id'    => $transactionId,
                'agent_wallet_id'   => $agentWalletId,
                'main_account_uuid' => $mainAccountUuid,
                'amount'            => $amount,
                'currency'          => $currency,
            ]);

            return $transaction;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to fund agent wallet from main account', [
                'error'             => $e->getMessage(),
                'agent_wallet_id'   => $agentWalletId,
                'main_account_uuid' => $mainAccountUuid,
            ]);
            throw $e;
        }
    }

    /**
     * Withdraw from an agent wallet to a main account.
     *
     * @param string $agentWalletId The agent wallet ID to withdraw from
     * @param string $mainAccountUuid The main account UUID to deposit to
     * @param float $amount The amount to transfer
     * @param string $currency The currency of the transfer
     * @param array $metadata Additional metadata for the transaction
     * @return AgentTransaction The created transaction record
     * @throws InvalidArgumentException|Exception
     */
    public function withdrawToMainAccount(
        string $agentWalletId,
        string $mainAccountUuid,
        float $amount,
        string $currency = 'USD',
        array $metadata = []
    ): AgentTransaction {
        DB::beginTransaction();

        try {
            // Validate agent wallet exists
            $agentWallet = AgentWallet::where('wallet_id', $agentWalletId)
                ->lockForUpdate()
                ->firstOrFail();

            // Validate main account exists
            $mainAccount = Account::where('uuid', $mainAccountUuid)->firstOrFail();

            // Validate amount
            if ($amount <= 0) {
                throw new InvalidArgumentException('Amount must be greater than zero');
            }

            // Calculate amount in wallet currency
            $walletAmount = $amount;
            $exchangeRate = 1.0;

            if ($agentWallet->currency !== $currency && $this->exchangeRateService) {
                $exchangeRate = $this->getExchangeRate($currency, $agentWallet->currency);
                $walletAmount = round($amount * $exchangeRate, 2);
            }

            // Check agent wallet has sufficient balance
            if ($agentWallet->available_balance < $walletAmount) {
                throw new InvalidArgumentException('Insufficient balance in agent wallet');
            }

            // Generate transaction ID
            $transactionId = 'agent_withdraw_' . Str::uuid()->toString();

            // Debit agent wallet
            $walletAggregate = AgentWalletAggregate::retrieve($agentWalletId);
            $walletAggregate->holdFunds($walletAmount, 'withdrawal_to_main_account', [
                'main_account_uuid' => $mainAccountUuid,
                'transaction_id'    => $transactionId,
            ]);
            $walletAggregate->releaseFunds($walletAmount, 'withdrawal_completed', [
                'destination' => 'main_account',
            ]);
            $walletAggregate->persist();

            // Update agent wallet read model
            $agentWallet->update([
                'available_balance' => $agentWallet->available_balance - $walletAmount,
                'total_balance'     => $agentWallet->total_balance - $walletAmount,
            ]);

            // Deposit to main account using the main payment workflow
            $workflow = WorkflowStub::make(DepositAccountWorkflow::class);
            $workflow->start(
                new AccountUuid($mainAccountUuid),
                new Money((int) ($amount * 100)) // Money expects cents
            );

            // Create transaction aggregate for audit trail
            $transactionAggregate = AgentTransactionAggregate::initiate(
                transactionId: $transactionId,
                fromAgentId: $agentWallet->agent_id,
                toAgentId: 'main_account_' . $mainAccountUuid,
                amount: $amount,
                currency: $currency,
                type: 'withdrawal',
                metadata: array_merge($metadata, [
                    'integration_type'  => 'agent_to_main',
                    'main_account_uuid' => $mainAccountUuid,
                    'agent_wallet_id'   => $agentWalletId,
                ])
            );
            $transactionAggregate->validate()->complete('success');
            $transactionAggregate->persist();

            // Record event
            event(new AgentWithdrewToMainAccount(
                agentWalletId: $agentWalletId,
                mainAccountUuid: $mainAccountUuid,
                amount: $amount,
                currency: $currency,
                walletAmount: $walletAmount,
                transactionId: $transactionId
            ));

            // Create transaction record
            $transaction = AgentTransaction::create([
                'transaction_id' => $transactionId,
                'from_agent_id'  => $agentWallet->agent_id,
                'to_agent_id'    => 'main_account_' . $mainAccountUuid,
                'amount'         => $amount,
                'currency'       => $currency,
                'fee_amount'     => 0,
                'fee_type'       => 'none',
                'status'         => 'completed',
                'type'           => 'withdrawal',
                'metadata'       => array_merge($metadata, [
                    'integration_type'  => 'agent_to_main',
                    'main_account_uuid' => $mainAccountUuid,
                    'wallet_amount'     => $walletAmount,
                    'exchange_rate'     => $exchangeRate,
                ]),
            ]);

            DB::commit();

            Log::info('Agent wallet withdrew to main account', [
                'transaction_id'    => $transactionId,
                'agent_wallet_id'   => $agentWalletId,
                'main_account_uuid' => $mainAccountUuid,
                'amount'            => $amount,
                'currency'          => $currency,
            ]);

            return $transaction;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to withdraw from agent wallet to main account', [
                'error'             => $e->getMessage(),
                'agent_wallet_id'   => $agentWalletId,
                'main_account_uuid' => $mainAccountUuid,
            ]);
            throw $e;
        }
    }

    /**
     * Get the linked main account for an agent.
     *
     * @param string $agentId The agent's DID
     * @return Account|null The linked main account, or null if not linked
     */
    public function getLinkedMainAccount(string $agentId): ?Account
    {
        // Check if there's a linked account in agent metadata
        $wallet = AgentWallet::where('agent_id', $agentId)->first();

        if (! $wallet || empty($wallet->metadata['linked_main_account'])) {
            return null;
        }

        return Account::where('uuid', $wallet->metadata['linked_main_account'])->first();
    }

    /**
     * Link an agent wallet to a main account.
     *
     * @param string $agentWalletId The agent wallet ID
     * @param string $mainAccountUuid The main account UUID to link
     * @return bool Success status
     */
    public function linkMainAccount(string $agentWalletId, string $mainAccountUuid): bool
    {
        $wallet = AgentWallet::where('wallet_id', $agentWalletId)->first();
        $mainAccount = Account::where('uuid', $mainAccountUuid)->first();

        if (! $wallet || ! $mainAccount) {
            return false;
        }

        $metadata = $wallet->metadata ?? [];
        $metadata['linked_main_account'] = $mainAccountUuid;

        $wallet->update(['metadata' => $metadata]);

        Log::info('Agent wallet linked to main account', [
            'agent_wallet_id'   => $agentWalletId,
            'main_account_uuid' => $mainAccountUuid,
        ]);

        return true;
    }

    /**
     * Get exchange rate between currencies using the main system's exchange rate service.
     */
    private function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        if ($this->exchangeRateService) {
            try {
                $rate = $this->exchangeRateService->getRate($fromCurrency, $toCurrency);

                return $rate ? (float) $rate->rate : 1.0;
            } catch (Exception $e) {
                Log::warning('Failed to get exchange rate from main service', [
                    'from'  => $fromCurrency,
                    'to'    => $toCurrency,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to default rates
        return 1.0;
    }

    /**
     * Get transaction history between agent wallets and main accounts.
     *
     * @param string $agentId The agent's DID
     * @param int $limit Maximum number of transactions to return
     * @return array Transaction history
     */
    public function getIntegrationTransactionHistory(string $agentId, int $limit = 50): array
    {
        return AgentTransaction::where(function ($query) use ($agentId) {
            $query->where('from_agent_id', $agentId)
                ->orWhere('to_agent_id', $agentId);
        })
            ->where(function ($query) {
                $query->where('type', 'funding')
                    ->orWhere('type', 'withdrawal');
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'transaction_id' => $transaction->transaction_id,
                    'type'           => $transaction->type,
                    'direction'      => $transaction->type === 'funding' ? 'incoming' : 'outgoing',
                    'amount'         => $transaction->amount,
                    'currency'       => $transaction->currency,
                    'status'         => $transaction->status,
                    'main_account'   => $transaction->metadata['main_account_uuid'] ?? null,
                    'created_at'     => $transaction->created_at,
                ];
            })
            ->toArray();
    }
}
