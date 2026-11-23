<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Console\Commands;

use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUps;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Closes a needs_attention top-up after an operator reconciled it at Lepo:
 * `completed` (with the cents that reached the card) or `failed`. A user
 * whose top-up had an unclear money outcome has their other top-ups held
 * until this is done.
 */
final class CardsResolveTopUpCommand extends Command
{
    protected $signature = 'cards:resolve-topup
        {topup : The top-up id}
        {outcome : completed or failed}
        {--credited-cents= : Cents that reached the card (required for completed)}
        {--note= : What was checked at Lepo}';

    protected $description = 'Close a needs_attention card top-up after reconciling it at Lepo';

    public function handle(LepoTopUps $topups): int
    {
        $topup = CardTopUp::query()->find((string) $this->argument('topup'));
        $outcome = (string) $this->argument('outcome');
        $credited = is_scalar($this->option('credited-cents')) ? (string) $this->option('credited-cents') : null;
        $note = trim((string) $this->option('note'));

        if (! $topup instanceof CardTopUp || $topup->status !== CardTopUp::STATUS_NEEDS_ATTENTION) {
            $this->error('No top-up waiting for attention with that id.');

            return self::FAILURE;
        }
        if (! in_array($outcome, [CardTopUp::STATUS_COMPLETED, CardTopUp::STATUS_FAILED], true)) {
            $this->error('The outcome must be completed or failed.');

            return self::FAILURE;
        }
        if ($outcome === CardTopUp::STATUS_COMPLETED && (! is_string($credited) || preg_match('/^\d+$/', $credited) !== 1)) {
            $this->error('A completed top-up needs --credited-cents.');

            return self::FAILURE;
        }
        if ($note === '') {
            $this->error('Write what was checked with --note.');

            return self::FAILURE;
        }

        $lock = Cache::lock(LepoTopUpPipeline::moneyLockKey((int) $topup->user_id), LepoTopUpPipeline::LOCK_SECONDS);
        if (! $lock->get()) {
            $this->error('A money operation is running for this user; try again in a minute.');

            return self::FAILURE;
        }

        try {
            $topup->forceFill([
                'status'         => $outcome,
                'credited_cents' => $outcome === CardTopUp::STATUS_COMPLETED ? (int) $credited : $topup->credited_cents,
                'last_error'     => mb_substr(($topup->last_error ?? 'NEEDS_ATTENTION') . '; resolved: ' . $note, 0, 255),
                'completed_at'   => $outcome === CardTopUp::STATUS_COMPLETED ? now() : $topup->completed_at,
            ])->save();
            $topups->announce($topup);
        } finally {
            $lock->release();
        }

        $this->info("Top-up {$topup->id} is now {$outcome}.");

        return self::SUCCESS;
    }
}
