<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Relayer\Services\SponsorshipService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SponsorshipController extends Controller
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/sponsorship/status',
        operationId: 'v1SponsorshipStatus',
        tags: ['Sponsorship'],
        summary: 'Get user gas sponsorship status',
        security: [['sanctum' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Sponsorship status',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'eligible', type: 'boolean', example: true),
                    new OA\Property(property: 'remaining_free_tx', type: 'integer', example: 3),
                    new OA\Property(property: 'total_limit', type: 'integer', example: 5),
                    new OA\Property(property: 'total_sponsored', type: 'integer', example: 2),
                    new OA\Property(property: 'progress_pct', type: 'number', example: 40),
                    new OA\Property(property: 'free_until', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'expires_in_days', type: 'integer', nullable: true, example: 25),
                ]),
            ]
        )
    )]
    public function status(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $eligible = $this->sponsorshipService->isEligible($user);
        $remaining = $this->sponsorshipService->getRemainingFreeTx($user);

        $limit = $user->sponsored_tx_limit;
        $expiresInDays = $user->free_tx_until?->isFuture()
            ? (int) now()->diffInDays($user->free_tx_until, false)
            : null;

        return response()->json([
            'data' => [
                'eligible'          => $eligible,
                'remaining_free_tx' => $remaining,
                'total_limit'       => $limit,
                'total_sponsored'   => $user->sponsored_tx_used,
                'progress_pct'      => $limit > 0 ? round(($user->sponsored_tx_used / $limit) * 100) : 0,
                'free_until'        => $user->free_tx_until?->toIso8601String(),
                'expires_in_days'   => $expiresInDays,
            ],
        ]);
    }
}
