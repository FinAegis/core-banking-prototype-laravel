<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\ReputationAggregate;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\ReputationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentProtocol\SubmitFeedbackRequest;
use App\Http\Resources\AgentProtocol\ReputationResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentReputationController extends Controller
{
    public function __construct(
        private readonly AgentRegistryService $registryService,
        private readonly ReputationService $reputationService
    ) {
    }

    /**
     * Get agent reputation score.
     *
     * @OA\Get(
     *     path="/api/agents/{did}/reputation",
     *     operationId="getAgentReputation",
     *     tags={"Agent Protocol - Reputation"},
     *     summary="Get agent reputation",
     *     description="Get the reputation score and trust level of an agent",
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reputation retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ReputationResource")
     *     ),
     *     @OA\Response(response=404, description="Agent not found")
     * )
     */
    public function getReputation(string $did): JsonResponse
    {
        try {
            // Verify agent exists
            $agent = $this->registryService->getAgentByDID($did);
            if (! $agent) {
                return response()->json([
                    'error' => 'Agent not found',
                    'did'   => $did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Get reputation from service
            $reputation = $this->reputationService->getAgentReputation($agent->agent_id);

            if ($reputation === null) {
                // If no reputation exists, return default values
                return response()->json([
                    'agent_did'               => $did,
                    'score'                   => 50.0,
                    'trust_level'             => 'neutral',
                    'total_transactions'      => 0,
                    'successful_transactions' => 0,
                    'failed_transactions'     => 0,
                    'disputed_transactions'   => 0,
                    'success_rate'            => 0,
                    'last_updated'            => null,
                ]);
            }

            // Load reputation aggregate to get detailed stats
            $reputationAggregate = ReputationAggregate::retrieve($reputation->reputation_id);
            $stats = $reputationAggregate->getStats();

            return response()->json([
                'agent_did'               => $did,
                'score'                   => $reputationAggregate->getScore(),
                'trust_level'             => $reputationAggregate->getTrustLevel(),
                'total_transactions'      => $stats['total_transactions'],
                'successful_transactions' => $stats['successful_transactions'],
                'failed_transactions'     => $stats['failed_transactions'],
                'disputed_transactions'   => $stats['disputed_transactions'],
                'success_rate'            => $stats['success_rate'],
                'last_updated'            => $reputation->updated_at->toIso8601String(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get agent reputation', [
                'error' => $e->getMessage(),
                'did'   => $did,
            ]);

            return response()->json([
                'error'   => 'Failed to retrieve reputation',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Submit feedback for an agent.
     *
     * @OA\Post(
     *     path="/api/agents/{did}/reputation/feedback",
     *     operationId="submitAgentFeedback",
     *     tags={"Agent Protocol - Reputation"},
     *     summary="Submit agent feedback",
     *     description="Submit feedback to affect an agent's reputation",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Agent DID to receive feedback",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/SubmitFeedbackRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Feedback submitted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ReputationResource")
     *     ),
     *     @OA\Response(response=404, description="Agent not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function submitFeedback(SubmitFeedbackRequest $request, string $did): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Verify target agent exists
            $targetAgent = $this->registryService->getAgentByDID($did);
            if (! $targetAgent) {
                return response()->json([
                    'error' => 'Target agent not found',
                    'did'   => $did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Verify submitter agent exists
            $submitterAgent = $this->registryService->getAgentByDID($request->submitter_did);
            if (! $submitterAgent) {
                return response()->json([
                    'error' => 'Submitter agent not found',
                    'did'   => $request->submitter_did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Ensure reputation exists
            $reputation = $this->reputationService->getOrCreateReputation($targetAgent->agent_id);

            // Load reputation aggregate
            $reputationId = is_object($reputation) && property_exists($reputation, 'reputation_id')
                ? $reputation->reputation_id
                : $reputation['reputation_id'];
            $reputationAggregate = ReputationAggregate::retrieve($reputationId);

            // Process feedback based on type
            switch ($request->feedback_type) {
                case 'transaction':
                    $reputationAggregate->recordTransaction(
                        transactionId: $request->transaction_id ?? Str::uuid()->toString(),
                        outcome: $request->outcome ?? 'success',
                        value: $request->transaction_value ?? 0.0,
                        metadata: [
                            'submitter_did' => $request->submitter_did,
                            'comment'       => $request->comment ?? '',
                            'rating'        => $request->rating ?? null,
                        ]
                    );
                    break;

                case 'dispute':
                    $reputationAggregate->applyDisputePenalty(
                        disputeId: $request->dispute_id ?? Str::uuid()->toString(),
                        severity: $request->severity ?? 'moderate',
                        reason: $request->reason ?? 'Dispute raised',
                        metadata: [
                            'submitter_did' => $request->submitter_did,
                            'evidence'      => $request->evidence ?? [],
                        ]
                    );
                    break;

                case 'endorsement':
                    $reputationAggregate->applyReputationBoost(
                        reason: $request->reason ?? 'Endorsement',
                        amount: $request->boost_amount ?? 5.0,
                        metadata: [
                            'submitter_did' => $request->submitter_did,
                            'comment'       => $request->comment ?? '',
                        ]
                    );
                    break;

                default:
                    // General feedback - affects score based on rating
                    if ($request->rating !== null) {
                        $scoreChange = ($request->rating - 3) * 2; // Rating 1-5 maps to -4 to +4
                        if ($scoreChange > 0) {
                            $reputationAggregate->applyReputationBoost(
                                reason: 'Positive feedback',
                                amount: $scoreChange,
                                metadata: ['submitter_did' => $request->submitter_did]
                            );
                        } elseif ($scoreChange < 0) {
                            $reputationAggregate->applyDisputePenalty(
                                disputeId: Str::uuid()->toString(),
                                severity: 'minor',
                                reason: 'Negative feedback',
                                metadata: ['submitter_did' => $request->submitter_did]
                            );
                        }
                    }
            }

            $reputationAggregate->persist();

            // Update reputation service cache
            $this->reputationService->refreshReputationCache($targetAgent->agent_id);

            DB::commit();

            Log::info('Agent feedback submitted', [
                'target_did'    => $did,
                'submitter_did' => $request->submitter_did,
                'feedback_type' => $request->feedback_type,
            ]);

            // Return updated reputation
            $stats = $reputationAggregate->getStats();

            return response()->json([
                'agent_did'          => $did,
                'score'              => $reputationAggregate->getScore(),
                'trust_level'        => $reputationAggregate->getTrustLevel(),
                'total_transactions' => $stats['total_transactions'],
                'success_rate'       => $stats['success_rate'],
                'message'            => 'Feedback submitted successfully',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit feedback', [
                'error'      => $e->getMessage(),
                'target_did' => $did,
            ]);

            return response()->json([
                'error'   => 'Failed to submit feedback',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
