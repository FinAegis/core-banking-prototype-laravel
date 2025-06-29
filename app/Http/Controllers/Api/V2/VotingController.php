<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\GcuVotingProposal;
use App\Models\GcuVote;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VotingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v2/gcu/voting/proposals",
     *     summary="Get voting proposals",
     *     tags={"GCU Voting"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (active, upcoming, past)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "upcoming", "past"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of voting proposals",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="voting_starts_at", type="string", format="date-time"),
     *                     @OA\Property(property="voting_ends_at", type="string", format="date-time"),
     *                     @OA\Property(property="participation_rate", type="number"),
     *                     @OA\Property(property="approval_rate", type="number")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function proposals(Request $request): JsonResponse
    {
        $query = GcuVotingProposal::query();
        
        if ($request->has('status')) {
            switch ($request->status) {
                case 'active':
                    $query->active();
                    break;
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'past':
                    $query->past();
                    break;
            }
        }
        
        $proposals = $query->get()->map(function ($proposal) {
            return [
                'id' => $proposal->id,
                'title' => $proposal->title,
                'description' => $proposal->description,
                'status' => $proposal->status,
                'proposed_composition' => $proposal->proposed_composition,
                'current_composition' => $proposal->current_composition,
                'voting_starts_at' => $proposal->voting_starts_at->toIso8601String(),
                'voting_ends_at' => $proposal->voting_ends_at->toIso8601String(),
                'participation_rate' => round($proposal->participation_rate, 2),
                'approval_rate' => round($proposal->approval_rate, 2),
                'minimum_participation' => $proposal->minimum_participation,
                'minimum_approval' => $proposal->minimum_approval,
                'votes_for' => $proposal->votes_for,
                'votes_against' => $proposal->votes_against,
                'total_votes_cast' => $proposal->total_votes_cast,
            ];
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $proposals,
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/v2/gcu/voting/proposals/{id}",
     *     summary="Get proposal details",
     *     tags={"GCU Voting"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Proposal ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Proposal details",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function proposalDetails($id): JsonResponse
    {
        $proposal = GcuVotingProposal::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $proposal->id,
                'title' => $proposal->title,
                'description' => $proposal->description,
                'rationale' => $proposal->rationale,
                'status' => $proposal->status,
                'proposed_composition' => $proposal->proposed_composition,
                'current_composition' => $proposal->current_composition,
                'voting_starts_at' => $proposal->voting_starts_at->toIso8601String(),
                'voting_ends_at' => $proposal->voting_ends_at->toIso8601String(),
                'participation_rate' => round($proposal->participation_rate, 2),
                'approval_rate' => round($proposal->approval_rate, 2),
                'minimum_participation' => $proposal->minimum_participation,
                'minimum_approval' => $proposal->minimum_approval,
                'votes_for' => $proposal->votes_for,
                'votes_against' => $proposal->votes_against,
                'total_votes_cast' => $proposal->total_votes_cast,
                'total_gcu_supply' => $proposal->total_gcu_supply,
                'is_voting_active' => $proposal->isVotingActive(),
                'time_remaining' => $proposal->time_remaining,
            ],
        ]);
    }
    
    /**
     * @OA\Post(
     *     path="/api/v2/gcu/voting/proposals/{id}/vote",
     *     summary="Cast a vote",
     *     tags={"GCU Voting"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Proposal ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="vote", type="string", enum={"for", "against", "abstain"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vote cast successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="vote", type="string"),
     *                 @OA\Property(property="voting_power", type="number")
     *             )
     *         )
     *     )
     * )
     */
    public function vote(Request $request, $id): JsonResponse
    {
        $proposal = GcuVotingProposal::findOrFail($id);
        
        if (!$proposal->isVotingActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Voting is not active for this proposal',
            ], 400);
        }
        
        $request->validate([
            'vote' => 'required|in:for,against,abstain',
        ]);
        
        $user = $request->user();
        
        // Get user's GCU balance
        $gcuAccount = $user->accounts()
            ->where('currency', 'GCU')
            ->where('type', 'personal')
            ->first();
            
        if (!$gcuAccount || $gcuAccount->balance <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'You need GCU holdings to vote',
            ], 400);
        }
        
        DB::transaction(function () use ($request, $proposal, $user, $gcuAccount) {
            // Create or update vote
            $vote = GcuVote::updateOrCreate(
                [
                    'proposal_id' => $proposal->id,
                    'user_uuid' => $user->uuid,
                ],
                [
                    'vote' => $request->vote,
                    'voting_power' => $gcuAccount->balance,
                ]
            );
            
            // Generate and save signature
            $vote->signature = $vote->generateSignature();
            $vote->save();
            
            // Update proposal vote counts
            $this->updateProposalVoteCounts($proposal);
        });
        
        return response()->json([
            'status' => 'success',
            'message' => 'Your vote has been recorded',
            'data' => [
                'vote' => $request->vote,
                'voting_power' => $gcuAccount->balance,
            ],
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/v2/gcu/voting/my-votes",
     *     summary="Get user's voting history",
     *     tags={"GCU Voting"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User's voting history",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="proposal_id", type="integer"),
     *                     @OA\Property(property="proposal_title", type="string"),
     *                     @OA\Property(property="vote", type="string"),
     *                     @OA\Property(property="voting_power", type="number"),
     *                     @OA\Property(property="voted_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function myVotes(Request $request): JsonResponse
    {
        $votes = GcuVote::where('user_uuid', $request->user()->uuid)
            ->with('proposal')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($vote) {
                return [
                    'proposal_id' => $vote->proposal_id,
                    'proposal_title' => $vote->proposal->title,
                    'vote' => $vote->vote,
                    'voting_power' => $vote->voting_power,
                    'voted_at' => $vote->created_at->toIso8601String(),
                ];
            });
            
        return response()->json([
            'status' => 'success',
            'data' => $votes,
        ]);
    }
    
    protected function updateProposalVoteCounts(GcuVotingProposal $proposal)
    {
        $votes = $proposal->votes()->get();
        
        $votesFor = $votes->where('vote', 'for')->sum('voting_power');
        $votesAgainst = $votes->where('vote', 'against')->sum('voting_power');
        $totalVotes = $votes->sum('voting_power');
        
        $proposal->update([
            'votes_for' => $votesFor,
            'votes_against' => $votesAgainst,
            'total_votes_cast' => $totalVotes,
        ]);
    }
}