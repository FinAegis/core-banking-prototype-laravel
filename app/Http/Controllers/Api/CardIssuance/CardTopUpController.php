<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUps;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * "Add to card" for Lepo cards (`/api/v1/cards/issued/{card}/topups`). The
 * app shows the estimate, starts a top-up, sends USDC from the wallet to the
 * returned deposit address and may report the transaction hash; status
 * changes arrive as `card.topup.updated`.
 */
final class CardTopUpController extends Controller
{
    private const AMOUNT = 'regex:/^\d{1,9}(\.\d{1,6})?$/';

    public function __construct(private readonly LepoTopUps $topups)
    {
    }

    #[OA\Get(path: '/api/v1/cards/issued/{card}/topups/estimate', operationId: 'v1CardsTopUpEstimate', tags: ['Cards'], summary: 'Estimate a top-up', description: 'Query: amount (USDC, decimal string) and network. Returns {amount, asset, network, credited_cents, fee_cents, issuance_fee_cents, min_amount, max_amount}.', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Estimate')]
    #[OA\Response(response: 503, description: 'ERR_CARDS_027: top-ups are not available yet')]
    public function estimate(Request $request, string $card): JsonResponse
    {
        if (! LepoTopUps::enabled()) {
            return ErrorResponse::make('ERR_CARDS_027');
        }

        $input = $request->validate(['amount' => ['required', 'string', self::AMOUNT], 'network' => ['required', 'string', 'max:32']]);

        return $this->withCard($request, $card, fn (Card $owned): JsonResponse => $this->attempt(
            fn (): JsonResponse => response()->json(['success' => true, 'data' => $this->topups->estimate($owned, (string) $input['amount'], (string) $input['network'])]),
        ));
    }

    #[OA\Get(path: '/api/v1/cards/issued/{card}/topups', operationId: 'v1CardsTopUpIndex', tags: ['Cards'], summary: 'The card\'s top-ups, newest first (page from 0)', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Top-ups {items, has_more, page}')]
    public function index(Request $request, string $card): JsonResponse
    {
        return $this->withCard($request, $card, function (Card $owned) use ($request): JsonResponse {
            $page = max(0, $request->integer('page', 0));
            $rows = CardTopUp::query()->where('card_id', $owned->id)->orderByDesc('created_at')->skip($page * 20)->take(21)->get();

            return response()->json(['success' => true, 'data' => [
                'items'    => $rows->take(20)->map(fn (CardTopUp $topup): array => LepoTopUps::present($topup))->values()->all(),
                'has_more' => $rows->count() > 20,
                'page'     => $page,
            ]]);
        });
    }

    #[OA\Post(path: '/api/v1/cards/issued/{card}/topups', operationId: 'v1CardsTopUpStart', tags: ['Cards'], summary: 'Start a top-up', description: 'Body: {amount (USDC decimal string), asset: USDC, network}. Returns the top-up with the deposit address to send USDC to.', security: [['sanctum' => []]])]
    #[OA\Response(response: 201, description: 'Top-up {id, card_id, source, status, amount, asset, network, deposit_address, tx_hash, credited_cents, fee_cents, issuance_fee_cents, expires_at, created_at, completed_at}')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_030: card cannot be topped up; ERR_CARDS_031: another top-up is in progress')]
    #[OA\Response(response: 422, description: 'ERR_CARDS_028: amount outside the limits (with min_amount, max_amount); ERR_CARDS_029: network not supported')]
    #[OA\Response(response: 503, description: 'ERR_CARDS_027: top-ups are not available yet')]
    public function store(Request $request, string $card): JsonResponse
    {
        if (! LepoTopUps::enabled()) {
            return ErrorResponse::make('ERR_CARDS_027');
        }

        $input = $request->validate([
            'amount'  => ['required', 'string', self::AMOUNT],
            'network' => ['required', 'string', 'max:32'],
            'asset'   => ['sometimes', 'string', 'in:USDC'],
        ]);

        return $this->withCard($request, $card, fn (Card $owned): JsonResponse => $this->attempt(
            fn (): JsonResponse => response()->json(['success' => true, 'data' => LepoTopUps::present($this->topups->start($owned, (string) $input['amount'], (string) $input['network']))], 201),
        ));
    }

    #[OA\Get(path: '/api/v1/cards/issued/{card}/topups/{topup}', operationId: 'v1CardsTopUpShow', tags: ['Cards'], summary: 'One top-up', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Top-up')]
    public function show(Request $request, string $card, string $topup): JsonResponse
    {
        return $this->withTopUp($request, $card, $topup, fn (CardTopUp $owned): JsonResponse => response()->json(['success' => true, 'data' => LepoTopUps::present($owned)]));
    }

    #[OA\Patch(path: '/api/v1/cards/issued/{card}/topups/{topup}', operationId: 'v1CardsTopUpTxHash', tags: ['Cards'], summary: 'Report the wallet transaction hash', description: 'Body: {tx_hash}. Optional; recorded once.', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Top-up')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_032: the top-up can no longer be changed')]
    public function update(Request $request, string $card, string $topup): JsonResponse
    {
        $input = $request->validate(['tx_hash' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{64}$/']]);

        return $this->withTopUp($request, $card, $topup, fn (CardTopUp $owned): JsonResponse => $this->attempt(
            fn (): JsonResponse => response()->json(['success' => true, 'data' => LepoTopUps::present($this->topups->attachTxHash($owned, (string) $input['tx_hash']))]),
        ));
    }

    #[OA\Post(path: '/api/v1/cards/issued/{card}/topups/{topup}/cancel', operationId: 'v1CardsTopUpCancel', tags: ['Cards'], summary: 'Cancel a top-up that has no deposit yet', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Top-up')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_032: the top-up can no longer be changed')]
    public function cancel(Request $request, string $card, string $topup): JsonResponse
    {
        return $this->withTopUp($request, $card, $topup, fn (CardTopUp $owned): JsonResponse => $this->attempt(
            fn (): JsonResponse => response()->json(['success' => true, 'data' => LepoTopUps::present($this->topups->cancel($owned))]),
        ));
    }

    /**
     * @param  callable(Card): JsonResponse  $handle
     */
    private function withCard(Request $request, string $card, callable $handle): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $owned = Str::isUuid($card)
            ? Card::query()->whereKey($card)->where('user_id', $user->id)->where('issuer', 'lepo')->first()
            : null;

        return $owned === null ? ErrorResponse::make('ERR_CARDS_015') : $handle($owned);
    }

    /**
     * @param  callable(CardTopUp): JsonResponse  $handle
     */
    private function withTopUp(Request $request, string $card, string $topup, callable $handle): JsonResponse
    {
        return $this->withCard($request, $card, function (Card $owned) use ($topup, $handle): JsonResponse {
            $row = Str::isUuid($topup) ? CardTopUp::query()->whereKey($topup)->where('card_id', $owned->id)->first() : null;

            return $row === null ? ErrorResponse::make('ERR_CARDS_015') : $handle($row);
        });
    }

    /**
     * @param  callable(): JsonResponse  $call
     */
    private function attempt(callable $call): JsonResponse
    {
        try {
            return $call();
        } catch (CardProviderException $e) {
            return match ($e->reason()) {
                'topups_disabled'     => ErrorResponse::make('ERR_CARDS_027'),
                'amount_out_of_range' => ErrorResponse::make('ERR_CARDS_028', null, ['min_amount' => LepoTopUps::minimum(), 'max_amount' => LepoTopUps::maximum()]),
                'network_unsupported' => ErrorResponse::make('ERR_CARDS_029'),
                'card_unavailable'    => ErrorResponse::make('ERR_CARDS_030'),
                'topup_in_progress'   => ErrorResponse::make('ERR_CARDS_031'),
                'not_cancellable'     => ErrorResponse::make('ERR_CARDS_032'),
                default               => ErrorResponse::make('ERR_CARDS_016'),
            };
        }
    }
}
