<?php

declare(strict_types=1);

namespace App\Domain\Referral\Services;

use App\Domain\Relayer\Services\SponsorshipService;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ReferralService
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {
    }

    /**
     * Generate or retrieve a referral code for a user.
     */
    public function generateCode(User $user): ReferralCode
    {
        $existing = ReferralCode::where('user_id', $user->id)
            ->where('active', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        $code = $this->createUniqueCode();

        $referralCode = ReferralCode::create([
            'user_id'  => $user->id,
            'code'     => $code,
            'max_uses' => 50,
            'active'   => true,
        ]);

        // Also store on user for quick lookup
        $user->update(['referral_code' => $code]);

        Log::info('Referral code generated', [
            'user_id' => $user->id,
            'code'    => $code,
        ]);

        return $referralCode;
    }

    /**
     * Apply a referral code for a new user.
     */
    public function applyCode(User $referee, string $code): Referral
    {
        // Check if user was already referred
        $existingReferral = Referral::where('referee_id', $referee->id)->first();
        if ($existingReferral) {
            throw new RuntimeException('You have already used a referral code.');
        }

        $referralCode = ReferralCode::where('code', $code)->first();

        if (! $referralCode) {
            throw new RuntimeException('Invalid referral code.');
        }

        if (! $referralCode->canBeUsed()) {
            throw new RuntimeException('This referral code has expired or reached its usage limit.');
        }

        // Can't refer yourself
        if ($referralCode->user_id === $referee->id) {
            throw new RuntimeException('You cannot use your own referral code.');
        }

        return DB::transaction(function () use ($referee, $referralCode): Referral {
            $referral = Referral::create([
                'referrer_id'      => $referralCode->user_id,
                'referee_id'       => $referee->id,
                'referral_code_id' => $referralCode->id,
                'status'           => Referral::STATUS_PENDING,
            ]);

            $referralCode->increment('uses_count');
            $referee->update(['referred_by' => $referralCode->user_id]);

            Log::info('Referral code applied', [
                'referee_id'  => $referee->id,
                'referrer_id' => $referralCode->user_id,
                'code'        => $referralCode->code,
            ]);

            return $referral;
        });
    }

    /**
     * Complete a referral when the referee passes KYC, granting sponsorship to both parties.
     */
    public function completeReferral(User $referee): void
    {
        $referral = Referral::where('referee_id', $referee->id)
            ->where('status', Referral::STATUS_PENDING)
            ->first();

        if (! $referral) {
            return; // No pending referral for this user
        }

        DB::transaction(function () use ($referral): void {
            $referral->update([
                'status'       => Referral::STATUS_REWARDED,
                'completed_at' => now(),
            ]);

            $referrer = User::find($referral->referrer_id);
            $referee = User::find($referral->referee_id);

            $txCount = (int) config('relayer.sponsorship.default_free_tx', 5);

            // Grant sponsorship to both referrer and referee
            if ($referrer) {
                $this->sponsorshipService->grantSponsorship($referrer, $txCount);
                Log::info('Referral reward granted to referrer', ['user_id' => $referrer->id, 'tx_count' => $txCount]);
            }

            if ($referee) {
                $this->sponsorshipService->grantSponsorship($referee, $txCount);
                Log::info('Referral reward granted to referee', ['user_id' => $referee->id, 'tx_count' => $txCount]);
            }
        });
    }

    /**
     * Get referral stats for a user (single aggregated query).
     *
     * @return array{total_referred: int, completed: int, pending: int, rewards_earned: int, reward_per_referral: int}
     */
    public function getUserStats(User $user): array
    {
        $stats = Referral::where('referrer_id', $user->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [Referral::STATUS_REWARDED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [Referral::STATUS_PENDING])
            ->first();

        $rewardPerReferral = (int) config('relayer.sponsorship.default_free_tx', 5);

        return [
            'total_referred'      => (int) $stats->total,
            'completed'           => (int) $stats->completed,
            'pending'             => (int) $stats->pending,
            'rewards_earned'      => (int) $stats->completed * $rewardPerReferral,
            'reward_per_referral' => $rewardPerReferral,
        ];
    }

    /**
     * Get referrals list for a user (people they referred), paginated.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Referral>
     */
    public function getUserReferrals(User $user, int $limit = 20, int $offset = 0): \Illuminate\Database\Eloquent\Collection
    {
        return Referral::where('referrer_id', $user->id)
            ->with('referee:id,name')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    private function createUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (ReferralCode::where('code', $code)->exists());

        return $code;
    }
}
