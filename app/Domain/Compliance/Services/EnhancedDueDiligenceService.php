<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Aggregates\ComplianceAlertAggregate;
use App\Domain\Compliance\Events\EnhancedDueDiligenceRequired;
use App\Domain\Compliance\Models\CustomerRiskProfile;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Service for managing Enhanced Due Diligence (EDD) workflows.
 *
 * EDD is required for high-risk customers and involves:
 * - Comprehensive document collection
 * - Ongoing risk assessments
 * - Periodic reviews
 * - Source of funds verification
 */
class EnhancedDueDiligenceService
{
    /**
     * Cache prefix for EDD workflows.
     */
    private const CACHE_PREFIX = 'edd_workflow:';

    /**
     * EDD workflow statuses.
     */
    private const STATUS_INITIATED = 'initiated';

    private const STATUS_DOCUMENT_COLLECTION = 'document_collection';

    private const STATUS_UNDER_REVIEW = 'under_review';

    private const STATUS_APPROVED = 'approved';

    private const STATUS_REJECTED = 'rejected';

    /** @phpstan-ignore-next-line classConstant.unused - Reserved for future workflow state transitions */
    private const STATUS_PENDING_PERIODIC_REVIEW = 'pending_periodic_review';

    /**
     * Required documents for EDD by risk category.
     *
     * @var array<string, array<string>>
     */
    private const REQUIRED_DOCUMENTS = [
        'high_risk' => [
            'government_id',
            'proof_of_address',
            'source_of_funds',
            'source_of_wealth',
            'business_registration',
        ],
        'pep' => [
            'government_id',
            'proof_of_address',
            'source_of_funds',
            'source_of_wealth',
            'pep_declaration',
            'beneficial_ownership',
        ],
        'high_value' => [
            'government_id',
            'proof_of_address',
            'source_of_funds',
            'source_of_wealth',
            'tax_returns',
            'bank_statements',
        ],
    ];

    public function __construct(
        /** @phpstan-ignore-next-line property.onlyWritten - Reserved for future risk assessment integration */
        private readonly CustomerRiskService $riskService,
        private readonly DocumentAnalysisService $documentService,
    ) {
    }

    /**
     * Initiate an EDD workflow for a customer.
     *
     * @param string      $customerId  The customer's UUID
     * @param string|null $triggerReason Reason for initiating EDD
     *
     * @return array{workflow_id: string, customer_id: string, status: string, required_documents: array<string>, created_at: string}
     *
     * @throws RuntimeException If customer not found or EDD already in progress
     */
    public function initiateEDD(string $customerId, ?string $triggerReason = null): array
    {
        $customer = User::where('uuid', $customerId)->first();
        if (! $customer) {
            throw new RuntimeException("Customer not found: {$customerId}");
        }

        // Check for existing active EDD
        $existingWorkflow = $this->getActiveWorkflow($customerId);
        if ($existingWorkflow !== null) {
            throw new RuntimeException("EDD workflow already in progress: {$existingWorkflow['workflow_id']}");
        }

        // Determine risk category
        $riskProfile = CustomerRiskProfile::where('user_id', $customer->id)->first();
        $riskCategory = $this->determineRiskCategory($riskProfile);

        // Create workflow
        $workflowId = Str::uuid()->toString();
        $requiredDocs = self::REQUIRED_DOCUMENTS[$riskCategory] ?? self::REQUIRED_DOCUMENTS['high_risk'];

        $workflow = [
            'workflow_id'         => $workflowId,
            'customer_id'         => $customerId,
            'customer_name'       => $customer->name,
            'status'              => self::STATUS_INITIATED,
            'risk_category'       => $riskCategory,
            'trigger_reason'      => $triggerReason ?? 'High risk customer',
            'required_documents'  => $requiredDocs,
            'collected_documents' => [],
            'risk_assessments'    => [],
            'created_at'          => now()->toIso8601String(),
            'updated_at'          => now()->toIso8601String(),
        ];

        // Store workflow
        DB::table('edd_workflows')->insert([
            'uuid'          => $workflowId,
            'customer_id'   => $customerId,
            'status'        => self::STATUS_INITIATED,
            'risk_category' => $riskCategory,
            'workflow_data' => json_encode($workflow),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Cache for quick access
        Cache::put(self::CACHE_PREFIX . $workflowId, $workflow, now()->addDays(30));

        // Fire event if risk profile exists
        if ($riskProfile !== null) {
            event(new EnhancedDueDiligenceRequired($riskProfile));
        }

        Log::info('EDD workflow initiated', [
            'workflow_id'   => $workflowId,
            'customer_id'   => $customerId,
            'risk_category' => $riskCategory,
        ]);

        return [
            'workflow_id'        => $workflowId,
            'customer_id'        => $customerId,
            'status'             => self::STATUS_INITIATED,
            'required_documents' => $requiredDocs,
            'created_at'         => $workflow['created_at'],
        ];
    }

    /**
     * Collect documents for an EDD workflow.
     *
     * @param string                                              $workflowId The workflow UUID
     * @param array<int, array{type: string, file_path: string, metadata?: array<string, mixed>}> $documents  Documents to add
     *
     * @return array{workflow_id: string, documents_added: int, documents_pending: array<string>, status: string}
     */
    public function collectDocuments(string $workflowId, array $documents): array
    {
        $workflow = $this->getWorkflow($workflowId);
        if ($workflow === null) {
            throw new RuntimeException("EDD workflow not found: {$workflowId}");
        }

        $documentsAdded = 0;
        $collectedDocs = $workflow['collected_documents'] ?? [];

        foreach ($documents as $document) {
            $docType = $document['type'];

            // Validate document is required
            if (! in_array($docType, $workflow['required_documents'], true)) {
                Log::warning('Document type not required for EDD', [
                    'workflow_id' => $workflowId,
                    'doc_type'    => $docType,
                ]);
                continue;
            }

            // Analyze and verify document
            $extractedData = $this->documentService->extractDocumentData(
                $document['file_path'],
                $docType
            );
            $authenticityCheck = $this->documentService->verifyAuthenticity(
                $document['file_path'],
                $docType
            );

            $analysisResult = [
                'extracted_data' => $extractedData,
                'authenticity'   => $authenticityCheck,
                'verified'       => ($authenticityCheck['is_authentic'] ?? false)
                    && ($authenticityCheck['confidence'] ?? 0) >= 0.7,
            ];

            $collectedDocs[$docType] = [
                'file_path'       => $document['file_path'],
                'analysis_result' => $analysisResult,
                'collected_at'    => now()->toIso8601String(),
                'verified'        => $analysisResult['verified'],
            ];

            $documentsAdded++;
        }

        // Update workflow
        $workflow['collected_documents'] = $collectedDocs;
        $workflow['updated_at'] = now()->toIso8601String();

        // Check if all documents collected
        $pendingDocs = array_diff(
            $workflow['required_documents'],
            array_keys($collectedDocs)
        );

        if (empty($pendingDocs)) {
            $workflow['status'] = self::STATUS_UNDER_REVIEW;
        } else {
            $workflow['status'] = self::STATUS_DOCUMENT_COLLECTION;
        }

        $this->updateWorkflow($workflowId, $workflow);

        Log::info('EDD documents collected', [
            'workflow_id'     => $workflowId,
            'documents_added' => $documentsAdded,
            'pending_count'   => count($pendingDocs),
        ]);

        return [
            'workflow_id'       => $workflowId,
            'documents_added'   => $documentsAdded,
            'documents_pending' => array_values($pendingDocs),
            'status'            => $workflow['status'],
        ];
    }

    /**
     * Perform risk assessment for an EDD workflow.
     *
     * @param string $workflowId The workflow UUID
     *
     * @return array{workflow_id: string, risk_score: float, risk_level: string, factors: array<string, float>, recommendation: string}
     */
    public function performRiskAssessment(string $workflowId): array
    {
        $workflow = $this->getWorkflow($workflowId);
        if ($workflow === null) {
            throw new RuntimeException("EDD workflow not found: {$workflowId}");
        }

        $factors = [];
        $baseScore = 50.0;

        // Assess document verification status
        $verifiedDocs = 0;
        $totalDocs = count($workflow['required_documents']);
        $collectedDocs = $workflow['collected_documents'] ?? [];

        foreach ($collectedDocs as $docType => $doc) {
            if ($doc['verified'] ?? false) {
                $verifiedDocs++;
            }
        }

        $documentScore = $totalDocs > 0 ? ($verifiedDocs / $totalDocs) * 100 : 0;
        $factors['document_verification'] = $documentScore;

        // Assess customer risk profile
        $customer = User::where('uuid', $workflow['customer_id'])->first();
        $riskProfile = $customer ? CustomerRiskProfile::where('user_id', $customer->id)->first() : null;

        $customerRiskScore = $riskProfile !== null
            ? (float) $riskProfile->overall_risk_score
            : 75.0;
        $factors['customer_risk_profile'] = 100 - $customerRiskScore;

        // Assess source of funds if available
        if (isset($collectedDocs['source_of_funds'])) {
            $sofAnalysis = $collectedDocs['source_of_funds']['analysis_result'] ?? [];
            $factors['source_of_funds'] = $sofAnalysis['confidence'] ?? 50.0;
        }

        // Calculate overall score (weighted average)
        $weights = [
            'document_verification' => 0.4,
            'customer_risk_profile' => 0.35,
            'source_of_funds'       => 0.25,
        ];

        $weightedSum = 0.0;
        $totalWeight = 0.0;
        foreach ($factors as $factor => $score) {
            /** @var string $factor */
            $weight = array_key_exists($factor, $weights) ? $weights[$factor] : 0.1;
            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }

        $overallScore = $weightedSum / $totalWeight;
        $riskLevel = $this->determineRiskLevel($overallScore);
        $recommendation = $this->generateRecommendation($overallScore, $workflow);

        // Store assessment
        $assessment = [
            'assessed_at'    => now()->toIso8601String(),
            'risk_score'     => round($overallScore, 2),
            'risk_level'     => $riskLevel,
            'factors'        => $factors,
            'recommendation' => $recommendation,
        ];

        $workflow['risk_assessments'][] = $assessment;
        $workflow['latest_risk_score'] = $overallScore;
        $workflow['latest_risk_level'] = $riskLevel;
        $workflow['updated_at'] = now()->toIso8601String();

        $this->updateWorkflow($workflowId, $workflow);

        Log::info('EDD risk assessment completed', [
            'workflow_id'    => $workflowId,
            'risk_score'     => $overallScore,
            'risk_level'     => $riskLevel,
            'recommendation' => $recommendation,
        ]);

        return [
            'workflow_id'    => $workflowId,
            'risk_score'     => round($overallScore, 2),
            'risk_level'     => $riskLevel,
            'factors'        => $factors,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Schedule periodic review for a customer.
     *
     * @param string              $customerId Customer UUID
     * @param CarbonInterval|null $interval   Review interval (default: 6 months)
     *
     * @return array{customer_id: string, next_review_at: string, interval_months: int}
     */
    public function schedulePeriodicReview(string $customerId, ?CarbonInterval $interval = null): array
    {
        $interval ??= CarbonInterval::months(6);
        $nextReview = now()->add($interval);

        // Store review schedule
        DB::table('edd_periodic_reviews')->updateOrInsert(
            ['customer_id' => $customerId],
            [
                'next_review_at'  => $nextReview,
                'interval_months' => $interval->totalMonths,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        Log::info('EDD periodic review scheduled', [
            'customer_id'    => $customerId,
            'next_review_at' => $nextReview->toIso8601String(),
        ]);

        return [
            'customer_id'     => $customerId,
            'next_review_at'  => $nextReview->toIso8601String(),
            'interval_months' => (int) $interval->totalMonths,
        ];
    }

    /**
     * Approve an EDD workflow.
     *
     * @param string $workflowId   Workflow UUID
     * @param string $approvedBy   Approver identifier
     * @param string $notes        Approval notes
     *
     * @return array{workflow_id: string, status: string, approved_at: string}
     */
    public function approveWorkflow(string $workflowId, string $approvedBy, string $notes = ''): array
    {
        $workflow = $this->getWorkflow($workflowId);
        if ($workflow === null) {
            throw new RuntimeException("EDD workflow not found: {$workflowId}");
        }

        $workflow['status'] = self::STATUS_APPROVED;
        $workflow['approved_by'] = $approvedBy;
        $workflow['approval_notes'] = $notes;
        $workflow['approved_at'] = now()->toIso8601String();
        $workflow['updated_at'] = now()->toIso8601String();

        $this->updateWorkflow($workflowId, $workflow);

        // Schedule periodic review
        $this->schedulePeriodicReview($workflow['customer_id']);

        Log::info('EDD workflow approved', [
            'workflow_id' => $workflowId,
            'approved_by' => $approvedBy,
        ]);

        return [
            'workflow_id' => $workflowId,
            'status'      => self::STATUS_APPROVED,
            'approved_at' => $workflow['approved_at'],
        ];
    }

    /**
     * Reject an EDD workflow.
     *
     * @param string $workflowId  Workflow UUID
     * @param string $rejectedBy  Rejector identifier
     * @param string $reason      Rejection reason
     *
     * @return array{workflow_id: string, status: string, rejected_at: string}
     */
    public function rejectWorkflow(string $workflowId, string $rejectedBy, string $reason): array
    {
        $workflow = $this->getWorkflow($workflowId);
        if ($workflow === null) {
            throw new RuntimeException("EDD workflow not found: {$workflowId}");
        }

        $workflow['status'] = self::STATUS_REJECTED;
        $workflow['rejected_by'] = $rejectedBy;
        $workflow['rejection_reason'] = $reason;
        $workflow['rejected_at'] = now()->toIso8601String();
        $workflow['updated_at'] = now()->toIso8601String();

        $this->updateWorkflow($workflowId, $workflow);

        // Create compliance alert
        try {
            $alert = ComplianceAlertAggregate::create(
                type: 'edd_rejection',
                severity: 'high',
                entityType: 'user',
                entityId: $workflow['customer_id'],
                description: "EDD workflow rejected: {$reason}",
                details: ['workflow_id' => $workflowId, 'reason' => $reason]
            );
            $alert->persist();
        } catch (Throwable $e) {
            Log::warning('Failed to create compliance alert for EDD rejection', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::warning('EDD workflow rejected', [
            'workflow_id' => $workflowId,
            'rejected_by' => $rejectedBy,
            'reason'      => $reason,
        ]);

        return [
            'workflow_id' => $workflowId,
            'status'      => self::STATUS_REJECTED,
            'rejected_at' => $workflow['rejected_at'],
        ];
    }

    /**
     * Get workflow status.
     *
     * @param string $workflowId Workflow UUID
     *
     * @return array<string, mixed>|null
     */
    public function getWorkflowStatus(string $workflowId): ?array
    {
        $workflow = $this->getWorkflow($workflowId);
        if ($workflow === null) {
            return null;
        }

        $pendingDocs = array_diff(
            $workflow['required_documents'] ?? [],
            array_keys($workflow['collected_documents'] ?? [])
        );

        return [
            'workflow_id'         => $workflowId,
            'customer_id'         => $workflow['customer_id'],
            'status'              => $workflow['status'],
            'risk_category'       => $workflow['risk_category'],
            'documents_pending'   => count($pendingDocs),
            'documents_collected' => count($workflow['collected_documents'] ?? []),
            'latest_risk_score'   => $workflow['latest_risk_score'] ?? null,
            'latest_risk_level'   => $workflow['latest_risk_level'] ?? null,
            'created_at'          => $workflow['created_at'],
            'updated_at'          => $workflow['updated_at'],
        ];
    }

    /**
     * Get customers due for periodic review.
     *
     * @return array<int, array{customer_id: string, next_review_at: string, days_overdue: int}>
     */
    public function getCustomersDueForReview(): array
    {
        return DB::table('edd_periodic_reviews')
            ->where('next_review_at', '<=', now())
            ->orderBy('next_review_at')
            ->get()
            ->map(fn ($row) => [
                'customer_id'    => $row->customer_id,
                'next_review_at' => $row->next_review_at,
                'days_overdue'   => (int) now()->diffInDays(Carbon::parse($row->next_review_at)),
            ])
            ->all();
    }

    /**
     * Get workflow from cache or database.
     *
     * @return array<string, mixed>|null
     */
    private function getWorkflow(string $workflowId): ?array
    {
        // Check cache first
        $cached = Cache::get(self::CACHE_PREFIX . $workflowId);
        if ($cached !== null) {
            return $cached;
        }

        // Load from database
        $row = DB::table('edd_workflows')->where('uuid', $workflowId)->first();
        if ($row === null) {
            return null;
        }

        $workflow = json_decode($row->workflow_data, true);
        Cache::put(self::CACHE_PREFIX . $workflowId, $workflow, now()->addDays(30));

        return $workflow;
    }

    /**
     * Get active workflow for customer.
     *
     * @return array<string, mixed>|null
     */
    private function getActiveWorkflow(string $customerId): ?array
    {
        $row = DB::table('edd_workflows')
            ->where('customer_id', $customerId)
            ->whereIn('status', [self::STATUS_INITIATED, self::STATUS_DOCUMENT_COLLECTION, self::STATUS_UNDER_REVIEW])
            ->first();

        if ($row === null) {
            return null;
        }

        return json_decode($row->workflow_data, true);
    }

    /**
     * Update workflow in database and cache.
     *
     * @param array<string, mixed> $workflow
     */
    private function updateWorkflow(string $workflowId, array $workflow): void
    {
        DB::table('edd_workflows')
            ->where('uuid', $workflowId)
            ->update([
                'status'        => $workflow['status'],
                'workflow_data' => json_encode($workflow),
                'updated_at'    => now(),
            ]);

        Cache::put(self::CACHE_PREFIX . $workflowId, $workflow, now()->addDays(30));
    }

    /**
     * Determine risk category from profile.
     */
    private function determineRiskCategory(?CustomerRiskProfile $profile): string
    {
        if ($profile === null) {
            return 'high_risk';
        }

        if ($profile->is_pep) {
            return 'pep';
        }

        if (($profile->overall_risk_score ?? 0) >= 80) {
            return 'high_risk';
        }

        return 'high_value';
    }

    /**
     * Determine risk level from score.
     */
    private function determineRiskLevel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'low',
            $score >= 60 => 'medium',
            $score >= 40 => 'high',
            default      => 'critical',
        };
    }

    /**
     * Generate recommendation based on assessment.
     *
     * @param array<string, mixed> $workflow
     */
    private function generateRecommendation(float $score, array $workflow): string
    {
        $pendingDocs = array_diff(
            $workflow['required_documents'] ?? [],
            array_keys($workflow['collected_documents'] ?? [])
        );

        if (! empty($pendingDocs)) {
            return 'Collect pending documents: ' . implode(', ', $pendingDocs);
        }

        return match (true) {
            $score >= 80 => 'Approve - Low risk profile with complete documentation',
            $score >= 60 => 'Review - Moderate risk, recommend manual review',
            $score >= 40 => 'Escalate - High risk, senior compliance review required',
            default      => 'Reject - Critical risk level, immediate escalation required',
        };
    }
}
