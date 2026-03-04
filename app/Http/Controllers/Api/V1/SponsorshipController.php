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
                    new OA\Property(property: 'free_until', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'total_sponsored', type: 'integer', example: 2),
                ]),
            ]
        )
    )]
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $eligible = $this->sponsorshipService->isEligible($user);
        $remaining = $this->sponsorshipService->getRemainingFreeTx($user);

        return response()->json([
            'data' => [
                'eligible'          => $eligible,
                'remaining_free_tx' => $remaining,
                'free_until'        => $user->free_tx_until?->toIso8601String(),
                'total_sponsored'   => $user->sponsored_tx_used,
            ],
        ]);
    }
}
