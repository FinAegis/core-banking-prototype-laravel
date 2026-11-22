<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardDepositAddress;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardOnboardingSteps;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\CardProviderRegistry;
use App\Domain\CardIssuance\Providers\CardProviderSelector;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardholderForm;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardOnboarding;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUps;
use App\Http\Controllers\Controller;
use App\Http\Requests\CardIssuance\CardholderQuestionnaireRequest;
use App\Http\Requests\CardIssuance\CreateCardholderRequest;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Provider-neutral card onboarding. The provider comes from the user's
 * cardholder, or from configuration for a new user (Lepo by default, only for
 * pilot users while the pilot is on). Lepo is served here; FinCard users are
 * handed to the FinCard controllers so their flow is unchanged.
 */
final class CardOnboardingController extends Controller
{
    public function __construct(
        private readonly CardProviderSelector $selector,
        private readonly CardProviderRegistry $registry,
        private readonly LepoCardholderForm $form,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/cards/onboarding',
        operationId: 'v1CardsOnboarding',
        tags: ['Cards'],
        summary: 'Card onboarding state for the current user',
        description: 'In the steps kyc and kyc_action_required it returns `kyc_submitted` (true once the identity-check page reported a submission Lepo has not answered). In the steps funding_pending and ready it also returns `card` {id, status, last4} of the current card, or null. Whether cards are available, the provider, the next step (cardholder, kyc, kyc_review, kyc_action_required, kyc_rejected, funding_pending, ready), the identity-check method (hosted_url or sumsub_sdk) and the deposit networks [{network, asset, default}]. In the steps cardholder, kyc and kyc_action_required it also returns `form` {fields, options, prefill}: the whole cardholder form for a new user, or only the questionnaire while the answers can still be corrected. Field names are the request keys and the keys of 422 validation errors.',
        security: [['sanctum' => []]],
    )]
    #[OA\Response(response: 200, description: 'Onboarding state')]
    public function onboarding(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $provider = $this->selector->providerFor($user);

        if ($provider === null) {
            return response()->json(['success' => true, 'data' => ['available' => false, 'reason' => 'not_available']]);
        }

        if ($provider === 'fincard') {
            return $this->delegate(FinCardOnboardingController::class, 'onboarding');
        }

        $cardholder = $this->cardholder($user, $provider);
        $step = CardOnboardingSteps::for($cardholder);
        $networks = $this->depositNetworks();

        $data = [
            'available'        => true,
            'provider'         => $provider,
            'step'             => $step,
            'kyc_method'       => LepoCardOnboarding::kycMethod(),
            'topups_enabled'   => LepoTopUps::enabled(),
            'deposit_networks' => array_map(
                static fn (string $network, int $i): array => ['network' => $network, 'asset' => 'USDC', 'default' => $i === 0],
                $networks,
                array_keys($networks),
            ),
        ];

        if (in_array($step, CardOnboardingSteps::CORRECTABLE, true)) {
            $data['form'] = $this->form->describe($user, $cardholder);
        }

        if (in_array($step, ['kyc', 'kyc_action_required'], true)) {
            // The identity-check page reported a submission Lepo has not
            // answered yet; the app shows "check sent" instead of asking again.
            $verification = is_array($cardholder?->verification_data) ? $cardholder->verification_data : [];
            $data['kyc_submitted'] = ! empty($verification['kyc_submitted_at']);
        }

        if (in_array($step, ['funding_pending', 'ready'], true)) {
            $data['card'] = $this->cardSummary($user);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    #[OA\Get(path: '/api/v1/cards/cardholder', operationId: 'v1CardsCardholderStatus', tags: ['Cards'], summary: 'Cardholder and identity-check status', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Cardholder status')]
    public function status(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $provider = $this->selector->providerFor($user);

        if ($provider === 'fincard') {
            return $this->delegate(FinCardOnboardingController::class, 'status');
        }

        $cardholder = $this->cardholder($user, $provider);

        return response()->json(['success' => true, 'data' => $cardholder === null
            ? ['provider' => $provider, 'kyc_status' => 'not_started']
            : $this->present($cardholder)]);
    }

    #[OA\Post(
        path: '/api/v1/cards/cardholder',
        operationId: 'v1CardsCreateCardholder',
        tags: ['Cards'],
        summary: 'Become a cardholder with the card provider',
        description: 'Takes the fields of `form` from GET /api/v1/cards/onboarding. A cardholder whose identity check has not started (or must be resubmitted) sends only the questionnaire fields to correct the answers.',
        security: [['sanctum' => []]],
    )]
    #[OA\Response(response: 201, description: 'Cardholder created')]
    #[OA\Response(response: 200, description: 'Existing cardholder; questionnaire answers updated when the identity check can still use them')]
    #[OA\Response(response: 403, description: 'ERR_CARDS_022: cards not available for this account')]
    #[OA\Response(response: 422, description: 'Validation error, or ERR_CARDS_023: nationality or country not supported')]
    public function createCardholder(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $provider = $this->selector->providerFor($user);

        if ($provider === null) {
            return ErrorResponse::make('ERR_CARDS_022');
        }

        if ($provider === 'fincard') {
            return $this->delegate(FinCardOnboardingController::class, 'createCardholder');
        }

        $existing = $this->cardholder($user, $provider);
        if ($existing !== null && ! in_array($existing->kyc_status, ['pending', 'action_required'], true)) {
            return response()->json(['success' => true, 'data' => $this->present($existing)]);
        }

        if ($existing !== null) {
            // Not yet through the identity check: only the answers may be
            // corrected, so only they are validated.
            $answers = app(CardholderQuestionnaireRequest::class);
            $onboarding = $this->registry->onboarding($provider);
            if ($onboarding instanceof LepoCardOnboarding) {
                $onboarding->updateQuestionnaire($existing, $answers->questionnaire());
            }

            return response()->json(['success' => true, 'data' => $this->present($existing)]);
        }

        $form = app(CreateCardholderRequest::class);

        try {
            $cardholder = $this->registry->onboarding($provider)->createCardholder($user, $form->toProfile());
        } catch (CardProviderException $e) {
            return $this->providerError($e);
        }

        return response()->json(['success' => true, 'data' => $this->present($cardholder)], 201);
    }

    #[OA\Post(
        path: '/api/v1/cards/kyc/session',
        operationId: 'v1CardsKycSession',
        tags: ['Cards'],
        summary: 'Start the identity check',
        description: 'Returns how the app starts the identity check. method hosted_url: open `url` (a signed page, valid until `expires_at`) in the in-app browser; it returns to zelta://cards/kyc-done when the check is sent, then read GET /api/v1/cards/onboarding again. method sumsub_sdk: `token` for the native SumSub SDK.',
        security: [['sanctum' => []]],
    )]
    #[OA\Response(response: 200, description: 'Identity-check session')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_024: no cardholder yet')]
    public function startKyc(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $provider = $this->selector->providerFor($user);

        if ($provider === null) {
            return ErrorResponse::make('ERR_CARDS_022');
        }

        $cardholder = $this->cardholder($user, $provider);
        if ($provider === 'fincard' || $cardholder === null) {
            return ErrorResponse::make('ERR_CARDS_024');
        }

        try {
            $start = $this->registry->onboarding($provider)->startKyc($cardholder);
        } catch (CardProviderException $e) {
            return $this->providerError($e);
        }

        return response()->json(['success' => true, 'data' => $start->toArray()]);
    }

    #[OA\Get(path: '/api/v1/cards/account', operationId: 'v1CardsFundingAccount', tags: ['Cards'], summary: 'Card funding account and deposit addresses', security: [['sanctum' => []]])]
    #[OA\Response(response: 200, description: 'Funding account')]
    #[OA\Response(response: 409, description: 'ERR_CARDS_024: identity check not finished')]
    public function account(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $provider = $this->selector->providerFor($user);

        if ($provider === null) {
            return ErrorResponse::make('ERR_CARDS_022');
        }

        if ($provider === 'fincard') {
            return $this->delegate(FinCardAccountController::class, 'account');
        }

        $cardholder = $this->cardholder($user, $provider);
        $account = $cardholder?->fundingAccount;
        if ($cardholder === null || $cardholder->kyc_status !== 'verified' || $account === null) {
            return ErrorResponse::make('ERR_CARDS_024');
        }

        return response()->json(['success' => true, 'data' => [
            'provider'          => $provider,
            'status'            => $account->status,
            'currency'          => $account->currency,
            'balance_cents'     => $account->balance_cents,
            'deposit_addresses' => $this->depositAddresses($account->depositAddresses()->where('is_active', true)->get(['network', 'asset', 'address'])->all()),
        ]]);
    }

    /**
     * The user's current card ({id, status, last4}), or null before one exists.
     *
     * @return array{id: string, status: string, last4: string|null}|null
     */
    private function cardSummary(User $user): ?array
    {
        $card = Card::query()
            ->where('user_id', $user->id)
            ->where('issuer', 'lepo')
            ->whereNotIn('status', Card::CLOSED_STATUSES)
            ->orderByDesc('created_at')
            ->first();

        return $card === null ? null : ['id' => $card->id, 'status' => $card->status, 'last4' => $card->last4];
    }

    /**
     * Active addresses on the offered networks, in the offered order; the
     * first offered network is the default.
     *
     * @param  array<int, CardDepositAddress>  $addresses
     * @return list<array{network: string, asset: string, address: string, default: bool}>
     */
    private function depositAddresses(array $addresses): array
    {
        $networks = $this->depositNetworks();
        $list = [];
        foreach ($networks as $i => $network) {
            foreach ($addresses as $address) {
                if ($address->network === $network) {
                    $list[] = ['network' => $network, 'asset' => $address->asset, 'address' => $address->address, 'default' => $i === 0];
                }
            }
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    private function depositNetworks(): array
    {
        return array_values(CardProviderSelector::listFromConfig('cardissuance.providers.lepo_deposit_networks'));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Cardholder $cardholder): array
    {
        return [
            'cardholder_id' => $cardholder->id,
            'provider'      => $cardholder->provider,
            'kyc_status'    => $cardholder->kyc_status,
        ];
    }

    private function providerError(CardProviderException $e): JsonResponse
    {
        return match ($e->reason()) {
            'restricted_nationality' => ErrorResponse::make('ERR_CARDS_023'),
            'not_eligible'           => ErrorResponse::make('ERR_CARDS_022'),
            default                  => ErrorResponse::make('ERR_CARDS_016'),
        };
    }

    private function cardholder(User $user, ?string $provider): ?Cardholder
    {
        return $provider === null
            ? null
            : Cardholder::query()->where('user_id', $user->id)->where('provider', $provider)->first();
    }

    /**
     * @param  class-string  $controller
     */
    private function delegate(string $controller, string $method): JsonResponse
    {
        $target = [app($controller), $method];
        if (! is_callable($target)) {
            abort(500);
        }

        /** @var JsonResponse $response */
        $response = app()->call($target);

        return $response;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
