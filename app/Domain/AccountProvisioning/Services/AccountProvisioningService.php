<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Services;

use App\Domain\AccountProvisioning\Contracts\AccountProfile;
use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Top-level orchestrator for reviewer/demo account provisioning.
 *
 * Wraps three operations inside a single DB::transaction:
 *   1. Find-or-create the user (with optional password rotation)
 *   2. Upsert the account_flags row from the profile's flag payload
 *   3. Delegate content seeding to the profile (unless $ctx->dryRun)
 *
 * Called by the CLI command in Task 18. Unit-test coverage for this service
 * is deferred to the CLI command feature tests.
 */
class AccountProvisioningService
{
    public function __construct(
        private readonly AccountFlagsService $flags,
    ) {
    }

    /**
     * @return array{user: User, password_action: 'created'|'rotated'|'unchanged'}
     */
    public function apply(
        AccountProfile $profile,
        ProvisioningContext $ctx,
        ?string $password,
        bool $rotatePassword,
        bool $forceConvert,
    ): array {
        return DB::transaction(function () use ($profile, $ctx, $password, $rotatePassword, $forceConvert) {
            $existing = User::where('email', $ctx->email)->first();

            if ($existing instanceof User) {
                $flag = $existing->accountFlag;

                if (($flag === null || ! $flag->is_review_account) && ! $forceConvert) {
                    throw new RuntimeException(
                        "Email {$ctx->email} belongs to a non-review user. Use --force-convert to override (blocked in production)."
                    );
                }

                $user = $existing;
                $action = 'unchanged';

                if ($rotatePassword && $password !== null) {
                    $user->password = Hash::make($password);
                    $user->save();
                    $this->flags->forget($user);
                    $action = 'rotated';
                }
            } else {
                if ($password === null) {
                    throw new RuntimeException('Password must be provided or generated before calling apply().');
                }

                $user = User::create([
                    'name'              => $ctx->name,
                    'email'             => $ctx->email,
                    'password'          => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
                $action = 'created';
            }

            AccountFlag::updateOrCreate(
                ['user_id' => $user->id],
                $profile->flags($ctx),
            );

            $this->flags->forget($user);

            if (! $ctx->dryRun) {
                $profile->provision($user, $ctx);
            }

            return ['user' => $user, 'password_action' => $action];
        });
    }

    public function disable(User $user): void
    {
        $flag = $user->accountFlag;
        if ($flag === null) {
            return;
        }

        $flag->update([
            'bypass_device_attestation'  => false,
            'bypass_rate_limit'          => false,
            'bypass_sanctions_screening' => false,
            'bypass_sms_otp'             => false,
            'suppress_notifications'     => false,
            'kyc_override_level'         => null,
            'disabled_at'                => now(),
        ]);

        $user->tokens()->delete();
        $this->flags->forget($user);
    }

    public function reEnable(User $user, AccountProfile $profile, ProvisioningContext $ctx): void
    {
        AccountFlag::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($profile->flags($ctx), ['disabled_at' => null]),
        );
        $this->flags->forget($user);
    }
}
