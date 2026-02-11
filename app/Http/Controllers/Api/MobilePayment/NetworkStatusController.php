<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MobilePayment;

use App\Domain\MobilePayment\Services\NetworkAvailabilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class NetworkStatusController extends Controller
{
    public function __construct(
        private readonly NetworkAvailabilityService $networkAvailabilityService,
    ) {
    }

    /**
     * Get the status of all supported payment networks.
     *
     * GET /v1/networks/status
     *
     * @OA\Get(
     *     path="/api/v1/networks/status",
     *     operationId="mobilePaymentNetworkStatus",
     *     summary="Get the status of all supported payment networks",
     *     description="Returns the current availability and health status of all supported payment networks (e.g., Solana, Tron). Useful for checking network congestion or downtime before initiating payments.",
     *     tags={"Mobile Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Network statuses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="networks", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="network", type="string", example="SOLANA"),
     *                     @OA\Property(property="status", type="string", enum={"operational", "degraded", "down"}, example="operational"),
     *                     @OA\Property(property="latency_ms", type="integer", example=120),
     *                     @OA\Property(property="block_height", type="integer", example=245000000)
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     )
     * )
     */
    public function __invoke(): JsonResponse
    {
        $statuses = $this->networkAvailabilityService->getNetworkStatuses();

        return response()->json([
            'success' => true,
            'data'    => [
                'networks' => $statuses,
            ],
        ]);
    }
}
