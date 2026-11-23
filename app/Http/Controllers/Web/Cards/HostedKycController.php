<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Cards;

use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardOnboarding;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * The identity-check page the app opens in its browser (KycStart hosted_url).
 * It runs the SumSub web SDK with an SDK token Lepo issues when the page
 * opens (sumsub-access-token; kyc-url answers a hosted link instead).
 * The signed link is the only credential: it is short-lived and the page
 * sends no referrer, so the link does not leak to SumSub.
 */
final class HostedKycController extends Controller
{
    /** Cardholder states in which the identity check can be (re)started. */
    private const OPEN = ['pending', 'action_required'];

    public function __construct(private readonly LepoCardOnboarding $onboarding)
    {
    }

    public function show(Request $request, string $cardholder): Response
    {
        if (! $request->hasValidRelativeSignature()) {
            return $this->page('expired', 403);
        }

        $holder = $this->cardholder($cardholder);
        if (! in_array($holder->kyc_status, self::OPEN, true)) {
            return $this->page('submitted');
        }

        try {
            $token = $this->onboarding->webKycToken($holder);
        } catch (CardProviderException) {
            return $this->page('unavailable', 502);
        }

        // Lepo says the check is already approved; the status is synced.
        if ($token === null) {
            return $this->page('submitted');
        }

        return $this->page('ready', 200, [
            'token'        => $token,
            'refreshUrl'   => URL::temporarySignedRoute('cards.kyc.hosted.token', now()->addMinutes(60), ['cardholder' => $holder->id], absolute: false),
            'submittedUrl' => URL::temporarySignedRoute('cards.kyc.hosted.submitted', now()->addMinutes(60), ['cardholder' => $holder->id], absolute: false),
        ]);
    }

    /**
     * The SumSub SDK reported a submission. Only a hint for the app until
     * Lepo's webhook decides; it never changes the cardholder's status.
     */
    public function submitted(string $cardholder): Response
    {
        $holder = $this->cardholder($cardholder);

        if (in_array($holder->kyc_status, self::OPEN, true)) {
            $data = is_array($holder->verification_data) ? $holder->verification_data : [];
            $data['kyc_submitted_at'] = now()->toIso8601String();
            $holder->forceFill(['verification_data' => $data])->save();
        }

        return response()->noContent();
    }

    /**
     * A new token when the SDK reports the current one expired.
     */
    public function token(string $cardholder): JsonResponse
    {
        $holder = $this->cardholder($cardholder);
        if (! in_array($holder->kyc_status, self::OPEN, true)) {
            return response()->json(['error' => 'submitted'], 409);
        }

        try {
            $token = $this->onboarding->webKycToken($holder);
        } catch (CardProviderException) {
            return response()->json(['error' => 'unavailable'], 502);
        }

        if ($token === null) {
            return response()->json(['error' => 'submitted'], 409);
        }

        return response()->json(['token' => $token])->header('Cache-Control', 'no-store');
    }

    private function cardholder(string $id): Cardholder
    {
        $holder = Cardholder::query()->whereKey($id)->where('provider', 'lepo')->first();
        abort_if($holder === null, 404);

        return $holder;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function page(string $state, int $status = 200, array $data = []): Response
    {
        return response()
            ->view('cards.kyc-hosted', [
                'state'     => $state,
                'brand'     => (string) config('brand.name', 'Zelta'),
                'returnUrl' => (string) config('cardissuance.providers.kyc_return_url', ''),
                ...$data,
            ], $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
