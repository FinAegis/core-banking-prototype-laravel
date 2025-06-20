<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Domain\Governance\Enums\PollStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserVotingPollResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="User Voting",
 *     description="User-friendly voting interface for GCU governance"
 * )
 */
class UserVotingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/voting/polls",
     *     summary="Get polls available for voting",
     *     description="Get all active polls with user's voting context",
     *     tags={"User Voting"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of voting polls",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UserVotingPoll"))
     *         )
     *     )
     * )
     */
    public function getActivePolls(): JsonResponse
    {
        $polls = Poll::where('status', PollStatus::ACTIVE)
            ->where('end_date', '>', now())
            ->orderBy('end_date', 'asc')
            ->get();

        return response()->json([
            'data' => UserVotingPollResource::collection($polls),
            'meta' => [
                'basket_name' => config('baskets.primary_name', 'Global Currency Unit'),
                'basket_code' => config('baskets.primary_code', 'GCU'),
                'basket_symbol' => config('baskets.primary_symbol', 'Ǥ'),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/voting/polls/upcoming",
     *     summary="Get upcoming polls",
     *     description="Get polls that will become active soon",
     *     tags={"User Voting"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of upcoming polls"
     *     )
     * )
     */
    public function getUpcomingPolls(): JsonResponse
    {
        $polls = Poll::where('status', PollStatus::DRAFT)
            ->where('start_date', '>', now())
            ->where('start_date', '<', now()->addDays(30))
            ->orderBy('start_date', 'asc')
            ->get();

        return response()->json([
            'data' => UserVotingPollResource::collection($polls),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/voting/polls/history",
     *     summary="Get user's voting history",
     *     description="Get all polls the user has participated in",
     *     tags={"User Voting"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User's voting history"
     *     )
     * )
     */
    public function getVotingHistory(): JsonResponse
    {
        $user = Auth::user();
        
        $votedPollIds = Vote::where('user_uuid', $user->uuid)
            ->pluck('poll_id')
            ->unique();
            
        $polls = Poll::whereIn('id', $votedPollIds)
            ->orderBy('end_date', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => UserVotingPollResource::collection($polls),
            'meta' => [
                'total_votes' => $votedPollIds->count(),
                'member_since' => $user->created_at->format('Y-m-d'),
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/voting/polls/{uuid}/vote",
     *     summary="Submit vote for GCU basket composition",
     *     description="Submit weighted allocation vote for basket composition",
     *     tags={"User Voting"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"allocations"},
     *             @OA\Property(
     *                 property="allocations",
     *                 type="object",
     *                 description="Currency allocations (must sum to 100)",
     *                 example={"USD": 40, "EUR": 30, "GBP": 15, "CHF": 10, "JPY": 3, "XAU": 2}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Vote submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="vote_id", type="string"),
     *             @OA\Property(property="voting_power_used", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid vote data"),
     *     @OA\Response(response=403, description="Cannot vote on this poll"),
     *     @OA\Response(response=404, description="Poll not found")
     * )
     */
    public function submitBasketVote(Request $request, string $uuid): JsonResponse
    {
        $poll = Poll::where('uuid', $uuid)->firstOrFail();
        
        // Verify it's a basket voting poll
        if ($poll->metadata['template'] !== 'monthly_basket') {
            return response()->json(['error' => 'This endpoint is for basket voting only'], 400);
        }
        
        // Validate allocations
        $validated = $request->validate([
            'allocations' => 'required|array',
            'allocations.*' => 'required|numeric|min:0|max:100',
        ]);
        
        // Verify allocations sum to 100
        $total = array_sum($validated['allocations']);
        if (abs($total - 100) > 0.01) {
            return response()->json([
                'error' => 'Allocations must sum to 100%',
                'current_sum' => $total
            ], 422);
        }
        
        // Get user's voting power
        $user = Auth::user();
        $strategy = app($poll->voting_power_strategy);
        $votingPower = $strategy->calculatePower($user, $poll);
        
        if ($votingPower <= 0) {
            return response()->json(['error' => 'You have no voting power for this poll'], 403);
        }
        
        // Check if already voted
        if ($poll->votes()->where('user_uuid', $user->uuid)->exists()) {
            return response()->json(['error' => 'You have already voted in this poll'], 403);
        }
        
        // Create vote
        $vote = Vote::create([
            'poll_id' => $poll->id,
            'user_uuid' => $user->uuid,
            'selected_options' => ['allocations' => $validated['allocations']],
            'voting_power' => $votingPower,
            'metadata' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'voted_via' => 'user_voting_api',
            ],
        ]);
        
        return response()->json([
            'message' => 'Your vote has been recorded successfully',
            'vote_id' => $vote->uuid,
            'voting_power_used' => $votingPower,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/voting/dashboard",
     *     summary="Get voting dashboard data",
     *     description="Get comprehensive voting dashboard information",
     *     tags={"User Voting"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data"
     *     )
     * )
     */
    public function getDashboard(): JsonResponse
    {
        $user = Auth::user();
        
        // Get active polls
        $activePolls = Poll::where('status', PollStatus::ACTIVE)
            ->where('end_date', '>', now())
            ->count();
            
        // Get user's participation
        $userVotes = Vote::where('user_uuid', $user->uuid)->count();
        
        // Get user's total voting power (GCU holdings)
        $gcuBalance = $user->accounts()
            ->join('account_balances', 'accounts.uuid', '=', 'account_balances.account_uuid')
            ->where('account_balances.asset_code', config('baskets.primary_code', 'GCU'))
            ->sum('account_balances.balance');
            
        // Get next poll
        $nextPoll = Poll::where('status', PollStatus::DRAFT)
            ->where('start_date', '>', now())
            ->orderBy('start_date', 'asc')
            ->first();
            
        return response()->json([
            'data' => [
                'stats' => [
                    'active_polls' => $activePolls,
                    'votes_cast' => $userVotes,
                    'gcu_balance' => intval($gcuBalance),
                    'voting_power' => intval($gcuBalance), // 1 GCU = 1 vote
                ],
                'next_poll' => $nextPoll ? [
                    'title' => $nextPoll->title,
                    'starts_in' => now()->diffForHumans($nextPoll->start_date),
                    'start_date' => $nextPoll->start_date->toISOString(),
                ] : null,
                'basket_info' => [
                    'name' => config('baskets.primary_name', 'Global Currency Unit'),
                    'code' => config('baskets.primary_code', 'GCU'),
                    'symbol' => config('baskets.primary_symbol', 'Ǥ'),
                ],
            ]
        ]);
    }
}