<?php

/**
 * WaitlistDepositController — Plan B Slice 5 endpoints.
 *
 *   POST /api/v1/cards/waitlist/deposit         — start (idempotency.required)
 *   POST /api/v1/cards/waitlist/deposit/cancel  — cancel (idempotency.required)
 *   GET  /api/v1/cards/waitlist/entry           — holistic waitlist state (auth)
 *
 * @see docs/superpowers/specs/2026-05-10-slice-5-card-waitlist-deposit-design.md §5
 */

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Controllers;

use App\Domain\CardIssuance\Http\Requests\StartDepositRequest;
use App\Domain\CardIssuance\Services\WaitlistDepositService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WaitlistDepositController extends Controller
{
    public function __construct(
        private readonly WaitlistDepositService $service,
    ) {
    }

    public function start(StartDepositRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array{quoteId: string, withdrawalConsent?: array<string, mixed>|null, successUrl?: string|null, cancelUrl?: string|null} $input */
        $input = [
            'quoteId'           => (string) $validated['quoteId'],
            'withdrawalConsent' => isset($validated['withdrawalConsent']) && is_array($validated['withdrawalConsent'])
                ? $validated['withdrawalConsent']
                : null,
            'successUrl' => isset($validated['successUrl']) && is_string($validated['successUrl'])
                ? $validated['successUrl']
                : null,
            'cancelUrl' => isset($validated['cancelUrl']) && is_string($validated['cancelUrl'])
                ? $validated['cancelUrl']
                : null,
        ];

        return $this->service->startDeposit($user, $input, $request->ip());
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        return $this->service->cancelDeposit($user);
    }

    public function entry(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        return response()->json($this->service->entryFor($user));
    }
}
