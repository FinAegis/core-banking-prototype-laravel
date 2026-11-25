<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\CardIssuance\Events\Broadcast\CardWithdrawalUpdated;
use App\Domain\CardIssuance\Jobs\AdvanceLepoWithdrawals;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Providers\CardProviderSelector;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\Amounts;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\WithdrawalJournal;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\WithdrawalRefused;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Send to wallet": money from a Lepo card back to the user's own wallet.
 * This class answers the app (estimate, start, the emailed code, resend);
 * the money steps after the start run in LepoWithdrawalPipeline.
 *
 * Our rules:
 * - The destination is the user's own registered smart-account address on
 *   the chosen network, picked by the server and copied onto the withdrawal.
 *   A client never names an address.
 * - A user has one money flow at a time: no withdrawal while a top-up waits,
 *   moves or is unclear, and no top-up while a withdrawal is active.
 * - Amounts leaving the card are US-dollar strings with 2 decimals; USDC is
 *   kept with 6. What the user receives is rounded down, fees are rounded up.
 * - The emailed code passes through confirm() only. It is never stored,
 *   logged, queued or put into an event.
 */
final class LepoWithdrawals
{
    /** The smallest dollar amount the provider exchanges into USDC. */
    public const EXCHANGE_MINIMUM = '0.50';

    public const CODE_LENGTH = 8;

    public const RESEND_WAIT_SECONDS = 60;

    public const MAX_RESENDS = 5;

    public const CONFIRM_LIMIT = 5;

    public const CONFIRM_WINDOW_SECONDS = 600;

    private const PUSH = [
        CardWithdrawal::STATUS_AWAITING_CODE   => 'Check your email for a code to finish sending money to your wallet.',
        CardWithdrawal::STATUS_COMPLETED       => 'The money from your card is in your wallet.',
        CardWithdrawal::STATUS_NEEDS_ATTENTION => 'Your transfer from the card is taking longer than usual. We are checking it.',
    ];

    /** Short user-safe reasons; anything else that needs an operator reads as "checking". */
    private const FAILURE_REASONS = [
        'CARD_FROZEN_BY_SUPPORT' => 'card_frozen_by_support',
        'CARD_FROZEN'            => 'card_frozen',
        'CARD_CLOSED'            => 'card_closed',
        'CARD_NOT_READY'         => 'card_not_ready',
        'BALANCE_CHANGED'        => 'balance_changed',
        'BALANCE_UNREADABLE'     => 'balance_changed',
        'UNLOAD_REFUSED'         => 'refused',
        'CODE_LOCKED'            => 'code_locked',
        'CODE_NOT_ENTERED'       => 'code_not_entered',
    ];

    public function __construct(
        private readonly LepoClient $client,
        private readonly PushNotificationService $push,
    ) {
    }

    public static function enabled(): bool
    {
        return (bool) config('cardissuance.providers.lepo_withdrawals_enabled', false);
    }

    /**
     * The switch is on and, while the Lepo pilot is on, the user is a pilot user.
     */
    public static function availableFor(User $user): bool
    {
        if (! self::enabled()) {
            return false;
        }

        if (! (bool) config('cardissuance.providers.lepo_pilot_only', true)) {
            return true;
        }

        $pilot = CardProviderSelector::listFromConfig('cardissuance.providers.lepo_pilot_users');

        return in_array(strtolower((string) $user->uuid), $pilot, true) || in_array(strtolower((string) $user->email), $pilot, true);
    }

    /**
     * @return numeric-string
     */
    public static function minimum(): string
    {
        return self::configAmount('lepo_withdrawal_min', '5.00');
    }

    /**
     * @return numeric-string
     */
    public static function maximum(): string
    {
        return self::configAmount('lepo_withdrawal_max', '1000.00');
    }

    /**
     * @return numeric-string
     */
    public static function dailyMaximum(): string
    {
        return self::configAmount('lepo_withdrawal_daily_max', '2000.00');
    }

    public static function dailyCount(): int
    {
        return max(1, (int) config('cardissuance.providers.lepo_withdrawal_daily_count', 3));
    }

    /**
     * The provider's chain code for one of our networks, or null when the
     * network is not offered or has no code configured.
     */
    public static function chainCode(string $network): ?string
    {
        if (! in_array($network, CardProviderSelector::listFromConfig('cardissuance.providers.lepo_withdrawal_networks'), true)) {
            return null;
        }

        foreach (explode(',', (string) config('cardissuance.providers.lepo_withdrawal_chain_codes', '')) as $pair) {
            [$ours, $theirs] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, '');
            if (strtolower($ours) === $network && $theirs !== '') {
                return $theirs;
            }
        }

        return null;
    }

    public static function moneyLockKey(int $userId): string
    {
        return LepoTopUpPipeline::moneyLockKey($userId);
    }

    /**
     * What reaches the wallet for this amount (or the whole card balance).
     *
     * @return array<string, mixed>
     */
    public function estimate(User $user, Card $card, ?string $amount, bool $all, string $network): array
    {
        $this->assertAvailable($user);
        $this->assertCardCanSend($card);
        $this->assertNetwork($network);
        $destination = $this->destination($user, $network);

        $available = $this->liveAvailable($card);
        $amount = $all ? Amounts::down($available, 2) : $this->amount((string) $amount);
        $estimate = $this->compute($card, $amount, $all, $network, $destination->address);
        $this->assertRange($amount, $estimate);

        [, $usedCents] = $this->dailyUsage((int) $card->user_id);

        return $this->publicEstimate($estimate, $available, $usedCents);
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $meta
     */
    public function start(User $user, Card $card, ?string $amount, bool $all, string $network, bool $closeCard, array $meta = []): CardWithdrawal
    {
        // Checks in the documented order; the first one that fails decides.
        $this->assertAvailable($user);
        $this->assertCardCanSend($card);
        $this->assertNetwork($network);
        $destination = $this->destination($user, $network);
        $this->assertNoOtherMoneyFlow((int) $card->user_id);

        // Reads before the start lock: they are network calls.
        $available = $this->liveAvailable($card);
        $amount = $all ? Amounts::down($available, 2) : $this->amount((string) $amount);
        $estimate = $this->compute($card, $amount, $all, $network, $destination->address);
        $this->assertRange($amount, $estimate);
        $this->assertDailyLimits((int) $card->user_id, $amount);
        if (bccomp($amount, $available, 2) === 1) {
            throw WithdrawalRefused::because('ERR_CARDS_040', ['available_cents' => Amounts::cents($available)]);
        }

        // One start per user at a time. The top-up start lock is taken too,
        // so a top-up and a withdrawal started together cannot both pass.
        try {
            $withdrawal = Cache::lock("cards:lepo:withdrawal-start:{$card->user_id}", 10)->block(5, fn (): CardWithdrawal => Cache::lock("cards:lepo:topup-start:{$card->user_id}", 10)->block(5, function () use ($card, $amount, $all, $network, $closeCard, $destination, $estimate, $available, $meta): CardWithdrawal {
                $this->assertNoOtherMoneyFlow((int) $card->user_id);
                $this->assertDailyLimits((int) $card->user_id, $amount);

                $withdrawal = new CardWithdrawal();
                $withdrawal->forceFill([
                    'user_id'                  => $card->user_id,
                    'card_id'                  => $card->id,
                    'provider'                 => 'lepo',
                    'network'                  => $network,
                    'asset'                    => 'USDC',
                    'destination_address'      => $destination->address,
                    'destination_address_uuid' => $destination->uuid,
                    'amount'                   => $amount,
                    'withdraw_all'             => $all,
                    'close_card'               => $closeCard,
                    'estimate'                 => $this->publicEstimate($estimate, $available, 0),
                    'status'                   => CardWithdrawal::STATUS_UNLOADING,
                ])->save();

                $this->countToday((int) $card->user_id, $amount);
                WithdrawalJournal::record($withdrawal, WithdrawalJournal::user((int) $card->user_id), 'started', [
                    'to_status'  => CardWithdrawal::STATUS_UNLOADING,
                    'note'       => ($all ? 'all' : 'amount') . ($closeCard ? ', close card' : ''),
                    'ip_address' => $meta['ip'] ?? null,
                    'user_agent' => $meta['user_agent'] ?? null,
                ]);

                return $withdrawal;
            }));
        } catch (LockTimeoutException) {
            throw WithdrawalRefused::because('ERR_CARDS_039', ['kind' => 'withdrawal']);
        }

        $this->announce($withdrawal);
        AdvanceLepoWithdrawals::dispatch($this->lepoUserId($card));

        return $withdrawal;
    }

    /**
     * Passes the code the user typed to the provider. Returns the withdrawal
     * and whether the outcome is still being checked (the call timed out or
     * answered unclearly: never repeated, the job reconciles it).
     *
     * @param  array{ip?: string|null, user_agent?: string|null}  $meta
     * @return array{0: CardWithdrawal, 1: bool}
     */
    public function confirm(CardWithdrawal $withdrawal, string $code, array $meta = []): array
    {
        if ($withdrawal->status !== CardWithdrawal::STATUS_AWAITING_CODE) {
            throw WithdrawalRefused::because('ERR_CARDS_046');
        }

        $code = (string) preg_replace('/\s+/', '', $code);
        if (mb_strlen($code) !== self::CODE_LENGTH) {
            throw WithdrawalRefused::because('ERR_CARDS_041', ['code_attempts_left' => null]);
        }

        $lock = Cache::lock(self::moneyLockKey((int) $withdrawal->user_id), LepoTopUpPipeline::LOCK_SECONDS);
        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            throw WithdrawalRefused::providerUnavailable();
        }

        try {
            $withdrawal->refresh();
            if ($withdrawal->status !== CardWithdrawal::STATUS_AWAITING_CODE) {
                throw WithdrawalRefused::because('ERR_CARDS_046');
            }

            $token = (string) $withdrawal->provider_verification_token;
            $userId = $this->lepoUserId($withdrawal->card()->firstOrFail());
            $actor = WithdrawalJournal::user((int) $withdrawal->user_id);

            // A new key for each code typed: each attempt is its own call.
            $withdrawal->forceFill(['confirm_key' => (string) Str::uuid(), 'confirm_requested_at' => now(), 'step_started_at' => now()])->save();
            WithdrawalJournal::record($withdrawal, $actor, 'call_started', ['note' => 'confirm', 'ip_address' => $meta['ip'] ?? null, 'user_agent' => $meta['user_agent'] ?? null]);

            try {
                $answer = $this->client->confirmCryptoWithdrawal($userId, $token, $code, (string) $withdrawal->confirm_key);
            } catch (LepoApiException $e) {
                WithdrawalJournal::record($withdrawal, $actor, 'call_finished', ['partner_http_status' => $e->httpStatus(), 'note' => 'confirm']);

                return $this->confirmFailed($withdrawal, $e);
            } catch (Throwable $e) {
                // Anything else after the call started: the code may have been
                // accepted, so it is checked, never sent again.
                WithdrawalJournal::record($withdrawal, $actor, 'call_finished', ['note' => 'confirm ' . $e::class]);

                return [$this->transition($withdrawal, CardWithdrawal::STATUS_SENDING, 'CONFIRM_CHECKING', $actor), true];
            }

            WithdrawalJournal::record($withdrawal, $actor, 'call_finished', ['partner_http_status' => 200, 'note' => 'confirm']);
            $withdrawal->forceFill([
                'provider_withdrawal_id' => $this->text($answer['transactionId'] ?? null) ?? $withdrawal->provider_withdrawal_id,
                'provider_transfer_id'   => $this->text($answer['interlaceTransferId'] ?? null) ?? $withdrawal->provider_transfer_id,
                'provider_status'        => $this->text($answer['status'] ?? null),
                'provider_actual_amount' => Amounts::parse($answer['actualAmount'] ?? null, 6) ?? $withdrawal->provider_actual_amount,
                'confirmed_at'           => now(),
            ])->save();

            return [$this->transition($withdrawal, CardWithdrawal::STATUS_SENDING, null, $actor), false];
        } finally {
            $lock->release();
        }
    }

    /**
     * Asks the provider to e-mail a new code.
     *
     * @param  array{ip?: string|null, user_agent?: string|null}  $meta
     */
    public function resend(CardWithdrawal $withdrawal, array $meta = []): CardWithdrawal
    {
        if ($withdrawal->status !== CardWithdrawal::STATUS_AWAITING_CODE) {
            throw WithdrawalRefused::because('ERR_CARDS_046');
        }

        if ($withdrawal->code_resends >= self::MAX_RESENDS) {
            throw WithdrawalRefused::because('ERR_CARDS_047', ['retry_after_seconds' => null]);
        }

        $last = $withdrawal->last_resend_at ?? $withdrawal->code_sent_at;
        if ($last !== null) {
            $wait = (int) ceil(self::RESEND_WAIT_SECONDS - $last->diffInSeconds(now(), true));
            if ($wait > 0) {
                throw WithdrawalRefused::because('ERR_CARDS_047', ['retry_after_seconds' => $wait]);
            }
        }

        $actor = WithdrawalJournal::user((int) $withdrawal->user_id);
        try {
            $answer = $this->client->resendCryptoWithdrawalCode($this->lepoUserId($withdrawal->card()->firstOrFail()), (string) $withdrawal->provider_verification_token);
        } catch (LepoApiException $e) {
            WithdrawalJournal::record($withdrawal, $actor, 'call_finished', ['partner_http_status' => $e->httpStatus(), 'note' => 'resend refused']);
            if ($e->httpStatus() === 429) {
                throw WithdrawalRefused::because('ERR_CARDS_047', ['retry_after_seconds' => self::RESEND_WAIT_SECONDS]);
            }

            // The request itself is gone. Whether the USDC is still reserved
            // for it is not known, so no second request is made here.
            if ($e->isDefiniteRefusal() && ($e->httpStatus() === 404 || str_contains(strtoupper((string) $e->lepoCode()), 'EXPIR'))) {
                return $this->stop($withdrawal, 'REQUEST_EXPIRED', $actor);
            }

            throw WithdrawalRefused::providerUnavailable();
        }

        $withdrawal->forceFill([
            'code_expires_at' => $this->time($answer['expiresAt'] ?? null) ?? $withdrawal->code_expires_at,
            'code_resends'    => $withdrawal->code_resends + 1,
            'last_resend_at'  => now(),
        ])->save();
        WithdrawalJournal::record($withdrawal, $actor, 'code_resent', ['ip_address' => $meta['ip'] ?? null, 'user_agent' => $meta['user_agent'] ?? null]);
        $this->announce($withdrawal);

        return $withdrawal;
    }

    /**
     * Moves the withdrawal to a new status, writes the audit line and tells
     * the app (and the phone for the statuses that need the user).
     *
     * @param  array<model-property<CardWithdrawal>, mixed>  $extra
     */
    public function transition(CardWithdrawal $withdrawal, string $status, ?string $reason, string $actor, array $extra = [], ?string $note = null): CardWithdrawal
    {
        $from = $withdrawal->status;
        $withdrawal->forceFill(['status' => $status, 'last_error' => $reason, 'step_started_at' => null] + $extra)->save();
        WithdrawalJournal::record($withdrawal, $actor, 'status_changed', ['from_status' => $from, 'to_status' => $status, 'note' => $note ?? $reason]);

        if ($status === CardWithdrawal::STATUS_NEEDS_ATTENTION) {
            Log::error('Card withdrawal needs attention', ['withdrawal_id' => $withdrawal->id, 'reason' => $reason]);
        }

        $this->announce($withdrawal, $from !== $status);

        return $withdrawal;
    }

    public function stop(CardWithdrawal $withdrawal, string $reason, string $actor = WithdrawalJournal::ACTOR_JOB): CardWithdrawal
    {
        return $this->transition($withdrawal, CardWithdrawal::STATUS_NEEDS_ATTENTION, $reason, $actor);
    }

    /**
     * Nothing moved: the withdrawal ends before the unload. It no longer
     * counts against today's limits.
     */
    public function cancel(CardWithdrawal $withdrawal, string $reason, string $actor = WithdrawalJournal::ACTOR_JOB): CardWithdrawal
    {
        $this->uncountToday($withdrawal);

        return $this->transition($withdrawal, CardWithdrawal::STATUS_CANCELLED, $reason, $actor);
    }

    public function announce(CardWithdrawal $withdrawal, bool $statusChanged = false): void
    {
        CardWithdrawalUpdated::dispatch(
            (int) $withdrawal->user_id,
            $withdrawal->id,
            $withdrawal->card_id,
            $withdrawal->status,
            $withdrawal->received_amount === null ? null : Amounts::down(Amounts::of($withdrawal->received_amount, 6), 2),
            $withdrawal->status === CardWithdrawal::STATUS_AWAITING_CODE ? $withdrawal->code_expires_at?->toIso8601String() : null,
        );

        $message = self::PUSH[$withdrawal->status] ?? null;
        if (! $statusChanged || $message === null) {
            return;
        }

        $user = $withdrawal->user()->first();
        if (! $user instanceof User) {
            return;
        }

        try {
            $this->push->sendToUser($user, 'card.withdrawal.updated', 'Send to wallet', $message, [
                'type'          => 'card.withdrawal.updated',
                'withdrawal_id' => $withdrawal->id,
                'card_id'       => $withdrawal->card_id,
            ]);
        } catch (Throwable) {
            // A push that cannot be sent never stops the money flow.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(CardWithdrawal $withdrawal): array
    {
        $estimate = is_array($withdrawal->estimate) ? $withdrawal->estimate : [];

        return [
            'id'                       => $withdrawal->id,
            'card_id'                  => $withdrawal->card_id,
            'status'                   => $withdrawal->status,
            'amount'                   => Amounts::of($withdrawal->amount, 2),
            'withdraw_all'             => $withdrawal->withdraw_all,
            'close_card'               => $withdrawal->close_card,
            'network'                  => $withdrawal->network,
            'asset'                    => $withdrawal->asset,
            'destination_address'      => $withdrawal->destination_address,
            'unloaded_cents'           => $withdrawal->unloaded_cents,
            'unload_fee_cents'         => $withdrawal->unload_fee_cents,
            'conversion_fee_cents'     => $withdrawal->conversion_fee_cents,
            'withdrawal_fee'           => $withdrawal->withdrawal_fee === null ? null : Amounts::up(Amounts::of($withdrawal->withdrawal_fee, 6), 2),
            'received_amount'          => $withdrawal->received_amount === null ? null : Amounts::down(Amounts::of($withdrawal->received_amount, 6), 2),
            'estimated_receive_amount' => is_string($estimate['receive_amount'] ?? null) ? $estimate['receive_amount'] : null,
            'tx_hash'                  => $withdrawal->tx_hash,
            'code_sent_to'             => $withdrawal->status === CardWithdrawal::STATUS_AWAITING_CODE ? $withdrawal->code_sent_to : null,
            'code_expires_at'          => $withdrawal->status === CardWithdrawal::STATUS_AWAITING_CODE ? $withdrawal->code_expires_at?->toIso8601String() : null,
            'code_attempts_left'       => $withdrawal->status === CardWithdrawal::STATUS_AWAITING_CODE ? $withdrawal->code_attempts_left : null,
            'card_closed'              => $withdrawal->card_closed,
            'failure_reason'           => self::failureReason($withdrawal),
            'created_at'               => $withdrawal->created_at?->toIso8601String(),
            'completed_at'             => $withdrawal->completed_at?->toIso8601String(),
        ];
    }

    public static function failureReason(CardWithdrawal $withdrawal): ?string
    {
        $reason = $withdrawal->last_error;
        if ($withdrawal->status === CardWithdrawal::STATUS_SENDING) {
            return $reason === 'CONFIRM_CHECKING' ? 'checking' : null;
        }

        if (! in_array($withdrawal->status, [CardWithdrawal::STATUS_NEEDS_ATTENTION, CardWithdrawal::STATUS_CANCELLED, CardWithdrawal::STATUS_FAILED], true)) {
            return null;
        }

        return self::FAILURE_REASONS[(string) $reason] ?? ($withdrawal->status === CardWithdrawal::STATUS_NEEDS_ATTENTION ? 'checking' : null);
    }

    public function lepoUserId(Card $card): string
    {
        $holder = $card->cardholder()->first();
        $id = $holder instanceof Cardholder ? (string) $holder->issuer_cardholder_id : '';
        if ($id === '') {
            throw WithdrawalRefused::providerUnavailable();
        }

        return $id;
    }

    /**
     * Why the card cannot send money now, or null when it can.
     */
    public static function cardBlocker(Card $card): ?string
    {
        if (in_array($card->status, [...Card::CLOSED_STATUSES], true)) {
            return 'card_closed';
        }

        if (! $card->isOpenAtProvider()) {
            return 'card_not_ready';
        }

        if ($card->status === Card::STATUS_BLOCKED || CardStaffFreeze::isHeld((string) $card->issuer_card_token)) {
            return 'frozen_by_support';
        }

        // Our decision until the provider says whether a frozen card can be
        // unloaded: the owner unfreezes the card first.
        if ($card->status === Card::STATUS_FROZEN) {
            return 'frozen';
        }

        return $card->status === Card::STATUS_ACTIVE ? null : 'card_not_ready';
    }

    /**
     * The smart-account address that receives the money: the user's most
     * recently registered Privy smart account on the network.
     */
    public function destination(User $user, string $network): BlockchainAddress
    {
        $row = BlockchainAddress::query()
            ->where('user_uuid', $user->uuid)
            ->where('chain', $network)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (BlockchainAddress $address): bool => ($address->metadata['provider'] ?? null) === 'privy'
                && ($address->metadata['wallet_kind'] ?? null) === 'privy_smart_account'
                && preg_match('/^0x[0-9a-fA-F]{40}$/', $address->address) === 1);

        if (! $row instanceof BlockchainAddress) {
            throw WithdrawalRefused::because('ERR_CARDS_037');
        }

        // A stolen session must not register a fresh address and empty the
        // card into it at once.
        $availableAt = ($row->created_at ?? now())->copy()->addHours(max(0, (int) config('cardissuance.providers.lepo_withdrawal_address_hours', 24)));
        if ($availableAt->isFuture()) {
            throw WithdrawalRefused::because('ERR_CARDS_045', ['available_at' => $availableAt->toIso8601String()]);
        }

        return $row;
    }

    /**
     * Today's (UTC) withdrawals of the user: count and dollar cents. The
     * database is the floor, so a cache flush cannot reset the limits.
     *
     * @return array{0: int, 1: int}
     */
    public function dailyUsage(int $userId): array
    {
        $rows = CardWithdrawal::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->utc()->startOfDay())
            ->where('status', '!=', CardWithdrawal::STATUS_CANCELLED)
            ->pluck('amount');

        $cents = 0;
        foreach ($rows as $amount) {
            $cents += Amounts::cents(Amounts::parse((string) $amount, 2) ?? '0');
        }

        $cachedCount = (int) Cache::get(self::dailyKey($userId, 'count'), 0);
        $cachedCents = (int) Cache::get(self::dailyKey($userId, 'cents'), 0);

        return [max($rows->count(), $cachedCount), max($cents, $cachedCents)];
    }

    /**
     * @param  array<string, mixed>  $estimate
     * @return array<string, mixed>
     */
    private function publicEstimate(array $estimate, string $available, int $usedCents): array
    {
        $remaining = bcsub(self::dailyMaximum(), Amounts::fromCents($usedCents), 2);

        return [
            'amount'               => $estimate['amount'],
            'withdraw_all'         => $estimate['withdraw_all'],
            'network'              => $estimate['network'],
            'asset'                => 'USDC',
            'destination_address'  => $estimate['destination_address'],
            'available_cents'      => Amounts::cents(Amounts::parse($available, 2) ?? '0'),
            'unload_fee_cents'     => 0,
            'conversion_fee_cents' => $estimate['conversion_fee_cents'],
            'withdrawal_fee'       => $estimate['withdrawal_fee'],
            'receive_amount'       => $estimate['receive_amount'],
            'min_amount'           => $estimate['min_amount'],
            'max_amount'           => self::maximum(),
            'daily_remaining'      => bccomp($remaining, '0', 2) === 1 ? $remaining : '0.00',
            'quote_expires_at'     => $estimate['quote_expires_at'],
        ];
    }

    /**
     * The estimate for $amount dollars leaving the card. Every rounding goes
     * against the user, so the wallet never receives less than shown.
     *
     * @param  numeric-string  $amount
     * @return array{amount: numeric-string, withdraw_all: bool, network: string, destination_address: string, conversion_fee_cents: int, withdrawal_fee: numeric-string, receive_amount: numeric-string, min_amount: numeric-string, quote_expires_at: string}
     */
    private function compute(Card $card, string $amount, bool $all, string $network, string $address): array
    {
        $userId = $this->lepoUserId($card);

        try {
            $quote = $this->client->estimateCardTopup(['userId' => $userId, 'amount' => $amount]);
        } catch (LepoApiException) {
            throw WithdrawalRefused::providerUnavailable();
        }

        // The dollars-to-USDC quote. The current spec names it usdToUsdc;
        // the earlier answers called it usdc.
        $leg = is_array($quote['usdToUsdc'] ?? null) ? $quote['usdToUsdc'] : (is_array($quote['usdc'] ?? null) ? $quote['usdc'] : []);
        $rate = Amounts::parse($leg['rate'] ?? null, 8);
        if ($rate === null || bccomp($rate, '0', 8) !== 1) {
            throw WithdrawalRefused::providerUnavailable();
        }
        // A quote written the other way round (USDC priced in dollars) is turned
        // into USDC per dollar, rounded down.
        if (strtoupper((string) ($leg['baseCurrency'] ?? 'USD')) === 'USDC') {
            $rate = bcdiv('1', $rate, 8);
        }

        $fee = Amounts::parse($leg['fee'] ?? null, 8) ?? '0';
        if (in_array(strtoupper((string) ($leg['feeCurrency'] ?? 'USD')), ['USDC', 'USDT'], true)) {
            $usdc = bcsub(Amounts::down(bcmul($amount, $rate, 12), 6), Amounts::up($fee, 6), 6);
            $conversionFeeCents = Amounts::centsUp(bcdiv($fee, $rate, 12));
        } else {
            $conversionFeeCents = Amounts::centsUp($fee);
            $usdc = Amounts::down(bcmul(bcsub($amount, Amounts::fromCents($conversionFeeCents), 2), $rate, 12), 6);
        }

        $withdrawalFee = $this->withdrawalFee($userId, $network, $address, bccomp($usdc, '0', 6) === 1 ? $usdc : '0');
        // Until a real withdrawal shows otherwise, the fee comes out of the
        // amount sent. If it is charged on top, the amount sent is lowered by
        // the fee instead and the user receives the same.
        $receive = Amounts::down(bcsub($usdc, $withdrawalFee, 6), 2);

        $minimum = Amounts::max(Amounts::max(self::minimum(), self::EXCHANGE_MINIMUM, 2), bcadd(Amounts::up($withdrawalFee, 2), '0.01', 2), 2);
        if ($all) {
            // The rest of a card may go below the minimum when it covers the
            // exchange minimum and every fee, with at least a cent left.
            $rest = bcadd(bcadd(bcadd(self::EXCHANGE_MINIMUM, Amounts::fromCents($conversionFeeCents), 2), Amounts::up($withdrawalFee, 2), 2), '0.01', 2);
            $minimum = Amounts::min($minimum, $rest, 2);
        }
        if (bccomp($receive, '0', 2) !== 1) {
            throw WithdrawalRefused::because('ERR_CARDS_035', ['min_amount' => $minimum, 'max_amount' => self::maximum()]);
        }

        $ttl = is_scalar($leg['ttlMs'] ?? null) && ctype_digit((string) $leg['ttlMs']) ? (int) $leg['ttlMs'] : 60000;

        return [
            'amount'               => Amounts::down($amount, 2),
            'withdraw_all'         => $all,
            'network'              => $network,
            'destination_address'  => $address,
            'conversion_fee_cents' => $conversionFeeCents,
            'withdrawal_fee'       => Amounts::up($withdrawalFee, 2),
            'receive_amount'       => $receive,
            'min_amount'           => $minimum,
            'quote_expires_at'     => now()->addMilliseconds($ttl)->toIso8601String(),
        ];
    }

    /**
     * The network fee for sending $usdc to the address, rounded up to 6
     * decimals. Unreadable is "provider unavailable", never zero.
     *
     * @param  numeric-string  $usdc
     * @return numeric-string
     */
    public function withdrawalFee(string $userId, string $network, string $address, string $usdc): string
    {
        $chain = self::chainCode($network) ?? throw WithdrawalRefused::because('ERR_CARDS_036');

        try {
            $answer = $this->client->getWithdrawalFeeAndQuota($userId, $chain, $address, 'USDC', bcadd($usdc, '0', 6));
        } catch (LepoApiException) {
            throw WithdrawalRefused::providerUnavailable();
        }

        $fees = is_array($answer['data'] ?? null) ? ($answer['data']['fees'] ?? null) : null;
        if (! is_array($fees)) {
            throw WithdrawalRefused::providerUnavailable();
        }

        $total = '0';
        foreach ($fees as $fee) {
            $value = is_array($fee) ? Amounts::parse($fee['amount'] ?? null, 12) : null;
            if ($value === null) {
                throw WithdrawalRefused::providerUnavailable();
            }
            $total = bcadd($total, $value, 12);
        }

        return Amounts::up($total, 6);
    }

    private function assertAvailable(User $user): void
    {
        if (! self::availableFor($user)) {
            throw WithdrawalRefused::because('ERR_CARDS_034');
        }
    }

    private function assertCardCanSend(Card $card): void
    {
        $blocker = self::cardBlocker($card);
        if ($blocker !== null) {
            throw WithdrawalRefused::because('ERR_CARDS_038', ['reason' => $blocker]);
        }
    }

    private function assertNetwork(string $network): void
    {
        if (self::chainCode($network) === null) {
            throw WithdrawalRefused::because('ERR_CARDS_036');
        }
    }

    private function assertNoOtherMoneyFlow(int $userId): void
    {
        $topup = CardTopUp::query()->where('user_id', $userId)
            ->where(fn ($query) => $query->whereIn('status', CardTopUp::OPEN)
                ->orWhere(fn ($unclear) => $unclear->where('status', CardTopUp::STATUS_NEEDS_ATTENTION)->whereIn('last_error', LepoTopUpPipeline::UNCLEAR)))
            ->exists();
        if ($topup) {
            throw WithdrawalRefused::because('ERR_CARDS_039', ['kind' => 'topup']);
        }

        if (CardWithdrawal::query()->where('user_id', $userId)->whereIn('status', CardWithdrawal::ACTIVE)->exists()) {
            throw WithdrawalRefused::because('ERR_CARDS_039', ['kind' => 'withdrawal']);
        }
    }

    /**
     * @param  numeric-string  $amount
     * @param  array{min_amount: numeric-string}  $estimate
     */
    private function assertRange(string $amount, array $estimate): void
    {
        if (bccomp($amount, $estimate['min_amount'], 2) === -1 || bccomp($amount, self::maximum(), 2) === 1) {
            throw WithdrawalRefused::because('ERR_CARDS_035', ['min_amount' => $estimate['min_amount'], 'max_amount' => self::maximum()]);
        }
    }

    /**
     * @param  numeric-string  $amount
     */
    private function assertDailyLimits(int $userId, string $amount): void
    {
        [$count, $usedCents] = $this->dailyUsage($userId);
        $remaining = bcsub(self::dailyMaximum(), Amounts::fromCents($usedCents), 2);
        $remaining = bccomp($remaining, '0', 2) === 1 ? $remaining : '0.00';
        $countLeft = max(0, self::dailyCount() - $count);

        if ($countLeft === 0 || bccomp($amount, $remaining, 2) === 1) {
            throw WithdrawalRefused::because('ERR_CARDS_044', [
                'daily_limit'           => self::dailyMaximum(),
                'daily_remaining'       => $countLeft === 0 ? '0.00' : $remaining,
                'daily_count_limit'     => self::dailyCount(),
                'daily_count_remaining' => $countLeft,
            ]);
        }
    }

    /**
     * @param  numeric-string  $amount
     */
    private function countToday(int $userId, string $amount): void
    {
        $ttl = now()->utc()->endOfDay()->addMinute();
        foreach (['count' => 1, 'cents' => Amounts::cents($amount)] as $what => $step) {
            Cache::add(self::dailyKey($userId, $what), 0, $ttl);
            Cache::increment(self::dailyKey($userId, $what), $step);
        }
    }

    private function uncountToday(CardWithdrawal $withdrawal): void
    {
        if ($withdrawal->created_at === null || $withdrawal->created_at->lt(now()->utc()->startOfDay())) {
            return;
        }

        $userId = (int) $withdrawal->user_id;
        if ((int) Cache::get(self::dailyKey($userId, 'count'), 0) > 0) {
            Cache::decrement(self::dailyKey($userId, 'count'));
            Cache::decrement(self::dailyKey($userId, 'cents'), Amounts::cents(Amounts::parse((string) $withdrawal->amount, 2) ?? '0'));
        }
    }

    private static function dailyKey(int $userId, string $what): string
    {
        return 'cards:lepo:withdrawals:' . now()->utc()->format('Ymd') . ":{$userId}:{$what}";
    }

    /**
     * The card's available balance now, from the provider, 2 decimals
     * rounded down. Unreadable is "provider unavailable".
     *
     * @return numeric-string
     */
    private function liveAvailable(Card $card): string
    {
        try {
            $live = $this->client->getCard((string) $card->issuer_card_token, $this->lepoUserId($card));
        } catch (LepoApiException) {
            throw WithdrawalRefused::providerUnavailable();
        }

        $available = is_array($live['balance'] ?? null) ? Amounts::parse($live['balance']['available'] ?? null, 2) : null;
        if ($available === null) {
            throw WithdrawalRefused::providerUnavailable();
        }

        return $available;
    }

    /**
     * @return numeric-string
     */
    private function amount(string $amount): string
    {
        $parsed = Amounts::parse($amount, 2);
        if ($parsed === null) {
            throw WithdrawalRefused::because('ERR_CARDS_035', ['min_amount' => self::minimum(), 'max_amount' => self::maximum()]);
        }

        return $parsed;
    }

    /**
     * @return array{0: CardWithdrawal, 1: bool}
     */
    private function confirmFailed(CardWithdrawal $withdrawal, LepoApiException $e): array
    {
        $actor = WithdrawalJournal::user((int) $withdrawal->user_id);
        $details = $e->details();

        if ($e->isDefiniteRefusal() && in_array($e->httpStatus(), [400, 404], true)) {
            if (($details['isLocked'] ?? false) === true) {
                $this->stop($withdrawal, 'CODE_LOCKED', $actor);

                throw WithdrawalRefused::because('ERR_CARDS_043');
            }

            $withdrawal->forceFill(['step_started_at' => null])->save();
            if (($details['isExpired'] ?? false) === true) {
                WithdrawalJournal::record($withdrawal, $actor, 'code_wrong', ['partner_http_status' => $e->httpStatus(), 'note' => 'expired']);

                throw WithdrawalRefused::because('ERR_CARDS_042');
            }

            $left = $details['remainingAttempts'] ?? null;
            $withdrawal->forceFill(['code_attempts' => $withdrawal->code_attempts + 1, 'code_attempts_left' => $left])->save();
            WithdrawalJournal::record($withdrawal, $actor, 'code_wrong', ['partner_http_status' => $e->httpStatus()]);

            throw WithdrawalRefused::because('ERR_CARDS_041', ['code_attempts_left' => $left]);
        }

        // Timed out or answered unclearly: the code may have been accepted.
        // Never sent again; the job reads what happened.
        return [$this->transition($withdrawal, CardWithdrawal::STATUS_SENDING, 'CONFIRM_CHECKING', $actor), true];
    }

    /**
     * @return numeric-string
     */
    private static function configAmount(string $key, string $default): string
    {
        return Amounts::parse((string) config("cardissuance.providers.{$key}", $default), 2) ?? Amounts::parse($default, 2) ?? '0';
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
