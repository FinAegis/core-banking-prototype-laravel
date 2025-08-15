<?php

declare(strict_types=1);

use App\Domain\Treasury\Aggregates\TreasuryAggregate;
use App\Domain\Treasury\Events\CashAllocated;
use App\Domain\Treasury\Events\RegulatoryReportGenerated;
use App\Domain\Treasury\Events\RiskAssessmentCompleted;
use App\Domain\Treasury\Events\TreasuryAccountCreated;
use App\Domain\Treasury\Events\YieldOptimizationStarted;
use App\Domain\Treasury\ValueObjects\AllocationStrategy;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->accountId = Str::uuid()->toString();
});

it('creates a treasury account with initial balance', function () {
    TreasuryAggregate::fake($this->accountId)
        ->when(function (TreasuryAggregate $aggregate) {
            $aggregate->createAccount(
                $this->accountId,
                'Main Treasury Account',
                'USD',
                'operating',
                1000000.0,
                ['region' => 'US', 'branch' => 'HQ']
            );
        })
        ->assertRecorded([
            new TreasuryAccountCreated(
                $this->accountId,
                'Main Treasury Account',
                'USD',
                'operating',
                1000000.0,
                ['region' => 'US', 'branch' => 'HQ']
            ),
        ]);
});

it('allocates cash based on strategy', function () {
    $allocationId = Str::uuid()->toString();
    $strategy = new AllocationStrategy(AllocationStrategy::BALANCED);

    TreasuryAggregate::fake($this->accountId)
        ->given([
            new TreasuryAccountCreated(
                $this->accountId,
                'Treasury Account',
                'USD',
                'operating',
                5000000.0,
                []
            ),
        ])
        ->when(function (TreasuryAggregate $aggregate) use ($allocationId, $strategy) {
            $aggregate->allocateCash(
                $allocationId,
                $strategy,
                2000000.0,
                'treasury_manager'
            );
        })
        ->assertRecorded([
            new CashAllocated(
                $this->accountId,
                $allocationId,
                AllocationStrategy::BALANCED,
                2000000.0,
                'USD',
                $strategy->getDefaultAllocations(),
                'treasury_manager'
            ),
        ]);
});

it('validates allocation amount does not exceed balance', function () {
    $aggregate = TreasuryAggregate::retrieve($this->accountId);

    $aggregate->createAccount(
        $this->accountId,
        'Treasury Account',
        'USD',
        'operating',
        1000000.0,
        []
    );

    $strategy = new AllocationStrategy(AllocationStrategy::CONSERVATIVE);

    expect(fn () => $aggregate->allocateCash(
        Str::uuid()->toString(),
        $strategy,
        2000000.0,
        'treasury_manager'
    ))->toThrow(InvalidArgumentException::class, 'Insufficient balance for allocation');
});

it('starts yield optimization with risk constraints', function () {
    $optimizationId = Str::uuid()->toString();
    $riskProfile = RiskProfile::fromScore(45.0, ['market_volatility' => 'medium']);

    TreasuryAggregate::fake($this->accountId)
        ->given([
            new TreasuryAccountCreated(
                $this->accountId,
                'Treasury Account',
                'USD',
                'operating',
                10000000.0,
                []
            ),
        ])
        ->when(function (TreasuryAggregate $aggregate) use ($optimizationId, $riskProfile) {
            $aggregate->startYieldOptimization(
                $optimizationId,
                'balanced_growth',
                6.5,
                $riskProfile,
                ['max_equity_exposure' => 0.4],
                'system'
            );
        })
        ->assertRecorded([
            new YieldOptimizationStarted(
                $this->accountId,
                $optimizationId,
                'balanced_growth',
                6.5,
                RiskProfile::MEDIUM,
                ['max_equity_exposure' => 0.4],
                'system'
            ),
        ]);
});

it('completes risk assessment and updates risk profile', function () {
    $assessmentId = Str::uuid()->toString();
    $riskProfile = RiskProfile::fromScore(
        35.0,
        ['liquidity_risk' => 'low', 'market_risk' => 'medium']
    );

    $recommendations = [
        'Maintain current allocation strategy',
        'Review risk limits quarterly',
    ];

    TreasuryAggregate::fake($this->accountId)
        ->given([
            new TreasuryAccountCreated(
                $this->accountId,
                'Treasury Account',
                'USD',
                'operating',
                5000000.0,
                []
            ),
        ])
        ->when(function (TreasuryAggregate $aggregate) use ($assessmentId, $riskProfile, $recommendations) {
            $aggregate->completeRiskAssessment(
                $assessmentId,
                $riskProfile,
                $recommendations,
                'risk_management_system'
            );
        })
        ->assertRecorded([
            new RiskAssessmentCompleted(
                $this->accountId,
                $assessmentId,
                35.0,
                RiskProfile::MEDIUM,
                ['liquidity_risk' => 'low', 'market_risk' => 'medium'],
                $recommendations,
                'risk_management_system'
            ),
        ]);
});

it('generates regulatory report with required data', function () {
    $reportId = Str::uuid()->toString();
    $reportData = [
        'capital_adequacy'   => ['ratio' => 14.5],
        'liquidity_coverage' => ['lcr' => 125.0],
        'leverage_ratio'     => ['ratio' => 5.2],
    ];

    TreasuryAggregate::fake($this->accountId)
        ->given([
            new TreasuryAccountCreated(
                $this->accountId,
                'Treasury Account',
                'USD',
                'operating',
                20000000.0,
                []
            ),
        ])
        ->when(function (TreasuryAggregate $aggregate) use ($reportId, $reportData) {
            $aggregate->generateRegulatoryReport(
                $reportId,
                'BASEL_III',
                'Q1-2025',
                $reportData,
                'regulatory_system'
            );
        })
        ->assertRecorded([
            new RegulatoryReportGenerated(
                $this->accountId,
                $reportId,
                'BASEL_III',
                'Q1-2025',
                $reportData,
                'generated',
                'regulatory_system'
            ),
        ]);
});

it('maintains event sourcing with proper aggregate state', function () {
    // Create initial account
    $aggregate = TreasuryAggregate::retrieve($this->accountId);
    $aggregate->createAccount(
        $this->accountId,
        'Treasury Account',
        'USD',
        'operating',
        15000000.0,
        []
    );
    $aggregate->persist();

    // Retrieve and perform allocation
    $aggregate = TreasuryAggregate::retrieve($this->accountId);
    $strategy = new AllocationStrategy(AllocationStrategy::CONSERVATIVE);
    $aggregate->allocateCash(
        Str::uuid()->toString(),
        $strategy,
        5000000.0,
        'treasury_manager'
    );
    $aggregate->persist();

    // Retrieve and verify state
    $aggregate = TreasuryAggregate::retrieve($this->accountId);
    expect($aggregate->getBalance())->toBe(15000000.0);
    expect($aggregate->getCurrentStrategy())->toBeInstanceOf(AllocationStrategy::class);
    expect($aggregate->getCurrentStrategy()->getValue())->toBe(AllocationStrategy::CONSERVATIVE);
});

it('uses separate treasury event storage tables', function () {
    $aggregate = TreasuryAggregate::retrieve($this->accountId);

    $aggregate->createAccount(
        $this->accountId,
        'Treasury Account',
        'USD',
        'operating',
        1000000.0,
        []
    );

    $aggregate->persist();

    // Verify event is stored in treasury_events table
    $this->assertDatabaseHas('treasury_events', [
        'aggregate_uuid' => $this->accountId,
        'event_class'    => 'treasury_account_created',
    ]);

    // Verify it's NOT in the default stored_events table
    $this->assertDatabaseMissing('stored_events', [
        'aggregate_uuid' => $this->accountId,
    ]);
});
