<?php

/**
 * SubscriptionController — Plan B Slice 1 endpoints.
 *
 * GET    /api/v1/subscription/me           — null-safe; returns free-tier shape on no sub
 * POST   /api/v1/subscription/checkout     — Stripe SetupIntent → Subscription
 * POST   /api/v1/subscription/change-plan  — Stripe-only Cashier swap
 * POST   /api/v1/subscription/cancel       — cancel-at-period-end
 * POST   /api/v1/subscription/reactivate   — clear cancel-at-period-end
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Http\Controllers;

use App\Domain\Subscription\Http\Requests\ChangePlanRequest;
use App\Domain\Subscription\Http\Requests\CheckoutRequest;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $service)
    {
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        return response()->json($this->service->read($user));
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array{
         *     plan: string,
         *     withdrawalConsent: array{
         *         given: bool, shownAt: string, acceptedAt: string,
         *         consentText: string, version: int
         *     },
         *     successUrl?: string|null,
         *     cancelUrl?: string|null
         * } $input
         */
        $input = $validated;

        $result = $this->service->startCheckout(
            $user,
            $input,
            (string) ($request->ip() ?? '0.0.0.0'),
            $request->userAgent(),
        );

        return $this->renderResult($result, successStatus: 200);
    }

    public function changePlan(ChangePlanRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $result = $this->service->changePlan($user, (string) $validated['plan']);

        return $this->renderResult($result);
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        $result = $this->service->cancel($user);

        return $this->renderResult($result);
    }

    public function reactivate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        $result = $this->service->reactivate($user);

        return $this->renderResult($result);
    }

    /**
     * @param array{code?: string, context?: array<string, mixed>, success?: array<string, mixed>} $result
     */
    private function renderResult(array $result, int $successStatus = 200): JsonResponse
    {
        if (isset($result['code'])) {
            $context = $result['context'] ?? [];

            return ErrorResponse::make($result['code'], null, $context);
        }

        $body = $result['success'] ?? [];

        return response()->json($body, $successStatus);
    }
}
