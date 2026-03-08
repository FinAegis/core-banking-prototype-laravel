<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Governance\Models\Vote;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Governance - Votes',
    description: 'Vote management and history operations'
)]
class VoteController extends Controller
{
        #[OA\Get(
            path: '/api/votes',
            summary: 'Get user\'s voting history',
            description: 'Retrieve the authenticated user\'s voting history',
            tags: ['Governance - Votes'],
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'poll_id', in: 'query', description: 'Filter by specific poll ID', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Number of votes per page', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'User\'s voting history',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Vote')),
        new OA\Property(property: 'meta', type: 'object'),
        new OA\Property(property: 'links', type: 'object'),
        ])
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'poll_id'  => ['sometimes', 'integer', 'exists:polls,id'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]
        );

        $query = Vote::byUser(Auth::user()->uuid)
            ->with(['poll', 'user'])
            ->orderByDesc('voted_at');

        if (isset($validated['poll_id'])) {
            $query->where('poll_id', $validated['poll_id']);
        }

        $votes = $query->paginate($validated['per_page'] ?? 15);

        return response()->json($votes);
    }

        #[OA\Get(
            path: '/api/votes/{id}',
            summary: 'Get vote details',
            description: 'Retrieve detailed information about a specific vote',
            tags: ['Governance - Votes'],
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Vote details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/Vote'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Vote not found'
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied'
    )]
    public function show(string $id): JsonResponse
    {
        $vote = Vote::with(['poll', 'user'])->find($id);

        if (! $vote) {
            return response()->json(
                [
                    'message' => 'Vote not found',
                ],
                404
            );
        }

        // Users can only view their own votes
        if ($vote->user_uuid !== Auth::user()->uuid) {
            return response()->json(
                [
                    'message' => 'Access denied',
                ],
                403
            );
        }

        return response()->json(
            [
                'data' => $vote,
            ]
        );
    }

        #[OA\Post(
            path: '/api/votes/{id}/verify',
            summary: 'Verify vote integrity',
            description: 'Verify the cryptographic signature of a vote',
            tags: ['Governance - Votes'],
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Vote verification result',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'verified', type: 'boolean'),
        new OA\Property(property: 'message', type: 'string'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Vote not found'
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied'
    )]
    public function verify(string $id): JsonResponse
    {
        $vote = Vote::find($id);

        if (! $vote) {
            return response()->json(
                [
                    'message' => 'Vote not found',
                ],
                404
            );
        }

        // Users can only verify their own votes
        if ($vote->user_uuid !== Auth::user()->uuid) {
            return response()->json(
                [
                    'message' => 'Access denied',
                ],
                403
            );
        }

        $isValid = $vote->verifySignature();

        return response()->json(
            [
                'verified' => $isValid,
                'message'  => $isValid
                    ? 'Vote signature is valid and vote has not been tampered with'
                    : 'Vote signature is invalid or vote has been tampered with',
            ]
        );
    }

        #[OA\Get(
            path: '/api/votes/stats',
            summary: 'Get user\'s voting statistics',
            description: 'Retrieve statistics about the user\'s voting activity',
            tags: ['Governance - Votes'],
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'User\'s voting statistics',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'total_votes', type: 'integer'),
        new OA\Property(property: 'total_voting_power', type: 'integer'),
        new OA\Property(property: 'recent_votes', type: 'integer', description: 'Votes in last 30 days'),
        new OA\Property(property: 'avg_voting_power', type: 'number', format: 'float'),
        new OA\Property(property: 'participation_rate', type: 'number', format: 'float'),
        ])
    )]
    public function stats(): JsonResponse
    {
        $userUuid = Auth::user()->uuid;

        $totalVotes = Vote::byUser($userUuid)->count();
        $totalVotingPower = Vote::byUser($userUuid)->sum('voting_power');
        $recentVotes = Vote::byUser($userUuid)->recentVotes(24 * 30)->count(); // Last 30 days

        $avgVotingPower = $totalVotes > 0 ? $totalVotingPower / $totalVotes : 0;

        // Calculate participation rate (votes cast / total polls available to user)
        // This is a simplified calculation - in a real system you'd need to check
        // which polls the user was eligible to vote in
        $totalPolls = \App\Domain\Governance\Models\Poll::count();
        $participationRate = $totalPolls > 0 ? ($totalVotes / $totalPolls) * 100 : 0;

        return response()->json(
            [
                'total_votes'        => $totalVotes,
                'total_voting_power' => $totalVotingPower,
                'recent_votes'       => $recentVotes,
                'avg_voting_power'   => round($avgVotingPower, 2),
                'participation_rate' => round($participationRate, 2),
            ]
        );
    }
}
