<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Jobs;

use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawalPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drives "Send to wallet": unload, exchange and withdrawal request one after
 * the other right after the start, then watches confirmed transfers until
 * they arrive and moves a code nobody entered to an operator.
 *
 * The scheduled run (no user) queues one job per Lepo user with work left;
 * the start request and the card.unload / wallet.withdrawal webhooks queue
 * the job for their user. Jobs are unique per user. It runs whether or not
 * the switch is on: rows already past the unload must always finish; the
 * pipeline cancels rows that have not reached the unload while it is off.
 */
final class AdvanceLepoWithdrawals implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /** Above the slowest run (several 15-second calls), below the money lock. */
    public int $timeout = 240;

    public int $uniqueFor = 300;

    /** Steps of one withdrawal per run: unload, exchange, request, and a spare. */
    private const MAX_STEPS = 5;

    public function __construct(public readonly ?string $lepoUserId = null)
    {
    }

    public function uniqueId(): string
    {
        return 'withdrawals:' . ($this->lepoUserId ?? 'all');
    }

    public function handle(): void
    {
        if ($this->lepoUserId === null) {
            $users = self::pending()->distinct()->pluck('user_id');
            Cardholder::query()
                ->where('provider', 'lepo')
                ->whereIn('user_id', $users)
                ->whereNotNull('issuer_cardholder_id')
                ->pluck('issuer_cardholder_id')
                ->each(fn ($id) => self::dispatch((string) $id));

            return;
        }

        $holder = Cardholder::query()->where('provider', 'lepo')->where('issuer_cardholder_id', $this->lepoUserId)->first();
        if ($holder === null) {
            return;
        }

        $pipeline = app(LepoWithdrawalPipeline::class);
        foreach (self::pending()->where('user_id', $holder->user_id)->orderBy('created_at')->get() as $withdrawal) {
            try {
                // One step per lock hold, repeated while each step moves it on.
                for ($i = 0; $i < self::MAX_STEPS; $i++) {
                    $before = self::progress($withdrawal);
                    $pipeline->advance($withdrawal);
                    if (self::progress($withdrawal) === $before) {
                        break;
                    }
                }
            } catch (Throwable $e) {
                Log::error('Card withdrawal could not be advanced', ['withdrawal_id' => $withdrawal->id, 'error' => $e::class]);
            }
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<CardWithdrawal>
     */
    private static function pending(): \Illuminate\Database\Eloquent\Builder
    {
        return CardWithdrawal::query()->where(fn ($query) => $query
            ->whereIn('status', CardWithdrawal::IN_PROGRESS)
            ->orWhere(fn ($closing) => $closing->where('status', CardWithdrawal::STATUS_COMPLETED)
                ->where('close_card', true)->where('card_closed', false)->whereNull('card_close_attempted_at')
                ->where('completed_at', '>=', now()->subHour())));
    }

    private static function progress(CardWithdrawal $withdrawal): string
    {
        return implode('|', [
            $withdrawal->status,
            $withdrawal->step_started_at?->getTimestamp(),
            $withdrawal->unloaded_at?->getTimestamp(),
            $withdrawal->exchange_requested_at?->getTimestamp(),
            $withdrawal->exchanged_at?->getTimestamp(),
            $withdrawal->withdrawal_requested_at?->getTimestamp(),
            $withdrawal->card_close_attempted_at?->getTimestamp(),
        ]);
    }
}
