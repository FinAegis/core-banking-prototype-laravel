<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Carbon\CarbonImmutable;
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

        $this->line((string) json_encode([
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
        ], JSON_PRETTY_PRINT));

        return 0;
    }
}
