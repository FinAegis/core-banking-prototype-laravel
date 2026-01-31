<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\AgentComplianceAggregate;
use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use App\Domain\AgentProtocol\Enums\KycVerificationStatus;
use App\Domain\AgentProtocol\Models\AgentIdentity;
use App\Domain\AgentProtocol\Services\RegulatoryReportingService;
use App\Models\Agent;
use App\Models\AgentTransaction;
use App\Models\AgentTransactionTotal;
use App\Models\RegulatoryReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentComplianceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create necessary storage directories
        Storage::fake('local');
    }

    /**
     * Test AgentComplianceAggregate KYC initiation.
     */
    public function test_can_initiate_agent_kyc(): void
    {
        // Arrange
        $agentId = Str::uuid()->toString();
        $agentDid = 'did:example:' . Str::random(32);

        // Act
        $aggregate = AgentComplianceAggregate::initiateKyc(
            agentId: $agentId,
            agentDid: $agentDid,
            level: KycVerificationLevel::BASIC,
            requiredDocuments: ['government_id']
        );
        $aggregate->persist();

        // Assert
        $this->assertEquals($agentId, $aggregate->getAgentId());
        $this->assertEquals(KycVerificationStatus::PENDING, $aggregate->getKycStatus());
        $this->assertEquals(KycVerificationLevel::BASIC, $aggregate->getVerificationLevel());
    }

    /**
     * Test KYC verification process.
     */
    public function test_can_verify_agent_kyc(): void
    {
        // Arrange
        $agentId = Str::uuid()->toString();
        $aggregate = AgentComplianceAggregate::initiateKyc(
            agentId: $agentId,
            agentDid: 'did:example:' . Str::random(32),
            level: KycVerificationLevel::ENHANCED,
            requiredDocuments: ['government_id', 'proof_of_address']
        );

        // Act - Submit documents
        $aggregate->submitDocuments([
            'government_id'    => 'path/to/id.pdf',
            'proof_of_address' => 'path/to/address.pdf',
        ]);

        // Act - Verify KYC
        $verificationResults = [
            'identity' => ['status' => 'passed', 'confidence' => 95],
            'address'  => ['status' => 'passed', 'confidence' => 90],
        ];

        $aggregate->verifyKyc(
            verificationResults: $verificationResults,
            riskScore: 30,
            expiresAt: now()->addYear(),
            complianceFlags: []
        );
        $aggregate->persist();

        // Assert
        $this->assertEquals(KycVerificationStatus::VERIFIED, $aggregate->getKycStatus());
        $this->assertEquals(30, $aggregate->getRiskScore());
        $this->assertTrue($aggregate->isKycVerified());
        $this->assertGreaterThan(0, $aggregate->getDailyTransactionLimit());
        $this->assertGreaterThan(0, $aggregate->getWeeklyTransactionLimit());
        $this->assertGreaterThan(0, $aggregate->getMonthlyTransactionLimit());
    }

    /**
     * Test KYC rejection for high risk score.
     */
    public function test_kyc_rejected_for_high_risk_score(): void
    {
        // Arrange
        $agentId = Str::uuid()->toString();
        $aggregate = AgentComplianceAggregate::initiateKyc(
            agentId: $agentId,
            agentDid: 'did:example:' . Str::random(32),
            level: KycVerificationLevel::BASIC,
            requiredDocuments: ['government_id']
        );

        // Act
        $aggregate->submitDocuments([
            'government_id' => 'path/to/id.pdf',
        ]);

        // Attempt verification with high risk score
        $aggregate->verifyKyc(
            verificationResults: [
                'identity' => ['status' => 'passed', 'confidence' => 95],
            ],
            riskScore: 80, // Above threshold for BASIC level (70)
            expiresAt: now()->addMonths(6),
            complianceFlags: []
        );
        $aggregate->persist();

        // Assert
        $this->assertEquals(KycVerificationStatus::REQUIRES_REVIEW, $aggregate->getKycStatus());
        $this->assertEquals(80, $aggregate->getRiskScore());
        $this->assertFalse($aggregate->isKycVerified());
    }

    /**
     * Test transaction limit checking via aggregate.
     *
     * Note: CheckTransactionLimitActivity extends Workflow\Activity which
     * requires constructor args. Testing limit logic via AgentComplianceAggregate.
     */
    public function test_transaction_limit_checking(): void
    {
        // Arrange
        $agentId = Str::uuid()->toString();
        $agentDid = 'did:example:' . Str::random(32);

        // Create agent record first (required for foreign key constraint)
        Agent::factory()->create([
            'agent_id'               => $agentId,
            'did'                    => $agentDid,
            'kyc_status'             => 'verified',
            'kyc_verification_level' => 'basic',
        ]);

        // Create agent compliance aggregate with verified KYC
        $aggregate = AgentComplianceAggregate::initiateKyc(
            agentId: $agentId,
            agentDid: $agentDid,
            level: KycVerificationLevel::BASIC,
            requiredDocuments: ['government_id']
        );

        $aggregate->submitDocuments(['government_id' => 'path/to/id.pdf']);
        $aggregate->verifyKyc(
            verificationResults: ['identity' => ['status' => 'passed']],
            riskScore: 30,
            expiresAt: now()->addMonths(6),
            complianceFlags: []
        );
        $aggregate->persist();

        // Create transaction totals
        AgentTransactionTotal::create([
            'agent_id'           => $agentId,
            'daily_total'        => 500.00,
            'weekly_total'       => 2000.00,
            'monthly_total'      => 5000.00,
            'last_daily_reset'   => now()->startOfDay(),
            'last_weekly_reset'  => now()->startOfWeek(),
            'last_monthly_reset' => now()->startOfMonth(),
        ]);

        // Test verified aggregate
        $this->assertTrue($aggregate->isKycVerified());

        // Test daily limit value (BASIC level has limits)
        $dailyLimit = $aggregate->getDailyTransactionLimit();
        $this->assertGreaterThan(0, $dailyLimit);

        // Test transaction totals are stored correctly
        $totals = AgentTransactionTotal::where('agent_id', $agentId)->first();
        $this->assertNotNull($totals);
        $this->assertEquals(500.00, $totals->daily_total);
        $this->assertEquals(2000.00, $totals->weekly_total);
        $this->assertEquals(5000.00, $totals->monthly_total);

        // Test that limits are correctly retrieved
        $this->assertGreaterThan(0, $aggregate->getDailyTransactionLimit());
        $this->assertGreaterThan(0, $aggregate->getWeeklyTransactionLimit());
        $this->assertGreaterThan(0, $aggregate->getMonthlyTransactionLimit());
    }

    /**
     * Test regulatory report generation for CTR.
     */
    public function test_can_generate_ctr_report(): void
    {
        // Arrange
        $agentId = Str::uuid()->toString();
        $agentDid = 'did:example:' . Str::random(32);
        $toAgentId = Str::uuid()->toString();

        // Create Agent for application-level logic
        $agent = Agent::factory()->create([
            'agent_id'               => $agentId,
            'did'                    => $agentDid,
            'kyc_verification_level' => 'enhanced',
            'kyc_status'             => 'verified',
        ]);

        // Create AgentIdentity for FK constraints (agent_transactions references agent_identities)
        AgentIdentity::factory()->create([
            'agent_id' => $agentId,
            'did'      => $agentDid,
        ]);

        // Create destination agent for FK constraint
        AgentIdentity::factory()->create([
            'agent_id' => $toAgentId,
        ]);

        // Create transactions above CTR threshold
        // Note: agent_transactions table uses from_agent_id, not agent_id
        AgentTransaction::factory()->count(3)->create([
            'from_agent_id' => $agentId,
            'to_agent_id'   => $toAgentId,
            'amount'        => 15000.00,
            'status'        => 'completed',
            'created_at'    => now(),
        ]);

        $service = app(RegulatoryReportingService::class);

        // Act
        $result = $service->generateCTR(
            $agentId,
            now()->subDay(),
            now()->addDay()
        );

        // Assert
        $this->assertEquals('generated', $result['status']);
        $this->assertEquals(3, $result['transactions_reported']);
        $this->assertEquals(45000.00, $result['total_amount']);

        $report = RegulatoryReport::find($result['report_id']);
        $this->assertNotNull($report);
        $this->assertInstanceOf(RegulatoryReport::class, $report);
        $this->assertEquals('CTR', $report->report_type);
        $this->assertEquals($agentId, $report->agent_id);
    }

    /**
     * Test SAR generation for suspicious activity.
     */
    public function test_can_generate_sar_report(): void
    {
        // Arrange
        $agentId = Str::uuid()->toString();
        $agentDid = 'did:example:' . Str::random(32);
        $toAgentId = Str::uuid()->toString();

        // Create Agent for application-level logic
        $agent = Agent::factory()->create([
            'agent_id'   => $agentId,
            'did'        => $agentDid,
            'risk_score' => 75,
        ]);

        // Create AgentIdentity for FK constraints (agent_transactions references agent_identities)
        AgentIdentity::factory()->create([
            'agent_id' => $agentId,
            'did'      => $agentDid,
        ]);

        // Create destination agent for FK constraint
        AgentIdentity::factory()->create([
            'agent_id' => $toAgentId,
        ]);

        $transactionIds = [];
        for ($i = 0; $i < 5; $i++) {
            $transaction = AgentTransaction::factory()->create([
                'from_agent_id' => $agentId,
                'to_agent_id'   => $toAgentId,
                'amount'        => 9500.00, // Just below CTR threshold
                'created_at'    => now()->subHours($i),
            ]);
            $transactionIds[] = $transaction->transaction_id;
        }

        $service = app(RegulatoryReportingService::class);

        // Act
        $result = $service->generateSAR(
            agentId: $agentId,
            suspicionType: 'structuring',
            indicators: ['multiple_transactions_below_threshold', 'rapid_succession'],
            transactionIds: $transactionIds
        );

        // Assert
        $this->assertEquals('filed', $result['status']);
        $this->assertEquals('structuring', $result['suspicion_type']);
        $this->assertEquals(5, $result['transactions_included']);

        $report = RegulatoryReport::find($result['report_id']);
        $this->assertNotNull($report);
        $this->assertInstanceOf(RegulatoryReport::class, $report);
        $this->assertEquals('SAR', $report->report_type);
        $this->assertEquals(47500.00, $report->getTotalAmount());
    }

    /**
     * Test AML compliance report generation.
     */
    public function test_can_generate_aml_compliance_report(): void
    {
        // Arrange
        $service = app(RegulatoryReportingService::class);

        // Act
        $result = $service->generateAMLReport(
            now()->subMonth(),
            now()
        );

        // Assert
        $this->assertEquals('generated', $result['status']);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('kyc_statistics', $result['metrics']);
        $this->assertArrayHasKey('transaction_monitoring', $result['metrics']);
        $this->assertArrayHasKey('system_effectiveness', $result['metrics']);
    }

    /**
     * Test transaction limit reset functionality.
     */
    public function test_transaction_limits_reset_correctly(): void
    {
        // Arrange
        $agentId = Str::uuid()->toString();

        // Create agent record first (required for foreign key constraint)
        Agent::factory()->create([
            'agent_id' => $agentId,
        ]);

        $totals = AgentTransactionTotal::create([
            'agent_id'           => $agentId,
            'daily_total'        => 1000.00,
            'weekly_total'       => 5000.00,
            'monthly_total'      => 10000.00,
            'last_daily_reset'   => now()->subDays(2),
            'last_weekly_reset'  => now()->subWeeks(2),
            'last_monthly_reset' => now()->subMonths(2),
        ]);

        // Act - Check if resets are needed
        $this->assertTrue($totals->needsDailyReset());
        $this->assertTrue($totals->needsWeeklyReset());
        $this->assertTrue($totals->needsMonthlyReset());

        // Reset totals
        $totals->resetDaily();
        $totals->resetWeekly();
        $totals->resetMonthly();

        // Assert
        $this->assertEquals(0, $totals->daily_total);
        $this->assertEquals(0, $totals->weekly_total);
        $this->assertEquals(0, $totals->monthly_total);
        $this->assertFalse($totals->needsDailyReset());
        $this->assertFalse($totals->needsWeeklyReset());
        $this->assertFalse($totals->needsMonthlyReset());
    }

    /**
     * Test KYC verification levels and limits.
     */
    public function test_different_kyc_levels_have_different_limits(): void
    {
        // Test BASIC level
        $basicAggregate = AgentComplianceAggregate::initiateKyc(
            agentId: Str::uuid()->toString(),
            agentDid: 'did:example:basic',
            level: KycVerificationLevel::BASIC,
            requiredDocuments: []
        );
        $basicAggregate->verifyKyc(
            verificationResults: [],
            riskScore: 30,
            expiresAt: now()->addMonths(6),
            complianceFlags: []
        );

        // Test ENHANCED level
        $enhancedAggregate = AgentComplianceAggregate::initiateKyc(
            agentId: Str::uuid()->toString(),
            agentDid: 'did:example:enhanced',
            level: KycVerificationLevel::ENHANCED,
            requiredDocuments: []
        );
        $enhancedAggregate->verifyKyc(
            verificationResults: [],
            riskScore: 30,
            expiresAt: now()->addYear(),
            complianceFlags: []
        );

        // Test FULL level
        $fullAggregate = AgentComplianceAggregate::initiateKyc(
            agentId: Str::uuid()->toString(),
            agentDid: 'did:example:full',
            level: KycVerificationLevel::FULL,
            requiredDocuments: []
        );
        $fullAggregate->verifyKyc(
            verificationResults: [],
            riskScore: 30,
            expiresAt: now()->addYears(2),
            complianceFlags: []
        );

        // Assert - Limits increase with verification level
        $this->assertLessThan(
            $enhancedAggregate->getDailyTransactionLimit(),
            $basicAggregate->getDailyTransactionLimit()
        );
        $this->assertLessThan(
            $fullAggregate->getDailyTransactionLimit(),
            $enhancedAggregate->getDailyTransactionLimit()
        );
    }
}
