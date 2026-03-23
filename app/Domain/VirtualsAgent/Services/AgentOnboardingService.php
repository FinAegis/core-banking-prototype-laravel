<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Services;

use App\Domain\VirtualsAgent\DataObjects\AgentOnboardingRequest;
use App\Domain\VirtualsAgent\Enums\AgentStatus;
use App\Domain\VirtualsAgent\Events\VirtualsAgentActivated;
use App\Domain\VirtualsAgent\Events\VirtualsAgentRegistered;
use App\Domain\VirtualsAgent\Events\VirtualsAgentSuspended;
use App\Domain\VirtualsAgent\Models\VirtualsAgentProfile;
use App\Domain\VisaCli\Services\VisaCliSpendingLimitService;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles the onboarding lifecycle for Virtuals Protocol agents.
 */
class AgentOnboardingService
{
    public function __construct(
        private readonly VisaCliSpendingLimitService $spendingLimitService,
    ) {
    }

    /**
     * Onboard a new Virtuals agent into FinAegis.
     *
     * Atomic operation: creates profile, provisions spending limit, generates
     * TrustCert subject, and activates — all within a single transaction.
     */
    public function onboardAgent(AgentOnboardingRequest $request): VirtualsAgentProfile
    {
        // Validate inputs
        $this->validateOnboardingRequest($request);

        return DB::transaction(function () use ($request): VirtualsAgentProfile {
            // Atomic duplicate check under transaction isolation
            $existing = VirtualsAgentProfile::where('virtuals_agent_id', $request->virtualsAgentId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new RuntimeException(
                    "Virtuals agent [{$request->virtualsAgentId}] is already registered."
                );
            }

            // 1. Create profile in REGISTERED state
            $profile = VirtualsAgentProfile::create([
                'virtuals_agent_id' => $request->virtualsAgentId,
                'employer_user_id'  => $request->employerUserId,
                'agent_name'        => trim($request->agentName),
                'agent_description' => $request->agentDescription !== null ? trim($request->agentDescription) : null,
                'status'            => AgentStatus::REGISTERED,
                'chain'             => $request->chain,
            ]);

            // 2. Create spending limit
            $dailyLimit = $request->dailyLimitCents ?? (int) config('virtuals-agent.default_daily_limit', 50000);
            $perTxLimit = $request->perTxLimitCents ?? (int) config('virtuals-agent.default_per_tx_limit', 10000);

            $spendingLimit = $this->spendingLimitService->updateLimit(
                agentId: $request->virtualsAgentId,
                dailyLimit: $dailyLimit,
                perTransactionLimit: $perTxLimit,
            );

            // 3. Generate TrustCert subject (sanitize agent ID to prevent delimiter injection)
            $safeAgentId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $request->virtualsAgentId) ?? '';
            if ($safeAgentId === '') {
                throw new RuntimeException('Agent ID contains no valid characters after sanitization.');
            }
            $trustcertSubjectId = 'agent:' . $safeAgentId . ':employer:' . $request->employerUserId;

            // 4. Update profile with linked IDs and activate
            $profile->update([
                'x402_spending_limit_id' => $spendingLimit->id,
                'trustcert_subject_id'   => $trustcertSubjectId,
                'status'                 => AgentStatus::ACTIVE,
            ]);

            // 5. Dispatch domain events
            event(new VirtualsAgentRegistered(
                agentProfileId: $profile->id,
                virtualsAgentId: $request->virtualsAgentId,
                employerUserId: $request->employerUserId,
                agentName: $request->agentName,
            ));

            event(new VirtualsAgentActivated(
                agentProfileId: $profile->id,
                virtualsAgentId: $request->virtualsAgentId,
            ));

            Log::info('Virtuals agent onboarded', [
                'profile_id'        => $profile->id,
                'virtuals_agent_id' => $request->virtualsAgentId,
                'employer_user_id'  => $request->employerUserId,
            ]);

            return $profile->refresh();
        });
    }

    /**
     * Suspend an active Virtuals agent.
     */
    public function suspendAgent(string $agentProfileId, string $reason): bool
    {
        /** @var VirtualsAgentProfile|null $profile */
        $profile = VirtualsAgentProfile::find($agentProfileId);

        if ($profile === null) {
            return false;
        }

        $status = $profile->status instanceof AgentStatus ? $profile->status : AgentStatus::tryFrom((string) $profile->status);

        if ($status === AgentStatus::SUSPENDED) {
            return true;
        }

        if ($status === AgentStatus::DEACTIVATED) {
            return false;
        }

        $profile->update(['status' => AgentStatus::SUSPENDED]);

        event(new VirtualsAgentSuspended(
            agentProfileId: $profile->id,
            virtualsAgentId: $profile->virtuals_agent_id,
            reason: $reason,
        ));

        Log::info('Virtuals agent suspended', [
            'profile_id'        => $profile->id,
            'virtuals_agent_id' => $profile->virtuals_agent_id,
            'reason'            => $reason,
        ]);

        return true;
    }

    /**
     * Retrieve an agent profile by its Virtuals agent ID.
     */
    public function getAgentProfile(string $virtualsAgentId): ?VirtualsAgentProfile
    {
        return VirtualsAgentProfile::where('virtuals_agent_id', $virtualsAgentId)->first();
    }

    /**
     * Retrieve all agents belonging to a given employer.
     *
     * @return Collection<int, VirtualsAgentProfile>
     */
    public function getEmployerAgents(int $userId): Collection
    {
        return VirtualsAgentProfile::where('employer_user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function validateOnboardingRequest(AgentOnboardingRequest $request): void
    {
        // Validate agent ID format (alphanumeric + hyphens/underscores, 1-255 chars)
        if (! preg_match('/^[a-zA-Z0-9_\-]{1,255}$/', $request->virtualsAgentId)) {
            throw new RuntimeException('Invalid Virtuals agent ID format. Must be alphanumeric with hyphens/underscores, 1-255 characters.');
        }

        // Validate employer exists
        if (! User::where('id', $request->employerUserId)->exists()) {
            throw new RuntimeException("Employer user [{$request->employerUserId}] not found.");
        }

        // Validate agent name
        if (trim($request->agentName) === '') {
            throw new RuntimeException('Agent name cannot be empty.');
        }

        // Validate chain
        $supported = (array) config('virtuals-agent.supported_chains', ['base', 'polygon', 'arbitrum', 'ethereum']);
        if (! in_array($request->chain, $supported, true)) {
            throw new RuntimeException("Unsupported chain [{$request->chain}]. Supported: " . implode(', ', $supported));
        }

        // Validate spending limits (if provided)
        if ($request->dailyLimitCents !== null && $request->dailyLimitCents <= 0) {
            throw new RuntimeException('Daily limit must be positive.');
        }

        if ($request->perTxLimitCents !== null && $request->perTxLimitCents <= 0) {
            throw new RuntimeException('Per-transaction limit must be positive.');
        }

        // Enforce system maximums
        $maxDaily = (int) config('virtuals-agent.max_daily_limit', 10000000);
        if ($request->dailyLimitCents !== null && $request->dailyLimitCents > $maxDaily) {
            throw new RuntimeException("Daily limit cannot exceed \${$this->formatCents($maxDaily)}.");
        }

        $maxPerTx = (int) config('virtuals-agent.max_per_tx_limit', 1000000);
        if ($request->perTxLimitCents !== null && $request->perTxLimitCents > $maxPerTx) {
            throw new RuntimeException("Per-transaction limit cannot exceed \${$this->formatCents($maxPerTx)}.");
        }

        if (
            $request->dailyLimitCents !== null && $request->perTxLimitCents !== null
            && $request->perTxLimitCents > $request->dailyLimitCents
        ) {
            throw new RuntimeException('Per-transaction limit cannot exceed daily limit.');
        }
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
