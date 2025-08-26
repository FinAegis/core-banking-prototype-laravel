<?php

declare(strict_types=1);

use App\Domain\Treasury\Aggregates\PortfolioAggregate;
use App\Domain\Treasury\Events\Portfolio\AllocationDriftDetected;
use App\Domain\Treasury\Events\Portfolio\AssetsAllocated;
use App\Domain\Treasury\Events\Portfolio\PerformanceRecorded;
use App\Domain\Treasury\Events\Portfolio\PortfolioCreated;
use App\Domain\Treasury\Events\Portfolio\PortfolioRebalanced;
use App\Domain\Treasury\Events\Portfolio\StrategyUpdated;
use App\Domain\Treasury\ValueObjects\AssetAllocation;
use App\Domain\Treasury\ValueObjects\InvestmentStrategy;
use App\Domain\Treasury\ValueObjects\PortfolioMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a portfolio with investment strategy', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $strategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_MODERATE,
        5.0, // 5% rebalance threshold
        0.08, // 8% target return
        ['max_single_asset' => 0.3],
        ['manager'          => 'AI Portfolio Manager']
    );

    PortfolioAggregate::fake($portfolioId)
        ->when(function (PortfolioAggregate $aggregate) use ($portfolioId, $treasuryId, $strategy) {
            $aggregate->createPortfolio(
                $portfolioId,
                $treasuryId,
                'Conservative Growth Portfolio',
                $strategy,
                ['created_by' => 'treasury_system']
            );
        })
        ->assertRecorded([
            new PortfolioCreated(
                $portfolioId,
                $treasuryId,
                'Conservative Growth Portfolio',
                $strategy->toArray(),
                ['created_by' => 'treasury_system']
            ),
        ]);
});

it('allocates assets with proper validation', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $strategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_MODERATE,
        5.0,
        0.08
    );

    $allocations = [
        ['assetClass' => 'equities', 'targetWeight' => 60.0],
        ['assetClass' => 'bonds', 'targetWeight' => 30.0],
        ['assetClass' => 'cash', 'targetWeight' => 10.0],
    ];

    $allocationId = Str::uuid()->toString();

    PortfolioAggregate::fake($portfolioId)
        ->given([
            new PortfolioCreated(
                $portfolioId,
                $treasuryId,
                'Growth Portfolio',
                $strategy->toArray(),
                []
            ),
        ])
        ->when(function (PortfolioAggregate $aggregate) use ($allocationId, $allocations) {
            $aggregate->allocateAssets(
                $allocationId,
                $allocations,
                1000000.0,
                'portfolio_manager'
            );
        })
        ->assertRecorded([
            new AssetsAllocated(
                $portfolioId,
                $allocationId,
                [
                    new AssetAllocation('equities', 60.0, 0.0, 0.0),
                    new AssetAllocation('bonds', 30.0, 0.0, 0.0),
                    new AssetAllocation('cash', 10.0, 0.0, 0.0),
                ],
                1000000.0,
                'portfolio_manager'
            ),
        ]);
});

it('validates allocations sum to 100 percent', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $strategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_CONSERVATIVE,
        3.0,
        0.05
    );

    $aggregate = PortfolioAggregate::retrieve($portfolioId);
    $aggregate->createPortfolio(
        $portfolioId,
        $treasuryId,
        'Test Portfolio',
        $strategy
    );

    $invalidAllocations = [
        ['assetClass' => 'equities', 'targetWeight' => 60.0],
        ['assetClass' => 'bonds', 'targetWeight' => 50.0], // Total = 110%
    ];

    expect(fn () => $aggregate->allocateAssets(
        Str::uuid()->toString(),
        $invalidAllocations,
        1000000.0,
        'manager'
    ))->toThrow(InvalidArgumentException::class, 'Asset allocations must sum to 100%');
});

it('rebalances portfolio with new target allocations', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $strategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_MODERATE,
        5.0,
        0.08
    );

    $currentAllocations = [
        new AssetAllocation('equities', 60.0, 65.0, 5.0),
        new AssetAllocation('bonds', 30.0, 25.0, 5.0),
        new AssetAllocation('cash', 10.0, 10.0, 0.0),
    ];

    $targetAllocations = [
        ['assetClass' => 'equities', 'targetWeight' => 55.0],
        ['assetClass' => 'bonds', 'targetWeight' => 35.0],
        ['assetClass' => 'cash', 'targetWeight' => 10.0],
    ];

    $rebalanceId = Str::uuid()->toString();

    PortfolioAggregate::fake($portfolioId)
        ->given([
            new PortfolioCreated(
                $portfolioId,
                $treasuryId,
                'Portfolio',
                $strategy->toArray(),
                []
            ),
            new AssetsAllocated(
                $portfolioId,
                Str::uuid()->toString(),
                $currentAllocations,
                1000000.0,
                'manager'
            ),
        ])
        ->when(function (PortfolioAggregate $aggregate) use ($rebalanceId, $targetAllocations) {
            $aggregate->rebalancePortfolio(
                $rebalanceId,
                $targetAllocations,
                'drift_threshold_exceeded',
                'system_rebalancer'
            );
        })
        ->assertRecorded([
            new PortfolioRebalanced(
                $portfolioId,
                $rebalanceId,
                $targetAllocations,
                $currentAllocations,
                'drift_threshold_exceeded',
                'system_rebalancer'
            ),
        ]);
});

it('updates investment strategy', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $originalStrategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_CONSERVATIVE,
        3.0,
        0.05
    );

    $newStrategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_MODERATE,
        5.0,
        0.08
    );

    PortfolioAggregate::fake($portfolioId)
        ->given([
            new PortfolioCreated(
                $portfolioId,
                $treasuryId,
                'Portfolio',
                $originalStrategy->toArray(),
                []
            ),
        ])
        ->when(function (PortfolioAggregate $aggregate) use ($newStrategy) {
            $aggregate->updateStrategy(
                $newStrategy,
                'increased_risk_tolerance',
                'portfolio_manager'
            );
        })
        ->assertRecorded([
            new StrategyUpdated(
                $portfolioId,
                $originalStrategy->toArray(),
                $newStrategy->toArray(),
                'increased_risk_tolerance',
                'portfolio_manager'
            ),
        ]);
});

it('records performance and triggers rebalancing on drift', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $strategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_MODERATE,
        5.0, // 5% threshold
        0.08
    );

    $metrics = new PortfolioMetrics(
        1100000.0, // 10% growth
        0.10, // 10% returns
        1.2, // Good Sharpe ratio
        0.15, // 15% volatility
        -0.05, // 5% max drawdown
        0.02, // 2% alpha
        1.1 // beta
    );

    $metricsId = Str::uuid()->toString();

    PortfolioAggregate::fake($portfolioId)
        ->given([
            new PortfolioCreated(
                $portfolioId,
                $treasuryId,
                'Portfolio',
                $strategy->toArray(),
                []
            ),
        ])
        ->when(function (PortfolioAggregate $aggregate) use ($metricsId, $metrics) {
            $aggregate->recordPerformance(
                $metricsId,
                $metrics,
                'monthly',
                'system'
            );
        })
        ->assertRecorded([
            new PerformanceRecorded(
                $portfolioId,
                $metricsId,
                $metrics->toArray(),
                'monthly',
                'system'
            ),
        ]);
});

it('detects allocation drift and triggers rebalancing', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $strategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_MODERATE,
        3.0, // 3% threshold
        0.08
    );

    $driftLevels = [
        ['assetClass' => 'equities', 'currentWeight' => 65.0, 'targetWeight' => 60.0, 'drift' => 5.0],
        ['assetClass' => 'bonds', 'currentWeight' => 25.0, 'targetWeight' => 30.0, 'drift' => 5.0],
        ['assetClass' => 'cash', 'currentWeight' => 10.0, 'targetWeight' => 10.0, 'drift' => 0.0],
    ];

    PortfolioAggregate::fake($portfolioId)
        ->given([
            new PortfolioCreated(
                $portfolioId,
                $treasuryId,
                'Portfolio',
                $strategy->toArray(),
                []
            ),
        ])
        ->when(function (PortfolioAggregate $aggregate) use ($driftLevels) {
            $aggregate->detectAllocationDrift($driftLevels, 'drift_monitor');
        })
        ->assertRecorded([
            new AllocationDriftDetected(
                $portfolioId,
                $driftLevels,
                5.0, // max drift
                3.0, // threshold
                'drift_monitor'
            ),
        ]);
});

it('maintains event sourcing with proper aggregate state', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $strategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_MODERATE,
        5.0,
        0.08
    );

    // Create portfolio
    $aggregate = PortfolioAggregate::retrieve($portfolioId);
    $aggregate->createPortfolio(
        $portfolioId,
        $treasuryId,
        'Growth Portfolio',
        $strategy
    );
    $aggregate->persist();

    // Allocate assets
    $aggregate = PortfolioAggregate::retrieve($portfolioId);
    $allocations = [
        ['assetClass' => 'equities', 'targetWeight' => 60.0],
        ['assetClass' => 'bonds', 'targetWeight' => 30.0],
        ['assetClass' => 'cash', 'targetWeight' => 10.0],
    ];

    $aggregate->allocateAssets(
        Str::uuid()->toString(),
        $allocations,
        1000000.0,
        'manager'
    );
    $aggregate->persist();

    // Verify state
    $aggregate = PortfolioAggregate::retrieve($portfolioId);
    expect($aggregate->getName())->toBe('Growth Portfolio');
    expect($aggregate->getTreasuryId())->toBe($treasuryId);
    expect($aggregate->getStrategy())->toBeInstanceOf(InvestmentStrategy::class);
    expect($aggregate->getTotalValue())->toBe(1000000.0);
    expect($aggregate->getAssetAllocations())->toHaveCount(3);
});

it('prevents rebalancing when already in progress', function () {
    $portfolioId = Str::uuid()->toString();
    $treasuryId = Str::uuid()->toString();

    $strategy = new InvestmentStrategy(
        InvestmentStrategy::RISK_MODERATE,
        5.0,
        0.08
    );

    $aggregate = PortfolioAggregate::retrieve($portfolioId);
    $aggregate->createPortfolio(
        $portfolioId,
        $treasuryId,
        'Portfolio',
        $strategy
    );

    // Trigger rebalancing
    $aggregate->detectAllocationDrift([
        ['assetClass' => 'equities', 'drift' => 6.0, 'currentWeight' => 66.0, 'targetWeight' => 60.0],
    ], 'monitor');

    $targetAllocations = [
        ['assetClass' => 'equities', 'targetWeight' => 60.0],
        ['assetClass' => 'bonds', 'targetWeight' => 40.0],
    ];

    expect(fn () => $aggregate->rebalancePortfolio(
        Str::uuid()->toString(),
        $targetAllocations,
        'manual',
        'manager'
    ))->toThrow(InvalidArgumentException::class, 'Portfolio is already being rebalanced');
});
