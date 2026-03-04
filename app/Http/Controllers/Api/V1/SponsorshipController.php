<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Relayer\Services\SponsorshipService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SponsorshipStatusResource;
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
        security: [['sanctum' => []]]
    )]
    #[OA\Response(response: 200, description: 'Sponsorship status')]
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $eligible = $this->sponsorshipService->isEligible($user);
        $remaining = $this->sponsorshipService->getRemainingFreeTx($user);

        return response()->json([
            'data' => new SponsorshipStatusResource($user, $eligible, $remaining),
        ]);
    }
}
