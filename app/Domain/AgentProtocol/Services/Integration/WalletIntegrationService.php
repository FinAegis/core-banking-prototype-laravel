<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services\Integration;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\Events\Integration\AgentWalletLinked;
use App\Domain\AgentProtocol\Events\Integration\CrossDomainTransactionInitiated;
use App\Domain\AgentProtocol\Events\Integration\WalletBalanceSynchronized;
use App\Domain\AgentProtocol\Models\AgentWallet;
use App\Domain\AgentProtocol\Services\AgentWalletService;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Services\KeyManagementService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Integration service to bridge Agent Protocol wallets with the main payment system.
 *
 * This service provides:
 * - Agent wallet to main account linking
 * - Cross-domain transaction processing
 * - Balance synchronization
 * - Event propagation between domains
 */
class WalletIntegrationService
{
    public function __construct(
        private readonly AgentWalletService $agentWalletService,
        private readonly BlockchainWalletService $blockchainService,
        private readonly KeyManagementService $keyManagementService
    ) {
    }

    /**
     * Link an agent wallet to a main system account.
     */
    public function linkAgentWalletToAccount(
        string $agentWalletId,
        string $accountUuid,
        array $options = []
    ): array {
        try {
            DB::beginTransaction();

            // Get agent wallet
            $agentWallet = AgentWallet::where('wallet_id', $agentWalletId)->firstOrFail();

            // Get main account
            $account = Account::where('uuid', $accountUuid)->firstOrFail();

            // Update agent wallet with link
            $agentWallet->linked_account_uuid = $accountUuid;
            $agentWallet->linked_at = now();
            $agentWallet->link_metadata = array_merge($agentWallet->link_metadata ?? [], [
                'account_name' => $account->name,
                'link_type'    => $options['link_type'] ?? 'standard',
                'permissions'  => $options['permissions'] ?? ['read', 'transfer'],
            ]);
            $agentWallet->save();

            // If blockchain integration is enabled, create blockchain wallet
            if ($options['enable_blockchain'] ?? false) {
                $blockchainWallet = $this->blockchainService->createWallet([
                    'label'    => "Agent Wallet {$agentWalletId}",
                    'owner_id' => $agentWallet->agent_id,
                    'type'     => 'agent',
                ]);

                $agentWallet->blockchain_address = $blockchainWallet['address'];
                $agentWallet->save();
            }

            // Sync initial balance
            $this->syncWalletBalance($agentWalletId);

            // Emit integration event
            Event::dispatch(new AgentWalletLinked(
                agentWalletId: $agentWalletId,
                accountUuid: $accountUuid,
                linkType: $options['link_type'] ?? 'standard',
                metadata: $agentWallet->link_metadata
            ));

            DB::commit();

            Log::info('Agent wallet linked to account', [
                'agent_wallet_id'    => $agentWalletId,
                'account_uuid'       => $accountUuid,
                'blockchain_enabled' => $options['enable_blockchain'] ?? false,
            ]);

            return [
                'success'            => true,
                'agent_wallet_id'    => $agentWalletId,
                'account_uuid'       => $accountUuid,
                'blockchain_address' => $agentWallet->blockchain_address,
                'linked_at'          => $agentWallet->linked_at->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to link agent wallet to account', [
                'error'           => $e->getMessage(),
                'agent_wallet_id' => $agentWalletId,
                'account_uuid'    => $accountUuid,
            ]);
            throw $e;
        }
    }

    /**
     * Process a cross-domain transaction between agent wallet and main system.
     */
    public function processCrossDomainTransaction(
        string $fromWalletId,
        string $toAccountUuid,
        float $amount,
        string $currency = 'USD',
        array $metadata = []
    ): array {
        try {
            DB::beginTransaction();

            $transactionId = 'cross_tx_' . Str::uuid()->toString();

            // Get source wallet
            $agentWallet = AgentWallet::where('wallet_id', $fromWalletId)->firstOrFail();

            // Get destination account
            $account = Account::where('uuid', $toAccountUuid)->firstOrFail();

            // Validate sufficient balance
            if ($agentWallet->balance < $amount) {
                throw new Exception('Insufficient balance in agent wallet');
            }

            // Create agent transaction
            $agentTxId = $this->agentWalletService->createTransaction(
                walletId: $fromWalletId,
                amount: -$amount,
                type: 'cross_domain_transfer',
                metadata: array_merge($metadata, [
                    'destination_account' => $toAccountUuid,
                    'cross_domain_tx_id'  => $transactionId,
                ])
            );

            // Update main account balance
            $account->balance = (int) ($account->balance + ($amount * 100)); // Convert to cents
            $account->save();

            // Create transaction record in main system
            $mainTransaction = Transaction::create([
                'aggregate_uuid'    => $transactionId,
                'aggregate_version' => 1,
                'event_version'     => 1,
                'event_class'       => CrossDomainTransactionInitiated::class,
                'event_properties'  => [
                    'transaction_id'    => $transactionId,
                    'from_agent_wallet' => $fromWalletId,
                    'to_account'        => $toAccountUuid,
                    'amount'            => $amount,
                    'currency'          => $currency,
                    'agent_tx_id'       => $agentTxId,
                    'metadata'          => $metadata,
                ],
                'meta_data' => [
                    'user_id'    => $account->user_uuid,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
            ]);

            // Emit cross-domain event
            Event::dispatch(new CrossDomainTransactionInitiated(
                transactionId: $transactionId,
                fromAgentWallet: $fromWalletId,
                toAccount: $toAccountUuid,
                amount: $amount,
                currency: $currency,
                metadata: $metadata
            ));

            DB::commit();

            Log::info('Cross-domain transaction processed', [
                'transaction_id' => $transactionId,
                'from_wallet'    => $fromWalletId,
                'to_account'     => $toAccountUuid,
                'amount'         => $amount,
            ]);

            return [
                'success'        => true,
                'transaction_id' => $transactionId,
                'agent_tx_id'    => $agentTxId,
                'main_tx_id'     => $mainTransaction->id,
                'from_wallet'    => $fromWalletId,
                'to_account'     => $toAccountUuid,
                'amount'         => $amount,
                'currency'       => $currency,
                'timestamp'      => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to process cross-domain transaction', [
                'error'       => $e->getMessage(),
                'from_wallet' => $fromWalletId,
                'to_account'  => $toAccountUuid,
                'amount'      => $amount,
            ]);
            throw $e;
        }
    }

    /**
     * Synchronize wallet balance between agent and main system.
     */
    public function syncWalletBalance(string $agentWalletId): array
    {
        try {
            $agentWallet = AgentWallet::where('wallet_id', $agentWalletId)->firstOrFail();

            if (! $agentWallet->linked_account_uuid) {
                throw new Exception('Agent wallet is not linked to an account');
            }

            $account = Account::where('uuid', $agentWallet->linked_account_uuid)->firstOrFail();

            // Calculate balance difference
            $accountBalanceInDollars = $account->balance / 100; // Convert from cents
            $balanceDiff = abs($agentWallet->balance - $accountBalanceInDollars);

            // Sync if difference exceeds threshold
            if ($balanceDiff > 0.01) {
                $oldBalance = $agentWallet->balance;

                // Update agent wallet balance to match account
                $aggregate = AgentWalletAggregate::retrieve($agentWallet->wallet_id);
                $aggregate->syncBalance($accountBalanceInDollars);
                $aggregate->persist();

                // Emit synchronization event
                Event::dispatch(new WalletBalanceSynchronized(
                    agentWalletId: $agentWalletId,
                    accountUuid: $agentWallet->linked_account_uuid,
                    oldBalance: $oldBalance,
                    newBalance: $accountBalanceInDollars,
                    syncedAt: now()
                ));

                Log::info('Wallet balance synchronized', [
                    'wallet_id'   => $agentWalletId,
                    'old_balance' => $oldBalance,
                    'new_balance' => $accountBalanceInDollars,
                    'difference'  => $balanceDiff,
                ]);

                return [
                    'success'     => true,
                    'synced'      => true,
                    'wallet_id'   => $agentWalletId,
                    'old_balance' => $oldBalance,
                    'new_balance' => $accountBalanceInDollars,
                    'difference'  => $balanceDiff,
                ];
            }

            return [
                'success'   => true,
                'synced'    => false,
                'wallet_id' => $agentWalletId,
                'balance'   => $agentWallet->balance,
                'message'   => 'Balance already in sync',
            ];
        } catch (Exception $e) {
            Log::error('Failed to sync wallet balance', [
                'error'     => $e->getMessage(),
                'wallet_id' => $agentWalletId,
            ]);
            throw $e;
        }
    }

    /**
     * Get integration status for an agent wallet.
     */
    public function getIntegrationStatus(string $agentWalletId): array
    {
        try {
            $agentWallet = AgentWallet::where('wallet_id', $agentWalletId)->firstOrFail();

            $status = [
                'wallet_id'          => $agentWalletId,
                'agent_id'           => $agentWallet->agent_id,
                'is_linked'          => ! empty($agentWallet->linked_account_uuid),
                'linked_account'     => $agentWallet->linked_account_uuid,
                'linked_at'          => $agentWallet->linked_at?->toIso8601String(),
                'blockchain_enabled' => ! empty($agentWallet->blockchain_address),
                'blockchain_address' => $agentWallet->blockchain_address,
                'balance'            => $agentWallet->balance,
                'currency'           => $agentWallet->currency,
            ];

            if ($agentWallet->linked_account_uuid) {
                $account = Account::where('uuid', $agentWallet->linked_account_uuid)->first();
                if ($account) {
                    $status['account_balance'] = $account->balance / 100;
                    $status['balance_in_sync'] = abs($agentWallet->balance - ($account->balance / 100)) < 0.01;
                }
            }

            return $status;
        } catch (Exception $e) {
            Log::error('Failed to get integration status', [
                'error'     => $e->getMessage(),
                'wallet_id' => $agentWalletId,
            ]);
            throw $e;
        }
    }

    /**
     * Handle incoming blockchain transaction for agent wallet.
     */
    public function handleBlockchainTransaction(
        string $agentWalletId,
        string $txHash,
        float $amount,
        string $direction = 'incoming'
    ): array {
        try {
            DB::beginTransaction();

            $agentWallet = AgentWallet::where('wallet_id', $agentWalletId)->firstOrFail();

            if (! $agentWallet->blockchain_address) {
                throw new Exception('Agent wallet does not have blockchain integration');
            }

            // Create transaction in agent wallet
            $txId = $this->agentWalletService->createTransaction(
                walletId: $agentWalletId,
                amount: $direction === 'incoming' ? $amount : -$amount,
                type: 'blockchain_' . $direction,
                metadata: [
                    'tx_hash'            => $txHash,
                    'blockchain_network' => 'ethereum',
                    'confirmations'      => 1,
                ]
            );

            // If linked to account, update account balance
            if ($agentWallet->linked_account_uuid) {
                $account = Account::where('uuid', $agentWallet->linked_account_uuid)->first();
                if ($account) {
                    $amountInCents = (int) ($amount * 100);
                    $account->balance = $direction === 'incoming'
                        ? $account->balance + $amountInCents
                        : $account->balance - $amountInCents;
                    $account->save();
                }
            }

            DB::commit();

            return [
                'success'        => true,
                'transaction_id' => $txId,
                'tx_hash'        => $txHash,
                'amount'         => $amount,
                'direction'      => $direction,
                'new_balance'    => $agentWallet->fresh()->balance,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to handle blockchain transaction', [
                'error'     => $e->getMessage(),
                'wallet_id' => $agentWalletId,
                'tx_hash'   => $txHash,
            ]);
            throw $e;
        }
    }
}
