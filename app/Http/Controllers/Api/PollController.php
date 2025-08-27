<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Services\Cache\PollCacheService;
use App\Domain\Governance\Services\GovernanceService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * @OA\Tag(
 *     name="Governance - Polls",
 *     description="Poll management and voting operations"
 * )
 */
class PollController extends Controller
{
    public function __construct(
        private readonly GovernanceService $governanceService,
        private readonly PollCacheService $cacheService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/polls",
     *     summary="List all polls",
     *     description="Retrieve a paginated list of polls with optional filtering",
     *     tags={"Governance - Polls"},
     *
     * @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by poll status",
     *         required=false,
     *
     * @OA\Schema(type="string",      enum={"draft", "active", "completed", "cancelled"})
     *     ),
     *
     * @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by poll type",
     *         required=false,
     *
     * @OA\Schema(type="string",      enum={"single_choice", "multiple_choice", "weighted_choice", "yes_no", "ranked_choice"})
     *     ),
     *
     * @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of polls per page",
     *         required=false,
     *
     * @OA\Schema(type="integer",     minimum=1, maximum=100, default=15)
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="List of polls",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",  type="array", @OA\Items(ref="#/components/schemas/Poll")),
     * @OA\Property(property="meta",  type="object"),
     * @OA\Property(property="links", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'status'   => ['sometimes', Rule::enum(PollStatus::class)],
                'type'     => ['sometimes', Rule::enum(PollType::class)],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]
        );

        $query = Poll::with(['creator', 'votes']);

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $polls = $query->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($polls);
    }

    /**
     * @OA\Get(
     *     path="/api/polls/active",
     *     summary="Get active polls",
     *     description="Retrieve all currently active polls that are available for voting",
     *     tags={"Governance - Polls"},
     *
     * @OA\Response(
     *         response=200,
     *         description="List of active polls",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Poll"))
     *         )
     *     )
     * )
     */
    public function active(): JsonResponse
    {
        $polls = $this->cacheService->getActivePolls();

        return response()->json(
            [
                'data'  => $polls,
                'count' => $polls->count(),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/polls",
     *     summary="Create a new poll",
     *     description="Create a new governance poll",
     *     tags={"Governance - Polls"},
     *     security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"title", "type", "options", "start_date", "end_date"},
     *
     * @OA\Property(property="title",                  type="string", example="Should we add support for Japanese Yen?"),
     * @OA\Property(property="description",            type="string", example="Proposal to add JPY as supported currency"),
     * @OA\Property(property="type",                   type="string", enum={"single_choice", "multiple_choice", "weighted_choice", "yes_no", "ranked_choice"}),
     * @OA\Property(
     *                 property="options",
     *                 type="array",
     *
     * @OA\Items(
     *
     * @OA\Property(property="id",                     type="string"),
     * @OA\Property(property="label",                  type="string"),
     * @OA\Property(property="description",            type="string")
     *                 )
     *             ),
     * @OA\Property(property="start_date",             type="string", format="date-time"),
     * @OA\Property(property="end_date",               type="string", format="date-time"),
     * @OA\Property(property="required_participation", type="integer", example=30),
     * @OA\Property(property="voting_power_strategy",  type="string", enum={"one_user_one_vote", "asset_weighted_vote"}),
     * @OA\Property(property="execution_workflow",     type="string", example="AddAssetWorkflow")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="Poll created successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                   ref="#/components/schemas/Poll"),
     * @OA\Property(property="message",                type="string")
     *         )
     *     ),
     *
     * @OA\Response(response=422,                      description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'title'                  => ['required', 'string', 'max:255'],
                'description'            => ['sometimes', 'string', 'max:2000'],
                'type'                   => ['required', Rule::enum(PollType::class)],
                'options'                => ['required', 'array', 'min:2'],
                'options.*.id'           => ['required', 'string', 'max:50'],
                'options.*.label'        => ['required', 'string', 'max:255'],
                'options.*.description'  => ['sometimes', 'string', 'max:500'],
                'start_date'             => ['required', 'date', 'after:now'],
                'end_date'               => ['required', 'date', 'after:start_date'],
                'required_participation' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'voting_power_strategy'  => ['sometimes', 'string', 'in:one_user_one_vote,asset_weighted_vote'],
                'execution_workflow'     => ['sometimes', 'string', 'max:255'],
                'metadata'               => ['sometimes', 'array'],
            ]
        );

        $validated['created_by'] = Auth::user()->uuid;

        $poll = $this->governanceService->createPoll($validated);

        $this->cacheService->cachePoll($poll);
        $this->cacheService->forgetActivePolls();

        return response()->json(
            [
                'data'    => $poll->load(['creator', 'votes']),
                'message' => 'Poll created successfully',
            ],
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/polls/{uuid}",
     *     summary="Get poll details",
     *     description="Retrieve detailed information about a specific poll",
     *     tags={"Governance - Polls"},
     *
     * @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string",     format="uuid")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Poll details",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data", ref="#/components/schemas/Poll")
     *         )
     *     ),
     *
     * @OA\Response(response=404,    description="Poll not found")
     * )
     */
    public function show(string $uuid): JsonResponse
    {
        $poll = $this->cacheService->getPoll($uuid);

        if (! $poll) {
            return response()->json(
                [
                    'message' => 'Poll not found',
                ],
                404
            );
        }

        return response()->json(
            [
                'data' => $poll,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/polls/{uuid}/activate",
     *     summary="Activate a poll",
     *     description="Activate a draft poll to make it available for voting",
     *     tags={"Governance - Polls"},
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string",        format="uuid")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Poll activated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",    ref="#/components/schemas/Poll"),
     * @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     * @OA\Response(response=400,       description="Poll cannot be activated"),
     * @OA\Response(response=404,       description="Poll not found")
     * )
     */
    public function activate(string $uuid): JsonResponse
    {
        $poll = Poll::where('uuid', $uuid)->first();

        if (! $poll) {
            return response()->json(
                [
                    'message' => 'Poll not found',
                ],
                404
            );
        }

        try {
            $this->governanceService->activatePoll($poll);

            $this->cacheService->invalidatePollCache($poll->uuid);

            return response()->json(
                [
                    'data'    => $poll->fresh(['creator', 'votes']),
                    'message' => 'Poll activated successfully',
                ]
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                400
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/polls/{uuid}/vote",
     *     summary="Cast a vote",
     *     description="Cast a vote in an active poll",
     *     tags={"Governance - Polls"},
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string",                 format="uuid")
     *     ),
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"selected_options"},
     *
     * @OA\Property(property="selected_options", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="Vote cast successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",             ref="#/components/schemas/Vote"),
     * @OA\Property(property="message",          type="string")
     *         )
     *     ),
     *
     * @OA\Response(response=400,                description="Cannot vote in this poll"),
     * @OA\Response(response=404,                description="Poll not found")
     * )
     */
    public function vote(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate(
            [
                'selected_options'   => ['required', 'array', 'min:1'],
                'selected_options.*' => ['required', 'string'],
            ]
        );

        $poll = Poll::where('uuid', $uuid)->first();

        if (! $poll) {
            return response()->json(
                [
                    'message' => 'Poll not found',
                ],
                404
            );
        }

        try {
            $vote = $this->governanceService->castVote(
                $poll,
                Auth::user(),
                $validated['selected_options']
            );

            // Invalidate relevant caches
            $this->cacheService->invalidatePollCache($poll->uuid);
            $this->cacheService->invalidateUserPollCache(Auth::user()->uuid, $poll->uuid);

            return response()->json(
                [
                    'data'    => $vote->load(['poll', 'user']),
                    'message' => 'Vote cast successfully',
                ],
                201
            );
        } catch (InvalidArgumentException $e) {
            // Check if this is an invalid option error
            if (str_contains($e->getMessage(), 'Invalid option')) {
                return response()->json(
                    [
                        'message' => 'The given data was invalid.',
                        'errors'  => [
                            'selected_options' => [$e->getMessage()],
                        ],
                    ],
                    422
                );
            }

            // Check if this is a validation error
            $validationErrors = [
                'Poll is not active for voting',
                'Poll voting period has ended',
                'You have already voted on this poll',
                'already voted',
                'not available for voting',
                'not eligible to vote',
            ];

            foreach ($validationErrors as $errorPattern) {
                if (str_contains($e->getMessage(), $errorPattern)) {
                    return response()->json(
                        [
                            'message' => $e->getMessage(),
                        ],
                        422
                    );
                }
            }

            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                400
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/polls/{uuid}/results",
     *     summary="Get poll results",
     *     description="Retrieve current results for a poll",
     *     tags={"Governance - Polls"},
     *
     * @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string",     format="uuid")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Poll results",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data", ref="#/components/schemas/PollResult")
     *         )
     *     ),
     *
     * @OA\Response(response=404,    description="Poll not found")
     * )
     */
    public function results(string $uuid): JsonResponse
    {
        $poll = Poll::where('uuid', $uuid)->first();

        if (! $poll) {
            return response()->json(
                [
                    'message' => 'Poll not found',
                ],
                404
            );
        }

        $results = $this->cacheService->getPollResults($poll->uuid);

        return response()->json(
            [
                'data' => $results->toArray(),
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/polls/{uuid}/voting-power",
     *     summary="Get user's voting power",
     *     description="Check the authenticated user's voting power for a specific poll",
     *     tags={"Governance - Polls"},
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string",             format="uuid")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="User's voting power",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="voting_power", type="integer"),
     * @OA\Property(property="can_vote",     type="boolean"),
     * @OA\Property(property="has_voted",    type="boolean"),
     * @OA\Property(property="strategy",     type="string")
     *         )
     *     ),
     *
     * @OA\Response(response=404,            description="Poll not found")
     * )
     */
    public function votingPower(string $uuid): JsonResponse
    {
        $poll = Poll::where('uuid', $uuid)->first();

        if (! $poll) {
            return response()->json(
                [
                    'message' => 'Poll not found',
                ],
                404
            );
        }

        $user = Auth::user();
        /** @var User $user */
        $votingPower = $this->cacheService->getUserVotingPower($user->uuid, $poll->uuid);
        $canVote = $this->governanceService->canUserVote($user, $poll);
        $hasVoted = $this->cacheService->hasUserVoted($user->uuid, $poll->uuid);

        return response()->json(
            [
                'voting_power' => $votingPower ?? 0,
                'can_vote'     => $canVote,
                'has_voted'    => $hasVoted,
                'strategy'     => $poll->voting_power_strategy,
            ]
        );
    }
}
