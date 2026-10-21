<?php

declare(strict_types=1);

namespace App\Domain\MachinePay\Services;

use App\Domain\MachinePay\DataObjects\MppChallenge;
use App\Domain\MachinePay\DataObjects\MppCredential;
use App\Domain\MachinePay\Exceptions\MppException;
use App\Domain\MachinePay\Exceptions\MppSettlementException;
use App\Domain\MachinePay\Models\MppSpendingLimit;
use App\Support\MachinePaymentScope;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Client-side MPP payment service.
 *
 * Used when FinAegis acts as a buyer, paying external MPP-enabled APIs
 * on behalf of AI agents. Handles challenge parsing, rail selection,
 * spending limit enforcement, and credential generation.
 */
class MppClientService
{
    public function __construct(
        private readonly MppRailResolverService $railResolver,
    ) {
    }

    /**
     * Handle a 402 Payment Required response from an external API.
     *
     * Parses the WWW-Authenticate: Payment header, selects the best
     * rail, enforces spending limits, and returns a credential for retry.
     *
     * @return array{credential: MppCredential, header: string}
     *
     * @throws MppException
     */
    public function handlePaymentRequired(string $wwwAuthenticateHeader, string $agentId): array
    {
        if (! config('machinepay.client.enabled', false)) {
            throw new MppException('MPP client mode is not enabled.');
        }

        // Parse the challenge from the header
        $challenge = $this->parseChallenge($wwwAuthenticateHeader);

        // Select the best available rail
        $selectedRail = $this->selectRail($challenge);

        if ($selectedRail === null) {
            throw new MppException('No compatible payment rail available for this challenge.');
        }

        // Enforce spending limits
        $this->enforceSpendingLimit($agentId, $challenge->amountCents, Auth::user());

        // Generate a credential (in demo mode, simulated proof)
        $credential = new MppCredential(
            challengeId: $challenge->id,
            rail: $selectedRail,
            proofOfPayment: $this->generateProof($selectedRail, $challenge),
            payerIdentifier: $agentId,
            timestamp: gmdate('Y-m-d\TH:i:s\Z'),
        );

        Log::info('MPP: Client credential generated', [
            'agent_id'     => $agentId,
            'challenge_id' => $challenge->id,
            'rail'         => $selectedRail,
            'amount_cents' => $challenge->amountCents,
        ]);

        return [
            'credential' => $credential,
            'header'     => 'Payment ' . $credential->toBase64Url(),
        ];
    }

    /**
     * Parse a challenge from the WWW-Authenticate header.
     */
    private function parseChallenge(string $header): MppChallenge
    {
        $payload = $header;

        if (str_starts_with($header, 'Payment ')) {
            $payload = substr($header, strlen('Payment '));
        }

        return MppChallenge::fromBase64Url($payload);
    }

    /**
     * Select the best available rail from the challenge options.
     *
     * Preference order follows config, with fallback to first available.
     *
     * @return string|null The selected rail identifier.
     */
    private function selectRail(MppChallenge $challenge): ?string
    {
        /** @var array<string> $preferred */
        $preferred = config('machinepay.client.preferred_rails', ['stripe', 'tempo', 'lightning']);

        foreach ($preferred as $rail) {
            if (in_array($rail, $challenge->availableRails, true) && $this->railResolver->isRailAvailable($rail)) {
                return $rail;
            }
        }

        // Fallback to first available rail in the challenge
        foreach ($challenge->availableRails as $rail) {
            if ($this->railResolver->isRailAvailable($rail)) {
                return $rail;
            }
        }

        return null;
    }

    /**
     * Enforce agent spending limits with row-level locking.
     *
     * The limit is the one stored for the agent under the caller's team, or
     * for the caller alone without a team (same lookup as the spending-limit
     * endpoints, {@see MachinePaymentScope::spendingLimits()}). Without one,
     * a default limit is created under that same owner. Agent ids are
     * unique per owner, so another team's limit for the same agent id is
     * never used or charged.
     *
     * @throws MppSettlementException
     */
    private function enforceSpendingLimit(string $agentId, int $amountCents, ?Authenticatable $user): void
    {
        DB::transaction(function () use ($agentId, $amountCents, $user): void {
            /** @var MppSpendingLimit|null $limit */
            $limit = MachinePaymentScope::spendingLimits(MppSpendingLimit::where('agent_id', $agentId), $user)
                ->lockForUpdate()
                ->first();

            if ($limit === null) {
                // Create the default limit under the caller's team (or user).
                $limit = MppSpendingLimit::create([
                    'agent_id'     => $agentId,
                    'daily_limit'  => (int) config('machinepay.agent_spending.default_daily_limit', 5000),
                    'per_tx_limit' => (int) config('machinepay.agent_spending.default_per_transaction_limit', 100),
                    'spent_today'  => 0,
                    'auto_pay'     => (bool) config('machinepay.client.auto_pay', false),
                    'last_reset'   => now()->toDateString(),
                    ...MachinePaymentScope::limitOwner($user),
                ]);
            }

            // Reset daily spending if new day (UTC to avoid timezone drift)
            $todayUtc = gmdate('Y-m-d');
            if ($limit->last_reset !== $todayUtc) {
                $limit->update([
                    'spent_today' => 0,
                    'last_reset'  => $todayUtc,
                ]);
                $limit->refresh();
            }

            // Check per-transaction limit
            if ($amountCents > $limit->per_tx_limit) {
                throw MppSettlementException::spendingLimitExceeded(
                    $agentId,
                    $amountCents,
                    $limit->per_tx_limit,
                );
            }

            // Check daily limit
            $remaining = $limit->daily_limit - $limit->spent_today;
            if ($amountCents > $remaining) {
                throw MppSettlementException::spendingLimitExceeded($agentId, $amountCents, $remaining);
            }

            // Debit spending
            $limit->increment('spent_today', $amountCents);
        });
    }

    /**
     * Generate a rail-specific payment proof.
     *
     * In demo mode, returns simulated proof structures.
     *
     * @return array<string, mixed>
     */
    private function generateProof(string $rail, MppChallenge $challenge): array
    {
        if (app()->environment('production')) {
            throw new MppException(
                "Production MPP client proof generation requires real rail integration (rail: {$rail})."
            );
        }

        // Demo mode: return simulated proof structures
        return match ($rail) {
            'stripe'    => ['spt' => 'spt_demo_' . bin2hex(random_bytes(12)), 'type' => 'stripe_payment_token'],
            'tempo'     => ['tx_hash' => '0x' . bin2hex(random_bytes(32)), 'type' => 'tempo_transfer'],
            'lightning' => ['preimage' => bin2hex(random_bytes(32)), 'type' => 'bolt11_preimage'],
            'card'      => ['jwe' => 'demo_encrypted_token_' . bin2hex(random_bytes(16)), 'type' => 'card_network_token'],
            default     => ['demo' => true, 'type' => 'unknown'],
        };
    }
}
