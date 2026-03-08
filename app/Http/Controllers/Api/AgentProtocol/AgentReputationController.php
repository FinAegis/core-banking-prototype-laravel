<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\ReputationAggregate;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\DIDService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Agent Protocol - Reputation',
    description: 'Agent reputation and trust management endpoints'
)]
class AgentReputationController extends Controller
{
    public function __construct(
        private readonly DIDService $didService,
        private readonly AgentRegistryService $agentRegistryService
    ) {
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/reputation',
            operationId: 'getAgentReputation',
            tags: ['Agent Protocol - Reputation'],
            summary: 'Get agent reputation score and details',
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Reputation details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'agent_did', type: 'string'),
        new OA\Property(property: 'score', type: 'number', example: 85.5),
        new OA\Property(property: 'trust_level', type: 'string', example: 'trusted'),
        new OA\Property(property: 'total_transactions', type: 'integer', example: 150),
        new OA\Property(property: 'success_rate', type: 'number', example: 98.5),
        new OA\Property(property: 'dispute_count', type: 'integer', example: 2),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    public function show(string $did): JsonResponse
    {
        try {
            // Validate DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            // Get agent
            $agent = $this->agentRegistryService->getAgentByDID($did);
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                ], 404);
            }

            // Get reputation from aggregate
            $aggregate = ReputationAggregate::retrieve($agent['agent_id']);

            // If no reputation exists yet, return initial values
            if (empty($aggregate->getAgentId())) {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'agent_did'           => $did,
                        'agent_id'            => $agent['agent_id'],
                        'score'               => 50.0, // Initial score
                        'trust_level'         => 'neutral',
                        'total_transactions'  => 0,
                        'success_rate'        => 0.0,
                        'failed_transactions' => 0,
                        'dispute_count'       => 0,
                        'last_activity'       => null,
                        'is_new_agent'        => true,
                    ],
                ]);
            }

            // Get stats from the aggregate
            $stats = $aggregate->getStats();

            return response()->json([
                'success' => true,
                'data'    => [
                    'agent_did'               => $did,
                    'agent_id'                => $aggregate->getAgentId(),
                    'score'                   => $aggregate->getScore(),
                    'trust_level'             => $aggregate->getTrustLevel(),
                    'total_transactions'      => $stats['total_transactions'],
                    'successful_transactions' => $stats['successful_transactions'],
                    'failed_transactions'     => $stats['failed_transactions'],
                    'success_rate'            => $stats['success_rate'],
                    'dispute_count'           => $stats['disputed_transactions'],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Get reputation failed', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to get reputation: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/reputation/feedback',
            operationId: 'submitAgentFeedback',
            tags: ['Agent Protocol - Reputation'],
            summary: 'Submit feedback about an agent transaction',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID to review', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reviewer_did', 'transaction_id', 'outcome'], properties: [
            new OA\Property(property: 'reviewer_did', type: 'string', example: 'did:finaegis:agent:reviewer123'),
            new OA\Property(property: 'transaction_id', type: 'string', example: 'txn-uuid-here'),
            new OA\Property(property: 'outcome', type: 'string', enum: ['success', 'failed', 'cancelled', 'timeout'], example: 'success'),
            new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 5),
            new OA\Property(property: 'comment', type: 'string', example: 'Fast and reliable service'),
            new OA\Property(property: 'transaction_value', type: 'number', example: 1000.00),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Feedback submitted',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'new_score', type: 'number'),
        new OA\Property(property: 'score_change', type: 'number'),
        new OA\Property(property: 'new_trust_level', type: 'string'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request'
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    public function submitFeedback(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_did'      => 'required|string',
            'transaction_id'    => 'required|string',
            'outcome'           => ['required', 'string', Rule::in(['success', 'failed', 'cancelled', 'timeout'])],
            'rating'            => 'nullable|integer|min:1|max:5',
            'comment'           => 'nullable|string|max:500',
            'transaction_value' => 'nullable|numeric|min:0',
        ]);

        try {
            // Validate DIDs
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid agent DID format',
                ], 400);
            }

            if (! $this->didService->validateDID($validated['reviewer_did'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid reviewer DID format',
                ], 400);
            }

            // Cannot review yourself
            if ($did === $validated['reviewer_did']) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Cannot submit feedback for yourself',
                ], 400);
            }

            // Get agent
            $agent = $this->agentRegistryService->getAgentByDID($did);
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                ], 404);
            }

            // Get or initialize reputation aggregate
            $reputationId = 'rep-' . $agent['agent_id'];
            $aggregate = ReputationAggregate::retrieve($reputationId);

            // Initialize if new
            if (empty($aggregate->getAgentId())) {
                $aggregate = ReputationAggregate::initializeReputation(
                    reputationId: $reputationId,
                    agentId: $agent['agent_id'],
                    initialScore: 50.0,
                    metadata: ['created_from' => 'feedback']
                );
                $aggregate->persist();
                // Re-retrieve to get fresh state
                $aggregate = ReputationAggregate::retrieve($reputationId);
            }

            $previousScore = $aggregate->getScore();

            // Record transaction based on outcome
            $aggregate->recordTransaction(
                transactionId: $validated['transaction_id'],
                outcome: $validated['outcome'],
                value: (float) ($validated['transaction_value'] ?? 0),
                metadata: [
                    'reviewer_did' => $validated['reviewer_did'],
                    'rating'       => $validated['rating'] ?? null,
                    'comment'      => $validated['comment'] ?? null,
                    'submitted_at' => now()->toIso8601String(),
                ]
            );

            $aggregate->persist();

            // Get updated score
            $newScore = $aggregate->getScore();
            $scoreChange = $newScore - $previousScore;

            Log::info('Agent feedback submitted', [
                'agent_did'    => $did,
                'reviewer_did' => $validated['reviewer_did'],
                'outcome'      => $validated['outcome'],
                'score_change' => $scoreChange,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'agent_did'         => $did,
                    'previous_score'    => $previousScore,
                    'new_score'         => $newScore,
                    'score_change'      => $scoreChange,
                    'new_trust_level'   => $this->calculateTrustLevel($newScore),
                    'feedback_recorded' => true,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Submit feedback failed', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to submit feedback: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/reputation/history',
            operationId: 'getAgentReputationHistory',
            tags: ['Agent Protocol - Reputation'],
            summary: 'Get agent reputation history',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Number of history entries', schema: new OA\Schema(type: 'integer', default: 20)),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Reputation history',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ])
    )]
    public function history(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            // Validate DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            // Get agent
            $agent = $this->agentRegistryService->getAgentByDID($did);
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                ], 404);
            }

            // In a real implementation, this would query the event store for history
            $history = $this->agentRegistryService->getAgentReputationHistory(
                $agent['agent_id'],
                $validated['limit'] ?? 20
            );

            return response()->json([
                'success' => true,
                'data'    => $history,
                'meta'    => [
                    'agent_did' => $did,
                    'count'     => count($history),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Get reputation history failed', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to get reputation history: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/reputation/leaderboard',
            operationId: 'getReputationLeaderboard',
            tags: ['Agent Protocol - Reputation'],
            summary: 'Get top-rated agents',
            parameters: [
            new OA\Parameter(name: 'limit', in: 'query', description: 'Number of agents', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'capability', in: 'query', description: 'Filter by capability', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Top agents by reputation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ])
    )]
    public function leaderboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit'      => 'nullable|integer|min:1|max:50',
            'capability' => 'nullable|string',
        ]);

        try {
            $leaderboard = $this->agentRegistryService->getReputationLeaderboard(
                $validated['limit'] ?? 10,
                $validated['capability'] ?? null
            );

            return response()->json([
                'success' => true,
                'data'    => $leaderboard,
                'meta'    => [
                    'count'      => count($leaderboard),
                    'capability' => $validated['capability'] ?? 'all',
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Get leaderboard failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to get leaderboard: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{agentA}/trust/{agentB}',
            operationId: 'evaluateTrustRelationship',
            tags: ['Agent Protocol - Reputation'],
            summary: 'Evaluate trust relationship between two agents',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'agentA', in: 'path', required: true, description: 'First agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'agentB', in: 'path', required: true, description: 'Second agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Trust evaluation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'trust_score', type: 'number'),
        new OA\Property(property: 'recommended_escrow', type: 'boolean'),
        new OA\Property(property: 'max_transaction_value', type: 'number'),
        ]),
        ])
    )]
    public function evaluateTrust(string $agentA, string $agentB): JsonResponse
    {
        try {
            // Validate DIDs
            if (! $this->didService->validateDID($agentA)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid first agent DID format',
                ], 400);
            }

            if (! $this->didService->validateDID($agentB)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid second agent DID format',
                ], 400);
            }

            // Get both agents
            $agentAData = $this->agentRegistryService->getAgentByDID($agentA);
            $agentBData = $this->agentRegistryService->getAgentByDID($agentB);

            if (! $agentAData || ! $agentBData) {
                return response()->json([
                    'success' => false,
                    'error'   => 'One or both agents not found',
                ], 404);
            }

            // Get reputation scores
            $aggregateA = ReputationAggregate::retrieve('rep-' . $agentAData['agent_id']);
            $aggregateB = ReputationAggregate::retrieve('rep-' . $agentBData['agent_id']);

            // Get scores, defaulting to 50.0 for new agents
            $scoreA = $aggregateA->getAgentId() ? $aggregateA->getScore() : 50.0;
            $scoreB = $aggregateB->getAgentId() ? $aggregateB->getScore() : 50.0;

            // Calculate combined trust score (average of both scores)
            $combinedScore = ($scoreA + $scoreB) / 2;

            // Determine transaction limits based on trust
            $recommendations = $this->getTrustRecommendations($combinedScore);

            return response()->json([
                'success' => true,
                'data'    => [
                    'agent_a' => [
                        'did'         => $agentA,
                        'score'       => $scoreA,
                        'trust_level' => $this->calculateTrustLevel($scoreA),
                    ],
                    'agent_b' => [
                        'did'         => $agentB,
                        'score'       => $scoreB,
                        'trust_level' => $this->calculateTrustLevel($scoreB),
                    ],
                    'combined_trust_score'       => $combinedScore,
                    'combined_trust_level'       => $this->calculateTrustLevel($combinedScore),
                    'escrow_recommended'         => $recommendations['escrow_required'],
                    'instant_settlement_allowed' => $recommendations['instant_settlement'],
                    'max_transaction_value'      => $recommendations['max_transaction_value'],
                    'recommendations'            => $recommendations['recommendations'],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Evaluate trust failed', [
                'agent_a' => $agentA,
                'agent_b' => $agentB,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to evaluate trust: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate trust level from score.
     */
    private function calculateTrustLevel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'trusted',
            $score >= 60 => 'high',
            $score >= 40 => 'neutral',
            $score >= 20 => 'low',
            default      => 'untrusted',
        };
    }

    /**
     * Get trust-based recommendations.
     */
    private function getTrustRecommendations(float $trustScore): array
    {
        if ($trustScore >= 80) {
            return [
                'level'                 => 'high',
                'escrow_required'       => false,
                'instant_settlement'    => true,
                'max_transaction_value' => 100000,
                'recommendations'       => [
                    'Allow high-value transactions',
                    'Enable instant settlement',
                    'Minimal verification required',
                ],
            ];
        } elseif ($trustScore >= 60) {
            return [
                'level'                 => 'moderate',
                'escrow_required'       => true,
                'instant_settlement'    => false,
                'max_transaction_value' => 10000,
                'recommendations'       => [
                    'Use escrow for transactions',
                    'Standard verification required',
                    'Monitor transaction patterns',
                ],
            ];
        } elseif ($trustScore >= 40) {
            return [
                'level'                 => 'low',
                'escrow_required'       => true,
                'instant_settlement'    => false,
                'max_transaction_value' => 1000,
                'recommendations'       => [
                    'Mandatory escrow',
                    'Enhanced verification required',
                    'Limit transaction values',
                    'Close monitoring recommended',
                ],
            ];
        } else {
            return [
                'level'                 => 'untrusted',
                'escrow_required'       => true,
                'instant_settlement'    => false,
                'max_transaction_value' => 100,
                'recommendations'       => [
                    'High-risk relationship',
                    'Manual approval required',
                    'Minimal transaction limits',
                    'Consider blocking if necessary',
                ],
            ];
        }
    }
}
