<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\Contracts\X402SignerInterface;
use App\Domain\X402\DataObjects\PaymentPayload;
use App\Domain\X402\DataObjects\PaymentRequirements;
use App\Domain\X402\Models\X402SpendingLimit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client-side service for handling x402 payments when FinAegis
 * acts as a buyer (e.g., AI agents paying external APIs).
 */
class X402ClientService
{
    public function __construct(
        private readonly X402SignerInterface $signer,
        private readonly X402HeaderCodecService $codec,
    ) {
    }

    /**
     * Handle a 402 Payment Required response from an external API.
     *
     * Parses the requirements, checks spending limits, signs payment,
     * and returns the PAYMENT-SIGNATURE header for retry.
     *
     * @return array<string, string> Headers to attach to the retry request
     */
    public function handlePaymentRequired(
        string $paymentRequiredHeader,
        string $agentId,
    ): array {
        $paymentRequired = $this->codec->decodePaymentRequired($paymentRequiredHeader);

        // Select the best payment option
        $selected = $this->selectPaymentOption($paymentRequired->accepts);
        if ($selected === null) {
            throw new RuntimeException('No compatible payment option found in x402 requirements. Ensure the server offers an EVM or Solana network with the "exact" scheme.');
        }

        // Enforce spending limits
        $this->enforceSpendingLimit($agentId, $selected->amount);

        // Create signed payment payload
        $signedPayload = $this->signer->signTransferAuthorization(
            network: $selected->network,
            to: $selected->payTo,
            amount: $selected->amount,
            asset: $selected->asset,
            maxTimeoutSeconds: $selected->maxTimeoutSeconds,
            extra: $selected->extra,
        );

        $paymentPayload = new PaymentPayload(
            x402Version: $paymentRequired->x402Version,
            resource: $paymentRequired->resource,
            accepted: $selected,
            payload: $signedPayload,
        );

        Log::info('x402: Created payment for external API', [
            'agent_id' => $agentId,
            'network'  => $selected->network,
            'amount'   => $selected->amount,
            'url'      => $paymentRequired->resource->url,
        ]);

        return [
            'PAYMENT-SIGNATURE' => $paymentPayload->toBase64(),
        ];
    }

    /**
     * Select the best payment option from available requirements.
     *
     * Prefers networks in config order, then falls back to any supported network.
     *
     * @param array<PaymentRequirements> $accepts
     */
    private function selectPaymentOption(array $accepts): ?PaymentRequirements
    {
        /** @var array<int, string> $preferred */
        $preferred = config('x402.client.preferred_networks', [
            'eip155:8453', 'eip155:84532', 'solana:mainnet', 'eip155:1',
        ]);

        foreach ($preferred as $network) {
            foreach ($accepts as $option) {
                if ($option->network === $network && $option->scheme === 'exact') {
                    return $option;
                }
            }
        }

        // Fallback: first EVM or Solana option
        foreach ($accepts as $option) {
            if (str_starts_with($option->network, 'eip155:') || str_starts_with($option->network, 'solana:')) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Enforce agent spending limits before authorizing a payment.
     *
     * Uses a database transaction with row-level locking to prevent
     * race conditions where concurrent requests could exceed limits.
     *
     * @throws RuntimeException If spending limit would be exceeded
     */
    private function enforceSpendingLimit(string $agentId, string $amount): void
    {
        /** @var numeric-string $amount */
        // Reject non-positive amounts to prevent budget manipulation
        if (bccomp($amount, '0') <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        DB::transaction(function () use ($agentId, $amount) {
            $limit = X402SpendingLimit::where('agent_id', $agentId)->lockForUpdate()->first();

            if ($limit === null) {
                // Check against global config limits
                /** @var numeric-string $maxAutoPay */
                $maxAutoPay = (string) config('x402.client.max_auto_pay_amount', '100000');
                if (bccomp($amount, $maxAutoPay) > 0) {
                    throw new RuntimeException(
                        "Payment of {$amount} atomic USDC exceeds the auto-pay limit of {$maxAutoPay} atomic USDC. Configure a spending limit for this agent or increase X402_CLIENT_MAX_AUTO_PAY."
                    );
                }

                return;
            }

            // Check per-transaction limit
            /** @var numeric-string $perTxLimit */
            $perTxLimit = (string) $limit->per_transaction_limit;
            if ($limit->per_transaction_limit !== null && bccomp($amount, $perTxLimit) > 0) {
                throw new RuntimeException(
                    "Payment of {$amount} atomic USDC exceeds per-transaction limit of {$limit->per_transaction_limit} atomic USDC."
                );
            }

            if (! $limit->canSpend($amount)) {
                throw new RuntimeException(
                    "Payment of {$amount} atomic USDC would exceed the daily limit for agent '{$agentId}'. Remaining budget: {$limit->remainingDailyBudget()} atomic USDC."
                );
            }

            /** @var numeric-string $approvalThreshold */
            $approvalThreshold = (string) config('x402.agent_spending.require_approval_above', '1000000');
            if (! $limit->auto_pay_enabled && bccomp($amount, $approvalThreshold) > 0) {
                throw new RuntimeException(
                    "Payment of {$amount} atomic USDC exceeds the auto-pay threshold. Manual approval is required before this payment can proceed."
                );
            }

            // Record spending atomically within this transaction
            $limit->recordSpending($amount);
        });
    }
}
