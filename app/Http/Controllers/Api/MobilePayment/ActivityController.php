<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MobilePayment;

use App\Domain\MobilePayment\Services\ActivityFeedService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(
        private readonly ActivityFeedService $activityFeedService,
    ) {
    }

    /**
     * Get activity feed with cursor-based pagination.
     *
     * GET /v1/activity
     *
     * @OA\Get(
     *     path="/api/v1/activity",
     *     operationId="mobilePaymentActivityFeed",
     *     summary="Get activity feed with cursor-based pagination",
     *     description="Returns a paginated activity feed for the authenticated user. Supports cursor-based pagination and filtering by income/expenses.",
     *     tags={"Mobile Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="cursor",
     *         in="query",
     *         required=false,
     *         description="Cursor for pagination (opaque string from previous response)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of items per page (1-50, default 20)",
     *         @OA\Schema(type="integer", minimum=1, maximum=50, default=20)
     *     ),
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         required=false,
     *         description="Filter activity type",
     *         @OA\Schema(type="string", enum={"all", "income", "expenses"}, default="all")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity feed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="items", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="type", type="string", example="payment"),
     *                     @OA\Property(property="amount", type="number", example=25.50),
     *                     @OA\Property(property="asset", type="string", example="USDC"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )),
     *                 @OA\Property(property="next_cursor", type="string", nullable=true, example="eyJpZCI6MTAwfQ=="),
     *                 @OA\Property(property="has_more", type="boolean", example=true)
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
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
     *                 @OA\Property(property="message", type="string", example="The given data was invalid.")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => ['sometimes', 'string'],
            'limit'  => ['sometimes', 'integer', 'min:1', 'max:50'],
            'filter' => ['sometimes', 'string', 'in:all,income,expenses'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $feed = $this->activityFeedService->getFeed(
            userId: $user->id,
            cursor: $request->input('cursor'),
            limit: (int) $request->input('limit', 20),
            filter: $request->input('filter', 'all'),
        );

        return response()->json([
            'success' => true,
            'data'    => $feed,
        ]);
    }
}
