<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Jobs;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardLifecycle;
use App\Domain\CardIssuance\Providers\Lepo\LepoDepositMatcher;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUps;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Drives "Add to card": finds deposits for waiting top-ups, moves received
 * money onto the card, expires top-ups nobody funded, and turns deposits
 * no top-up claimed into external top-ups (opening a card when the user has
 * none), so money that arrives always ends up spendable. Runs every minute
 * and when Lepo reports a deposit for a user; does nothing while the
 * top-up switch is off.
 */
final class AdvanceLepoTopUps implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly ?string $lepoUserId = null)
    {
    }

    public function handle(): void
    {
        if (! LepoTopUps::enabled()) {
            return;
        }

        $holders = Cardholder::query()
            ->where('provider', 'lepo')
            ->where('kyc_status', 'verified')
            ->when($this->lepoUserId !== null, fn ($query) => $query->where('issuer_cardholder_id', $this->lepoUserId))
            ->get();

        foreach ($holders as $holder) {
            try {
                $this->advanceFor($holder);
            } catch (CardProviderException $e) {
                Log::warning('Lepo top-ups could not be advanced', ['cardholder_id' => $holder->id, 'reason' => $e->reason()]);
            }
        }
    }

    private function advanceFor(Cardholder $holder): void
    {
        $matcher = app(LepoDepositMatcher::class);
        $pipeline = app(LepoTopUpPipeline::class);

        $rows = CardTopUp::query()
            ->where('user_id', $holder->user_id)
            ->where('provider', 'lepo')
            ->where(fn ($query) => $query->whereIn('status', CardTopUp::OPEN)
                ->orWhere(fn ($late) => $late->where('status', CardTopUp::STATUS_EXPIRED)->where('expires_at', '>=', now()->subDays(7))))
            ->orderBy('created_at')
            ->get();

        foreach ($rows as $topup) {
            if (in_array($topup->status, [CardTopUp::STATUS_AWAITING_DEPOSIT, CardTopUp::STATUS_EXPIRED], true)) {
                if ($matcher->detect($topup)) {
                    $pipeline->advance($topup);
                } elseif ($topup->status === CardTopUp::STATUS_AWAITING_DEPOSIT && $topup->expires_at->isPast()) {
                    $this->expire($topup);
                }

                continue;
            }

            $pipeline->advance($topup);
        }

        foreach ($matcher->findUnmatched($holder) as $deposit) {
            $external = $this->external($holder, $deposit);
            if ($external !== null) {
                $pipeline->advance($external);
            }
        }
    }

    private function expire(CardTopUp $topup): void
    {
        $changed = CardTopUp::query()->whereKey($topup->id)
            ->where('status', CardTopUp::STATUS_AWAITING_DEPOSIT)
            ->whereNull('provider_deposit_id')
            ->update(['status' => CardTopUp::STATUS_EXPIRED, 'updated_at' => now()]);

        if ($changed === 1) {
            app(LepoTopUps::class)->announce($topup->refresh());
        }
    }

    /**
     * @param  array{id: string, amount: string, network: string, address: string}  $deposit
     */
    private function external(Cardholder $holder, array $deposit): ?CardTopUp
    {
        $card = Card::query()
            ->where('cardholder_id', $holder->id)
            ->where('issuer', 'lepo')
            ->whereNotIn('status', [...Card::CLOSED_STATUSES, Card::STATUS_BLOCKED])
            ->orderByDesc('created_at')
            ->first();

        try {
            $card ??= app(LepoCardLifecycle::class)->createPending($holder);
        } catch (CardProviderException $e) {
            Log::error('Lepo deposit without a card to load', ['cardholder_id' => $holder->id, 'deposit_id' => $deposit['id'], 'reason' => $e->reason()]);

            return null;
        }

        $topup = new CardTopUp();
        try {
            $topup->forceFill([
                'user_id'             => $holder->user_id,
                'card_id'             => $card->id,
                'funding_account_id'  => $card->funding_account_id,
                'provider'            => 'lepo',
                'source'              => CardTopUp::SOURCE_EXTERNAL,
                'asset'               => 'USDC',
                'network'             => $deposit['network'],
                'amount'              => $deposit['amount'],
                'received_amount'     => $deposit['amount'],
                'deposit_address'     => $deposit['address'],
                'status'              => CardTopUp::STATUS_DEPOSIT_RECEIVED,
                'provider_deposit_id' => $deposit['id'],
                'expires_at'          => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return null; // another run recorded this deposit first
        }

        app(LepoTopUps::class)->announce($topup);

        return $topup;
    }
}
