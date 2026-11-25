<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawals;
use App\Domain\CardIssuance\Providers\Lepo\Withdrawals\WithdrawalRefused;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * "Send to wallet" for Lepo cards (`/api/v1/cards/issued/{card}/withdrawals`).
 * Money leaves the card, becomes USDC and goes to the user's own registered
 * wallet address on the chosen network. The app shows the estimate, starts
 * the withdrawal, follows `card.withdrawal.updated` (or polls), and passes
 * the 8-character code the user received by e-mail. The request never names
 * an address: the server picks the user's own wallet.
 */
final class CardWithdrawalController extends Controller
{
    private const AMOUNT = 'regex:/^\d{1,9}(\.\d{1,2})?$/';

    /** A client bug that sends an address must not look like it worked. */
    private const ADDRESS_FIELDS = ['destination_address', 'address', 'to'];

    private const WITHDRAWAL_SHAPE = 'Withdrawal {id, card_id, status (unloading|converting|awaiting_code|sending|completed|cancelled|failed|needs_attention), amount (USD leaving the card, 2 decimals), withdraw_all, close_card, network, asset (USDC), destination_address, unloaded_cents, unload_fee_cents, conversion_fee_cents, withdrawal_fee (USDC, 2 decimals, rounded up), received_amount (USDC, 2 decimals, rounded down), estimated_receive_amount, tx_hash, code_sent_to (masked e-mail, while awaiting_code), code_expires_at, code_attempts_left, card_closed, failure_reason (card_frozen_by_support|card_frozen|card_closed|card_not_ready|balance_changed|refused|code_locked|code_not_entered|withdrawals_disabled|checking), created_at, completed_at}';

    public function __construct(private readonly LepoWithdrawals $withdrawals)
    {
    }

    #[OA\Get(
        path: '/api/v1/cards/issued/{card}/withdrawals/estimate',
        operationId: 'v1CardsWithdrawalEstimate',
        tags: ['Cards'],
        summary: 'Estimate sending money from the card to the wallet',
        description: 'Query: amount (USD decimal string, at most 2 decimals) or all=1 (the whole available balance), and network (arbitrum or polygon). Returns {amount, withdraw_all, network, asset, destination_address, available_cents, unload_fee_cents, conversion_fee_cents, withdrawal_fee, receive_amount, min_amount, max_amount, daily_remaining, quote_expires_at}. receive_amount is never more than what reaches the wallet. destination_address is the user\'s own wallet address that will receive the money. Read min_amount from here; do not hard-code it.',
        security: [['sanctum' => []]],
    )]
    #[OA\Parameter(name: 'card', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'amount', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '50.00'))]
    #[OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1]))]
    #[OA\Parameter(name: 'network', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'arbitrum'))]
    #[OA\Response(response: 200, description: 'Estimate')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_037: no wallet address on the network; ERR_CARDS_038: the card cannot send money (reason); ERR_CARDS_045: the address was added recently (available_at)')]
    #[OA\Response(response: 422, description: 'ERR_CARDS_035: amount outside the limits (min_amount, max_amount); ERR_CARDS_036: network not supported')]
    #[OA\Response(response: 502, description: 'ERR_CARDS_016: the card balance, quote or fee could not be read')]
    #[OA\Response(response: 503, description: 'ERR_CARDS_034: not available yet')]
    public function estimate(Request $request, string $card): JsonResponse
    {
        $input = $request->validate([
            'amount'  => ['required_without:all', 'prohibits:all', 'string', self::AMOUNT],
            'all'     => ['sometimes', 'in:1,true'],
            'network' => ['required', 'string', 'max:32'],
        ]);

        return $this->withCard($request, $card, fn (User $user, Card $owned): JsonResponse => $this->attempt(
            fn (): JsonResponse => response()->json(['success' => true, 'data' => $this->withdrawals->estimate(
                $user,
                $owned,
                isset($input['amount']) ? (string) $input['amount'] : null,
                isset($input['all']),
                (string) $input['network'],
            )]),
        ));
    }

    #[OA\Get(path: '/api/v1/cards/issued/{card}/withdrawals', operationId: 'v1CardsWithdrawalIndex', tags: ['Cards'], summary: 'The card\'s withdrawals, newest first (page from 0, 20 per page)', security: [['sanctum' => []]])]
    #[OA\Parameter(name: 'card', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 0))]
    #[OA\Response(response: 200, description: 'Withdrawals {items, has_more, page}')]
    public function index(Request $request, string $card): JsonResponse
    {
        return $this->withCard($request, $card, function (User $user, Card $owned) use ($request): JsonResponse {
            $page = max(0, $request->integer('page', 0));
            $rows = CardWithdrawal::query()->where('card_id', $owned->id)->orderByDesc('created_at')->skip($page * 20)->take(21)->get();

            return response()->json(['success' => true, 'data' => [
                'items'    => $rows->take(20)->map(fn (CardWithdrawal $w): array => LepoWithdrawals::present($w))->values()->all(),
                'has_more' => $rows->count() > 20,
                'page'     => $page,
            ]]);
        }, requireEnabled: false);
    }

    #[OA\Post(
        path: '/api/v1/cards/issued/{card}/withdrawals',
        operationId: 'v1CardsWithdrawalStart',
        tags: ['Cards'],
        summary: 'Send money from the card to the wallet',
        description: 'Header Idempotency-Key (required). Body: {amount (USD decimal string) | withdraw_all: true, network, close_card (optional, only with withdraw_all)}. There is no address field: a body with destination_address, address or to is refused with ERR_CARDS_036. Answers 201 with the withdrawal in status unloading; follow card.withdrawal.updated or poll. When it reaches awaiting_code, the user received an 8-character code by e-mail; pass it to /confirm. One money transfer per user at a time.',
        security: [['sanctum' => []]],
    )]
    #[OA\Parameter(name: 'card', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'amount', type: 'string', example: '50.00'),
        new OA\Property(property: 'withdraw_all', type: 'boolean', example: false),
        new OA\Property(property: 'network', type: 'string', example: 'arbitrum'),
        new OA\Property(property: 'close_card', type: 'boolean', example: false),
    ]))]
    #[OA\Response(response: 201, description: self::WITHDRAWAL_SHAPE)]
    #[OA\Response(response: 409, description: 'ERR_CARDS_037: no wallet address on the network; ERR_CARDS_038: the card cannot send money (reason: frozen, frozen_by_support, card_closed, card_not_ready); ERR_CARDS_039: another money transfer is in progress (kind: topup, withdrawal); ERR_CARDS_045: the address was added recently (available_at)')]
    #[OA\Response(response: 422, description: 'ERR_CARDS_035: amount outside the limits (min_amount, max_amount); ERR_CARDS_036: network not supported or an address in the body; ERR_CARDS_040: more than the card holds (available_cents)')]
    #[OA\Response(response: 429, description: 'ERR_CARDS_044: today\'s limit reached (daily_limit, daily_remaining, daily_count_limit, daily_count_remaining)')]
    #[OA\Response(response: 502, description: 'ERR_CARDS_016: the card balance, quote or fee could not be read')]
    #[OA\Response(response: 503, description: 'ERR_CARDS_034: not available yet')]
    public function store(Request $request, string $card): JsonResponse
    {
        $user = $this->user($request);
        if (! LepoWithdrawals::availableFor($user)) {
            return ErrorResponse::make('ERR_CARDS_034');
        }

        foreach (self::ADDRESS_FIELDS as $field) {
            if ($request->exists($field)) {
                return ErrorResponse::make('ERR_CARDS_036');
            }
        }

        $input = $request->validate([
            'amount'       => ['sometimes', 'string', self::AMOUNT],
            'withdraw_all' => ['sometimes', 'boolean'],
            'network'      => ['required', 'string', 'max:32'],
            'close_card'   => ['sometimes', 'boolean'],
        ]);

        // Exactly one of amount and withdraw_all; close_card only with withdraw_all.
        $all = (bool) ($input['withdraw_all'] ?? false);
        if ($all === isset($input['amount'])) {
            throw ValidationException::withMessages(['amount' => 'Give either amount or withdraw_all.']);
        }
        if ((bool) ($input['close_card'] ?? false) && ! $all) {
            throw ValidationException::withMessages(['close_card' => 'close_card is only accepted with withdraw_all.']);
        }

        return $this->withCard($request, $card, fn (User $user, Card $owned): JsonResponse => $this->attempt(
            fn (): JsonResponse => response()->json(['success' => true, 'data' => LepoWithdrawals::present($this->withdrawals->start(
                $user,
                $owned,
                isset($input['amount']) ? (string) $input['amount'] : null,
                $all,
                (string) $input['network'],
                (bool) ($input['close_card'] ?? false),
                $this->meta($request),
            ))], 201),
        ));
    }

    #[OA\Get(path: '/api/v1/cards/issued/{card}/withdrawals/{withdrawal}', operationId: 'v1CardsWithdrawalShow', tags: ['Cards'], summary: 'One withdrawal', security: [['sanctum' => []]])]
    #[OA\Parameter(name: 'card', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'withdrawal', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: self::WITHDRAWAL_SHAPE)]
    #[OA\Response(response: 404, description: 'ERR_CARDS_015: not found')]
    public function show(Request $request, string $card, string $withdrawal): JsonResponse
    {
        return $this->withWithdrawal($request, $card, $withdrawal, fn (CardWithdrawal $owned): JsonResponse => response()->json(['success' => true, 'data' => LepoWithdrawals::present($owned)]));
    }

    #[OA\Post(
        path: '/api/v1/cards/issued/{card}/withdrawals/{withdrawal}/confirm',
        operationId: 'v1CardsWithdrawalConfirm',
        tags: ['Cards'],
        summary: 'Confirm the withdrawal with the code from the e-mail',
        description: 'Header Idempotency-Key (required; a new key for each code typed). Body: {code}: the 8-character code from the e-mail; spaces are ignored. 200: the code was accepted and the money is on its way (status sending). 202: the answer is being checked (status sending, failure_reason checking); do not send the code again. A code of the wrong length is refused without using an attempt. At most 5 tries per withdrawal in 10 minutes.',
        security: [['sanctum' => []]],
    )]
    #[OA\Parameter(name: 'card', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'withdrawal', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'code', type: 'string', example: 'AB12CD34')]))]
    #[OA\Response(response: 200, description: self::WITHDRAWAL_SHAPE)]
    #[OA\Response(response: 202, description: 'The answer is being checked: the withdrawal in status sending with failure_reason checking')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_043: too many wrong codes (the withdrawal waits for our support team); ERR_CARDS_046: the withdrawal is not waiting for a code')]
    #[OA\Response(response: 410, description: 'ERR_CARDS_042: the code expired; ask for a new code')]
    #[OA\Response(response: 422, description: 'ERR_CARDS_041: the code is not correct (code_attempts_left; null when the code had the wrong length)')]
    #[OA\Response(response: 429, description: 'ERR_CARDS_047: too many tries; wait (retry_after_seconds)')]
    public function confirm(Request $request, string $card, string $withdrawal): JsonResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'max:32']]);

        return $this->withWithdrawal($request, $card, $withdrawal, function (CardWithdrawal $owned) use ($request, $input): JsonResponse {
            // Tries are limited per withdrawal, so a client cannot guess codes
            // faster than the provider locks the request.
            $key = "cards:withdrawal-confirm:{$owned->id}";
            if (RateLimiter::tooManyAttempts($key, LepoWithdrawals::CONFIRM_LIMIT)) {
                return ErrorResponse::make('ERR_CARDS_047', 'Too many tries. Please wait before entering the code again.', ['retry_after_seconds' => RateLimiter::availableIn($key)]);
            }
            RateLimiter::hit($key, LepoWithdrawals::CONFIRM_WINDOW_SECONDS);

            return $this->attempt(function () use ($owned, $input, $request): JsonResponse {
                [$result, $checking] = $this->withdrawals->confirm($owned, (string) $input['code'], $this->meta($request));

                return response()->json(['success' => true, 'data' => LepoWithdrawals::present($result)], $checking ? 202 : 200);
            });
        });
    }

    #[OA\Post(
        path: '/api/v1/cards/issued/{card}/withdrawals/{withdrawal}/resend-code',
        operationId: 'v1CardsWithdrawalResendCode',
        tags: ['Cards'],
        summary: 'Send a new code by e-mail',
        description: 'Header Idempotency-Key (required). Empty body. Once a minute, at most 5 times per withdrawal. The new expiry is code_expires_at.',
        security: [['sanctum' => []]],
    )]
    #[OA\Parameter(name: 'card', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'withdrawal', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: self::WITHDRAWAL_SHAPE)]
    #[OA\Response(response: 409, description: 'ERR_CARDS_046: the withdrawal is not waiting for a code')]
    #[OA\Response(response: 429, description: 'ERR_CARDS_047: wait before asking again (retry_after_seconds; null after the fifth resend)')]
    public function resendCode(Request $request, string $card, string $withdrawal): JsonResponse
    {
        return $this->withWithdrawal($request, $card, $withdrawal, fn (CardWithdrawal $owned): JsonResponse => $this->attempt(
            fn (): JsonResponse => response()->json(['success' => true, 'data' => LepoWithdrawals::present($this->withdrawals->resend($owned, $this->meta($request)))]),
        ));
    }

    /**
     * @param  callable(User, Card): JsonResponse  $handle
     */
    private function withCard(Request $request, string $card, callable $handle, bool $requireEnabled = true): JsonResponse
    {
        $user = $this->user($request);
        if ($requireEnabled && ! LepoWithdrawals::availableFor($user)) {
            return ErrorResponse::make('ERR_CARDS_034');
        }

        // Another user's card and a card of another provider both read as
        // "not found", so card ids cannot be probed.
        $owned = Str::isUuid($card)
            ? Card::query()->whereKey($card)->where('user_id', $user->id)->where('issuer', 'lepo')->first()
            : null;

        return $owned === null ? ErrorResponse::make('ERR_CARDS_015') : $handle($user, $owned);
    }

    /**
     * Reads, the code and resend work while the switch is off, so a
     * withdrawal already under way can finish.
     *
     * @param  callable(CardWithdrawal): JsonResponse  $handle
     */
    private function withWithdrawal(Request $request, string $card, string $withdrawal, callable $handle): JsonResponse
    {
        return $this->withCard($request, $card, function (User $user, Card $owned) use ($withdrawal, $handle): JsonResponse {
            $row = Str::isUuid($withdrawal) ? CardWithdrawal::query()->whereKey($withdrawal)->where('card_id', $owned->id)->first() : null;

            return $row === null ? ErrorResponse::make('ERR_CARDS_015') : $handle($row);
        }, requireEnabled: false);
    }

    /**
     * @param  callable(): JsonResponse  $call
     */
    private function attempt(callable $call): JsonResponse
    {
        try {
            return $call();
        } catch (WithdrawalRefused $e) {
            return ErrorResponse::make($e->errorCode(), null, $e->context());
        }
    }

    /**
     * @return array{ip: string|null, user_agent: string|null}
     */
    private function meta(Request $request): array
    {
        return ['ip' => $request->ip(), 'user_agent' => $request->userAgent()];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
