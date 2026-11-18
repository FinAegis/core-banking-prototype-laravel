<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Http\Requests\FinCardAmountRequest;
use App\Domain\CardIssuance\Http\Requests\OpenFinCardCardRequest;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Services\FinCardCardholderService;
use App\Domain\CardIssuance\Services\FinCardCardService;
use App\Http\Controllers\Controller;
use App\Infrastructure\FinCard\FinCardApiException;
use App\Models\User;
use App\Support\ErrorResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * FinCard card lifecycle (Phase 4) — open, view, sensitive details, freeze/
 * unfreeze/cancel, top-up/withdraw, and transactions. Namespaced under
 * /v1/cards/fincard/* to avoid colliding with the generic card routes.
 *
 * @see docs/FINCARD_MOBILE_INTEGRATION.md §4.3
 */
class FinCardCardController extends Controller
{
    public function __construct(
        private readonly FinCardCardService $cards,
        private readonly FinCardCardholderService $cardholders,
    ) {
    }

    /**
     * Open a virtual card (requires a verified cardholder + funded account).
     */
    public function open(OpenFinCardCardRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cardholder = $this->cardholders->existingFor($user);
        if (! $cardholder instanceof Cardholder) {
            return ErrorResponse::make('ERR_CARDS_007');
        }
        if ($cardholder->kyc_status !== 'verified') {
            return ErrorResponse::make('ERR_CARDS_008');
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        // card_type_id is optional: fall back to the tenant default so a
        // single-product v1 client never has to send one. No id + no default
        // configured is a clean 422 rather than a downstream issuer 502.
        $cardTypeId = isset($validated['card_type_id'])
            ? (int) $validated['card_type_id']
            : (int) config('cardissuance.issuers.fincard.default_card_type_id');
        if ($cardTypeId < 1) {
            return ErrorResponse::make('ERR_CARDS_018');
        }

        try {
            $card = $this->cards->openCard(
                $user,
                $cardholder,
                $cardTypeId,
                (int) $validated['amount_cents'],
                $this->orderNo($request),
                $this->context($request),
            );
        } catch (FinCardApiException $e) {
            Log::warning('FinCard open card failed', ['user_id' => $user->id, 'msg' => $e->getMessage()]);

            return ErrorResponse::make('ERR_CARDS_016');
        }

        return response()->json(['success' => true, 'data' => $this->present($card)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->cards->listCards($user)->map(fn (Card $c): array => $this->present($c))->all();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show(Request $request, string $cardId): JsonResponse
    {
        return $this->withCard($request, $cardId, function (Card $card) use ($request): JsonResponse {
            try {
                $card = $this->cards->refresh($card, $this->context($request));
            } catch (FinCardApiException $e) {
                Log::warning('FinCard card refresh failed; serving cache', ['msg' => $e->getMessage()]);
            }

            return response()->json(['success' => true, 'data' => $this->present($card)]);
        });
    }

    public function sensitive(Request $request, string $cardId): JsonResponse
    {
        return $this->withCard($request, $cardId, function (Card $card) use ($request): JsonResponse {
            try {
                $details = $this->cards->sensitiveDetails($card, $this->context($request));
            } catch (FinCardApiException $e) {
                Log::warning('FinCard sensitive fetch failed', ['msg' => $e->getMessage()]);

                return ErrorResponse::make('ERR_CARDS_016');
            }

            return response()->json(['success' => true, 'data' => $details]);
        });
    }

    public function freeze(Request $request, string $cardId): JsonResponse
    {
        return $this->mutate($request, $cardId, fn (Card $card): Card => $this->cards->freeze($card, $this->orderNo($request), $this->context($request)));
    }

    public function unfreeze(Request $request, string $cardId): JsonResponse
    {
        return $this->mutate($request, $cardId, fn (Card $card): Card => $this->cards->unfreeze($card, $this->orderNo($request), $this->context($request)));
    }

    public function cancel(Request $request, string $cardId): JsonResponse
    {
        return $this->mutate($request, $cardId, fn (Card $card): Card => $this->cards->cancel($card, $this->orderNo($request), $this->context($request)));
    }

    public function topUp(FinCardAmountRequest $request, string $cardId): JsonResponse
    {
        $amount = (int) $request->validated('amount_cents');

        return $this->mutate($request, $cardId, fn (Card $card): Card => $this->cards->topUp($card, $amount, $this->orderNo($request), $this->context($request)));
    }

    public function withdraw(FinCardAmountRequest $request, string $cardId): JsonResponse
    {
        $amount = (int) $request->validated('amount_cents');

        return $this->mutate($request, $cardId, function (Card $card) use ($amount, $request): Card {
            if ((int) $card->balance_cents < $amount) {
                throw new FinCardApiException('insufficient card balance');
            }

            return $this->cards->withdraw($card, $amount, $this->orderNo($request), $this->context($request));
        }, insufficientCode: 'ERR_CARDS_017');
    }

    public function transactions(Request $request, string $cardId): JsonResponse
    {
        return $this->withCard($request, $cardId, function (Card $card) use ($request): JsonResponse {
            try {
                $limit = min((int) $request->query('limit', '20'), 100);
                $page = max((int) $request->query('page', '1'), 1);
                $response = app(\App\Infrastructure\FinCard\FinCardClient::class)
                    ->getCardPurchaseTransactions($card->issuer_card_token, $page, $limit, $this->context($request));
                $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            } catch (FinCardApiException $e) {
                Log::warning('FinCard tx fetch failed', ['msg' => $e->getMessage()]);

                return ErrorResponse::make('ERR_CARDS_016');
            }

            return response()->json(['success' => true, 'data' => $data]);
        });
    }

    /**
     * Resolve the user's card and run the callback, or 404.
     *
     * @param  Closure(Card): JsonResponse  $callback
     */
    private function withCard(Request $request, string $cardId, Closure $callback): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $card = $this->cards->findForUser($user, $cardId);
        if (! $card instanceof Card) {
            return ErrorResponse::make('ERR_CARDS_015');
        }

        return $callback($card);
    }

    /**
     * Resolve the card, run a mutating action, and return the presented card.
     *
     * @param  Closure(Card): Card  $action
     */
    private function mutate(Request $request, string $cardId, Closure $action, string $insufficientCode = 'ERR_CARDS_016'): JsonResponse
    {
        return $this->withCard($request, $cardId, function (Card $card) use ($action, $insufficientCode): JsonResponse {
            try {
                $card = $action($card);
            } catch (FinCardApiException $e) {
                $code = str_contains($e->getMessage(), 'insufficient') ? $insufficientCode : 'ERR_CARDS_016';
                Log::warning('FinCard card mutation failed', ['msg' => $e->getMessage()]);

                return ErrorResponse::make($code);
            }

            return response()->json(['success' => true, 'data' => $this->present($card)]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Card $card): array
    {
        return [
            'card_id'       => $card->issuer_card_token,
            'last4'         => $card->last4,
            'network'       => $card->network,
            'status'        => $card->status,
            'currency'      => $card->currency,
            'balance_cents' => (int) $card->balance_cents,
            'label'         => $card->label,
            'expires_at'    => $card->expires_at?->toDateString(),
        ];
    }

    /**
     * FinCard merchantOrderNo derived from the Idempotency-Key so a client retry
     * reuses the same order number (aligning our idempotency with FinCard's).
     */
    private function orderNo(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key', '');

        return $key !== '' ? substr(preg_replace('/[^A-Za-z0-9]/', '', $key) ?? '', 0, 32) : (string) Str::uuid();
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        return [
            'forwarded_for' => $request->ip() ?? '127.0.0.1',
            'platform'      => (string) ($request->header('platform') ?? 'ios'),
            'device_id'     => (string) ($request->header('deviceId') ?? ''),
        ];
    }
}
