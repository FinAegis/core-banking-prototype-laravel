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
use OpenApi\Attributes as OA;

final class IapVerifyController extends Controller
{
    public function __construct(private readonly IapSubscriptionService $service)
    {
    }

    #[OA\Post(
        path: '/api/v1/subscription/iap/verify',
        operationId: 'v1SubscriptionIapVerify',
        summary: 'Verify an Apple App Store / Google Play receipt and activate the IAP subscription',
        description: 'Server-side validation of a store receipt (Apple JWS StoreKit 2 transaction or Google Play purchase token). Creates or updates the iap_subscriptions row and returns the unified subscription projection. Idempotent via the required Idempotency-Key header (handled by the idempotency.required middleware — not a body field).',
        tags: ['Subscription'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['platform', 'receipt', 'productId'], properties: [
        new OA\Property(property: 'platform', type: 'string', enum: ['apple_iap', 'google_play'], example: 'apple_iap'),
        new OA\Property(property: 'receipt', type: 'string', example: 'eyJhbGciOiJFUzI1NiIs...', description: 'Apple: signed JWS transaction (StoreKit 2). Google: purchase token.'),
        new OA\Property(property: 'originalTransactionId', type: 'string', nullable: true, example: '2000000123456789', description: 'Apple-only StoreKit 2 stable transaction id.'),
        new OA\Property(property: 'productId', type: 'string', enum: ['zelta_pro_monthly', 'zelta_pro_annual'], example: 'zelta_pro_monthly'),
        new OA\Property(property: 'appVersion', type: 'string', nullable: true, example: '1.3.0'),
        new OA\Property(property: 'currency', type: 'string', nullable: true, example: 'EUR', description: 'ISO 4217, 3 chars. Defaults to EUR; non-EUR is rejected as ERR_CUR_001 (EUR-only in v1.3.x).'),
        new OA\Property(property: 'withdrawalConsent', type: 'object', nullable: true, description: 'Optional in v1.3.0, required from v1.3.1 (§8.6).', properties: [
        new OA\Property(property: 'given', type: 'boolean'),
        new OA\Property(property: 'shownAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'acceptedAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'consentText', type: 'string'),
        new OA\Property(property: 'version', type: 'integer', minimum: 1),
        ]),
        ]))
    )]
    #[OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string', maxLength: 255), description: 'Required by the idempotency.required middleware. Same key + same body replays the original response; same key + different body → 409 IDEMPOTENCY_CONFLICT.')]
    #[OA\Response(
        response: 200,
        description: 'Receipt verified — unified subscription projection (also returned for an idempotent duplicate of the same store subscription)',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'tier', type: 'string', example: 'pro'),
        new OA\Property(property: 'status', type: 'string', nullable: true, example: 'active'),
        new OA\Property(property: 'source', type: 'string', nullable: true, example: 'apple'),
        new OA\Property(property: 'plan', type: 'string', nullable: true, example: 'monthly_pro'),
        new OA\Property(property: 'currentPeriodEnd', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'trialEndsAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'cancelledAtPeriodEnd', type: 'boolean', example: false),
        new OA\Property(property: 'pausedUntil', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'reactivated', type: 'boolean', example: false),
        ])
    )]
    #[OA\Response(
        response: 409,
        description: 'ERR_SUB_002 — IAP subscription conflict. Mobile branches on error.conflict.kind; existingSubscription is always present.',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ERR_SUB_002'),
        new OA\Property(property: 'message', type: 'string', example: 'Already subscribed via different store.'),
        new OA\Property(property: 'conflict', type: 'object', properties: [
        new OA\Property(property: 'kind', type: 'string', enum: ['two_stores_active', 'different_zelta_user', 'family_sharing_unsupported', 'stale_receipt']),
        new OA\Property(property: 'attemptedSource', type: 'string', enum: ['apple_iap', 'google_play']),
        new OA\Property(property: 'existingSubscription', type: 'object', properties: [
        new OA\Property(property: 'source', type: 'string', example: 'google_play'),
        new OA\Property(property: 'currentPeriodEndsAt', type: 'string', format: 'date-time', nullable: true),
        ]),
        ]),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'ERR_SUB_001 — invalid receipt (store rejected the receipt / unknown product id); also returned for request validation failures',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ERR_SUB_001'),
        new OA\Property(property: 'message', type: 'string', example: 'Invalid receipt.'),
        ]),
        ])
    )]
    #[OA\Response(response: 429, description: 'Rate limit exceeded')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
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
