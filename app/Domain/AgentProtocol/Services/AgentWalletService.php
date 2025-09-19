<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\AgentTransactionAggregate;
use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\Models\AgentTransaction;
use App\Domain\AgentProtocol\Models\AgentWallet;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AgentWalletService
{
    // Supported currencies
    private const SUPPORTED_CURRENCIES = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD'];

    // Exchange rate cache duration (seconds)
    private const EXCHANGE_RATE_CACHE_TTL = 300; // 5 minutes

    // Transaction fee rates
    private const FEE_RATES = [
        'domestic'      => 0.01,      // 1%
        'international' => 0.025, // 2.5%
        'crypto'        => 0.005,       // 0.5%
        'escrow'        => 0.02,        // 2%
    ];

    /**
     * Create a new wallet for an agent.
     */
    public function createWallet(
        string $agentId,
        string $currency = 'USD',
        float $initialBalance = 0.0,
        array $metadata = []
    ): AgentWallet {
        if (! $this->isCurrencySupported($currency)) {
            throw new InvalidArgumentException("Unsupported currency: {$currency}");
        }

        $walletId = 'wallet_' . Str::uuid()->toString();

        // Create wallet aggregate
        $aggregate = AgentWalletAggregate::create(
            walletId: $walletId,
            agentId: $agentId,
            currency: $currency,
            initialBalance: $initialBalance,
            metadata: $metadata
        );
        $aggregate->persist();

        // Create read model
        return AgentWallet::create([
            'wallet_id'         => $walletId,
            'agent_id'          => $agentId,
            'currency'          => $currency,
            'available_balance' => $initialBalance,
            'held_balance'      => 0.0,
            'total_balance'     => $initialBalance,
            'is_active'         => true,
            'metadata'          => $metadata,
        ]);
    }

    /**
     * Get wallet balance with multi-currency support.
     */
    public function getBalance(string $walletId, ?string $targetCurrency = null): array
    {
        $wallet = AgentWallet::where('wallet_id', $walletId)->firstOrFail();

        $balance = [
            'wallet_id' => $walletId,
            'currency'  => $wallet->currency,
            'available' => $wallet->available_balance,
            'held'      => $wallet->held_balance,
            'total'     => $wallet->total_balance,
        ];

        // Convert to target currency if requested
        if ($targetCurrency && $targetCurrency !== $wallet->currency) {
            $rate = $this->getExchangeRate($wallet->currency, $targetCurrency);
            $balance['converted'] = [
                'currency'      => $targetCurrency,
                'available'     => round($wallet->available_balance * $rate, 2),
                'held'          => round($wallet->held_balance * $rate, 2),
                'total'         => round($wallet->total_balance * $rate, 2),
                'exchange_rate' => $rate,
            ];
        }

        return $balance;
    }

    /**
     * Transfer funds between agent wallets with multi-currency support.
     */
    public function transfer(
        string $fromWalletId,
        string $toWalletId,
        float $amount,
        string $currency,
        string $type = 'transfer',
        array $metadata = []
    ): AgentTransaction {
        DB::beginTransaction();

        try {
            $fromWallet = AgentWallet::where('wallet_id', $fromWalletId)
                ->lockForUpdate()
                ->firstOrFail();
            $toWallet = AgentWallet::where('wallet_id', $toWalletId)
                ->lockForUpdate()
                ->firstOrFail();

            // Handle currency conversion
            $fromAmount = $amount;
            $toAmount = $amount;

            if ($fromWallet->currency !== $currency) {
                $rate = $this->getExchangeRate($currency, $fromWallet->currency);
                $fromAmount = round($amount * $rate, 2);
            }

            if ($toWallet->currency !== $currency) {
                $rate = $this->getExchangeRate($currency, $toWallet->currency);
                $toAmount = round($amount * $rate, 2);
            }

            // Check sufficient balance
            if ($fromWallet->available_balance < $fromAmount) {
                throw new InvalidArgumentException('Insufficient balance for transfer');
            }

            // Calculate fees
            $feeType = $this->determineFeeType($fromWallet->currency, $toWallet->currency);
            $feeAmount = round($amount * self::FEE_RATES[$feeType], 2);

            // Create transaction aggregate
            $transactionId = 'trans_' . Str::uuid()->toString();
            $aggregate = AgentTransactionAggregate::initiate(
                transactionId: $transactionId,
                fromAgentId: $fromWallet->agent_id,
                toAgentId: $toWallet->agent_id,
                amount: $amount,
                currency: $currency,
                type: 'direct',
                metadata: $metadata
            );

            $aggregate->validate()
                ->calculateFees($feeAmount, $feeType)
                ->complete('success');
            $aggregate->persist();

            // Update wallet balances
            $this->updateWalletBalance($fromWalletId, -$fromAmount - $feeAmount);
            $this->updateWalletBalance($toWalletId, $toAmount);

            // Create transaction record
            $transaction = AgentTransaction::create([
                'transaction_id' => $transactionId,
                'from_agent_id'  => $fromWallet->agent_id,
                'to_agent_id'    => $toWallet->agent_id,
                'amount'         => $amount,
                'currency'       => $currency,
                'fee_amount'     => $feeAmount,
                'fee_type'       => $feeType,
                'status'         => 'completed',
                'type'           => $type,
                'metadata'       => array_merge($metadata, [
                    'from_currency' => $fromWallet->currency,
                    'to_currency'   => $toWallet->currency,
                    'from_amount'   => $fromAmount,
                    'to_amount'     => $toAmount,
                ]),
            ]);

            DB::commit();

            Log::info('Agent wallet transfer completed', [
                'transaction_id' => $transactionId,
                'from_wallet'    => $fromWalletId,
                'to_wallet'      => $toWalletId,
                'amount'         => $amount,
                'currency'       => $currency,
            ]);

            return $transaction;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Agent wallet transfer failed', [
                'error'       => $e->getMessage(),
                'from_wallet' => $fromWalletId,
                'to_wallet'   => $toWalletId,
            ]);
            throw $e;
        }
    }

    /**
     * Hold funds for escrow or pending transactions.
     */
    public function holdFunds(string $walletId, float $amount, string $reason, array $metadata = []): void
    {
        $wallet = AgentWallet::where('wallet_id', $walletId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($wallet->available_balance < $amount) {
            throw new InvalidArgumentException('Insufficient available balance to hold');
        }

        $aggregate = AgentWalletAggregate::retrieve($walletId);
        $aggregate->holdFunds($amount, $reason, $metadata);
        $aggregate->persist();

        // Update read model
        $wallet->update([
            'available_balance' => $wallet->available_balance - $amount,
            'held_balance'      => $wallet->held_balance + $amount,
        ]);
    }

    /**
     * Release held funds.
     */
    public function releaseFunds(string $walletId, float $amount, string $reason, array $metadata = []): void
    {
        $wallet = AgentWallet::where('wallet_id', $walletId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($wallet->held_balance < $amount) {
            throw new InvalidArgumentException('Insufficient held balance to release');
        }

        $aggregate = AgentWalletAggregate::retrieve($walletId);
        $aggregate->releaseFunds($amount, $reason, $metadata);
        $aggregate->persist();

        // Update read model
        $wallet->update([
            'available_balance' => $wallet->available_balance + $amount,
            'held_balance'      => $wallet->held_balance - $amount,
        ]);
    }

    /**
     * Get supported currencies.
     */
    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Check if currency is supported.
     */
    public function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * Get exchange rate between currencies (mock implementation).
     */
    private function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = "exchange_rate:{$fromCurrency}:{$toCurrency}";

        $rate = Cache::remember($cacheKey, self::EXCHANGE_RATE_CACHE_TTL, function () use ($fromCurrency, $toCurrency): float {
            // Mock exchange rates (in production, use real exchange rate API)
            $rates = [
                'USD' => ['EUR' => 0.85, 'GBP' => 0.73, 'JPY' => 110.0, 'CHF' => 0.92, 'CAD' => 1.25, 'AUD' => 1.35, 'NZD' => 1.45],
                'EUR' => ['USD' => 1.18, 'GBP' => 0.86, 'JPY' => 129.0, 'CHF' => 1.08, 'CAD' => 1.47, 'AUD' => 1.59, 'NZD' => 1.71],
                'GBP' => ['USD' => 1.37, 'EUR' => 1.16, 'JPY' => 150.0, 'CHF' => 1.26, 'CAD' => 1.71, 'AUD' => 1.85, 'NZD' => 1.99],
            ];

            return (float) ($rates[$fromCurrency][$toCurrency] ?? 1.0);
        });

        return (float) $rate;
    }

    /**
     * Determine fee type based on currencies.
     */
    private function determineFeeType(string $fromCurrency, string $toCurrency): string
    {
        if ($fromCurrency === $toCurrency) {
            return 'domestic';
        }

        // Check if crypto (simplified check)
        if (in_array($fromCurrency, ['BTC', 'ETH', 'USDT']) || in_array($toCurrency, ['BTC', 'ETH', 'USDT'])) {
            return 'crypto';
        }

        return 'international';
    }

    /**
     * Update wallet balance.
     */
    private function updateWalletBalance(string $walletId, float $amount): void
    {
        $aggregate = AgentWalletAggregate::retrieve($walletId);

        if ($amount > 0) {
            $aggregate->receivePayment(
                transactionId: 'internal_' . Str::uuid()->toString(),
                fromAgentId: 'system',
                amount: $amount,
                metadata: ['type' => 'internal_transfer']
            );
        } else {
            // For negative amounts (withdrawals), we don't have a direct method
            // This would need to be handled through the transaction aggregate
        }

        $aggregate->persist();

        // Update read model
        $wallet = AgentWallet::where('wallet_id', $walletId)->first();
        if ($wallet) {
            $wallet->update([
                'available_balance' => $wallet->available_balance + $amount,
                'total_balance'     => $wallet->total_balance + $amount,
            ]);
        }
    }

    /**
     * Get transaction history for a wallet.
     */
    public function getTransactionHistory(string $walletId, int $limit = 50): array
    {
        $wallet = AgentWallet::where('wallet_id', $walletId)->firstOrFail();

        $transactions = AgentTransaction::where(function ($query) use ($wallet) {
                $query->where('from_agent_id', $wallet->agent_id)
                      ->orWhere('to_agent_id', $wallet->agent_id);
        })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $transactions->map(function ($transaction) use ($wallet) {
            $isOutgoing = $transaction->from_agent_id === $wallet->agent_id;

            return [
                'transaction_id' => $transaction->transaction_id,
                'type'           => $isOutgoing ? 'outgoing' : 'incoming',
                'amount'         => $transaction->amount,
                'currency'       => $transaction->currency,
                'fee'            => $isOutgoing ? $transaction->fee_amount : 0,
                'status'         => $transaction->status,
                'created_at'     => $transaction->created_at,
                'metadata'       => $transaction->metadata,
            ];
        })->toArray();
    }
}
