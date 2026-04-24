<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Profiles;

use App\Domain\AccountProvisioning\Contracts\AccountProfile;
use App\Domain\AccountProvisioning\Seeders\BalanceSeeder;
use App\Domain\AccountProvisioning\Seeders\CardSeeder;
use App\Domain\AccountProvisioning\Seeders\RewardsSeeder;
use App\Domain\AccountProvisioning\Seeders\TrustCertSeeder;
use App\Domain\AccountProvisioning\Seeders\WalletSeeder;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Profile that provisions a reviewer / app-store demo account.
 *
 * Composition of the five sub-seeders (order-sensitive: WalletSeeder must run
 * before BalanceSeeder because BalanceSeeder upserts token_balances rows keyed
 * on the user's BlockchainAddress).
 *
 * This profile intentionally does NOT write the account_flags row — the
 * AccountProvisioningService orchestrator owns that, so create/rotate/
 * re-enable flows can upsert the flag independently of content seeding.
 */
class ReviewerAccountProfile implements AccountProfile
{
    public function __construct(
        private readonly WalletSeeder $wallets,
        private readonly BalanceSeeder $balances,
        private readonly CardSeeder $cards,
        private readonly TrustCertSeeder $trustCert,
        private readonly RewardsSeeder $rewards,
    ) {
    }

    public function name(): string
    {
        return 'reviewer';
    }

    /**
     * @return array<string, bool|int|string|CarbonImmutable|null>
     */
    public function flags(ProvisioningContext $ctx): array
    {
        return [
            'is_review_account'          => true,
            'bypass_device_attestation'  => true,
            'bypass_rate_limit'          => true,
            'bypass_sanctions_screening' => true,
            'bypass_sms_otp'             => true,
            'suppress_notifications'     => true,
            'kyc_override_level'         => 2,
            'note'                       => $ctx->note,
            'expires_at'                 => $ctx->expiresAt,
            'created_by'                 => $ctx->operatorId,
        ];
    }

    public function provision(User $user, ProvisioningContext $ctx): void
    {
        DB::transaction(function () use ($user): void {
            // Order matters: WalletSeeder creates BlockchainAddress rows that
            // BalanceSeeder then looks up to upsert token_balances.
            $this->wallets->seed($user);
            $this->balances->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');
            $this->cards->seed($user);
            $this->trustCert->seed($user);
            $this->rewards->seed($user);
        });
    }
}
