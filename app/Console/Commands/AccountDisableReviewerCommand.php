<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Illuminate\Console\Command;

class AccountDisableReviewerCommand extends Command
{
    /** @var string */
    protected $signature = 'account:disable-reviewer
                            {--email=}
                            {--all-expired}
                            {--re-enable}';

    /** @var string */
    protected $description = 'Disable (revoke bypasses) or re-enable a reviewer account';

    public function handle(AccountProvisioningService $service, ReviewerAccountProfile $profile): int
    {
        $reEnable = (bool) $this->option('re-enable');

        if ($this->option('all-expired')) {
            $flags = AccountFlag::where('is_review_account', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->whereNull('disabled_at')
                ->with('user')
                ->get();

            foreach ($flags as $flag) {
                if ($flag->user instanceof User) {
                    $service->disable($flag->user);
                    $this->line("disabled: {$flag->user->email}");
                }
            }

            return 0;
        }

        $email = (string) $this->option('email');
        if ($email === '') {
            $this->error('--email or --all-expired is required');

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
                expiresAt: null,
                note: $user->accountFlag->note,
                operatorId: (int) ($user->accountFlag->created_by ?? 0),
            );
            $service->reEnable($user, $profile, $ctx);
            $this->info("re-enabled: {$email}");

            return 0;
        }

        $service->disable($user);
        $this->info("disabled: {$email}");

        return 0;
    }
}
