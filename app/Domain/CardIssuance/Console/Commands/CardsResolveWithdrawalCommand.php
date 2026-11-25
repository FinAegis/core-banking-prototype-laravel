<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Console\Commands;

use App\Domain\CardIssuance\Jobs\AdvanceLepoWithdrawals;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawals;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\Amounts;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\WithdrawalJournal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Closes or continues a needs_attention withdrawal after an operator checked
 * it at Lepo (cards:inspect-withdrawal shows the reads):
 *
 * - completed: the money reached the wallet (--received-amount, --tx-hash).
 * - failed: the withdrawal ends; --money-location says where the money is
 *   now (card, usd, usdc or returned) for the follow-up with the user.
 * - resume: the step that stopped clearly happened; the job continues from
 *   the next step. A withdrawal request that stopped without an answer is
 *   repeated once, with the same idempotency key.
 *
 * Takes the user's money lock and writes an audit line with the operator.
 */
final class CardsResolveWithdrawalCommand extends Command
{
    protected $signature = 'cards:resolve-withdrawal
        {withdrawal : The withdrawal id}
        {outcome : completed, failed or resume}
        {--note= : What was checked at Lepo}
        {--operator-email= : The operator doing this}
        {--received-amount= : USDC that reached the wallet (completed)}
        {--tx-hash= : The on-chain transaction hash (completed)}
        {--money-location= : card, usd, usdc or returned (failed)}
        {--unloaded-cents= : Dollars the unload put on the USD balance, in cents (resume after an unclear unload)}
        {--usdc-amount= : USDC the exchange produced (resume after an unclear exchange)}';

    protected $description = 'Close or continue a needs_attention card withdrawal after reconciling it at Lepo';

    /** Reasons where a resume needs the partner's answer first. */
    private const NOT_RESUMABLE = ['CODE_LOCKED', 'REQUEST_EXPIRED', 'SEND_FAILED'];

    public function handle(LepoWithdrawals $withdrawals): int
    {
        $withdrawal = CardWithdrawal::query()->find((string) $this->argument('withdrawal'));
        $outcome = (string) $this->argument('outcome');
        $note = trim((string) $this->option('note'));
        $operator = trim((string) $this->option('operator-email'));

        if (! $withdrawal instanceof CardWithdrawal || $withdrawal->status !== CardWithdrawal::STATUS_NEEDS_ATTENTION) {
            $this->error('No withdrawal waiting for attention with that id.');

            return self::FAILURE;
        }
        if (! in_array($outcome, ['completed', 'failed', 'resume'], true)) {
            $this->error('The outcome must be completed, failed or resume.');

            return self::FAILURE;
        }
        if ($note === '') {
            $this->error('Write what was checked with --note.');

            return self::FAILURE;
        }
        if (filter_var($operator, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Name the operator with --operator-email.');

            return self::FAILURE;
        }

        $lock = Cache::lock(LepoWithdrawals::moneyLockKey((int) $withdrawal->user_id), LepoTopUpPipeline::LOCK_SECONDS);
        if (! $lock->get()) {
            $this->error('A money operation is running for this user; try again in a minute.');

            return self::FAILURE;
        }

        try {
            $withdrawal->refresh();
            if ($withdrawal->status !== CardWithdrawal::STATUS_NEEDS_ATTENTION) {
                $this->error('The withdrawal changed meanwhile; inspect it again.');

                return self::FAILURE;
            }

            $actor = WithdrawalJournal::operator($operator);
            $result = match ($outcome) {
                'completed' => $this->markCompleted($withdrawals, $withdrawal, $actor, $note),
                'failed'    => $this->markFailed($withdrawals, $withdrawal, $actor, $note),
                default     => $this->resume($withdrawals, $withdrawal, $actor, $note),
            };
        } finally {
            $lock->release();
        }

        if ($result === self::SUCCESS && $outcome === 'resume') {
            AdvanceLepoWithdrawals::dispatch($withdrawals->lepoUserId($withdrawal->card()->firstOrFail()));
        }

        return $result;
    }

    private function markCompleted(LepoWithdrawals $withdrawals, CardWithdrawal $withdrawal, string $actor, string $note): int
    {
        $received = is_string($this->option('received-amount')) ? Amounts::parse($this->option('received-amount'), 6) : null;
        $hash = is_string($this->option('tx-hash')) ? strtolower($this->option('tx-hash')) : '';
        if ($received === null || preg_match('/^0x[0-9a-f]{64}$/', $hash) !== 1) {
            $this->error('A completed withdrawal needs --received-amount and --tx-hash.');

            return self::FAILURE;
        }

        $this->journal($withdrawal, $actor, "completed: {$note}");
        $withdrawals->transition($withdrawal, CardWithdrawal::STATUS_COMPLETED, null, $actor, ['received_amount' => $received, 'tx_hash' => $hash, 'completed_at' => now()], 'resolved by operator');
        $this->info("Withdrawal {$withdrawal->id} is now completed.");

        return self::SUCCESS;
    }

    private function markFailed(LepoWithdrawals $withdrawals, CardWithdrawal $withdrawal, string $actor, string $note): int
    {
        $location = (string) $this->option('money-location');
        if (! in_array($location, ['card', 'usd', 'usdc', 'returned'], true)) {
            $this->error('A failed withdrawal needs --money-location=card|usd|usdc|returned.');

            return self::FAILURE;
        }

        $this->journal($withdrawal, $actor, "failed, money: {$location}: {$note}");
        $withdrawals->transition($withdrawal, CardWithdrawal::STATUS_FAILED, $withdrawal->last_error, $actor, [], "money: {$location}");
        $this->info("Withdrawal {$withdrawal->id} is now failed (money: {$location}).");

        return self::SUCCESS;
    }

    private function resume(LepoWithdrawals $withdrawals, CardWithdrawal $withdrawal, string $actor, string $note): int
    {
        if (in_array((string) $withdrawal->last_error, self::NOT_RESUMABLE, true)) {
            $this->error('This withdrawal cannot be resumed until the card provider says what happens to the request. Close it with completed or failed.');

            return self::FAILURE;
        }

        $sent = $withdrawal->confirm_requested_at !== null || $withdrawal->confirmed_at !== null;
        $coded = $withdrawal->code_sent_at !== null;

        [$status, $extra, $message] = match (true) {
            $sent                              => [CardWithdrawal::STATUS_SENDING, ['resumed_at' => now()], 'The job keeps watching for the transfer.'],
            $coded                             => [CardWithdrawal::STATUS_AWAITING_CODE, ['code_sent_at' => now()], 'The user can enter or ask for a new code again.'],
            $withdrawal->exchanged_at !== null => $this->resumeRequest($withdrawal),
            $withdrawal->unloaded_at !== null  => $this->resumeAfterExchange(),
            default                            => $this->resumeAfterUnload(),
        };

        if ($status === null) {
            $this->error($message);

            return self::FAILURE;
        }

        $this->journal($withdrawal, $actor, "resume: {$note}");
        $withdrawals->transition($withdrawal, $status, null, $actor, $extra, 'resumed by operator');
        $this->info("Withdrawal {$withdrawal->id} continues. {$message}");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>, 2: string}
     */
    private function resumeRequest(CardWithdrawal $withdrawal): array
    {
        if ($withdrawal->withdrawal_attempts >= 2) {
            return [null, [], 'The withdrawal request was already repeated once. Close it with completed or failed.'];
        }

        // Repeated once, with the same idempotency key.
        return [CardWithdrawal::STATUS_CONVERTING, ['withdrawal_requested_at' => null], 'The withdrawal request is sent once more with the same key.'];
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>, 2: string}
     */
    private function resumeAfterExchange(): array
    {
        $usdc = is_string($this->option('usdc-amount')) ? Amounts::parse($this->option('usdc-amount'), 6) : null;
        if ($usdc === null || bccomp($usdc, '0', 6) !== 1) {
            return [null, [], 'Resuming after the exchange needs --usdc-amount (what the exchange produced).'];
        }

        return [CardWithdrawal::STATUS_CONVERTING, ['usdc_amount' => $usdc, 'exchanged_at' => now()], 'The withdrawal request comes next.'];
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>, 2: string}
     */
    private function resumeAfterUnload(): array
    {
        $cents = $this->option('unloaded-cents');
        if (! is_string($cents) || preg_match('/^[1-9]\d*$/', $cents) !== 1) {
            return [null, [], 'Resuming after the unload needs --unloaded-cents (what reached the USD balance).'];
        }

        return [CardWithdrawal::STATUS_CONVERTING, ['unloaded_cents' => (int) $cents, 'unloaded_at' => now()], 'The exchange comes next.'];
    }

    private function journal(CardWithdrawal $withdrawal, string $actor, string $note): void
    {
        WithdrawalJournal::record($withdrawal, $actor, 'resolved', ['from_status' => $withdrawal->status, 'note' => $note]);
    }
}
