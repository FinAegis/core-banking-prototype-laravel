<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\Account\Models\BlockchainTransaction;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\Amounts;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\WithdrawalJournal;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\WithdrawalRefused;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * The money steps of a withdrawal after the start: unload the card to the
 * user's USD balance at Lepo, exchange exactly those dollars to USDC, ask
 * Lepo to send exactly that USDC to the pinned address (Lepo e-mails the
 * user a code; the code is confirmed in the user's request), then watch for
 * the transfer to arrive.
 *
 * Our rules for every money call: the status, step_started_at and a new
 * idempotency key are saved, and an audit line written, before the call.
 * The key is sent as a header but nothing relies on Lepo honouring it. A
 * call that timed out or answered unclearly is never repeated: safe reads
 * decide what happened, and when they cannot decide in time the withdrawal
 * stops in needs_attention for an operator. One step per hold of the user's
 * money lock, which top-ups and card cancels take too.
 */
final class LepoWithdrawalPipeline
{
    /** Reads decide an interrupted unload or exchange within this time. */
    private const STEP_TIMEOUT_MINUTES = 15;

    /** Safe reads before a money call are retried this long. */
    private const READ_RETRY_MINUTES = 60;

    /** A confirm whose answer was unclear is matched within this time. */
    private const CONFIRM_UNCLEAR_MINUTES = 60;

    /** A confirmed transfer that has not arrived after this long needs a look. */
    private const SEND_SLOW_HOURS = 24;

    /** A code nobody entered: our team contacts the user. Nothing goes back to the card on its own. */
    public const CODE_NOT_ENTERED_HOURS = 72;

    /** The card is closed after a "close the card" withdrawal for this long. */
    private const CLOSE_RETRY_MINUTES = 60;

    /** Lepo's statuses for a finished or failed transfer, lower-cased. */
    private const DONE = ['completed', 'success', 'succeeded', 'closed'];

    private const FAILED = ['failed', 'cancelled', 'canceled', 'rejected', 'declined'];

    public function __construct(
        private readonly LepoClient $client,
        private readonly LepoWithdrawals $withdrawals,
        private readonly LepoCardLifecycle $cards,
    ) {
    }

    public function advance(CardWithdrawal $withdrawal): CardWithdrawal
    {
        $lock = Cache::lock(LepoWithdrawals::moneyLockKey((int) $withdrawal->user_id), LepoTopUpPipeline::LOCK_SECONDS);
        if (! $lock->get()) {
            return $withdrawal;
        }

        try {
            $withdrawal->refresh();
            $this->step($withdrawal);
        } catch (WithdrawalRefused) {
            // A read failed (provider unavailable): the next run tries again.
        } finally {
            $lock->release();
        }

        // Outside the money lock: the guarded cancel takes it itself.
        $this->closeCardIfAsked($withdrawal);

        return $withdrawal;
    }

    private function step(CardWithdrawal $withdrawal): void
    {
        match ($withdrawal->status) {
            CardWithdrawal::STATUS_UNLOADING     => $withdrawal->step_started_at !== null ? $this->reconcileUnload($withdrawal) : $this->unload($withdrawal),
            CardWithdrawal::STATUS_CONVERTING    => $this->convertingStep($withdrawal),
            CardWithdrawal::STATUS_AWAITING_CODE => $this->checkCodeAge($withdrawal),
            CardWithdrawal::STATUS_SENDING       => $this->reconcileDelivery($withdrawal),
            default                              => null,
        };
    }

    private function convertingStep(CardWithdrawal $withdrawal): void
    {
        if ($withdrawal->exchanged_at === null) {
            $withdrawal->step_started_at !== null ? $this->reconcileExchange($withdrawal) : $this->exchange($withdrawal);

            return;
        }

        // A withdrawal request was in flight when a run stopped: without its
        // token it cannot be confirmed, and it is not repeated on its own.
        if ($withdrawal->withdrawal_requested_at !== null) {
            $this->withdrawals->stop($withdrawal, 'WITHDRAWAL_UNCLEAR');

            return;
        }

        $this->requestWithdrawal($withdrawal);
    }

    // ─── Step 2: unload ─────────────────────────────────────────────────────

    private function unload(CardWithdrawal $withdrawal): void
    {
        // Rows past the unload keep going while the switch is off; a row that
        // has not started yet waits.
        if (! LepoWithdrawals::enabled()) {
            return;
        }

        $card = $withdrawal->card()->firstOrFail();
        $blocker = LepoWithdrawals::cardBlocker($card);
        if ($blocker !== null) {
            $this->withdrawals->cancel($withdrawal, match ($blocker) {
                'frozen_by_support' => 'CARD_FROZEN_BY_SUPPORT',
                'frozen'            => 'CARD_FROZEN',
                'card_closed'       => 'CARD_CLOSED',
                default             => 'CARD_NOT_READY',
            });

            return;
        }

        $available = $this->cardAvailable($card);
        if ($available === null) {
            if ($this->olderThan($withdrawal->created_at, self::READ_RETRY_MINUTES)) {
                $this->withdrawals->cancel($withdrawal, 'BALANCE_UNREADABLE');
            }

            return;
        }

        $amount = Amounts::of($withdrawal->amount, 2);
        if (bccomp($available, $amount, 2) === -1) {
            $this->withdrawals->cancel($withdrawal, 'BALANCE_CHANGED');

            return;
        }

        $key = $withdrawal->unload_key ?? (string) Str::uuid();
        $withdrawal->forceFill([
            'unload_key'                => $key,
            'step_started_at'           => now(),
            'unload_requested_at'       => now(),
            'card_balance_before_cents' => Amounts::cents($available),
            'last_error'                => null,
        ])->save();
        WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_started', ['note' => 'unload']);

        try {
            $answer = $this->client->unloadCard((string) $card->issuer_card_token, $amount, $key);
        } catch (LepoApiException $e) {
            WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['partner_http_status' => $e->httpStatus(), 'note' => 'unload ' . ($e->lepoCode() ?? 'error')]);
            $e->isDefiniteRefusal() ? $this->unloadRefused($withdrawal, $card) : $this->reconcileUnload($withdrawal);

            return;
        }
        WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['partner_http_status' => 200, 'note' => 'unload']);

        // The request must be ours. What reaches the USD balance is netAmount;
        // a fee or a partial unload is carried forward, never more than sent.
        $requested = Amounts::parse($answer['requestedAmount'] ?? null, 2);
        $net = Amounts::parse($answer['netAmount'] ?? $answer['actualAmount'] ?? null, 2);
        if ($requested === null || bccomp($requested, $amount, 2) !== 0 || $net === null || bccomp($net, '0', 2) !== 1 || bccomp($net, $amount, 2) === 1) {
            $this->withdrawals->stop($withdrawal, 'UNLOAD_AMOUNT_MISMATCH');

            return;
        }

        $fee = Amounts::parse($answer['fee'] ?? null, 8);
        $note = bccomp($net, $amount, 2) === -1 ? "unloaded {$net} of {$amount}" : null;
        $after = is_array($answer['balanceAfter'] ?? null) ? $answer['balanceAfter'] : [];
        $pending = Amounts::parse($after['pendingBalance'] ?? null, 2);
        if ($withdrawal->close_card && $pending !== null && bccomp($pending, '0', 2) === 1) {
            // The card cannot be closed while payments are pending; the app
            // already shows card_closed: false.
            WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['note' => 'card has pending payments; it cannot be closed yet']);
        }

        $this->withdrawals->transition($withdrawal, CardWithdrawal::STATUS_CONVERTING, null, WithdrawalJournal::ACTOR_JOB, [
            'unloaded_cents'     => Amounts::cents($net),
            'unload_fee_cents'   => $fee === null ? 0 : Amounts::centsUp($fee),
            'provider_unload_id' => $this->text($answer['transactionId'] ?? null),
            'unloaded_at'        => now(),
        ], $note);
    }

    /**
     * Lepo refused the unload. Nothing moved only if the card balance is
     * unchanged; anything else needs an operator.
     */
    private function unloadRefused(CardWithdrawal $withdrawal, Card $card): void
    {
        $available = $this->cardAvailable($card);
        if ($available !== null && Amounts::cents($available) === $withdrawal->card_balance_before_cents) {
            $this->withdrawals->cancel($withdrawal, 'UNLOAD_REFUSED');

            return;
        }

        $this->withdrawals->stop($withdrawal, 'UNLOAD_UNCLEAR');
    }

    /**
     * An unload call without a clear answer: Lepo's card_unload record or the
     * card balance falling by exactly the amount shows it happened.
     */
    private function reconcileUnload(CardWithdrawal $withdrawal): void
    {
        $card = $withdrawal->card()->firstOrFail();
        $amount = Amounts::of($withdrawal->amount, 2);
        $since = ($withdrawal->unload_requested_at ?? $withdrawal->step_started_at ?? now())->copy()->subMinute();

        $row = $this->findTransaction($withdrawal, ['card_unload'], $since, function (array $row) use ($amount, $card): bool {
            $value = Amounts::parse(ltrim((string) ($row['amount'] ?? ''), '-'), 2);
            $cardId = $row['cardId'] ?? null;

            return $value !== null && bccomp($value, $amount, 2) === 0
                && ($cardId === null || (string) $cardId === (string) $card->issuer_card_token)
                && ! in_array(strtolower((string) ($row['status'] ?? '')), self::FAILED, true);
        });

        $happened = $row !== null;
        if (! $happened && $withdrawal->card_balance_before_cents !== null) {
            $available = $this->cardAvailable($card);
            $happened = $available !== null && $withdrawal->card_balance_before_cents - Amounts::cents($available) === Amounts::cents($amount);
        }

        if ($happened) {
            $fee = $row === null ? null : Amounts::parse($row['feeAmount'] ?? null, 8);
            $this->withdrawals->transition($withdrawal, CardWithdrawal::STATUS_CONVERTING, null, WithdrawalJournal::ACTOR_JOB, [
                'unloaded_cents'     => Amounts::cents($amount),
                'unload_fee_cents'   => $fee === null ? 0 : Amounts::centsUp($fee),
                'provider_unload_id' => $row === null ? null : $this->text($row['id'] ?? null),
                'unloaded_at'        => now(),
            ], 'unload confirmed by reads');

            return;
        }

        $this->waitOrStop($withdrawal, 'UNLOAD_CHECKING', 'UNLOAD_UNCLEAR');
    }

    // ─── Step 3: exchange ───────────────────────────────────────────────────

    private function exchange(CardWithdrawal $withdrawal): void
    {
        $net = Amounts::fromCents((int) $withdrawal->unloaded_cents);
        if (bccomp($net, LepoWithdrawals::EXCHANGE_MINIMUM, 2) === -1) {
            $this->withdrawals->stop($withdrawal, 'BELOW_EXCHANGE_MINIMUM');

            return;
        }

        // Lepo allows one exchange every 15 seconds for all users together;
        // until it says the reverse direction has its own limit, it shares
        // the top-up conversion slot.
        if (! Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY, 15)->get()) {
            $this->setReason($withdrawal, 'EXCHANGE_QUEUED');

            return;
        }

        $userId = $this->withdrawals->lepoUserId($withdrawal->card()->firstOrFail());
        $before = $this->usdcAvailable($userId);
        $key = $withdrawal->exchange_key ?? (string) Str::uuid();
        $withdrawal->forceFill([
            'exchange_key'          => $key,
            'step_started_at'       => now(),
            'exchange_requested_at' => now(),
            'usdc_balance_before'   => $before,
            'last_error'            => null,
        ])->save();
        WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_started', ['note' => 'exchange']);

        try {
            // Exactly the dollars this unload brought, never the whole balance.
            $answer = $this->client->exchangeUsdToCrypto($userId, 'USDC', $net, $key);
        } catch (LepoApiException $e) {
            WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['partner_http_status' => $e->httpStatus(), 'note' => 'exchange ' . ($e->lepoCode() ?? 'error')]);
            if ($e->httpStatus() === 429) {
                // The rate limit: the exchange did not happen; the next run tries again.
                $withdrawal->forceFill(['step_started_at' => null, 'exchange_requested_at' => null, 'last_error' => 'EXCHANGE_RATE_LIMITED'])->save();

                return;
            }

            $e->isDefiniteRefusal() ? $this->withdrawals->stop($withdrawal, 'EXCHANGE_REFUSED') : $this->reconcileExchange($withdrawal);

            return;
        }
        WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['partner_http_status' => 200, 'note' => 'exchange']);

        $source = Amounts::parse($answer['sourceAmount'] ?? null, 2);
        if ($source === null || bccomp($source, $net, 2) !== 0) {
            $this->withdrawals->stop($withdrawal, 'EXCHANGE_AMOUNT_MISMATCH');

            return;
        }

        if (in_array(strtolower((string) ($answer['status'] ?? '')), self::FAILED, true)) {
            $this->withdrawals->stop($withdrawal, 'EXCHANGE_REFUSED');

            return;
        }

        $usdc = Amounts::parse($answer['cryptoAmountReceived'] ?? null, 6);
        if ($usdc === null || bccomp($usdc, '0', 6) !== 1) {
            $this->withdrawals->stop($withdrawal, 'EXCHANGE_UNCLEAR');

            return;
        }

        // The fee is in US dollars (current spec).
        $fee = Amounts::parse($answer['fee'] ?? null, 8);
        $withdrawal->forceFill([
            'usdc_amount'          => $usdc,
            'conversion_fee_cents' => $fee === null ? null : Amounts::centsUp($fee),
            'provider_trade_id'    => $this->text($answer['tradeId'] ?? null),
            'exchanged_at'         => now(),
            'step_started_at'      => null,
            'last_error'           => null,
        ])->save();
        $this->withdrawals->announce($withdrawal);
    }

    /**
     * An exchange without a clear answer: the USDC balance grew by no more
     * than the dollars sent, or Lepo's exchange record names a USDC amount.
     */
    private function reconcileExchange(CardWithdrawal $withdrawal): void
    {
        $userId = $this->withdrawals->lepoUserId($withdrawal->card()->firstOrFail());
        $net = Amounts::fromCents((int) $withdrawal->unloaded_cents);
        $usdc = null;

        $before = $withdrawal->usdc_balance_before === null ? null : Amounts::parse((string) $withdrawal->usdc_balance_before, 6);
        $now = $before === null ? null : $this->usdcAvailable($userId);
        if ($before !== null && $now !== null) {
            $grown = bcsub($now, $before, 6);
            // About one USDC per dollar, less the fee: more than the dollars
            // sent cannot be this exchange alone.
            if (bccomp($grown, '0', 6) === 1 && bccomp($grown, $net, 6) <= 0) {
                $usdc = $grown;
            }
        }

        if ($usdc === null) {
            $since = ($withdrawal->exchange_requested_at ?? now())->copy()->subMinute();
            $row = $this->findTransaction($withdrawal, ['quantum_to_crypto_exchange', 'exchange'], $since, fn (array $row): bool => strtoupper((string) ($row['currency'] ?? '')) === 'USDC'
                && ! in_array(strtolower((string) ($row['status'] ?? '')), self::FAILED, true));
            $value = $row === null ? null : Amounts::parse(ltrim((string) ($row['amount'] ?? ''), '-'), 6);
            if ($value !== null && bccomp($value, '0', 6) === 1 && bccomp($value, $net, 6) <= 0) {
                $usdc = $value;
            }
        }

        if ($usdc !== null) {
            $withdrawal->forceFill(['usdc_amount' => $usdc, 'exchanged_at' => now(), 'step_started_at' => null, 'last_error' => null])->save();
            WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['note' => 'exchange confirmed by reads']);

            return;
        }

        $this->waitOrStop($withdrawal, 'EXCHANGE_CHECKING', 'EXCHANGE_UNCLEAR');
    }

    // ─── Step 4: withdrawal request ─────────────────────────────────────────

    private function requestWithdrawal(CardWithdrawal $withdrawal): void
    {
        $card = $withdrawal->card()->firstOrFail();
        $userId = $this->withdrawals->lepoUserId($card);
        $usdc = Amounts::parse((string) $withdrawal->usdc_amount, 6) ?? '0';
        $chain = LepoWithdrawals::chainCode($withdrawal->network);
        $address = $withdrawal->destination_address;

        if ($chain === null) {
            $this->withdrawals->stop($withdrawal, 'NO_CHAIN_CODE');

            return;
        }
        // The address comes from the row only, checked once more before use.
        if (preg_match('/^0x[0-9a-fA-F]{40}$/', $address) !== 1) {
            $this->withdrawals->stop($withdrawal, 'DESTINATION_INVALID');

            return;
        }

        try {
            $fee = $this->withdrawals->withdrawalFee($userId, $withdrawal->network, $address, $usdc);
        } catch (WithdrawalRefused) {
            $this->olderThan($withdrawal->exchanged_at, self::READ_RETRY_MINUTES)
                ? $this->withdrawals->stop($withdrawal, 'FEE_UNREADABLE')
                : $this->setReason($withdrawal, 'FEE_UNREADABLE');

            return;
        }

        if (bccomp($fee, $usdc, 6) >= 0) {
            $this->withdrawals->stop($withdrawal, 'BELOW_WITHDRAWAL_FEE');

            return;
        }

        // Exactly the USDC this exchange produced, never the whole balance.
        // Until a real withdrawal shows otherwise, the fee comes out of it.
        $key = $withdrawal->withdrawal_key ?? (string) Str::uuid();
        $withdrawal->forceFill([
            'withdrawal_key'          => $key,
            'withdrawal_attempts'     => $withdrawal->withdrawal_attempts + 1,
            'step_started_at'         => now(),
            'withdrawal_requested_at' => now(),
            'withdrawal_fee'          => $fee,
            'withdrawal_amount'       => $usdc,
            'last_error'              => null,
        ])->save();
        WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_started', ['note' => 'withdrawal request']);

        try {
            $answer = $this->client->createCryptoWithdrawal($userId, 'USDC', $chain, $usdc, $address, $key);
        } catch (LepoApiException $e) {
            WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['partner_http_status' => $e->httpStatus(), 'note' => 'withdrawal request ' . ($e->lepoCode() ?? 'error')]);
            $this->withdrawals->stop($withdrawal, $e->isDefiniteRefusal() ? 'WITHDRAWAL_REFUSED' : 'WITHDRAWAL_UNCLEAR');

            return;
        }
        WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['partner_http_status' => 201, 'note' => 'withdrawal request']);

        $token = $this->text($answer['verificationToken'] ?? null);
        $codeRequired = ($answer['otpRequired'] ?? true) !== false;
        if ($codeRequired && $token === null) {
            $this->withdrawals->stop($withdrawal, 'WITHDRAWAL_UNCLEAR');

            return;
        }

        $withdrawal->forceFill([
            'provider_verification_token' => $token,
            'provider_withdrawal_id'      => $this->text($answer['transactionId'] ?? null) ?? $this->text($answer['withdrawalRequestId'] ?? null),
            'provider_transfer_id'        => $this->text($answer['interlaceTransferId'] ?? null),
            'provider_status'             => $this->text($answer['status'] ?? null),
            'provider_actual_amount'      => Amounts::parse($answer['actualAmount'] ?? null, 6),
        ])->save();

        if (! $codeRequired) {
            $this->withdrawals->transition($withdrawal, CardWithdrawal::STATUS_SENDING, null, WithdrawalJournal::ACTOR_JOB, ['confirmed_at' => now()], 'no code required');

            return;
        }

        $withdrawal->forceFill([
            'code_sent_at'       => now(),
            'code_expires_at'    => $this->time($answer['otpExpiresAt'] ?? null),
            'code_sent_to'       => $this->maskedEmail($userId),
            'code_attempts'      => 0,
            'code_attempts_left' => null,
        ])->save();
        WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'code_sent');
        $this->withdrawals->transition($withdrawal, CardWithdrawal::STATUS_AWAITING_CODE, null, WithdrawalJournal::ACTOR_JOB);
    }

    private function checkCodeAge(CardWithdrawal $withdrawal): void
    {
        if ($this->olderThan($withdrawal->code_sent_at, self::CODE_NOT_ENTERED_HOURS * 60)) {
            $this->withdrawals->stop($withdrawal, 'CODE_NOT_ENTERED');
        }
    }

    // ─── Step 6: delivery ───────────────────────────────────────────────────

    /**
     * The confirmed transfer arrives: seen by our own inbound mirror of the
     * wallet, or reported completed by Lepo. Lepo's webhook only wakes this
     * up; it carries no hash and no chain.
     */
    private function reconcileDelivery(CardWithdrawal $withdrawal): void
    {
        $since = $withdrawal->confirm_requested_at ?? $withdrawal->confirmed_at ?? $withdrawal->withdrawal_requested_at ?? $withdrawal->created_at ?? now();
        if ($withdrawal->resumed_at !== null && $withdrawal->resumed_at->gt($since)) {
            $since = $withdrawal->resumed_at;
        }

        $mirrored = $this->mirroredTransfer($withdrawal, $since);
        if ($mirrored !== null) {
            $this->complete($withdrawal, $mirrored['amount'], $mirrored['hash']);

            return;
        }

        $reported = $this->reportedTransfer($withdrawal, $since);
        if ($reported === 'failed') {
            $this->withdrawals->stop($withdrawal, 'SEND_FAILED');

            return;
        }
        if (is_array($reported)) {
            $this->complete($withdrawal, $reported['amount'], $reported['hash']);

            return;
        }

        if ($withdrawal->last_error === 'CONFIRM_CHECKING' && $this->olderThan($since, self::CONFIRM_UNCLEAR_MINUTES)) {
            $this->withdrawals->stop($withdrawal, 'CONFIRM_UNCLEAR');

            return;
        }

        if ($this->olderThan($since, self::SEND_SLOW_HOURS * 60)) {
            $this->withdrawals->stop($withdrawal, 'SEND_SLOW');
        }
    }

    /**
     * A USDC transfer to the pinned address seen by our inbound EVM mirror,
     * of about the amount sent (at most what was asked, at least 1 % less
     * than expected), and not used by another withdrawal.
     *
     * @return array{amount: string, hash: string}|null
     */
    private function mirroredTransfer(CardWithdrawal $withdrawal, Carbon $since): ?array
    {
        $sent = Amounts::parse((string) $withdrawal->withdrawal_amount, 6);
        if ($sent === null) {
            return null;
        }
        $expected = Amounts::parse((string) $withdrawal->provider_actual_amount, 6) ?? bcsub($sent, Amounts::parse((string) $withdrawal->withdrawal_fee, 6) ?? '0', 6);
        $floor = bcsub($expected, bcdiv($sent, '100', 6), 6);

        $rows = BlockchainTransaction::query()
            ->where('address_uuid', $withdrawal->destination_address_uuid)
            ->where('chain', $withdrawal->network)
            ->where('type', 'receive')
            ->where('created_at', '>=', $since->copy()->subMinutes(2))
            ->orderBy('created_at')
            ->get();

        foreach ($rows as $row) {
            $asset = strtoupper((string) (is_array($row->metadata) ? ($row->metadata['asset'] ?? 'USDC') : 'USDC'));
            $amount = Amounts::parse((string) $row->amount, 6);
            if ($asset !== 'USDC' || $amount === null || bccomp($amount, $sent, 6) === 1 || bccomp($amount, $floor, 6) === -1) {
                continue;
            }
            if (strtolower($row->to_address) !== strtolower($withdrawal->destination_address)) {
                continue;
            }
            $hash = strtolower($row->tx_hash);
            if (CardWithdrawal::query()->where('network', $withdrawal->network)->where('tx_hash', $hash)->whereKeyNot($withdrawal->id)->exists()) {
                continue;
            }

            return ['amount' => $amount, 'hash' => $hash];
        }

        return null;
    }

    /**
     * Lepo's view: its crypto transfer to the pinned address, then its
     * crypto_withdrawal record. 'failed', a completed transfer, or null when
     * nothing is known yet (or the reads failed).
     *
     * @return array{amount: string|null, hash: string|null}|'failed'|null
     */
    private function reportedTransfer(CardWithdrawal $withdrawal, Carbon $since): array|string|null
    {
        $userId = $this->withdrawals->lepoUserId($withdrawal->card()->firstOrFail());
        $ids = array_values(array_filter([$withdrawal->provider_transfer_id, $withdrawal->provider_withdrawal_id]));

        try {
            $transfers = $this->client->getCryptoTransactions($userId, ['startTime' => (string) ($since->copy()->subMinutes(2)->getTimestamp() * 1000), 'limit' => 50])['transfers'] ?? [];
        } catch (LepoApiException) {
            $transfers = [];
        }

        foreach (is_array($transfers) ? $transfers : [] as $transfer) {
            if (! is_array($transfer)) {
                continue;
            }
            $destination = is_array($transfer['destination'] ?? null) ? $transfer['destination'] : [];
            $byId = in_array((string) ($transfer['id'] ?? ''), $ids, true) || in_array((string) ($transfer['referenceId'] ?? ''), $ids, true);
            $byAddress = strtolower((string) ($destination['address'] ?? '')) === strtolower($withdrawal->destination_address);
            if (! $byId && ! $byAddress) {
                continue;
            }

            $status = strtolower((string) ($transfer['status'] ?? ''));
            if (in_array($status, self::FAILED, true)) {
                return 'failed';
            }
            if (in_array($status, self::DONE, true)) {
                $amount = is_array($transfer['amount'] ?? null) ? Amounts::parse($transfer['amount']['amount'] ?? null, 6) : null;

                return ['amount' => $amount, 'hash' => $this->hash($transfer)];
            }
        }

        $row = $this->findTransaction($withdrawal, ['crypto_withdrawal'], $since->copy()->subMinutes(2), fn (array $row): bool => $ids === []
            || in_array((string) ($row['externalTransactionId'] ?? ''), $ids, true) || in_array((string) ($row['tradeId'] ?? ''), $ids, true) || in_array((string) ($row['id'] ?? ''), $ids, true));
        if ($row !== null) {
            $status = strtolower((string) ($row['status'] ?? ''));
            if (in_array($status, self::FAILED, true)) {
                return 'failed';
            }
            if (in_array($status, self::DONE, true)) {
                return ['amount' => null, 'hash' => $this->hash($row)];
            }
        }

        return null;
    }

    private function complete(CardWithdrawal $withdrawal, ?string $amount, ?string $hash): void
    {
        $received = $amount ?? (Amounts::parse((string) $withdrawal->provider_actual_amount, 6));
        $extra = ['received_amount' => $received, 'tx_hash' => $hash, 'completed_at' => now()];

        try {
            $this->withdrawals->transition($withdrawal, CardWithdrawal::STATUS_COMPLETED, null, WithdrawalJournal::ACTOR_JOB, $extra);
        } catch (UniqueConstraintViolationException) {
            // The hash already completes another withdrawal: keep this one
            // without it rather than claim the same transfer twice.
            $withdrawal->refresh();
            $this->withdrawals->transition($withdrawal, CardWithdrawal::STATUS_COMPLETED, null, WithdrawalJournal::ACTOR_JOB, ['tx_hash' => null] + $extra);
        }
    }

    /**
     * "Close the card" after the money left it: the existing guarded cancel,
     * which refuses while the card still holds money (pending payments).
     */
    private function closeCardIfAsked(CardWithdrawal $withdrawal): void
    {
        $wanted = $withdrawal->status === CardWithdrawal::STATUS_COMPLETED && $withdrawal->close_card && ! $withdrawal->card_closed;
        if (! $wanted || $withdrawal->card_close_attempted_at !== null || $this->olderThan($withdrawal->completed_at, self::CLOSE_RETRY_MINUTES)) {
            return;
        }

        $card = $withdrawal->card()->first();
        if (! $card instanceof Card) {
            return;
        }

        try {
            $card = $this->cards->cancel($card);
        } catch (CardProviderException $e) {
            if ($e->reason() === 'holds_money') {
                $withdrawal->forceFill(['card_close_attempted_at' => now()])->save();
                WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['note' => 'card not closed: it still holds money']);
                $this->withdrawals->announce($withdrawal);
            }

            return; // provider unavailable or busy: the next run tries again
        }

        $closed = $card->status === Card::STATUS_CANCELLED;
        $withdrawal->forceFill(['card_close_attempted_at' => now(), 'card_closed' => $closed])->save();
        WithdrawalJournal::record($withdrawal, WithdrawalJournal::ACTOR_JOB, 'call_finished', ['note' => $closed ? 'card closed' : 'card not closed']);
        $this->withdrawals->announce($withdrawal);
    }

    // ─── Reads and helpers ──────────────────────────────────────────────────

    /**
     * Within the step timeout the reads are tried again on the next run;
     * after it the withdrawal stops for an operator.
     */
    private function waitOrStop(CardWithdrawal $withdrawal, string $waiting, string $unclear): void
    {
        if ($this->olderThan($withdrawal->step_started_at, self::STEP_TIMEOUT_MINUTES)) {
            $this->withdrawals->stop($withdrawal, $unclear);

            return;
        }

        $this->setReason($withdrawal, $waiting);
    }

    private function setReason(CardWithdrawal $withdrawal, string $reason): void
    {
        if ($withdrawal->last_error !== $reason) {
            $withdrawal->forceFill(['last_error' => $reason])->save();
        }
    }

    /**
     * The first of the user's Lepo transactions of these types since $since
     * that $matches accepts; null when none or unreadable.
     *
     * @param  list<string>  $types
     * @param  callable(array<mixed>): bool  $matches
     * @return array<mixed>|null
     */
    private function findTransaction(CardWithdrawal $withdrawal, array $types, Carbon $since, callable $matches): ?array
    {
        try {
            $rows = $this->client->getTransactions($this->withdrawals->lepoUserId($withdrawal->card()->firstOrFail()), $types, $since->toIso8601String())['data'] ?? [];
        } catch (LepoApiException) {
            return null;
        }

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && in_array(strtolower((string) ($row['type'] ?? '')), $types, true) && $matches($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return numeric-string|null
     */
    private function cardAvailable(Card $card): ?string
    {
        try {
            $live = $this->client->getCard((string) $card->issuer_card_token, $this->withdrawals->lepoUserId($card));
        } catch (LepoApiException) {
            return null;
        }

        return is_array($live['balance'] ?? null) ? Amounts::parse($live['balance']['available'] ?? null, 2) : null;
    }

    /**
     * The available USDC on the user's crypto balance at Lepo (6 decimals),
     * or null when unreadable.
     *
     * @return numeric-string|null
     */
    private function usdcAvailable(string $userId): ?string
    {
        try {
            $assets = $this->client->getAssets($userId)['assets'] ?? null;
        } catch (LepoApiException) {
            return null;
        }

        if (! is_array($assets)) {
            return null;
        }

        $total = null;
        foreach ($assets as $asset) {
            if (! is_array($asset) || strtoupper((string) ($asset['currency'] ?? '')) !== 'USDC' || strtolower((string) ($asset['assetType'] ?? 'crypto')) === 'fiat') {
                continue;
            }
            $value = Amounts::parse($asset['availableBalance'] ?? null, 6);
            if ($value === null) {
                return null;
            }
            $total = bcadd($total ?? '0', $value, 6);
        }

        return $total;
    }

    private function maskedEmail(string $userId): ?string
    {
        try {
            $email = $this->client->getUser($userId)['email'] ?? null;
        } catch (LepoApiException) {
            return null;
        }

        if (! is_string($email) || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1) . '***@' . $domain;
    }

    /**
     * A transaction hash when the record carries one (the current spec
     * names none on transfer records, so this is read defensively).
     *
     * @param  array<mixed>  $record
     */
    private function hash(array $record): ?string
    {
        foreach (['transactionHash', 'txHash', 'hash'] as $field) {
            $value = $record[$field] ?? null;
            if (is_string($value) && preg_match('/^0x[0-9a-fA-F]{64}$/', $value) === 1) {
                return strtolower($value);
            }
        }

        return null;
    }

    private function olderThan(?Carbon $time, int $minutes): bool
    {
        return $time !== null && $time->lt(now()->subMinutes($minutes));
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? mb_substr((string) $value, 0, 255) : null;
    }

    private function time(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
