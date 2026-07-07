<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Console\Commands;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Domain\CardIssuance\Models\FinCardDepositAddress;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Operator query: print everything FinCard-related for a single user. Reads only
 * — never writes. Mirror of bridge:inspect-user / wallet:inspect-user.
 *
 * Use when support needs to see a user's card state end to end:
 *
 *   php artisan fincard:inspect-user user@example.com
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md
 */
final class FinCardInspectUserCommand extends Command
{
    protected $signature = 'fincard:inspect-user {email : User email to look up}';

    protected $description = 'Print FinCard cardholder KYC, funding account, deposit addresses and cards for a user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->error(sprintf('No user with email "%s".', $email));

            return self::FAILURE;
        }

        $this->info("User #{$user->id} — {$user->email}");
        $this->newLine();

        $this->printCardholder($user);
        $this->newLine();
        $this->printAccount($user);
        $this->newLine();
        $this->printCards($user);

        return self::SUCCESS;
    }

    private function printCardholder(User $user): void
    {
        $this->line('<comment>Cardholder (KYC)</comment>');
        $cardholder = Cardholder::query()
            ->where('user_id', $user->id)
            ->whereNotNull('issuer_cardholder_id')
            ->first();

        if (! $cardholder instanceof Cardholder) {
            $this->line('  (none — KYC not started)');

            return;
        }

        $this->table(['holderId', 'kyc_status', 'kyc_stage', 'rejection', 'verified_at'], [[
            $cardholder->issuer_cardholder_id,
            $cardholder->kyc_status,
            $cardholder->kyc_stage ?? '—',
            $cardholder->kyc_rejection_reason ?? '—',
            $cardholder->verified_at?->toDateTimeString() ?? '—',
        ]]);
    }

    private function printAccount(User $user): void
    {
        $this->line('<comment>Funding account + deposit addresses</comment>');
        $account = FinCardAccount::query()->where('user_id', $user->id)->first();

        if (! $account instanceof FinCardAccount) {
            $this->line('  (no FinCard account provisioned)');

            return;
        }

        $this->table(['accountId', 'balance', 'currency', 'status'], [[
            $account->fincard_account_id,
            $this->money((int) $account->balance_cents),
            $account->currency,
            $account->status,
        ]]);

        $addresses = FinCardDepositAddress::query()->where('user_id', $user->id)->get();
        if ($addresses->isNotEmpty()) {
            $this->table(
                ['coin', 'chain', 'address'],
                $addresses->map(fn (FinCardDepositAddress $a): array => [$a->coin_key, $a->chain ?? '—', $a->address])->all(),
            );
        }
    }

    private function printCards(User $user): void
    {
        $this->line('<comment>Cards</comment>');
        $cards = Card::query()->where('user_id', $user->id)->where('issuer', 'fincard')->get();

        if ($cards->isEmpty()) {
            $this->line('  (no cards)');

            return;
        }

        $this->table(
            ['cardId', 'last4', 'network', 'status', 'balance'],
            $cards->map(fn (Card $c): array => [
                $c->issuer_card_token,
                $c->last4,
                $c->network,
                $c->status,
                $this->money((int) $c->balance_cents),
            ])->all(),
        );
    }

    private function money(int $cents): string
    {
        return '$' . bcdiv((string) $cents, '100', 2);
    }
}
