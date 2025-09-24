<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services\Integration;

use App\Domain\AgentProtocol\Aggregates\AgentComplianceAggregate;
use App\Domain\AgentProtocol\DataObjects\KycVerificationRequest;
use App\Domain\AgentProtocol\Events\Integration\AgentComplianceLinked;
use App\Domain\AgentProtocol\Events\Integration\AgentTransactionMonitored;
use App\Domain\AgentProtocol\Events\Integration\KycStatusSynchronized;
use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Models\AgentCompliance;
use App\Domain\AgentProtocol\Workflows\AgentKycWorkflow;
use App\Domain\Compliance\Services\AmlScreeningService;
use App\Domain\Compliance\Services\KycService;
use App\Domain\Compliance\Services\TransactionMonitoringService;
use App\Models\User;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

/**
 * Integration service to connect Agent Protocol with existing KYC/AML workflows.
 *
 * This service provides:
 * - Agent KYC integration with main compliance system
 * - Transaction monitoring across both systems
 * - AML screening for agent transactions
 * - Regulatory reporting consolidation
 */
class ComplianceIntegrationService
{
    public function __construct(
        private readonly KycService $kycService,
        private readonly AmlScreeningService $amlService,
        private readonly TransactionMonitoringService $monitoringService
    ) {
    }

    /**
     * Link agent compliance profile with main KYC system.
     */
    public function linkAgentCompliance(
        string $agentId,
        string $customerId,
        array $options = []
    ): array {
        try {
            DB::beginTransaction();

            // Get or create agent compliance record
            $agentCompliance = AgentCompliance::firstOrCreate(
                ['agent_id' => $agentId],
                [
                    'compliance_id' => 'comp_' . Str::uuid()->toString(),
                    'status'        => 'pending',
                    'level'         => 'basic',
                    'risk_score'    => 0,
                    'metadata'      => [],
                ]
            );

            // Link to main customer KYC profile
            $agentCompliance->linked_customer_id = $customerId;
            $agentCompliance->linked_at = now();
            $agentCompliance->link_metadata = array_merge($agentCompliance->link_metadata ?? [], [
                'link_type'        => $options['link_type'] ?? 'standard',
                'sync_enabled'     => $options['sync_enabled'] ?? true,
                'monitoring_level' => $options['monitoring_level'] ?? 'standard',
            ]);
            $agentCompliance->save();

            // Sync KYC status from main system
            $customer = User::find($customerId);
            if ($customer) {
                $kycStatus = $this->kycService->getKycStatus($customer);
                if ($kycStatus && is_array($kycStatus)) {
                    $this->syncKycStatus($agentId, $kycStatus);
                }
            }

            // Emit integration event
            Event::dispatch(new AgentComplianceLinked(
                agentId: $agentId,
                customerId: $customerId,
                linkType: $options['link_type'] ?? 'standard',
                metadata: $agentCompliance->link_metadata
            ));

            DB::commit();

            Log::info('Agent compliance linked to main KYC', [
                'agent_id'    => $agentId,
                'customer_id' => $customerId,
                'kyc_status'  => $kycStatus['status'] ?? 'unknown',
            ]);

            return [
                'success'       => true,
                'agent_id'      => $agentId,
                'customer_id'   => $customerId,
                'compliance_id' => $agentCompliance->compliance_id,
                'kyc_status'    => $kycStatus['status'] ?? 'pending',
                'linked_at'     => $agentCompliance->linked_at->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to link agent compliance', [
                'error'       => $e->getMessage(),
                'agent_id'    => $agentId,
                'customer_id' => $customerId,
            ]);
            throw $e;
        }
    }

    /**
     * Run unified KYC verification for agent using main system.
     */
    public function runUnifiedKycVerification(
        string $agentId,
        array $documents,
        array $options = []
    ): array {
        try {
            DB::beginTransaction();

            $agentCompliance = AgentCompliance::where('agent_id', $agentId)->firstOrFail();

            if (! $agentCompliance->linked_customer_id) {
                throw new Exception('Agent not linked to customer profile');
            }

            // Run main system KYC verification
            $mainKycResult = $this->kycService->verifyIdentity(
                customerId: $agentCompliance->linked_customer_id,
                documents: $documents,
                options: array_merge($options, [
                    'source'   => 'agent_protocol',
                    'agent_id' => $agentId,
                ])
            );

            // Update agent compliance with results
            $aggregate = AgentComplianceAggregate::retrieve($agentCompliance->compliance_id);

            // Use verifyKyc method based on the result status
            if ($mainKycResult['status'] === 'verified' || $mainKycResult['status'] === 'approved') {
                $aggregate->verifyKyc(
                    verificationResults: $mainKycResult,
                    riskScore: (int) ($mainKycResult['risk_score'] ?? 0),
                    expiresAt: now()->addYear(),
                    complianceFlags: $mainKycResult['compliance_flags'] ?? []
                );
            } elseif ($mainKycResult['status'] === 'rejected' || $mainKycResult['status'] === 'failed') {
                $aggregate->rejectKyc(
                    reason: $mainKycResult['reason'] ?? 'Verification failed',
                    failedChecks: $mainKycResult['failed_checks'] ?? []
                );
            }

            $aggregate->persist();

            // Run agent-specific workflow if needed
            if ($options['run_agent_workflow'] ?? false) {
                $workflow = WorkflowStub::make(AgentKycWorkflow::class);

                // Get agent details for KYC request
                $agent = Agent::where('agent_id', $agentId)->first();

                $request = new KycVerificationRequest(
                    agentId: $agentId,
                    agentDid: $agent->did ?? 'did:ap:' . $agentId,
                    agentName: $agent->name ?? 'Agent ' . $agentId,
                    verificationLevel: \App\Domain\AgentProtocol\Enums\KycVerificationLevel::from(
                        $mainKycResult['verification_level'] ?? 'basic'
                    ),
                    documents: $documents,
                    countryCode: $options['country_code'] ?? 'US',
                    enableBiometric: $options['enable_biometric'] ?? false,
                    businessName: $options['business_name'] ?? null,
                    metadata: $mainKycResult
                );
                $workflow->start($request);
            }

            // Set transaction limits based on KYC level
            $this->setTransactionLimits($agentId, $mainKycResult['verification_level'] ?? 'basic');

            DB::commit();

            return [
                'success'            => true,
                'agent_id'           => $agentId,
                'kyc_status'         => $mainKycResult['status'],
                'verification_level' => $mainKycResult['verification_level'] ?? 'basic',
                'risk_score'         => $mainKycResult['risk_score'] ?? 0,
                'limits_set'         => true,
                'timestamp'          => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to run unified KYC verification', [
                'error'    => $e->getMessage(),
                'agent_id' => $agentId,
            ]);
            throw $e;
        }
    }

    /**
     * Monitor agent transaction across both systems.
     */
    public function monitorAgentTransaction(
        string $transactionId,
        string $agentId,
        float $amount,
        string $type,
        array $metadata = []
    ): array {
        try {
            $monitoringResult = [
                'transaction_id'   => $transactionId,
                'agent_id'         => $agentId,
                'checks_performed' => [],
                'risk_score'       => 0,
                'flags'            => [],
                'action_required'  => false,
            ];

            // Get agent compliance profile
            $agentCompliance = AgentCompliance::where('agent_id', $agentId)->first();

            // Run main system transaction monitoring
            if ($agentCompliance && $agentCompliance->linked_customer_id) {
                $mainMonitoring = $this->monitoringService->analyzeTransaction([
                    'customer_id' => $agentCompliance->linked_customer_id,
                    'amount'      => $amount,
                    'type'        => $type,
                    'metadata'    => array_merge($metadata, [
                        'source'   => 'agent_protocol',
                        'agent_id' => $agentId,
                    ]),
                ]);

                $monitoringResult['checks_performed'][] = 'main_system_monitoring';
                $monitoringResult['risk_score'] = $mainMonitoring['risk_score'] ?? 0;
                $monitoringResult['flags'] = array_merge(
                    $monitoringResult['flags'],
                    $mainMonitoring['flags'] ?? []
                );
            }

            // Run AML screening
            $amlResult = $this->amlService->screenTransaction([
                'transaction_id' => $transactionId,
                'entity_id'      => $agentId,
                'entity_type'    => 'agent',
                'amount'         => $amount,
                'currency'       => $metadata['currency'] ?? 'USD',
            ]);

            $monitoringResult['checks_performed'][] = 'aml_screening';
            if ($amlResult['matches'] ?? false) {
                $monitoringResult['flags'][] = 'aml_match';
                $monitoringResult['risk_score'] = max(
                    $monitoringResult['risk_score'],
                    $amlResult['risk_score'] ?? 50
                );
            }

            // Check velocity limits
            $velocityCheck = $this->checkVelocityLimits($agentId, $amount, $type);
            $monitoringResult['checks_performed'][] = 'velocity_limits';
            if (! $velocityCheck['passed']) {
                $monitoringResult['flags'][] = 'velocity_exceeded';
                $monitoringResult['risk_score'] = max(
                    $monitoringResult['risk_score'],
                    30
                );
            }

            // Determine if action is required
            $monitoringResult['action_required'] =
                $monitoringResult['risk_score'] > 70 ||
                in_array('aml_match', $monitoringResult['flags']) ||
                count($monitoringResult['flags']) > 2;

            // Store monitoring result
            if ($agentCompliance) {
                // Store monitoring result in the database directly
                // since AgentComplianceAggregate doesn't have recordTransactionMonitoring method
                DB::table('agent_compliance_monitoring')->insert([
                    'id'             => Str::uuid()->toString(),
                    'compliance_id'  => $agentCompliance->compliance_id,
                    'transaction_id' => $transactionId,
                    'risk_score'     => $monitoringResult['risk_score'],
                    'flags'          => json_encode($monitoringResult['flags']),
                    'metadata'       => json_encode($monitoringResult),
                    'created_at'     => now(),
                ]);

                // Check if transaction limit was exceeded
                if ($monitoringResult['risk_score'] > 70 || count($monitoringResult['flags']) > 2) {
                    $aggregate = AgentComplianceAggregate::retrieve($agentCompliance->compliance_id);
                    $aggregate->recordLimitExceeded($amount, 'transaction');
                    $aggregate->persist();
                }
            }

            // Emit monitoring event
            Event::dispatch(new AgentTransactionMonitored(
                transactionId: $transactionId,
                agentId: $agentId,
                riskScore: $monitoringResult['risk_score'],
                flags: $monitoringResult['flags'],
                actionRequired: $monitoringResult['action_required']
            ));

            Log::info('Agent transaction monitored', [
                'transaction_id'  => $transactionId,
                'agent_id'        => $agentId,
                'risk_score'      => $monitoringResult['risk_score'],
                'action_required' => $monitoringResult['action_required'],
            ]);

            return $monitoringResult;
        } catch (Exception $e) {
            Log::error('Failed to monitor agent transaction', [
                'error'          => $e->getMessage(),
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
            ]);
            throw $e;
        }
    }

    /**
     * Generate consolidated regulatory report including agent transactions.
     */
    public function generateConsolidatedReport(
        string $reportType,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $options = []
    ): array {
        try {
            $report = [
                'report_id'    => 'report_' . Str::uuid()->toString(),
                'type'         => $reportType,
                'period_start' => $startDate->format('Y-m-d'),
                'period_end'   => $endDate->format('Y-m-d'),
                'sections'     => [],
                'statistics'   => [],
                'generated_at' => now()->toIso8601String(),
            ];

            // Get agent transaction data
            $agentTransactions = DB::table('agent_transactions')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $report['sections']['agent_transactions'] = [
                'total_count'          => $agentTransactions->count(),
                'total_volume'         => $agentTransactions->sum('amount'),
                'unique_agents'        => $agentTransactions->pluck('agent_id')->unique()->count(),
                'flagged_transactions' => $agentTransactions->where('risk_score', '>', 50)->count(),
            ];

            // Get compliance data
            $complianceData = AgentCompliance::whereBetween('updated_at', [$startDate, $endDate])
                ->get();

            $report['sections']['agent_compliance'] = [
                'total_agents'     => $complianceData->count(),
                'verified_agents'  => $complianceData->where('status', 'verified')->count(),
                'high_risk_agents' => $complianceData->where('risk_score', '>', 70)->count(),
                'pending_reviews'  => $complianceData->where('status', 'pending_review')->count(),
            ];

            // Include main system data if requested
            if ($options['include_main_system'] ?? true) {
                // This would integrate with the main regulatory reporting service
                // For now, we'll add placeholder data
                $report['sections']['main_system'] = [
                    'note' => 'Main system data would be integrated here',
                ];
            }

            // Calculate statistics
            $report['statistics'] = [
                'agent_transaction_percentage' => $agentTransactions->count() > 0
                    ? round(($agentTransactions->where('risk_score', '<', 30)->count() / $agentTransactions->count()) * 100, 2)
                    : 0,
                'average_risk_score' => round($agentTransactions->avg('risk_score') ?? 0, 2),
                'compliance_rate'    => $complianceData->count() > 0
                    ? round(($complianceData->where('status', 'verified')->count() / $complianceData->count()) * 100, 2)
                    : 0,
            ];

            Log::info('Consolidated regulatory report generated', [
                'report_id' => $report['report_id'],
                'type'      => $reportType,
                'period'    => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            ]);

            return $report;
        } catch (Exception $e) {
            Log::error('Failed to generate consolidated report', [
                'error'       => $e->getMessage(),
                'report_type' => $reportType,
            ]);
            throw $e;
        }
    }

    /**
     * Sync KYC status between systems.
     */
    private function syncKycStatus(string $agentId, array $kycStatus): void
    {
        try {
            $agentCompliance = AgentCompliance::where('agent_id', $agentId)->first();
            if (! $agentCompliance) {
                return;
            }

            $aggregate = AgentComplianceAggregate::retrieve($agentCompliance->compliance_id);

            // Use appropriate method based on status
            if ($kycStatus['status'] === 'verified' || $kycStatus['status'] === 'approved') {
                $aggregate->verifyKyc(
                    verificationResults: $kycStatus,
                    riskScore: (int) ($kycStatus['risk_score'] ?? 0),
                    expiresAt: now()->addYear(),
                    complianceFlags: $kycStatus['compliance_flags'] ?? []
                );
            } elseif ($kycStatus['status'] === 'rejected' || $kycStatus['status'] === 'failed') {
                $aggregate->rejectKyc(
                    reason: $kycStatus['reason'] ?? 'Verification failed during sync',
                    failedChecks: $kycStatus['failed_checks'] ?? []
                );
            }

            $aggregate->persist();

            Event::dispatch(new KycStatusSynchronized(
                agentId: $agentId,
                status: $kycStatus['status'],
                level: $kycStatus['level'] ?? 'basic',
                syncedAt: now()
            ));
        } catch (Exception $e) {
            Log::error('Failed to sync KYC status', [
                'error'    => $e->getMessage(),
                'agent_id' => $agentId,
            ]);
        }
    }

    /**
     * Set transaction limits based on KYC level.
     */
    private function setTransactionLimits(string $agentId, string $level): void
    {
        $limits = match ($level) {
            'enhanced' => [
                'daily_limit'       => 50000,
                'transaction_limit' => 10000,
                'monthly_limit'     => 500000,
            ],
            'standard' => [
                'daily_limit'       => 10000,
                'transaction_limit' => 2500,
                'monthly_limit'     => 100000,
            ],
            default => [
                'daily_limit'       => 1000,
                'transaction_limit' => 500,
                'monthly_limit'     => 10000,
            ],
        };

        $agentCompliance = AgentCompliance::where('agent_id', $agentId)->first();
        if ($agentCompliance) {
            $agentCompliance->transaction_limits = $limits;
            $agentCompliance->save();
        }
    }

    /**
     * Check velocity limits for agent.
     */
    private function checkVelocityLimits(string $agentId, float $amount, string $type): array
    {
        $dailyTotal = DB::table('agent_transactions')
            ->where('agent_id', $agentId)
            ->where('created_at', '>=', now()->subDay())
            ->sum('amount');

        $agentCompliance = AgentCompliance::where('agent_id', $agentId)->first();
        $limits = $agentCompliance->transaction_limits ?? [
            'daily_limit'       => 1000,
            'transaction_limit' => 500,
        ];

        $passed = $amount <= $limits['transaction_limit'] &&
                  ($dailyTotal + $amount) <= $limits['daily_limit'];

        return [
            'passed'            => $passed,
            'daily_total'       => $dailyTotal,
            'daily_limit'       => $limits['daily_limit'],
            'transaction_limit' => $limits['transaction_limit'],
            'amount'            => $amount,
        ];
    }
}
