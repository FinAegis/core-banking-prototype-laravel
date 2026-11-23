<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Jobs;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardLifecycle;
use App\Domain\CardIssuance\Providers\Lepo\LepoDepositMatcher;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUps;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Drives "Add to card": finds deposits for waiting top-ups, moves received
 * money onto the card, expires top-ups nobody funded, and turns deposits
 * no top-up claimed into external top-ups (opening a card when the user has
 * none), so money that arrives always ends up spendable. Does nothing while
 * the top-up switch is off.
 *
 * The scheduled run (no user) queues one job per Lepo cardholder; a webhook
 * queues the job for its user. Jobs are unique per user, so two runs for one
 * user never overlap, and each has its own time limit.
 */
final class AdvanceLepoTopUps implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /** Above the slowest run (three Lepo calls with retries), below the money lock. */
    public int $timeout = 240;

    public int $uniqueFor = 300;

    /** Deposits below this are address-poisoning dust, not top-ups. */
    private const DUST = '1.00';

    public function __construct(public readonly ?string $lepoUserId = null)
    {
    }

    public function uniqueId(): string
    {
        return $this->lepoUserId ?? 'all';
    }

    public function handle(): void
    {
        if (! LepoTopUps::enabled()) {
            return;
        }

        if ($this->lepoUserId === null) {
            Cardholder::query()
                ->where('provider', 'lepo')
                ->where('kyc_status', 'verified')
                ->whereNotNull('issuer_cardholder_id')
                ->pluck('issuer_cardholder_id')
                ->each(fn ($id) => self::dispatch((string) $id));

            return;
        }

        $holder = Cardholder::query()->where('provider', 'lepo')->where('issuer_cardholder_id', $this->lepoUserId)->first();
        if ($holder === null) {
            return;
        }

        try {
            $this->advanceFor($holder);
        } catch (CardProviderException | LockTimeoutException $e) {
            Log::warning('Lepo top-ups could not be advanced', ['cardholder_id' => $holder->id, 'reason' => $e instanceof CardProviderException ? $e->reason() : 'LOCK_TIMEOUT']);
        }
    }

    private function advanceFor(Cardholder $holder): void
    {
        $matcher = app(LepoDepositMatcher::class);
        $pipeline = app(LepoTopUpPipeline::class);

        // Money already moving first, then waiting top-ups, then new deposits.
        $moving = CardTopUp::query()->where('user_id', $holder->user_id)->where('provider', 'lepo')
            ->whereIn('status', CardTopUp::IN_FLIGHT)->orderBy('created_at')->get();
        foreach ($moving as $topup) {
            $pipeline->advance($topup);
        }

        $waiting = CardTopUp::query()->where('user_id', $holder->user_id)->where('provider', 'lepo')
            ->where(fn ($query) => $query->where('status', CardTopUp::STATUS_AWAITING_DEPOSIT)
                ->orWhere(fn ($late) => $late->where('status', CardTopUp::STATUS_EXPIRED)->where('expires_at', '>=', now()->subDay())))
            ->orderBy('created_at')->get();
        foreach ($waiting as $topup) {
            if ($matcher->detect($topup)) {
                $pipeline->advance($topup);
            } elseif ($topup->status === CardTopUp::STATUS_AWAITING_DEPOSIT && $topup->expires_at->isPast()) {
                $this->expire($topup);
            }
        }

        // The whole history at most once an hour; recent transfers otherwise.
        $since = Cache::add("cards:lepo:full-scan:{$holder->id}", true, now()->addHour()) ? null : now()->subHours(2);
        foreach ($matcher->findUnmatched($holder, $since) as $deposit) {
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
        if (bccomp(LepoTopUps::number($deposit['amount']), self::DUST, 6) === -1) {
            Log::info('Lepo dust deposit ignored', ['cardholder_id' => $holder->id, 'deposit_id' => $deposit['id']]);

            return null;
        }

        $card = Card::query()
            ->where('cardholder_id', $holder->id)
            ->where('issuer', 'lepo')
            ->whereNotIn('status', [...Card::CLOSED_STATUSES, Card::STATUS_BLOCKED])
            ->orderByDesc('created_at')
            ->first();

        $reason = null;
        try {
            $card ??= app(LepoCardLifecycle::class)->createPending($holder);
        } catch (CardProviderException | LockTimeoutException) {
            // No card can take it (for example the only card is blocked): the
            // deposit is still recorded, without a card, for an operator.
            $card = null;
            $reason = 'NO_LOADABLE_CARD';
        }

        $topup = new CardTopUp();
        try {
            $topup->forceFill([
                'user_id'            => $holder->user_id,
                'card_id'            => $card instanceof Card ? $card->id : null,
                'funding_account_id' => $card instanceof Card && $card->funding_account_id !== null
                    ? $card->funding_account_id
                    : CardFundingAccount::query()->where('cardholder_id', $holder->id)->value('id'),
                'provider'            => 'lepo',
                'source'              => CardTopUp::SOURCE_EXTERNAL,
                'asset'               => 'USDC',
                'network'             => $deposit['network'],
                'amount'              => $deposit['amount'],
                'received_amount'     => $deposit['amount'],
                'deposit_address'     => $deposit['address'],
                'status'              => $reason === null ? CardTopUp::STATUS_DEPOSIT_RECEIVED : CardTopUp::STATUS_NEEDS_ATTENTION,
                'last_error'          => $reason,
                'provider_deposit_id' => $deposit['id'],
                'expires_at'          => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return null; // another run recorded this deposit first
        }

        if ($reason !== null) {
            Log::error('Lepo top-up needs attention', ['topup_id' => $topup->id, 'reason' => $reason]);
        }
        app(LepoTopUps::class)->announce($topup);

        return $reason === null ? $topup : null;
    }
}
