<?php

declare(strict_types=1);

namespace App\Domain\AI\Workflows;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Models\User;
use Exception;
use Generator;
use Workflow\Workflow;

/**
 * Human-in-the-Loop Workflow.
 *
 * Manages AI decisions that require human oversight, approval, or intervention.
 * Implements confidence thresholds, approval workflows, override mechanisms,
 * and comprehensive audit trails for AI decisions.
 *
 * Features:
 * - Approval workflows for high-value operations
 * - Confidence threshold management
 * - Human override mechanisms
 * - Complete audit trail of AI and human decisions
 * - Escalation procedures
 * - Feedback collection for AI improvement
 */
class HumanInTheLoopWorkflow extends Workflow
{
    /**
     * Confidence thresholds for different operation types.
     */
    private const CONFIDENCE_THRESHOLDS = [
        'high_value_transaction' => 0.95,
        'account_closure'        => 0.90,
        'large_withdrawal'       => 0.85,
        'trading_execution'      => 0.80,
        'loan_approval'          => 0.75,
        'kyc_verification'       => 0.70,
        'general_operation'      => 0.65,
    ];

    /**
     * Value thresholds requiring human approval (in USD).
     */
    private const VALUE_THRESHOLDS = [
        'transaction' => 10000,
        'withdrawal'  => 5000,
        'trading'     => 25000,
        'loan'        => 50000,
    ];

    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    /**
     * @var array<array{action: string, timestamp: string, actor: string, decision: string|false}>
     */
    private array $auditTrail = [];

    /**
     * @var array<string, mixed>
     */
    private array $pendingApprovals = [];

    /**
     * Execute human-in-the-loop workflow.
     *
     * @param string $conversationId Unique conversation identifier
     * @param string $userId User identifier
     * @param string $operationType Type of operation requiring oversight
     * @param array<string, mixed> $aiDecision AI's proposed decision
     * @param array<string, mixed> $parameters Additional parameters
     *
     * @return Generator
     */
    public function execute(
        string $conversationId,
        string $userId,
        string $operationType,
        array $aiDecision,
        array $parameters = []
    ): Generator {
        // Initialize workflow context
        $this->initializeContext($conversationId, $userId, $operationType, $aiDecision, $parameters);

        // Step 1: Evaluate AI confidence
        $confidenceCheck = yield from $this->evaluateConfidence($aiDecision, $operationType);

        // Step 2: Check value thresholds
        $valueCheck = yield from $this->checkValueThresholds($operationType, $parameters);

        // Step 3: Determine if human approval needed
        $requiresApproval = $this->determineApprovalRequirement(
            $confidenceCheck,
            $valueCheck,
            $operationType,
            $parameters
        );

        if ($requiresApproval) {
            // Step 4: Create approval request
            $approvalRequest = yield from $this->createApprovalRequest(
                $userId,
                $operationType,
                $aiDecision,
                $parameters
            );

            // Step 5: Wait for human decision (simulated in demo)
            $humanDecision = yield from $this->waitForHumanDecision($approvalRequest);

            // Step 6: Process human decision
            $result = yield from $this->processHumanDecision(
                $humanDecision,
                $aiDecision,
                $operationType
            );

            // Step 7: Collect feedback
            yield from $this->collectFeedback($humanDecision, $aiDecision);
        } else {
            // Auto-approve with high confidence
            $result = yield from $this->autoApprove($aiDecision, $confidenceCheck);
        }

        // Record decision in audit trail
        $this->recordDecision($conversationId, $result);

        return $result;
    }

    /**
     * Initialize workflow context.
     */
    private function initializeContext(
        string $conversationId,
        string $userId,
        string $operationType,
        array $aiDecision,
        array $parameters
    ): void {
        $this->context = [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'operation_type'  => $operationType,
            'ai_decision'     => $aiDecision,
            'parameters'      => $parameters,
            'started_at'      => now()->toIso8601String(),
        ];

        $this->auditTrail[] = [
            'action'    => 'workflow_started',
            'timestamp' => now()->toIso8601String(),
            'actor'     => 'system',
            'decision'  => 'initiate_human_in_loop',
        ];
    }

    /**
     * Evaluate AI confidence against thresholds.
     */
    private function evaluateConfidence(array $aiDecision, string $operationType): Generator
    {
        $aiConfidence = $aiDecision['confidence'] ?? 0.5;
        $threshold = self::CONFIDENCE_THRESHOLDS[$operationType]
            ?? self::CONFIDENCE_THRESHOLDS['general_operation'];

        $evaluation = [
            'ai_confidence'      => $aiConfidence,
            'required_threshold' => $threshold,
            'meets_threshold'    => $aiConfidence >= $threshold,
            'confidence_gap'     => max(0, $threshold - $aiConfidence),
        ];

        $this->auditTrail[] = [
            'action'    => 'confidence_evaluation',
            'timestamp' => now()->toIso8601String(),
            'actor'     => 'system',
            'decision'  => json_encode($evaluation),
        ];

        yield $evaluation;

        return $evaluation;
    }

    /**
     * Check if operation exceeds value thresholds.
     */
    private function checkValueThresholds(string $operationType, array $parameters): Generator
    {
        $operationValue = $parameters['value'] ?? 0;
        $threshold = $this->getValueThreshold($operationType);

        $check = [
            'operation_value'   => $operationValue,
            'threshold'         => $threshold,
            'exceeds_threshold' => $operationValue > $threshold,
            'requires_approval' => $operationValue > $threshold,
        ];

        $this->auditTrail[] = [
            'action'    => 'value_check',
            'timestamp' => now()->toIso8601String(),
            'actor'     => 'system',
            'decision'  => json_encode($check),
        ];

        yield $check;

        return $check;
    }

    /**
     * Get value threshold for operation type.
     */
    private function getValueThreshold(string $operationType): float
    {
        $typeMap = [
            'high_value_transaction' => 'transaction',
            'large_withdrawal'       => 'withdrawal',
            'trading_execution'      => 'trading',
            'loan_approval'          => 'loan',
        ];

        $thresholdType = $typeMap[$operationType] ?? 'transaction';

        return self::VALUE_THRESHOLDS[$thresholdType];
    }

    /**
     * Determine if human approval is required.
     */
    private function determineApprovalRequirement(
        array $confidenceCheck,
        array $valueCheck,
        string $operationType,
        array $parameters
    ): bool {
        // Check explicit override requirements
        if ($parameters['force_human_review'] ?? false) {
            return true;
        }

        // Check regulatory requirements
        if ($this->hasRegulatoryRequirement($operationType)) {
            return true;
        }

        // Check confidence threshold
        if (! $confidenceCheck['meets_threshold']) {
            return true;
        }

        // Check value threshold
        if ($valueCheck['exceeds_threshold']) {
            return true;
        }

        // Check risk level
        if (($parameters['risk_level'] ?? 'low') === 'high') {
            return true;
        }

        return false;
    }

    /**
     * Check if operation has regulatory requirement for human review.
     */
    private function hasRegulatoryRequirement(string $operationType): bool
    {
        $regulatoryOperations = [
            'account_closure',
            'suspicious_activity',
            'regulatory_reporting',
            'large_cash_transaction',
        ];

        return in_array($operationType, $regulatoryOperations);
    }

    /**
     * Create approval request for human review.
     */
    private function createApprovalRequest(
        string $userId,
        string $operationType,
        array $aiDecision,
        array $parameters
    ): Generator {
        $requestId = uniqid('approval_');

        $request = [
            'id'              => $requestId,
            'user_id'         => $userId,
            'operation_type'  => $operationType,
            'ai_decision'     => $aiDecision,
            'ai_reasoning'    => $aiDecision['reasoning'] ?? 'Not provided',
            'ai_confidence'   => $aiDecision['confidence'] ?? 0.5,
            'operation_value' => $parameters['value'] ?? 0,
            'risk_factors'    => $this->identifyRiskFactors($aiDecision, $parameters),
            'priority'        => $this->calculatePriority($operationType, $parameters),
            'created_at'      => now()->toIso8601String(),
            'expires_at'      => now()->addMinutes(30)->toIso8601String(),
            'status'          => 'pending_review',
        ];

        $this->pendingApprovals[$requestId] = $request;

        $this->auditTrail[] = [
            'action'    => 'approval_request_created',
            'timestamp' => now()->toIso8601String(),
            'actor'     => 'system',
            'decision'  => "Request ID: {$requestId}",
        ];

        yield $request;

        return $request;
    }

    /**
     * Identify risk factors for human review.
     */
    private function identifyRiskFactors(array $aiDecision, array $parameters): array
    {
        $riskFactors = [];

        if ($aiDecision['confidence'] < 0.7) {
            $riskFactors[] = 'low_ai_confidence';
        }

        if (($parameters['value'] ?? 0) > 5000) {
            $riskFactors[] = 'high_value';
        }

        if (($parameters['user_history']['violations'] ?? 0) > 0) {
            $riskFactors[] = 'previous_violations';
        }

        if ($parameters['unusual_pattern'] ?? false) {
            $riskFactors[] = 'unusual_activity_pattern';
        }

        return $riskFactors;
    }

    /**
     * Calculate priority for approval request.
     */
    private function calculatePriority(string $operationType, array $parameters): string
    {
        $value = $parameters['value'] ?? 0;
        $urgency = $parameters['urgency'] ?? 'normal';

        if ($urgency === 'critical' || $value > 50000) {
            return 'critical';
        }

        if ($operationType === 'account_closure' || $value > 10000) {
            return 'high';
        }

        if ($value > 5000 || $urgency === 'high') {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Wait for human decision (simulated for demo).
     */
    private function waitForHumanDecision(array $approvalRequest): Generator
    {
        // In production, this would wait for actual human input
        // For demo, simulate human decision based on risk factors

        $simulatedDecision = $this->simulateHumanDecision($approvalRequest);

        $this->auditTrail[] = [
            'action'    => 'human_decision_received',
            'timestamp' => now()->toIso8601String(),
            'actor'     => $simulatedDecision['reviewer'] ?? 'human_reviewer',
            'decision'  => $simulatedDecision['decision'],
        ];

        yield $simulatedDecision;

        return $simulatedDecision;
    }

    /**
     * Simulate human decision for demo.
     */
    private function simulateHumanDecision(array $approvalRequest): array
    {
        $riskFactorCount = count($approvalRequest['risk_factors']);
        $aiConfidence = $approvalRequest['ai_confidence'];

        // Simulate decision based on risk and confidence
        $approvalProbability = 0.9 - ($riskFactorCount * 0.15) + ($aiConfidence * 0.2);
        $approved = rand(0, 100) / 100 < $approvalProbability;

        return [
            'request_id'    => $approvalRequest['id'],
            'decision'      => $approved ? 'approved' : 'rejected',
            'reviewer'      => 'senior_analyst_' . rand(100, 999),
            'review_time'   => rand(30, 300), // seconds
            'comments'      => $this->generateReviewComments($approved, $approvalRequest),
            'modifications' => $approved ? [] : $this->suggestModifications($approvalRequest),
            'override_ai'   => ! $approved && $aiConfidence > 0.7,
            'timestamp'     => now()->toIso8601String(),
        ];
    }

    /**
     * Generate review comments.
     */
    private function generateReviewComments(bool $approved, array $request): string
    {
        if ($approved) {
            return 'Operation approved after review. AI decision aligns with policy. Risk factors addressed.';
        }

        $reasons = [];
        if (count($request['risk_factors']) > 2) {
            $reasons[] = 'Multiple risk factors present';
        }
        if ($request['ai_confidence'] < 0.6) {
            $reasons[] = 'AI confidence below acceptable threshold';
        }

        return 'Operation rejected. ' . implode('. ', $reasons) . '.';
    }

    /**
     * Suggest modifications for rejected operations.
     */
    private function suggestModifications(array $request): array
    {
        $modifications = [];

        if ($request['operation_value'] > 10000) {
            $modifications[] = [
                'field'     => 'value',
                'suggested' => 9999,
                'reason'    => 'Reduce below approval threshold',
            ];
        }

        if (in_array('unusual_activity_pattern', $request['risk_factors'])) {
            $modifications[] = [
                'field'     => 'verification',
                'suggested' => 'additional_kyc',
                'reason'    => 'Require additional verification',
            ];
        }

        return $modifications;
    }

    /**
     * Process human decision.
     */
    private function processHumanDecision(
        array $humanDecision,
        array $aiDecision,
        string $operationType
    ): Generator {
        $result = [
            'final_decision'    => $humanDecision['decision'],
            'decision_maker'    => 'human',
            'ai_decision'       => $aiDecision['recommendation'] ?? 'unknown',
            'ai_overridden'     => $humanDecision['override_ai'],
            'modifications'     => $humanDecision['modifications'],
            'execution_allowed' => $humanDecision['decision'] === 'approved',
            'feedback_required' => $humanDecision['override_ai'],
        ];

        // Update approval request status
        if (isset($this->pendingApprovals[$humanDecision['request_id']])) {
            $this->pendingApprovals[$humanDecision['request_id']]['status'] =
                $humanDecision['decision'] === 'approved' ? 'approved' : 'rejected';
        }

        $this->auditTrail[] = [
            'action'    => 'decision_processed',
            'timestamp' => now()->toIso8601String(),
            'actor'     => 'system',
            'decision'  => json_encode($result),
        ];

        yield $result;

        return $result;
    }

    /**
     * Collect feedback for AI improvement.
     */
    private function collectFeedback(array $humanDecision, array $aiDecision): Generator
    {
        $feedback = [
            'conversation_id'   => $this->context['conversation_id'],
            'ai_decision'       => $aiDecision,
            'human_decision'    => $humanDecision,
            'agreement'         => $this->calculateAgreement($humanDecision, $aiDecision),
            'learning_points'   => $this->extractLearningPoints($humanDecision, $aiDecision),
            'improvement_areas' => $this->identifyImprovementAreas($humanDecision),
            'timestamp'         => now()->toIso8601String(),
        ];

        // Store feedback for AI training
        $this->storeFeedback($feedback);

        yield $feedback;

        return $feedback;
    }

    /**
     * Calculate agreement between human and AI decisions.
     */
    private function calculateAgreement(array $humanDecision, array $aiDecision): float
    {
        $aiRecommendation = $aiDecision['recommendation'] ?? 'unknown';
        $humanChoice = $humanDecision['decision'];

        // Map recommendations to decisions
        $aiApproved = in_array($aiRecommendation, ['approve', 'proceed', 'allow']);
        $humanApproved = $humanChoice === 'approved';

        if ($aiApproved === $humanApproved) {
            return 1.0; // Full agreement
        }

        // Partial agreement if human suggested modifications
        if (! empty($humanDecision['modifications'])) {
            return 0.5;
        }

        return 0.0; // No agreement
    }

    /**
     * Extract learning points from human decision.
     */
    private function extractLearningPoints(array $humanDecision, array $aiDecision): array
    {
        $points = [];

        if ($humanDecision['override_ai']) {
            $points[] = [
                'type'   => 'decision_override',
                'reason' => $humanDecision['comments'],
                'weight' => 'high',
            ];
        }

        if (! empty($humanDecision['modifications'])) {
            foreach ($humanDecision['modifications'] as $mod) {
                $points[] = [
                    'type'   => 'parameter_adjustment',
                    'field'  => $mod['field'],
                    'reason' => $mod['reason'],
                    'weight' => 'medium',
                ];
            }
        }

        return $points;
    }

    /**
     * Identify areas for AI improvement.
     */
    private function identifyImprovementAreas(array $humanDecision): array
    {
        $areas = [];

        if ($humanDecision['override_ai']) {
            $areas[] = 'confidence_calibration';
        }

        if (! empty($humanDecision['modifications'])) {
            $areas[] = 'risk_assessment';
        }

        if (str_contains($humanDecision['comments'] ?? '', 'policy')) {
            $areas[] = 'policy_understanding';
        }

        return $areas;
    }

    /**
     * Store feedback for future training.
     */
    private function storeFeedback(array $feedback): void
    {
        // In production, this would store to a database or training pipeline
        logger()->info('AI feedback collected', $feedback);
    }

    /**
     * Auto-approve with high confidence.
     */
    private function autoApprove(array $aiDecision, array $confidenceCheck): Generator
    {
        $result = [
            'final_decision'    => 'approved',
            'decision_maker'    => 'ai_automatic',
            'ai_confidence'     => $confidenceCheck['ai_confidence'],
            'execution_allowed' => true,
            'auto_approved'     => true,
            'approval_reason'   => 'High confidence and within thresholds',
        ];

        $this->auditTrail[] = [
            'action'    => 'auto_approved',
            'timestamp' => now()->toIso8601String(),
            'actor'     => 'ai_system',
            'decision'  => 'approved_automatically',
        ];

        yield $result;

        return $result;
    }

    /**
     * Record final decision in event store.
     */
    private function recordDecision(string $conversationId, array $result): void
    {
        try {
            $aggregate = AIInteractionAggregate::retrieve($conversationId);

            $aggregate->makeDecision(
                decision: 'human_in_loop_' . $result['final_decision'],
                reasoning: [
                    'decision_maker' => $result['decision_maker'],
                    'ai_overridden'  => $result['ai_overridden'] ?? false,
                    'audit_trail'    => $this->auditTrail,
                    'outcome'        => $result,
                ],
                confidence: $result['ai_confidence'] ?? 0.0,
                requiresApproval: false
            );

            $aggregate->persist();
        } catch (Exception $e) {
            logger()->error('Failed to record human-in-loop decision', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get current approval queue.
     */
    public function getApprovalQueue(): array
    {
        return array_filter(
            $this->pendingApprovals,
            fn ($approval) => $approval['status'] === 'pending_review'
        );
    }

    /**
     * Get audit trail for conversation.
     */
    public function getAuditTrail(): array
    {
        return $this->auditTrail;
    }

    /**
     * Override AI decision manually.
     */
    public function overrideDecision(
        string $approvalId,
        string $decision,
        string $reviewer,
        string $reason
    ): array {
        if (! isset($this->pendingApprovals[$approvalId])) {
            throw new Exception("Approval request not found: {$approvalId}");
        }

        $this->pendingApprovals[$approvalId]['status'] = $decision;
        $this->pendingApprovals[$approvalId]['reviewer'] = $reviewer;
        $this->pendingApprovals[$approvalId]['review_reason'] = $reason;
        $this->pendingApprovals[$approvalId]['reviewed_at'] = now()->toIso8601String();

        $this->auditTrail[] = [
            'action'    => 'manual_override',
            'timestamp' => now()->toIso8601String(),
            'actor'     => $reviewer,
            'decision'  => "{$decision}: {$reason}",
        ];

        return $this->pendingApprovals[$approvalId];
    }
}
