<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\CardProviderRegistry;
use App\Domain\CardIssuance\Providers\CardProviderSelector;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardOnboarding;
use App\Http\Controllers\Controller;
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
    ) {
    }

    #[OA\Get(
        path: '/api/v1/cards/onboarding',
        operationId: 'v1CardsOnboarding',
        tags: ['Cards'],
        summary: 'Card onboarding state for the current user',
        description: 'Whether cards are available, the provider, the next step (cardholder, kyc, kyc_review, kyc_action_required, kyc_rejected, funding_pending, ready), the identity-check method and the deposit networks.',
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

        return response()->json(['success' => true, 'data' => [
            'available'        => true,
            'provider'         => $provider,
            'step'             => $this->step($cardholder),
            'kyc_method'       => 'sumsub_sdk',
            'deposit_networks' => CardProviderSelector::listFromConfig('cardissuance.providers.lepo_deposit_networks'),
            'prefill'          => array_filter(['email' => $user->email]),
        ]]);
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

    #[OA\Post(path: '/api/v1/cards/cardholder', operationId: 'v1CardsCreateCardholder', tags: ['Cards'], summary: 'Become a cardholder with the card provider', security: [['sanctum' => []]])]
    #[OA\Response(response: 201, description: 'Cardholder created')]
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

        $form = app(CreateCardholderRequest::class);

        if ($existing !== null) {
            // Not yet through the identity check: the answers may be corrected.
            $onboarding = $this->registry->onboarding($provider);
            if ($onboarding instanceof LepoCardOnboarding) {
                $onboarding->updateQuestionnaire($existing, $form->toProfile());
            }

            return response()->json(['success' => true, 'data' => $this->present($existing)]);
        }

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
        description: 'Returns how the app starts the identity check: method sumsub_sdk with a token for the SumSub mobile SDK.',
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
            'deposit_addresses' => $account->depositAddresses()
                ->where('is_active', true)
                ->whereIn('network', CardProviderSelector::listFromConfig('cardissuance.providers.lepo_deposit_networks'))
                ->orderBy('network')
                ->get(['network', 'asset', 'address'])
                ->map(static fn ($address): array => ['network' => $address->network, 'asset' => $address->asset, 'address' => $address->address])
                ->all(),
        ]]);
    }

    private function step(?Cardholder $cardholder): string
    {
        if ($cardholder === null) {
            return 'cardholder';
        }

        return match ($cardholder->kyc_status) {
            'verified'        => $this->fundingReady($cardholder->fundingAccount) ? 'ready' : 'funding_pending',
            'in_review'       => 'kyc_review',
            'action_required' => 'kyc_action_required',
            'rejected'        => 'kyc_rejected',
            default           => 'kyc',
        };
    }

    private function fundingReady(?CardFundingAccount $account): bool
    {
        return $account !== null
            && $account->status === 'active'
            && $account->depositAddresses()->where('is_active', true)->exists();
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
