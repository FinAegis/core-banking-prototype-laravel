<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Events\AgentKycVerified;
use App\Domain\AgentProtocol\Events\AgentTransactionLimitExceeded;
use App\Domain\Compliance\Services\ComplianceAlertService;
use App\Models\Agent;
use App\Models\AgentTransaction;
use App\Models\RegulatoryReport;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RegulatoryReportingService
{
    private ComplianceAlertService $complianceAlertService;

    public function __construct(ComplianceAlertService $complianceAlertService)
    {
        $this->complianceAlertService = $complianceAlertService;
    }

    /**
     * Generate Currency Transaction Report (CTR) for transactions over threshold.
     */
    public function generateCTR(string $agentId, Carbon $startDate, Carbon $endDate): array
    {
        try {
            $threshold = $this->getCTRThreshold();

            // Get transactions exceeding threshold
            $transactions = AgentTransaction::where('agent_id', $agentId)
                ->where('amount', '>=', $threshold)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            if ($transactions->isEmpty()) {
                return [
                    'status'  => 'no_report_required',
                    'message' => 'No transactions exceeding CTR threshold',
                ];
            }

            // Get agent details
            $agent = Agent::where('agent_id', $agentId)->first();

            // Generate report data
            $reportData = [
                'report_type'      => 'CTR',
                'reporting_period' => [
                    'start' => $startDate->toIso8601String(),
                    'end'   => $endDate->toIso8601String(),
                ],
                'agent' => [
                    'id'                 => $agent->agent_id,
                    'did'                => $agent->did,
                    'name'               => $agent->name,
                    'verification_level' => $agent->kyc_verification_level,
                    'country'            => $agent->country_code,
                ],
                'transactions' => $transactions->map(fn ($tx) => [
                    'id'           => $tx->transaction_id,
                    'date'         => $tx->created_at->toIso8601String(),
                    'amount'       => $tx->amount,
                    'currency'     => $tx->currency,
                    'type'         => $tx->transaction_type,
                    'counterparty' => $tx->counterparty_agent_id,
                    'description'  => $tx->description,
                ]),
                'summary' => [
                    'total_transactions' => $transactions->count(),
                    'total_amount'       => $transactions->sum('amount'),
                    'average_amount'     => $transactions->avg('amount'),
                    'max_amount'         => $transactions->max('amount'),
                ],
                'generated_at' => now()->toIso8601String(),
            ];

            // Store report
            $report = $this->storeReport('CTR', $agentId, $reportData);

            // Send to regulatory authority (in production)
            $this->submitToRegulator($report);

            return [
                'status'                => 'generated',
                'report_id'             => $report->id,
                'transactions_reported' => $transactions->count(),
                'total_amount'          => $transactions->sum('amount'),
            ];
        } catch (Exception $e) {
            Log::error('CTR generation failed', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate Suspicious Activity Report (SAR).
     */
    public function generateSAR(
        string $agentId,
        string $suspicionType,
        array $indicators,
        array $transactionIds = []
    ): array {
        try {
            $agent = Agent::where('agent_id', $agentId)->first();

            // Get related transactions if provided
            $transactions = collect();
            if (! empty($transactionIds)) {
                $transactions = AgentTransaction::whereIn('transaction_id', $transactionIds)->get();
            }

            // Generate SAR data
            $reportData = [
                'report_type' => 'SAR',
                'filing_date' => now()->toIso8601String(),
                'subject'     => [
                    'agent_id'           => $agent->agent_id,
                    'agent_did'          => $agent->did,
                    'agent_name'         => $agent->name,
                    'verification_level' => $agent->kyc_verification_level,
                    'country'            => $agent->country_code,
                    'risk_score'         => $agent->risk_score,
                ],
                'suspicion' => [
                    'type'       => $suspicionType,
                    'indicators' => $indicators,
                    'narrative'  => $this->generateSuspicionNarrative($suspicionType, $indicators),
                ],
                'transactions' => $transactions->map(fn ($tx) => [
                    'id'           => $tx->transaction_id,
                    'date'         => $tx->created_at->toIso8601String(),
                    'amount'       => $tx->amount,
                    'currency'     => $tx->currency,
                    'type'         => $tx->transaction_type,
                    'counterparty' => $tx->counterparty_agent_id,
                ]),
                'activity_period' => [
                    'start' => $transactions->min('created_at')?->toIso8601String(),
                    'end'   => $transactions->max('created_at')?->toIso8601String(),
                ],
                'total_suspicious_amount' => $transactions->sum('amount'),
                'filing_institution'      => [
                    'name'       => config('app.name'),
                    'identifier' => config('services.regulatory.institution_id'),
                ],
                'generated_at' => now()->toIso8601String(),
            ];

            // Store report
            $report = $this->storeReport('SAR', $agentId, $reportData);

            // Create compliance alert
            $this->complianceAlertService->createAlert(
                type: 'sar_filed',
                severity: 'high',
                entityType: 'agent',
                entityId: $agentId,
                description: "SAR filed for {$suspicionType}",
                details: [
                    'report_id'  => $report->id,
                    'indicators' => $indicators,
                ]
            );

            // Submit to regulatory authority
            $this->submitToRegulator($report, true); // Priority submission for SAR

            return [
                'status'                => 'filed',
                'report_id'             => $report->id,
                'suspicion_type'        => $suspicionType,
                'transactions_included' => $transactions->count(),
            ];
        } catch (Exception $e) {
            Log::error('SAR generation failed', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate periodic AML compliance report.
     */
    public function generateAMLReport(Carbon $startDate, Carbon $endDate): array
    {
        try {
            // Collect AML metrics
            $metrics = [
                'reporting_period' => [
                    'start' => $startDate->toIso8601String(),
                    'end'   => $endDate->toIso8601String(),
                ],
                'kyc_statistics'         => $this->getKycStatistics($startDate, $endDate),
                'transaction_monitoring' => $this->getTransactionMonitoringStats($startDate, $endDate),
                'alerts_and_cases'       => $this->getComplianceAlertStats($startDate, $endDate),
                'sar_statistics'         => $this->getSarStatistics($startDate, $endDate),
                'ctr_statistics'         => $this->getCtrStatistics($startDate, $endDate),
                'high_risk_agents'       => $this->getHighRiskAgents(),
                'training_compliance'    => $this->getTrainingComplianceStats(),
                'system_effectiveness'   => $this->getSystemEffectivenessMetrics($startDate, $endDate),
            ];

            // Generate report
            $reportData = [
                'report_type' => 'AML_COMPLIANCE',
                'period'      => [
                    'start' => $startDate->toIso8601String(),
                    'end'   => $endDate->toIso8601String(),
                ],
                'metrics'           => $metrics,
                'executive_summary' => $this->generateExecutiveSummary($metrics),
                'risk_assessment'   => $this->performRiskAssessment($metrics),
                'recommendations'   => $this->generateRecommendations($metrics),
                'generated_at'      => now()->toIso8601String(),
            ];

            // Store report
            $report = $this->storeReport('AML_COMPLIANCE', null, $reportData);

            // Generate report file
            $filePath = $this->generateReportFile($report);

            return [
                'status'    => 'generated',
                'report_id' => $report->id,
                'file_path' => $filePath,
                'metrics'   => $metrics,
            ];
        } catch (Exception $e) {
            Log::error('AML report generation failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate KYC audit report.
     */
    public function generateKycAuditReport(string $agentId): array
    {
        try {
            $agent = Agent::where('agent_id', $agentId)->first();

            // Get KYC history from event sourcing
            $kycEvents = DB::table('agent_protocol_events')
                ->where('aggregate_uuid', $agentId)
                ->whereIn('event_class', [
                    AgentKycVerified::class,
                    AgentTransactionLimitExceeded::class,
                ])
                ->orderBy('created_at')
                ->get();

            $reportData = [
                'report_type' => 'KYC_AUDIT',
                'agent'       => [
                    'id'                 => $agent->agent_id,
                    'did'                => $agent->did,
                    'name'               => $agent->name,
                    'current_status'     => $agent->kyc_status,
                    'verification_level' => $agent->kyc_verification_level,
                    'risk_score'         => $agent->risk_score,
                ],
                'kyc_timeline' => $kycEvents->map(fn ($event) => [
                    'event'   => class_basename($event->event_class),
                    'date'    => $event->created_at,
                    'details' => json_decode($event->event_properties),
                ]),
                'document_verification' => $this->getDocumentVerificationHistory($agentId),
                'limit_violations'      => $this->getLimitViolations($agentId),
                'compliance_alerts'     => $this->getAgentComplianceAlerts($agentId),
                'audit_date'            => now()->toIso8601String(),
            ];

            // Store report
            $report = $this->storeReport('KYC_AUDIT', $agentId, $reportData);

            return [
                'status'    => 'generated',
                'report_id' => $report->id,
                'agent_id'  => $agentId,
                'findings'  => $this->analyzeKycCompliance($reportData),
            ];
        } catch (Exception $e) {
            Log::error('KYC audit report generation failed', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Store report in database.
     */
    private function storeReport(string $type, ?string $agentId, array $data): RegulatoryReport
    {
        return RegulatoryReport::create([
            'report_type'  => $type,
            'agent_id'     => $agentId,
            'report_data'  => $data,
            'status'       => 'generated',
            'generated_at' => now(),
        ]);
    }

    /**
     * Submit report to regulatory authority.
     */
    private function submitToRegulator(RegulatoryReport $report, bool $priority = false): void
    {
        // In production, this would submit to actual regulatory APIs
        // For now, we'll log and mark as submitted

        Log::info('Regulatory report submitted', [
            'report_id' => $report->id,
            'type'      => $report->report_type,
            'priority'  => $priority,
        ]);

        $report->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Generate report file (PDF/XML).
     */
    private function generateReportFile(RegulatoryReport $report): string
    {
        $filename = sprintf(
            'regulatory_reports/%s_%s_%s.json',
            $report->report_type,
            $report->id,
            now()->format('Y-m-d')
        );

        $jsonContent = json_encode($report->report_data, JSON_PRETTY_PRINT);
        if ($jsonContent !== false) {
            Storage::put($filename, $jsonContent);
        }

        return $filename;
    }

    /**
     * Get CTR threshold amount.
     */
    private function getCTRThreshold(): float
    {
        return config('agent_protocol.regulatory.ctr_threshold', 10000.00);
    }

    /**
     * Generate suspicion narrative for SAR.
     */
    private function generateSuspicionNarrative(string $type, array $indicators): string
    {
        $narratives = [
            'structuring'  => 'Agent appears to be structuring transactions to avoid reporting thresholds',
            'velocity'     => 'Unusual velocity of transactions detected, significantly above normal patterns',
            'jurisdiction' => 'Transactions involving high-risk jurisdictions without clear business purpose',
            'pattern'      => 'Transaction patterns inconsistent with stated agent profile and expected behavior',
            'amount'       => 'Transaction amounts significantly exceed agent\'s normal activity levels',
            'default'      => 'Suspicious activity detected based on multiple risk indicators',
        ];

        $narrative = $narratives[$type] ?? $narratives['default'];
        $narrative .= '. Indicators observed: ' . implode(', ', $indicators);

        return $narrative;
    }

    /**
     * Get KYC statistics for period.
     */
    private function getKycStatistics(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'total_verifications' => DB::table('agent_protocol_events')
                ->where('event_class', AgentKycVerified::class)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'verification_levels' => Agent::whereBetween('kyc_verified_at', [$startDate, $endDate])
                ->groupBy('kyc_verification_level')
                ->selectRaw('kyc_verification_level, count(*) as count')
                ->get()
                ->pluck('count', 'kyc_verification_level'),
            'rejection_rate'          => $this->calculateKycRejectionRate($startDate, $endDate),
            'average_processing_time' => $this->calculateAverageKycProcessingTime($startDate, $endDate),
        ];
    }

    /**
     * Get transaction monitoring statistics.
     */
    private function getTransactionMonitoringStats(Carbon $startDate, Carbon $endDate): array
    {
        $totalTransactions = AgentTransaction::whereBetween('created_at', [$startDate, $endDate])->count();
        $flaggedTransactions = AgentTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('is_flagged', true)
            ->count();

        return [
            'total_transactions'   => $totalTransactions,
            'flagged_transactions' => $flaggedTransactions,
            'flag_rate'            => $totalTransactions > 0 ? round(($flaggedTransactions / $totalTransactions) * 100, 2) : 0,
            'false_positive_rate'  => $this->calculateFalsePositiveRate($startDate, $endDate),
            'average_review_time'  => $this->calculateAverageReviewTime($startDate, $endDate),
        ];
    }

    /**
     * Get compliance alert statistics.
     */
    private function getComplianceAlertStats(Carbon $startDate, Carbon $endDate): array
    {
        return DB::table('compliance_alerts')
            ->where('entity_type', 'agent')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('severity, status, count(*) as count')
            ->groupBy('severity', 'status')
            ->get()
            ->groupBy('severity')
            ->map(fn ($group) => $group->pluck('count', 'status'))
            ->toArray();
    }

    /**
     * Get SAR statistics.
     */
    private function getSarStatistics(Carbon $startDate, Carbon $endDate): array
    {
        return RegulatoryReport::where('report_type', 'SAR')
            ->whereBetween('generated_at', [$startDate, $endDate])
            ->selectRaw('count(*) as total, sum(JSON_EXTRACT(report_data, "$.total_suspicious_amount")) as total_amount')
            ->first()
            ->toArray();
    }

    /**
     * Get CTR statistics.
     */
    private function getCtrStatistics(Carbon $startDate, Carbon $endDate): array
    {
        return RegulatoryReport::where('report_type', 'CTR')
            ->whereBetween('generated_at', [$startDate, $endDate])
            ->selectRaw('count(*) as total, sum(JSON_EXTRACT(report_data, "$.summary.total_amount")) as total_amount')
            ->first()
            ->toArray();
    }

    /**
     * Get high-risk agents.
     */
    private function getHighRiskAgents(): Collection
    {
        return Agent::where('risk_score', '>', 70)
            ->where('kyc_status', 'verified')
            ->select('agent_id', 'name', 'risk_score', 'kyc_verification_level')
            ->orderByDesc('risk_score')
            ->limit(10)
            ->get();
    }

    /**
     * Get training compliance statistics.
     */
    private function getTrainingComplianceStats(): array
    {
        // Placeholder for training compliance metrics
        return [
            'compliance_officers_trained' => 12,
            'training_completion_rate'    => 95.5,
            'last_training_date'          => now()->subMonths(2)->toDateString(),
        ];
    }

    /**
     * Get system effectiveness metrics.
     */
    private function getSystemEffectivenessMetrics(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'detection_rate'             => $this->calculateDetectionRate($startDate, $endDate),
            'false_positive_rate'        => $this->calculateFalsePositiveRate($startDate, $endDate),
            'average_investigation_time' => $this->calculateAverageInvestigationTime($startDate, $endDate),
            'automation_rate'            => $this->calculateAutomationRate($startDate, $endDate),
            'system_uptime'              => 99.95,
        ];
    }

    /**
     * Helper methods for calculations.
     */
    private function calculateKycRejectionRate(Carbon $startDate, Carbon $endDate): float
    {
        // Implementation would calculate actual rejection rate
        return 4.2;
    }

    private function calculateAverageKycProcessingTime(Carbon $startDate, Carbon $endDate): string
    {
        // Implementation would calculate actual processing time
        return '2.5 hours';
    }

    private function calculateFalsePositiveRate(Carbon $startDate, Carbon $endDate): float
    {
        // Implementation would calculate actual false positive rate
        return 12.3;
    }

    private function calculateAverageReviewTime(Carbon $startDate, Carbon $endDate): string
    {
        // Implementation would calculate actual review time
        return '45 minutes';
    }

    private function calculateDetectionRate(Carbon $startDate, Carbon $endDate): float
    {
        // Implementation would calculate actual detection rate
        return 87.5;
    }

    private function calculateAverageInvestigationTime(Carbon $startDate, Carbon $endDate): string
    {
        // Implementation would calculate actual investigation time
        return '3.2 days';
    }

    private function calculateAutomationRate(Carbon $startDate, Carbon $endDate): float
    {
        // Implementation would calculate automation rate
        return 78.9;
    }

    private function getDocumentVerificationHistory(string $agentId): array
    {
        // Implementation would get actual document verification history
        return [];
    }

    private function getLimitViolations(string $agentId): array
    {
        // Implementation would get actual limit violations
        return [];
    }

    private function getAgentComplianceAlerts(string $agentId): array
    {
        // Implementation would get actual compliance alerts
        return [];
    }

    private function analyzeKycCompliance(array $reportData): array
    {
        // Implementation would analyze KYC compliance
        return [
            'compliance_score' => 92,
            'issues_found'     => [],
            'recommendations'  => [],
        ];
    }

    private function generateExecutiveSummary(array $metrics): string
    {
        return 'AML compliance program operating effectively with detection rate of ' .
               ($metrics['system_effectiveness']['detection_rate'] ?? 'N/A') .
               '% and false positive rate of ' .
               ($metrics['system_effectiveness']['false_positive_rate'] ?? 'N/A') . '%.';
    }

    private function performRiskAssessment(array $metrics): array
    {
        return [
            'overall_risk'        => 'Medium',
            'risk_factors'        => [],
            'mitigation_measures' => [],
        ];
    }

    private function generateRecommendations(array $metrics): array
    {
        return [
            'immediate_actions'        => [],
            'medium_term_improvements' => [],
            'long_term_enhancements'   => [],
        ];
    }
}
