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
    #[OA\Response(
        response: 200,
        description: 'Referral code',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'ABC12345'),
                    new OA\Property(property: 'share_link', type: 'string', example: 'https://finaegis.com/invite/ABC12345'),
                    new OA\Property(property: 'share_text', type: 'string'),
                    new OA\Property(property: 'uses_count', type: 'integer', example: 3),
                    new OA\Property(property: 'max_uses', type: 'integer', example: 50),
                    new OA\Property(property: 'active', type: 'boolean', example: true),
                    new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                ]),
            ]
        )
    )]
    public function myCode(Request $request): JsonResponse
    {
        $referralCode = $this->referralService->generateCode($request->user());

        $code = $referralCode->code;

        return response()->json([
            'data' => [
                'code'       => $code,
                'share_link' => url("/invite/{$code}"),
                'share_text' => "Join FinAegis with my referral code {$code} and get free transactions!",
                'uses_count' => $referralCode->uses_count,
                'max_uses'   => $referralCode->max_uses,
                'active'     => $referralCode->active,
                'expires_at' => $referralCode->expires_at?->toIso8601String(),
                'created_at' => $referralCode->created_at->toIso8601String(),
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
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 50)),
        ]
    )]
    #[OA\Response(response: 200, description: 'Referral list')]
    public function index(Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = min(max(1, (int) $request->input('limit', 20)), 50);

        $referrals = $this->referralService->getUserReferrals($request->user(), $limit, $offset);

        return response()->json([
            'data' => ReferralResource::collection($referrals),
            'meta' => [
                'offset' => $offset,
                'limit'  => $limit,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/referrals/stats',
        operationId: 'v1ReferralStats',
        tags: ['Referrals'],
        summary: 'Get referral stats',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Referral stats',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'total_referred', type: 'integer', example: 5),
                    new OA\Property(property: 'completed', type: 'integer', example: 3),
                    new OA\Property(property: 'pending', type: 'integer', example: 2),
                    new OA\Property(property: 'rewards_earned', type: 'integer', example: 15),
                    new OA\Property(property: 'reward_per_referral', type: 'integer', example: 5),
                ]),
            ]
        )
    )]
    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->referralService->getUserStats($request->user()),
        ]);
    }
}
