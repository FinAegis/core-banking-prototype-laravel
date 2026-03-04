<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Referral\Services\ReferralService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ReferralResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use RuntimeException;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/referrals/my-code',
        operationId: 'v1GetReferralCode',
        tags: ['Referrals'],
        summary: 'Get or generate user referral code',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(response: 200, description: 'Referral code')]
    public function myCode(Request $request): JsonResponse
    {
        $referralCode = $this->referralService->generateCode($request->user());

        return response()->json([
            'data' => [
                'code'       => $referralCode->code,
                'uses_count' => $referralCode->uses_count,
                'max_uses'   => $referralCode->max_uses,
                'active'     => $referralCode->active,
                'expires_at' => $referralCode->expires_at?->toIso8601String(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/referrals/apply',
        operationId: 'v1ApplyReferralCode',
        tags: ['Referrals'],
        summary: 'Apply a referral code',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'ABC12345'),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Referral applied')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:8',
        ]);

        try {
            $referral = $this->referralService->applyCode(
                $request->user(),
                strtoupper($request->input('code'))
            );

            return response()->json([
                'data'    => new ReferralResource($referral->load('referee')),
                'message' => 'Referral code applied successfully',
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'REFERRAL_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    #[OA\Get(
        path: '/api/v1/referrals',
        operationId: 'v1ListReferrals',
        tags: ['Referrals'],
        summary: 'List user referrals',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(response: 200, description: 'Referral list')]
    public function index(Request $request): JsonResponse
    {
        $referrals = $this->referralService->getUserReferrals($request->user());

        return response()->json([
            'data' => ReferralResource::collection($referrals),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/referrals/stats',
        operationId: 'v1ReferralStats',
        tags: ['Referrals'],
        summary: 'Get referral stats',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(response: 200, description: 'Referral stats')]
    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->referralService->getUserStats($request->user()),
        ]);
    }
}
