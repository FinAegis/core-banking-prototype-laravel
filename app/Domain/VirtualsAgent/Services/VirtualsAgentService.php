<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Services;

use App\Domain\VirtualsAgent\Models\VirtualsAgentProfile;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentRequest;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\Services\VisaCliPaymentService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * High-level service for Virtuals agent payment operations.
 *
 * Delegates payment execution to the VisaCli payment infrastructure while
 * enforcing agent-level preconditions (active status, spending limits).
 */
class VirtualsAgentService
{
    public function __construct(
        private readonly AgentOnboardingService $onboardingService,
        private readonly VisaCliPaymentService $paymentService,
    ) {
    }

    /**
     * Execute a payment on behalf of a Virtuals agent.
     */
    public function executeAgentPayment(
        string $virtualsAgentId,
        string $url,
        int $amountCents,
        ?string $purpose = null,
    ): VisaCliPaymentResult {
        $profile = $this->loadActiveProfile($virtualsAgentId);

        $request = new VisaCliPaymentRequest(
            agentId: $profile->virtuals_agent_id,
            url: $url,
            amountCents: $amountCents,
            purpose: $purpose,
            metadata: [
                'virtuals_profile_id' => $profile->id,
                'employer_user_id'    => $profile->employer_user_id,
                'chain'               => $profile->chain,
            ],
        );

        Log::info('Virtuals agent payment initiated', [
            'virtuals_agent_id' => $virtualsAgentId,
            'url'               => $url,
            'amount_cents'      => $amountCents,
        ]);

        return $this->paymentService->executePayment($request);
    }

    /**
     * Build a spending summary for the given Virtuals agent.
     *
     * @return array{daily_limit: int, spent_today: int, remaining: int, last_transactions: array<int, array<string, mixed>>}
     */
    public function getAgentSpendingSummary(string $virtualsAgentId): array
    {
        $profile = $this->onboardingService->getAgentProfile($virtualsAgentId);

        if ($profile === null) {
            throw new RuntimeException("Virtuals agent [{$virtualsAgentId}] not found.");
        }

        $limit = $profile->x402SpendingLimit;

        $dailyLimit = 0;
        $spentToday = 0;
        $remaining = 0;

        if ($limit !== null) {
            $dailyLimit = (int) $limit->daily_limit;
            $spentToday = (int) $limit->spent_today;
            $remaining = $limit->remainingDailyBudget();
        }

        $recentPayments = $this->paymentService->getAgentPayments($virtualsAgentId, 10);

        $transactions = [];
        foreach ($recentPayments as $payment) {
            $transactions[] = [
                'id'           => $payment->id,
                'url'          => $payment->url,
                'amount_cents' => $payment->amount_cents,
                'status'       => $payment->status,
                'created_at'   => $payment->created_at->toIso8601String(),
            ];
        }

        return [
            'daily_limit'       => $dailyLimit,
            'spent_today'       => $spentToday,
            'remaining'         => $remaining,
            'last_transactions' => $transactions,
        ];
    }

    /**
     * Load and validate an active agent profile.
     */
    private function loadActiveProfile(string $virtualsAgentId): VirtualsAgentProfile
    {
        $profile = $this->onboardingService->getAgentProfile($virtualsAgentId);

        if ($profile === null) {
            throw new RuntimeException("Virtuals agent [{$virtualsAgentId}] not found.");
        }

        if (! $profile->isActive()) {
            throw new RuntimeException(
                "Virtuals agent [{$virtualsAgentId}] is not active (status: {$profile->status->value})."
            );
        }

        return $profile;
    }
}
