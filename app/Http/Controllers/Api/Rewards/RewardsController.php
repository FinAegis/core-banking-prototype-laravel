<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Rewards;

use App\Domain\Rewards\Services\RewardsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RewardsController extends Controller
{
    public function __construct(
        private readonly RewardsService $rewardsService,
    ) {
    }

    /**
     * Get the user's rewards profile.
     *
     * @OA\Get(
     *     path="/api/v1/rewards/profile",
     *     operationId="getRewardsProfile",
     *     summary="Get rewards profile",
     *     description="Returns the user's XP, level, streak, and points balance.",
     *     tags={"Rewards"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Rewards profile", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="xp", type="integer", example=250),
     *             @OA\Property(property="level", type="integer", example=3),
     *             @OA\Property(property="xp_for_next", type="integer", example=300),
     *             @OA\Property(property="xp_progress", type="number", example=0.83),
     *             @OA\Property(property="current_streak", type="integer", example=5),
     *             @OA\Property(property="longest_streak", type="integer", example=12),
     *             @OA\Property(property="points_balance", type="integer", example=1500),
     *             @OA\Property(property="quests_completed", type="integer", example=8)
     *         )
     *     )),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => $this->rewardsService->getProfileData($user),
        ]);
    }

    /**
     * Get available quests.
     *
     * @OA\Get(
     *     path="/api/v1/rewards/quests",
     *     operationId="getRewardsQuests",
     *     summary="Get available quests",
     *     description="Returns active quests with the user's completion status.",
     *     tags={"Rewards"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Quests list", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="array", @OA\Items(type="object",
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="slug", type="string", example="first-shield"),
     *             @OA\Property(property="title", type="string", example="First Shield"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="xp_reward", type="integer", example=50),
     *             @OA\Property(property="points_reward", type="integer", example=100),
     *             @OA\Property(property="category", type="string", example="onboarding"),
     *             @OA\Property(property="completed", type="boolean", example=false)
     *         ))
     *     )),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function quests(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => $this->rewardsService->getQuests($user),
        ]);
    }

    /**
     * Complete a quest.
     *
     * @OA\Post(
     *     path="/api/v1/rewards/quests/{id}/complete",
     *     operationId="completeRewardsQuest",
     *     summary="Complete a quest",
     *     description="Marks a quest as completed and awards XP/points to the user.",
     *     tags={"Rewards"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Quest completed", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="quest_id", type="string"),
     *             @OA\Property(property="xp_earned", type="integer", example=50),
     *             @OA\Property(property="points_earned", type="integer", example=100),
     *             @OA\Property(property="new_level", type="integer", example=2),
     *             @OA\Property(property="level_up", type="boolean", example=true)
     *         )
     *     )),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Quest already completed or not found")
     * )
     */
    public function completeQuest(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $result = $this->rewardsService->completeQuest($user, $id);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'QUEST_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get shop items.
     *
     * @OA\Get(
     *     path="/api/v1/rewards/shop",
     *     operationId="getRewardsShop",
     *     summary="Get rewards shop items",
     *     description="Returns active shop items available for point redemption.",
     *     tags={"Rewards"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Shop items list", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="array", @OA\Items(type="object",
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="points_cost", type="integer"),
     *             @OA\Property(property="available", type="boolean")
     *         ))
     *     )),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function shop(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->rewardsService->getShopItems(),
        ]);
    }

    /**
     * Redeem a shop item.
     *
     * @OA\Post(
     *     path="/api/v1/rewards/shop/{id}/redeem",
     *     operationId="redeemRewardsShopItem",
     *     summary="Redeem a shop item",
     *     description="Spends points to redeem a shop item.",
     *     tags={"Rewards"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Item redeemed", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="redemption_id", type="string"),
     *             @OA\Property(property="points_spent", type="integer"),
     *             @OA\Property(property="points_balance", type="integer")
     *         )
     *     )),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Insufficient points or item unavailable")
     * )
     */
    public function redeemItem(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $result = $this->rewardsService->redeemItem($user, $id);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'REDEMPTION_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }
}
