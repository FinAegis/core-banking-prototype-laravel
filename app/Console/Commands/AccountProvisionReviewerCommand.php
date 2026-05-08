<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class AccountProvisionReviewerCommand extends Command
{
    /** @var string */
    protected $signature = 'account:provision-reviewer
                            {--email= : Reviewer email}
                            {--password= : Password (generated if omitted)}
                            {--region=US : Reviewer region}
                            {--expires-days=60 : Days until auto-disable (0=none, hard cap 90)}
                            {--note= : Audit note}
                            {--operator-email= : Admin operator email (required)}
                            {--allow-production : Required when APP_ENV=production}
                            {--rotate-password : Rotate password only, skip reseed}
                            {--force-convert : Convert a non-review user (blocked in production)}
                            {--issue-mobile-token : Also issue a long-lived Sanctum token for app-store reviewer paste-to-login (shown once, treat like the password)}
                            {--dry-run : Print intended changes, write nothing}';

    /** @var string */
    protected $description = 'Provision a pre-seeded reviewer/demo account for app-store review (operator-only)';

    public function handle(AccountProvisioningService $service, ReviewerAccountProfile $profile): int
    {
        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production guard: --allow-production is required when APP_ENV=production.');

            return 1;
        }

        if (app()->environment('production') && $this->option('force-convert')) {
            $this->error('--force-convert is blocked in production.');

            return 1;
        }

        $email = (string) $this->option('email');
        if ($email === '') {
            $this->error('--email is required');

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

        $expiresDays = (int) $this->option('expires-days');
        if ($expiresDays < 0 || $expiresDays > 90) {
            $this->error('--expires-days must be 0..90 (hard cap).');

            return 1;
        }

        $passwordOpt = $this->option('password');
        $password = is_string($passwordOpt) && $passwordOpt !== '' ? $passwordOpt : null;
        $rotate = (bool) $this->option('rotate-password');

        if ($password === null) {
            if ($rotate || User::where('email', $email)->doesntExist()) {
                $password = Str::password(20);
            }
        }

        $ctx = new ProvisioningContext(
            email: $email,
            name: 'App Reviewer',
            region: (string) $this->option('region'),
            expiresAt: $expiresDays > 0 ? CarbonImmutable::now()->addDays($expiresDays) : null,
            note: is_string($this->option('note')) && $this->option('note') !== '' ? (string) $this->option('note') : null,
            operatorId: (int) $operator->id,
            dryRun: (bool) $this->option('dry-run'),
        );

        try {
            $result = $service->apply(
                profile: $profile,
                ctx: $ctx,
                password: $password,
                rotatePassword: $rotate,
                forceConvert: (bool) $this->option('force-convert'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $output = [
            'email'    => $email,
            'password' => $result['password_action'] === 'unchanged' ? 'unchanged' : $password,
            'user_id'  => $result['user']->id,
            'flags'    => array_map(
                fn ($v) => $v instanceof CarbonImmutable ? $v->toIso8601String() : $v,
                $profile->flags($ctx),
            ),
            'expires_at' => $ctx->expiresAt?->toIso8601String(),
            'operator'   => $operator->email,
            'dry_run'    => $ctx->dryRun,
        ];

        if ((bool) $this->option('issue-mobile-token')) {
            // Token expiry tracks the reviewer flag's expiry when set, otherwise
            // hard-caps at 90 days so a stale token cannot outlive the review cycle.
            $tokenExpiresAt = $ctx->expiresAt instanceof DateTimeInterface
                ? $ctx->expiresAt
                : CarbonImmutable::now()->addDays(90);

            if ($ctx->dryRun) {
                // Preview only — keep `dry_run: true` honest, never write a token row.
                $output['mobile_token'] = '(would-be-issued)';
            } else {
                $newToken = $result['user']->createToken(
                    'reviewer-mobile',
                    ['read', 'write', 'delete'],
                    $tokenExpiresAt,
                );
                // plainTextToken is shown ONCE; store in 1Password immediately,
                // never log/Slack/email it. Disable revokes via name='reviewer-mobile'.
                $output['mobile_token'] = $newToken->plainTextToken;
            }
        }

        $this->line((string) json_encode($output, JSON_PRETTY_PRINT));

        return 0;
    }
}
