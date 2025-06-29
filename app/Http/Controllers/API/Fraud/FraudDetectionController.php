<?php

namespace App\Http\Controllers\API\Fraud;

use App\Http\Controllers\Controller;
use App\Domain\Fraud\Services\FraudDetectionService;
use App\Models\Transaction;
use App\Models\User;
use App\Models\FraudScore;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FraudDetectionController extends Controller
{
    private FraudDetectionService $fraudService;
    
    public function __construct(FraudDetectionService $fraudService)
    {
        $this->fraudService = $fraudService;
    }
    
    /**
     * Analyze transaction for fraud
     */
    public function analyzeTransaction(Request $request, string $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);
        
        // Ensure user can analyze this transaction
        $this->authorize('analyze', $transaction);
        
        // Get additional context from request
        $context = $request->only([
            'device_data',
            'ip_address',
            'ip_country',
            'ip_city',
            'ip_region',
            'isp',
            'user_agent',
        ]);
        
        $fraudScore = $this->fraudService->analyzeTransaction($transaction, $context);
        
        return response()->json([
            'fraud_score' => [
                'id' => $fraudScore->id,
                'total_score' => $fraudScore->total_score,
                'risk_level' => $fraudScore->risk_level,
                'decision' => $fraudScore->decision,
                'triggered_rules' => $fraudScore->triggered_rules,
                'score_breakdown' => $fraudScore->score_breakdown,
                'decision_factors' => $fraudScore->decision_factors,
            ],
            'requires_action' => in_array($fraudScore->decision, [
                FraudScore::DECISION_BLOCK,
                FraudScore::DECISION_CHALLENGE,
                FraudScore::DECISION_REVIEW,
            ]),
            'action_required' => $fraudScore->decision,
        ]);
    }
    
    /**
     * Analyze user for fraud patterns
     */
    public function analyzeUser(Request $request, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        // Ensure user can analyze this user
        $this->authorize('analyze', $user);
        
        $context = $request->only(['reason', 'trigger']);
        
        $fraudScore = $this->fraudService->analyzeUser($user, $context);
        
        return response()->json([
            'fraud_score' => [
                'id' => $fraudScore->id,
                'total_score' => $fraudScore->total_score,
                'risk_level' => $fraudScore->risk_level,
                'decision' => $fraudScore->decision,
                'score_breakdown' => $fraudScore->score_breakdown,
                'decision_factors' => $fraudScore->decision_factors,
            ],
            'user_risk_profile' => [
                'current_rating' => $user->risk_rating,
                'suggested_rating' => $this->suggestRiskRating($fraudScore),
                'requires_review' => $fraudScore->decision === FraudScore::DECISION_REVIEW,
            ],
        ]);
    }
    
    /**
     * Get fraud score details
     */
    public function getFraudScore(string $fraudScoreId): JsonResponse
    {
        $fraudScore = FraudScore::with(['fraudCase'])->findOrFail($fraudScoreId);
        
        // Ensure user can view this fraud score
        $this->authorize('view', $fraudScore);
        
        return response()->json([
            'fraud_score' => $fraudScore,
            'has_case' => $fraudScore->fraudCase !== null,
            'case_number' => $fraudScore->fraudCase?->case_number,
        ]);
    }
    
    /**
     * Update fraud score outcome (for ML training)
     */
    public function updateOutcome(Request $request, string $fraudScoreId): JsonResponse
    {
        $request->validate([
            'outcome' => 'required|in:fraud,legitimate,unknown',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        $fraudScore = FraudScore::findOrFail($fraudScoreId);
        
        // Ensure user can update this fraud score
        $this->authorize('update', $fraudScore);
        
        $fraudScore->update([
            'outcome' => $request->outcome,
            'outcome_updated_at' => now(),
            'outcome_updated_by' => auth()->id(),
        ]);
        
        // Train ML model with feedback
        app(\App\Domain\Fraud\Services\MachineLearningService::class)
            ->trainWithFeedback($fraudScore, $request->outcome);
        
        return response()->json([
            'message' => 'Fraud score outcome updated successfully',
            'fraud_score' => $fraudScore,
        ]);
    }
    
    /**
     * Get fraud statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'entity_type' => 'nullable|in:transaction,user',
        ]);
        
        $query = FraudScore::query();
        
        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }
        
        if ($request->entity_type) {
            $entityClass = $request->entity_type === 'transaction' ? Transaction::class : User::class;
            $query->where('entity_type', $entityClass);
        }
        
        $statistics = [
            'total_analyzed' => $query->count(),
            'by_risk_level' => [
                'very_low' => (clone $query)->where('risk_level', FraudScore::RISK_LEVEL_VERY_LOW)->count(),
                'low' => (clone $query)->where('risk_level', FraudScore::RISK_LEVEL_LOW)->count(),
                'medium' => (clone $query)->where('risk_level', FraudScore::RISK_LEVEL_MEDIUM)->count(),
                'high' => (clone $query)->where('risk_level', FraudScore::RISK_LEVEL_HIGH)->count(),
                'very_high' => (clone $query)->where('risk_level', FraudScore::RISK_LEVEL_VERY_HIGH)->count(),
            ],
            'by_decision' => [
                'allow' => (clone $query)->where('decision', FraudScore::DECISION_ALLOW)->count(),
                'challenge' => (clone $query)->where('decision', FraudScore::DECISION_CHALLENGE)->count(),
                'review' => (clone $query)->where('decision', FraudScore::DECISION_REVIEW)->count(),
                'block' => (clone $query)->where('decision', FraudScore::DECISION_BLOCK)->count(),
            ],
            'by_outcome' => [
                'fraud' => (clone $query)->where('outcome', FraudScore::OUTCOME_FRAUD)->count(),
                'legitimate' => (clone $query)->where('outcome', FraudScore::OUTCOME_LEGITIMATE)->count(),
                'unknown' => (clone $query)->where('outcome', FraudScore::OUTCOME_UNKNOWN)->count(),
            ],
            'average_score' => round((clone $query)->avg('total_score') ?? 0, 2),
            'fraud_rate' => $this->calculateFraudRate($query),
            'false_positive_rate' => $this->calculateFalsePositiveRate($query),
        ];
        
        return response()->json(['statistics' => $statistics]);
    }
    
    /**
     * Get ML model metrics
     */
    public function getModelMetrics(): JsonResponse
    {
        $mlService = app(\App\Domain\Fraud\Services\MachineLearningService::class);
        
        if (!$mlService->isEnabled()) {
            return response()->json([
                'message' => 'ML service is not enabled',
                'enabled' => false,
            ]);
        }
        
        $metrics = $mlService->getModelMetrics();
        
        return response()->json([
            'enabled' => true,
            'metrics' => $metrics,
        ]);
    }
    
    /**
     * Suggest risk rating based on fraud score
     */
    private function suggestRiskRating(FraudScore $fraudScore): string
    {
        if ($fraudScore->total_score >= 80) {
            return 'very_high';
        } elseif ($fraudScore->total_score >= 60) {
            return 'high';
        } elseif ($fraudScore->total_score >= 40) {
            return 'medium';
        } elseif ($fraudScore->total_score >= 20) {
            return 'low';
        } else {
            return 'very_low';
        }
    }
    
    /**
     * Calculate fraud rate
     */
    private function calculateFraudRate($query): float
    {
        $total = (clone $query)->whereNotNull('outcome')->count();
        
        if ($total === 0) {
            return 0;
        }
        
        $fraudCount = (clone $query)->where('outcome', FraudScore::OUTCOME_FRAUD)->count();
        
        return round(($fraudCount / $total) * 100, 2);
    }
    
    /**
     * Calculate false positive rate
     */
    private function calculateFalsePositiveRate($query): float
    {
        $blocked = (clone $query)
            ->where('decision', FraudScore::DECISION_BLOCK)
            ->whereNotNull('outcome')
            ->count();
        
        if ($blocked === 0) {
            return 0;
        }
        
        $falsePositives = (clone $query)
            ->where('decision', FraudScore::DECISION_BLOCK)
            ->where('outcome', FraudScore::OUTCOME_LEGITIMATE)
            ->count();
        
        return round(($falsePositives / $blocked) * 100, 2);
    }
}