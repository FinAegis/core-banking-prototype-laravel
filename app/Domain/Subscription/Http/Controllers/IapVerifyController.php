<?php

/**
 * IapVerifyController — POST /api/v1/subscription/iap/verify.
 *
 * Thin entry point: extracts the authenticated user, delegates to
 * IapSubscriptionService::verify(), and renders the result via the same
 * ErrorResponse / success-envelope convention used by SubscriptionController.
 *
 * Idempotency is handled by the `idempotency.required` middleware applied to
 * the route — no body field needed.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.1
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Http\Controllers;

use App\Domain\Subscription\Http\Requests\IapVerifyRequest;
use App\Domain\Subscription\Iap\IapSubscriptionService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;

final class IapVerifyController extends Controller
{
    public function __construct(private readonly IapSubscriptionService $service)
    {
    }

    public function verify(IapVerifyRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array{
         *     platform: string,
         *     receipt: string,
         *     originalTransactionId?: string|null,
         *     productId: string,
         *     appVersion?: string,
         *     currency?: string,
         *     withdrawalConsent?: array{
         *         given: bool, shownAt: string, acceptedAt: string,
         *         consentText: string, version: int
         *     }|null
         * } $input
         */
        $input = $validated;

        // Default currency = EUR if absent (mobile always sends "EUR" per spec).
        if (! isset($input['currency']) || $input['currency'] === '') {
            $input['currency'] = 'EUR';
        }

        $result = $this->service->verify(
            user: $user,
            input: $input,
            remoteIp: (string) ($request->ip() ?? '0.0.0.0'),
            userAgent: $request->userAgent(),
        );

        if (isset($result['code'])) {
            $context = $result['context'] ?? [];

            return ErrorResponse::make($result['code'], null, $context);
        }

        /** @var array<string, mixed> $body */
        $body = $result['success'] ?? [];

        return response()->json($body, 200);
    }
}
