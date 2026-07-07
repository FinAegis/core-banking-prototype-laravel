<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Services\FinCardAccountService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * DEV/QA-ONLY FinCard test affordances. The route is registered in
 * non-production ONLY (see Routes/api.php) and every action additionally
 * requires FINCARD_DEV_SIMULATE_ENABLED — a money-crediting surface must never
 * be reachable in production.
 *
 * @see docs/FINCARD_MOBILE_INTEGRATION.md §4.2
 */
class FinCardDevController extends Controller
{
    public function __construct(
        private readonly FinCardAccountService $accounts,
    ) {
    }

    /**
     * Simulate an inbound crypto deposit crediting the caller's account, firing
     * the same `fincard.account.funded` broadcast a real webhook would — so the
     * funding UI can be exercised without a chain deposit or FinCard creds.
     */
    public function simulateDeposit(Request $request): JsonResponse
    {
        $this->assertEnabled();

        /** @var User $user */
        $user = $request->user();

        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'coin_key'     => ['nullable', 'string', 'max:32'],
        ]);

        $account = $this->accounts->simulateDeposit(
            $user,
            (int) $validated['amount_cents'],
            isset($validated['coin_key']) ? (string) $validated['coin_key'] : null,
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'provisioned'   => true,
                'account_id'    => $account->fincard_account_id,
                'currency'      => $account->currency,
                'balance_cents' => $account->balance_cents,
                'status'        => $account->status,
            ],
        ]);
    }

    /**
     * Hard gate: never in production, and only when explicitly enabled.
     */
    private function assertEnabled(): void
    {
        $enabled = ! app()->environment('production')
            && (bool) config('cardissuance.issuers.fincard.dev_simulate_enabled', false);

        if (! $enabled) {
            throw new NotFoundHttpException();
        }
    }
}
