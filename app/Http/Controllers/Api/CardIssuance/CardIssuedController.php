<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\Providers\CardOnboardingSteps;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\CardProviderSelector;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardLifecycle;
use App\Domain\CardIssuance\Providers\Lepo\LepoThreeDsCodes;
use App\Domain\CardIssuance\Support\PublicLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Provider-neutral cards (`/api/v1/cards/issued`). Lepo cards are served here;
 * FinCard users are handed to FinCardCardController so their flow is
 * unchanged. A Lepo card row exists before Lepo opens the card (it opens after
 * the first top-up), so a new card starts as `pending` without a number.
 */
final class CardIssuedController extends Controller
{
    public function __construct(
        private readonly CardProviderSelector $selector,
        private readonly LepoCardLifecycle $cards,
    ) {
    }

    #[OA\Get(path: '/api/v1/cards/issued', operationId: 'v1CardsIssuedIndex', tags: ['Cards'], summary: 'The user\'s cards', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Cards: [{id, provider, status, last4, network, currency, balance_cents, expires_at, frozen_by_support, created_at}]')]
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $provider = $this->selector->providerFor($user);

        if ($provider === 'fincard') {
            return $this->delegate('index');
        }

        $cards = Card::query()->where('user_id', $user->id)->where('issuer', 'lepo')->orderByDesc('created_at')->get();

        return response()->json(['success' => true, 'data' => $cards->map(fn (Card $card): array => self::present($card))->all()]);
    }

    #[OA\Post(
        path: '/api/v1/cards/issued',
        operationId: 'v1CardsIssuedCreate',
        tags: ['Cards'],
        summary: 'Get a card',
        description: 'Creates the user\'s card. A Lepo card starts as `pending` without a number and opens after the first top-up.',
        security: [['sanctum' => []]],
    )]
    #[OA\Response(response: 201, description: 'Card created')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_024: onboarding step is not ready yet; ERR_CARDS_026: the user already has a card')]
    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $provider = $this->selector->providerFor($user);

        if ($provider === null) {
            return ErrorResponse::make('ERR_CARDS_022');
        }

        if ($provider === 'fincard') {
            return $this->delegate('open');
        }

        $cardholder = Cardholder::query()->where('user_id', $user->id)->where('provider', 'lepo')->first();
        // Card actions need a ready funding account: the app shows "Add to
        // card" next to "Get card", and a top-up needs a deposit address.
        if ($cardholder === null || CardOnboardingSteps::for($cardholder) !== 'ready') {
            return ErrorResponse::make('ERR_CARDS_024');
        }

        try {
            $card = $this->cards->createPending($cardholder);
        } catch (CardProviderException $e) {
            return $this->providerError($e);
        }

        return response()->json(['success' => true, 'data' => self::present($card)], 201);
    }

    #[OA\Get(path: '/api/v1/cards/issued/{card}', operationId: 'v1CardsIssuedShow', tags: ['Cards'], summary: 'One card', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Card')]
    #[OA\Response(response: 404, description: 'ERR_CARDS_015: card not found')]
    public function show(Request $request, string $card): JsonResponse
    {
        return $this->withCard($request, $card, 'show', function (Card $owned): JsonResponse {
            if ($owned->isOpenAtProvider()) {
                try {
                    $this->cards->refresh($owned);
                } catch (CardProviderException) {
                    // Show the last known state when Lepo cannot be reached.
                }
            }

            return response()->json(['success' => true, 'data' => self::present($owned)]);
        });
    }

    #[OA\Get(path: '/api/v1/cards/issued/{card}/transactions', operationId: 'v1CardsIssuedTransactions', tags: ['Cards'], summary: 'Card transactions (page from 0, limit up to 50)', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Transactions {items: [{id, type (payment|refund|top_up|withdrawal|fee|other), direction (debit|credit), amount (positive, 2 decimals), currency, fee, status (pending|completed|failed|cancelled|unknown), merchant, merchant_country, occurred_at, provider_type}], has_more, page, total}')]
    public function transactions(Request $request, string $card): JsonResponse
    {
        return $this->withCard($request, $card, 'transactions', function (Card $owned) use ($request): JsonResponse {
            if (! $owned->isOpenAtProvider()) {
                return response()->json(['success' => true, 'data' => ['items' => [], 'has_more' => false, 'total' => 0, 'page' => 0]]);
            }

            $page = max(0, $request->integer('page', 0));

            return response()->json(['success' => true, 'data' => [
                ...$this->cards->transactions($owned, $page, max(1, min(50, $request->integer('limit', 20)))),
                'page' => $page,
            ]]);
        });
    }

    #[OA\Post(path: '/api/v1/cards/issued/{card}/freeze', operationId: 'v1CardsIssuedFreeze', tags: ['Cards'], summary: 'Freeze a card', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Card')]
    public function freeze(Request $request, string $card): JsonResponse
    {
        return $this->withOpenCard($request, $card, 'freeze', fn (Card $owned): Card => $this->cards->freeze($owned));
    }

    #[OA\Post(path: '/api/v1/cards/issued/{card}/unfreeze', operationId: 'v1CardsIssuedUnfreeze', tags: ['Cards'], summary: 'Unfreeze a card', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Card')]
    #[OA\Response(response: 403, description: 'ERR_CARDS_021: frozen by support')]
    public function unfreeze(Request $request, string $card): JsonResponse
    {
        return $this->withCard($request, $card, 'unfreeze', function (Card $owned): JsonResponse {
            // A support or issuer freeze is not the owner's to lift.
            if ($owned->isOpenAtProvider() && CardStaffFreeze::isHeld((string) $owned->issuer_card_token)) {
                return ErrorResponse::make('ERR_CARDS_021');
            }

            if (! $owned->isOpenAtProvider() || $owned->status !== Card::STATUS_FROZEN) {
                return ErrorResponse::make('ERR_CARDS_024');
            }

            return $this->mutate($owned, fn (Card $c): Card => $this->cards->unfreeze($c));
        });
    }

    #[OA\Post(
        path: '/api/v1/cards/issued/{card}/cancel',
        operationId: 'v1CardsIssuedCancel',
        tags: ['Cards'],
        summary: 'Cancel a card',
        description: 'Refused while the card holds any money. A card that was never opened is cancelled at once.',
        security: [['sanctum' => []]],
    )]
    #[OA\Response(response: 200, description: 'Card')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_025: the card still holds money')]
    public function cancel(Request $request, string $card): JsonResponse
    {
        return $this->withCard($request, $card, 'cancel', function (Card $owned): JsonResponse {
            // A row Lepo has not opened as far as we know is checked at Lepo
            // first: an unfinished open may have created the card there.
            return $this->mutate($owned, fn (Card $c): Card => $c->isOpenAtProvider() ? $this->cards->cancel($c) : $this->cards->cancelUnopened($c));
        });
    }

    #[OA\Get(
        path: '/api/v1/cards/issued/{card}/sensitive',
        operationId: 'v1CardsIssuedSensitive',
        tags: ['Cards'],
        summary: 'Card details page',
        description: 'Returns {display_mode: widget, url, expires_at}: a signed page that shows the card number, expiry and security code through Interlace Widget.js. The link is valid for 5 minutes and opens once.',
        security: [['sanctum' => []]],
    )]
    #[OA\Response(response: 200, description: 'Card details link')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_024: the card is not open yet')]
    public function sensitive(Request $request, string $card): JsonResponse
    {
        return $this->withCard($request, $card, 'sensitive', function (Card $owned): JsonResponse {
            if (! $owned->isOpenAtProvider() || ! in_array($owned->status, [Card::STATUS_ACTIVE, Card::STATUS_FROZEN], true)) {
                return ErrorResponse::make('ERR_CARDS_024');
            }

            $expires = now()->addMinutes(5);
            $path = URL::temporarySignedRoute('cards.details.hosted', $expires, ['card' => $owned->id, 'n' => bin2hex(random_bytes(16))], absolute: false);

            return response()->json(['success' => true, 'data' => [
                'display_mode' => 'widget',
                'url'          => PublicLink::to($path),
                'expires_at'   => $expires->toIso8601String(),
            ]])->header('Cache-Control', 'no-store');
        });
    }

    #[OA\Get(
        path: '/api/v1/cards/issued/{card}/3ds/code',
        operationId: 'v1CardsIssuedThreeDsCode',
        tags: ['Cards'],
        summary: 'The waiting 3-D Secure code',
        description: 'Returns {code, expires_at, merchant, amount, currency} while a code from the card issuer is waiting (at most 10 minutes). The push and the cards.3ds.code event never carry the code.',
        security: [['sanctum' => []]],
    )]
    #[OA\Response(response: 200, description: '3-D Secure code')]
    #[OA\Response(response: 404, description: 'ERR_CARDS_033: no code is waiting')]
    public function threeDsCode(Request $request, string $card): JsonResponse
    {
        $user = $this->user($request);
        $owned = Str::isUuid($card)
            ? Card::query()->whereKey($card)->where('user_id', $user->id)->where('issuer', 'lepo')->first()
            : null;
        if ($owned === null) {
            return ErrorResponse::make('ERR_CARDS_015');
        }

        $code = app(LepoThreeDsCodes::class)->current($owned);

        return ($code === null
            ? ErrorResponse::make('ERR_CARDS_033')
            : response()->json(['success' => true, 'data' => $code]))->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Card $card): array
    {
        return [
            'id'            => $card->id,
            'provider'      => $card->issuer,
            'status'        => $card->status,
            'last4'         => $card->last4,
            'network'       => $card->network,
            'currency'      => $card->currency,
            'balance_cents' => $card->balance_cents ?? 0,
            'expires_at'    => $card->expires_at?->toIso8601String(),
            // A support or issuer freeze: only staff can lift it (ERR_CARDS_021).
            'frozen_by_support' => $card->isOpenAtProvider() && CardStaffFreeze::isHeld((string) $card->issuer_card_token),
            'created_at'        => $card->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  callable(Card): JsonResponse  $handle
     */
    private function withCard(Request $request, string $card, string $finCardMethod, callable $handle): JsonResponse
    {
        $user = $this->user($request);
        if ($this->selector->providerFor($user) === 'fincard') {
            return $this->delegate($finCardMethod, ['cardId' => $card]);
        }

        // Card ids are uuids; anything else cannot be a card (and must not reach
        // a native UUID column on MariaDB).
        $owned = Str::isUuid($card)
            ? Card::query()->whereKey($card)->where('user_id', $user->id)->where('issuer', 'lepo')->first()
            : null;
        if ($owned === null) {
            return ErrorResponse::make('ERR_CARDS_015');
        }

        return $handle($owned);
    }

    /**
     * @param  callable(Card): Card  $action
     */
    private function withOpenCard(Request $request, string $card, string $finCardMethod, callable $action): JsonResponse
    {
        return $this->withCard($request, $card, $finCardMethod, function (Card $owned) use ($action): JsonResponse {
            if (! $owned->isOpenAtProvider()) {
                return ErrorResponse::make('ERR_CARDS_024');
            }

            return $this->mutate($owned, $action);
        });
    }

    /**
     * @param  callable(Card): Card  $action
     */
    private function mutate(Card $card, callable $action): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => self::present($action($card))]);
        } catch (CardProviderException $e) {
            return $this->providerError($e);
        }
    }

    private function providerError(CardProviderException $e): JsonResponse
    {
        return match ($e->reason()) {
            'holds_money'       => ErrorResponse::make('ERR_CARDS_025'),
            'card_limit'        => ErrorResponse::make('ERR_CARDS_026'),
            'topup_in_progress' => ErrorResponse::make('ERR_CARDS_031'),
            default             => ErrorResponse::make('ERR_CARDS_016'),
        };
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function delegate(string $method, array $parameters = []): JsonResponse
    {
        $target = [app(FinCardCardController::class), $method];
        if (! is_callable($target)) {
            abort(500);
        }

        /** @var JsonResponse $response */
        $response = app()->call($target, $parameters);

        return $response;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
