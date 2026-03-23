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
use Illuminate\Database\Eloquent\Collection;
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
     * Creates the agent profile, provisions a spending limit, generates a
     * TrustCert subject identifier, and transitions the agent to ACTIVE.
     */
    public function onboardAgent(AgentOnboardingRequest $request): VirtualsAgentProfile
    {
        // Guard: prevent duplicate registrations
        $existing = VirtualsAgentProfile::where('virtuals_agent_id', $request->virtualsAgentId)->first();
        if ($existing !== null) {
            throw new RuntimeException(
                "Virtuals agent [{$request->virtualsAgentId}] is already registered."
            );
        }

        // 1. Create profile in REGISTERED state
        $profile = VirtualsAgentProfile::create([
            'virtuals_agent_id' => $request->virtualsAgentId,
            'employer_user_id'  => $request->employerUserId,
            'agent_name'        => $request->agentName,
            'agent_description' => $request->agentDescription,
            'status'            => AgentStatus::REGISTERED,
            'chain'             => $request->chain,
        ]);

        // 2. Create spending limit (reuses VisaCli spending limit infrastructure)
        $dailyLimit = $request->dailyLimitCents ?? (int) config('virtuals-agent.default_daily_limit', 50000);
        $perTxLimit = $request->perTxLimitCents ?? (int) config('virtuals-agent.default_per_tx_limit', 10000);

        $spendingLimit = $this->spendingLimitService->updateLimit(
            agentId: $request->virtualsAgentId,
            dailyLimit: $dailyLimit,
            perTransactionLimit: $perTxLimit,
        );

        $profile->update([
            'x402_spending_limit_id' => $spendingLimit->id,
        ]);

        // 3. Generate TrustCert subject ID
        $trustcertSubjectId = 'agent:' . $request->virtualsAgentId . ':employer:' . $request->employerUserId;
        $profile->update([
            'trustcert_subject_id' => $trustcertSubjectId,
        ]);

        // 4. Dispatch registered event
        event(new VirtualsAgentRegistered(
            agentProfileId: $profile->id,
            virtualsAgentId: $request->virtualsAgentId,
            employerUserId: $request->employerUserId,
            agentName: $request->agentName,
        ));

        // 5. Transition to ACTIVE
        $profile->update(['status' => AgentStatus::ACTIVE]);

        // 6. Dispatch activated event
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

        if ($profile->status === AgentStatus::SUSPENDED) {
            return true;
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
}
