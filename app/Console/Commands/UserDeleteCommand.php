<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\Account;
use App\Domain\User\Values\UserRoles;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Hard-delete a single User row plus its Sanctum tokens for staging cleanup.
 *
 * Cleans Sanctum tokens explicitly because `personal_access_tokens` is a
 * morph-keyed table without an FK to `users`, so onDelete cascades miss it.
 * Accounts are also deleted explicitly: the original `accounts.user_uuid`
 * FK was created without `onDelete('cascade')`, so a bare `$user->delete()`
 * would fail with a constraint violation. Tables that DO cascade (kyc_documents,
 * bank_accounts, votes, smart_accounts, multi_sig_*, hardware_*, rewards_*,
 * payment_intents, etc.) are swept by the FK on `users.id` / `users.uuid`.
 * Audit logs use `set null`, preserving the trail.
 *
 *   php artisan user:delete --email=test@example.com --confirm \
 *       --operator-email=admin@zelta.app
 *   php artisan user:delete --email=test@example.com --confirm \
 *       --allow-production --operator-email=admin@zelta.app
 */
final class UserDeleteCommand extends Command
{
    /** @var string */
    protected $signature = 'user:delete
                            {--email= : Email of the user to delete}
                            {--confirm : Required. This is destructive and irreversible.}
                            {--allow-production : Required when APP_ENV=production}
                            {--operator-email= : Admin operator email (required, must have admin role)}';

    /** @var string */
    protected $description = 'Hard-delete a user (staging cleanup); refuses on admins, KYC-approved, balance, or wallet-send history';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('--confirm is required. This is destructive and irreversible.');

            return self::FAILURE;
        }

        $email = trim((string) $this->option('email'));
        if ($email === '') {
            $this->error('--email is required.');

            return self::FAILURE;
        }

        $operatorEmail = trim((string) $this->option('operator-email'));
        if ($operatorEmail === '') {
            $this->error('--operator-email is required.');

            return self::FAILURE;
        }

        $operator = User::where('email', $operatorEmail)->first();
        if ($operator === null || ! $operator->hasRole(UserRoles::ADMIN->value)) {
            $this->error("Operator {$operatorEmail} not found or not an admin.");

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production guard: --allow-production is required.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user === null) {
            $this->error("User {$email} not found.");

            return self::FAILURE;
        }

        if ($user->hasRole(UserRoles::ADMIN->value)) {
            $this->error("Refusing to delete admin user {$email}. Demote first.");

            return self::FAILURE;
        }

        if ($user->kyc_status === 'approved') {
            $this->error("Refusing to delete KYC-approved user {$email}.");

            return self::FAILURE;
        }

        if ($this->userHasNonZeroBalance($user)) {
            $this->error("Refusing to delete user {$email} with non-zero account balance.");

            return self::FAILURE;
        }

        if (WalletSendRecord::where('user_id', $user->id)->exists()) {
            $this->error("Refusing to delete user {$email} with wallet send history.");

            return self::FAILURE;
        }

        $userId = (int) $user->id;

        try {
            DB::transaction(function () use ($user): void {
                // personal_access_tokens uses morph keys with no FK — cascades miss it.
                $user->tokens()->delete();

                // accounts.user_uuid was created without onDelete('cascade') in the
                // original migration; delete explicitly so account_balances (which
                // does cascade off accounts.uuid) is swept too.
                foreach ($user->accounts()->get() as $account) {
                    /** @var Account $account */
                    $account->delete();
                }

                $user->delete();
            });
        } catch (Throwable $e) {
            $this->error("Delete failed for {$email}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("deleted: {$email} (user_id={$userId})");

        return self::SUCCESS;
    }

    /**
     * Returns true if the user has any account whose `balance` column or
     * AccountBalance row is greater than zero in any asset.
     */
    private function userHasNonZeroBalance(User $user): bool
    {
        foreach ($user->accounts()->get() as $account) {
            /** @var Account $account */
            if ((int) $account->getAttributes()['balance'] > 0) {
                return true;
            }

            $hasNonZeroAssetBalance = $account->balances()->where('balance', '>', 0)->exists();
            if ($hasNonZeroAssetBalance) {
                return true;
            }
        }

        return false;
    }
}
