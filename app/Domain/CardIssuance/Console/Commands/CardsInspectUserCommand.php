<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Console\Commands;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Support view of a user's Lepo card setup: cardholder, funding account,
 * cards and top-ups with their needs_attention reasons. Prints no personal
 * data beyond the e-mail asked for and no card numbers or codes.
 */
final class CardsInspectUserCommand extends Command
{
    protected $signature = 'cards:inspect-user {email : User email to look up}';

    protected $description = 'Print a user\'s Lepo cardholder, funding account, cards and top-ups (no secrets)';

    public function handle(): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No user with that e-mail.');

            return self::FAILURE;
        }

        $holder = Cardholder::query()->where('user_id', $user->id)->where('provider', 'lepo')->first();
        if ($holder === null) {
            $this->line('No Lepo cardholder for this user.');

            return self::SUCCESS;
        }

        $this->line("Cardholder {$holder->id}: KYC {$holder->kyc_status}, Lepo user {$holder->issuer_cardholder_id}");

        $account = CardFundingAccount::query()->where('cardholder_id', $holder->id)->first();
        $this->line($account === null ? 'No funding account.' : "Funding account {$account->id}: {$account->status}");

        $this->table(
            ['Card', 'Status', 'Last 4', 'Balance (cents)', 'Lepo card'],
            Card::query()->where('cardholder_id', $holder->id)->where('issuer', 'lepo')->orderByDesc('created_at')->get()
                ->map(fn (Card $card): array => [$card->id, $card->status, $card->last4 ?? '-', (string) ($card->balance_cents ?? 0), $card->issuer_card_token ?? '-'])
                ->all(),
        );

        $this->table(
            ['Top-up', 'Source', 'Status', 'Amount', 'Network', 'Credited (cents)', 'Reason', 'Created'],
            CardTopUp::query()->where('user_id', $user->id)->orderByDesc('created_at')->limit(50)->get()
                ->map(fn (CardTopUp $topup): array => [
                    $topup->id, $topup->source, $topup->status, (string) $topup->amount, $topup->network,
                    $topup->credited_cents === null ? '-' : (string) $topup->credited_cents, $topup->last_error ?? '-', (string) $topup->created_at,
                ])->all(),
        );

        return self::SUCCESS;
    }
}
