<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class AccountDisableReviewerCommand extends Command
{
    /** @var string */
    protected $signature = 'account:disable-reviewer
                            {--email=}
                            {--all-expired}
                            {--re-enable}
                            {--allow-production : Required when APP_ENV=production}
                            {--operator-email= : Admin operator email (required for --email and --re-enable; not required for --all-expired)}';

    /** @var string */
    protected $description = 'Disable (revoke bypasses) or re-enable a reviewer account';

    public function handle(AccountProvisioningService $service, ReviewerAccountProfile $profile): int
    {
        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production guard: --allow-production is required when APP_ENV=production.');

            return 1;
        }

        $reEnable = (bool) $this->option('re-enable');

        if ($this->option('all-expired')) {
            $disabled = 0;
            $failed = 0;
            $tokensRevoked = 0;

            AccountFlag::where('is_review_account', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->whereNull('disabled_at')
                ->with('user')
                ->chunkById(100, function (Collection $chunk) use ($service, &$disabled, &$failed, &$tokensRevoked): void {
                    foreach ($chunk as $flag) {
                        if (! $flag->user instanceof User) {
                            continue;
                        }
                        try {
                            // Count BEFORE delegating: the service's disable()
                            // wipes the user's tokens table-wide, so a post-call
                            // count would always be 0. Tally for the summary line.
                            $preTokens = (int) $flag->user->tokens()
                                ->where('name', 'reviewer-mobile')
                                ->count();
                            $service->disable($flag->user);
                            $flag->user->tokens()->where('name', 'reviewer-mobile')->delete();
                            $tokensRevoked += $preTokens;
                            $this->line("disabled: {$flag->user->email}");
                            $disabled++;
                        } catch (Throwable $e) {
                            $this->error("failed: {$flag->user->email} ({$e->getMessage()})");
                            $failed++;
                        }
                    }
                });

            $this->info("Sweep complete. disabled={$disabled} failed={$failed} tokens-revoked={$tokensRevoked}");

            return $failed > 0 ? 1 : 0;
        }

        $email = (string) $this->option('email');
        if ($email === '') {
            $this->error('--email or --all-expired is required');

            return 1;
        }

        $operatorEmail = (string) $this->option('operator-email');
        if ($operatorEmail === '') {
            $this->error('--operator-email is required');

            return 1;
        }

        $operator = User::where('email', $operatorEmail)->first();
        if ($operator === null || ! $operator->hasRole(UserRoles::ADMIN->value)) {
            $this->error("Operator {$operatorEmail} not found or not an admin.");

            return 1;
        }

        $user = User::where('email', $email)->first();
        if ($user === null || $user->accountFlag === null || ! $user->accountFlag->is_review_account) {
            $this->error("User {$email} is not a review account.");

            return 1;
        }

        if ($reEnable) {
            $ctx = new ProvisioningContext(
                email: $email,
                name: 'App Reviewer',
                region: 'US',
                expiresAt: $user->accountFlag->expires_at !== null
                    ? CarbonImmutable::instance($user->accountFlag->expires_at)
                    : null,
                note: $user->accountFlag->note,
                operatorId: (int) $operator->id,
            );
            $service->reEnable($user, $profile, $ctx);
            $this->info("re-enabled: {$email}");

            return 0;
        }

        // Count paste-to-login tokens BEFORE delegating to the service: the
        // service's disable() already wipes the user's tokens table-wide, so
        // counting after would always be 0. We surface the count for operators
        // so they have a clear "what got revoked" line in the output. 0 is fine
        // — the operator may not have issued a token.
        $revoked = (int) $user->tokens()->where('name', 'reviewer-mobile')->count();
        $service->disable($user);
        $user->tokens()->where('name', 'reviewer-mobile')->delete();
        $this->info("disabled: {$email}");
        $this->line("revoked-tokens: {$revoked}");

        return 0;
    }
}
