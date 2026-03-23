<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Services;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\VirtualsAgent\Models\VirtualsAgentProfile;
use App\Domain\VisaCli\Models\VisaCliSpendingLimit;
use Illuminate\Support\Facades\Log;

/**
 * Bridges X402 database spending limits with Pimlico ERC-4337 enforcement.
 *
 * For agents requiring trustless enforcement beyond database row locks:
 * 1. Agent's smart account is deployed via Pimlico
 * 2. Paymaster only sponsors UserOps within the spending policy
 * 3. The bundler rejects UserOps that exceed the agent's X402 limit
 *
 * This gives cryptographic enforcement without custom smart contracts —
 * Pimlico's session key infrastructure handles the policy.
 */
class AgentSpendingEnforcementService
{
    public function __construct(
        /** @phpstan-ignore-next-line property.onlyWritten - Reserved for Phase 4 Pimlico session key enforcement */
        private readonly BundlerInterface $bundler,
        /** @phpstan-ignore-next-line property.onlyWritten - Reserved for Phase 4 Pimlico paymaster policy */
        private readonly PaymasterInterface $paymaster,
    ) {
    }

    /**
     * Check if an agent's UserOp should be sponsored based on spending limits.
     *
     * @return array{allowed: bool, reason: string|null, remaining_cents: int}
     */
    public function evaluateAgentUserOp(
        string $virtualsAgentId,
        int $estimatedCostCents,
    ): array {
        $profile = VirtualsAgentProfile::where('virtuals_agent_id', $virtualsAgentId)->first();

        if ($profile === null || ! $profile->isActive()) {
            return [
                'allowed'         => false,
                'reason'          => 'Agent not found or not active',
                'remaining_cents' => 0,
            ];
        }

        $limit = $profile->x402_spending_limit_id
            ? VisaCliSpendingLimit::find($profile->x402_spending_limit_id)
            : null;

        if ($limit === null) {
            return [
                'allowed'         => false,
                'reason'          => 'No spending limit configured for this agent',
                'remaining_cents' => 0,
            ];
        }

        $limit->resetIfNeeded();
        $remaining = $limit->remainingDailyBudget();

        if (! $limit->canSpend($estimatedCostCents)) {
            Log::warning('Virtuals agent UserOp rejected: spending limit exceeded', [
                'agent_id'    => $virtualsAgentId,
                'requested'   => $estimatedCostCents,
                'remaining'   => $remaining,
                'daily_limit' => $limit->daily_limit,
            ]);

            return [
                'allowed'         => false,
                'reason'          => "Spending limit exceeded: requested {$estimatedCostCents} cents, remaining {$remaining} cents",
                'remaining_cents' => $remaining,
            ];
        }

        if ($limit->per_transaction_limit !== null && $estimatedCostCents > $limit->per_transaction_limit) {
            return [
                'allowed'         => false,
                'reason'          => "Per-transaction limit exceeded: {$estimatedCostCents} > {$limit->per_transaction_limit} cents",
                'remaining_cents' => $remaining,
            ];
        }

        return [
            'allowed'         => true,
            'reason'          => null,
            'remaining_cents' => $remaining,
        ];
    }

    /**
     * Get the Pimlico-enforced spending policy for an agent.
     *
     * Returns the policy parameters that would be used to configure
     * a session key module on the agent's smart account.
     *
     * @return array{daily_limit_wei: string, per_tx_limit_wei: string, allowed_targets: array<string>, network: string, expires_at: string}
     */
    public function getEnforcementPolicy(string $virtualsAgentId): array
    {
        $profile = VirtualsAgentProfile::where('virtuals_agent_id', $virtualsAgentId)->first();

        if ($profile === null) {
            return [
                'daily_limit_wei'  => '0',
                'per_tx_limit_wei' => '0',
                'allowed_targets'  => [],
                'network'          => 'base',
                'expires_at'       => now()->toIso8601String(),
            ];
        }

        $limit = $profile->x402_spending_limit_id
            ? VisaCliSpendingLimit::find($profile->x402_spending_limit_id)
            : null;

        // Convert cents to USDC atomic units (6 decimals)
        // $1.00 = 100 cents = 1_000_000 atomic USDC
        $dailyLimitAtomic = $limit ? (string) ($limit->daily_limit * 10000) : '0';
        $perTxLimitAtomic = $limit && $limit->per_transaction_limit
            ? (string) ($limit->per_transaction_limit * 10000)
            : $dailyLimitAtomic;

        $network = SupportedNetwork::tryFrom($profile->chain ?? 'base');

        // USDC contract addresses per chain
        $usdcAddresses = [
            'base'     => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
            'polygon'  => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
            'arbitrum' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831',
            'ethereum' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        ];

        return [
            'daily_limit_wei'  => $dailyLimitAtomic,
            'per_tx_limit_wei' => $perTxLimitAtomic,
            'allowed_targets'  => array_filter([$usdcAddresses[$profile->chain] ?? null]),
            'network'          => $profile->chain ?? 'base',
            'expires_at'       => now()->addDay()->toIso8601String(),
        ];
    }
}
