<?php

/**
 * @property string $positionId
 * @property string $ownerId
 */

declare(strict_types=1);

use App\Domain\Stablecoin\Aggregates\CollateralPositionAggregate;
use App\Domain\Stablecoin\Events\CollateralAdded;
use App\Domain\Stablecoin\Events\CollateralHealthChecked;
use App\Domain\Stablecoin\Events\CollateralLiquidationStarted;
use App\Domain\Stablecoin\Events\CollateralPriceUpdated;
use App\Domain\Stablecoin\Events\CollateralRebalanced;
use App\Domain\Stablecoin\Events\CollateralWithdrawn;
use App\Domain\Stablecoin\Events\EnhancedCollateralPositionClosed;
use App\Domain\Stablecoin\Events\EnhancedCollateralPositionCreated;
use App\Domain\Stablecoin\Events\MarginCallIssued;
use App\Domain\Stablecoin\ValueObjects\CollateralType;
use App\Domain\Stablecoin\ValueObjects\LiquidationThreshold;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->positionId = Str::uuid()->toString();
    $this->ownerId = Str::uuid()->toString();
});

it('can create a new collateral position', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2, 'BTC' => 0.5],
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    // Get events before persist (they're still in memory)
    $events = $aggregate->getRecordedEvents();

    $aggregate->persist();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(EnhancedCollateralPositionCreated::class)
        ->and($events[0]->positionId)->toBe($this->positionId)
        ->and($events[0]->ownerId)->toBe($this->ownerId)
        ->and($events[0]->collateral)->toBe(['ETH' => 2, 'BTC' => 0.5])
        ->and($events[0]->initialDebt)->toBe(5000.0)
        ->and($events[0]->collateralType)->toBe('crypto')
        ->and($events[0]->liquidationThreshold)->toBe(150.0);
});

it('can add collateral to an existing position', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2],
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->addCollateral(['ETH' => 1, 'BTC' => 0.25]);

    $events = $aggregate->getRecordedEvents();
    $aggregate->persist();

    expect($events)->toHaveCount(2)
        ->and($events[1])->toBeInstanceOf(CollateralAdded::class)
        ->and($events[1]->collateral)->toBe(['ETH' => 1, 'BTC' => 0.25])
        ->and($events[1]->newTotalCollateral)->toBe(['ETH' => 3, 'BTC' => 0.25]);
});

it('cannot add collateral to inactive position', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2],
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->closePosition('User requested');

    expect(fn () => $aggregate->addCollateral(['ETH' => 1]))
        ->toThrow(DomainException::class, 'Cannot add collateral to inactive position');
});

it('can withdraw collateral if position remains healthy', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['USD' => 10000], // High collateral
        BigDecimal::of('1000'), // Low debt
        CollateralType::FIAT,
        new LiquidationThreshold(110)
    );

    $aggregate->withdrawCollateral(['USD' => 2000]);

    $events = $aggregate->getRecordedEvents();
    $aggregate->persist();

    expect($events)->toHaveCount(2)
        ->and($events[1])->toBeInstanceOf(CollateralWithdrawn::class)
        ->and($events[1]->withdrawn)->toBe(['USD' => 2000])
        ->and($events[1]->remainingCollateral)->toBe(['USD' => 8000]);
});

it('cannot withdraw collateral during liquidation', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2],
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->startLiquidation();

    expect(fn () => $aggregate->withdrawCollateral(['ETH' => 0.5]))
        ->toThrow(DomainException::class, 'Cannot withdraw during liquidation');
});

it('updates price and checks health', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 5],  // More collateral to avoid auto-liquidation
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->updatePrice(BigDecimal::of('2000')); // ETH price = $10,000 collateral vs $5,000 debt = 200% ratio

    $events = $aggregate->getRecordedEvents();
    $aggregate->persist();

    expect($events)->toHaveCount(3) // Created, PriceUpdated, HealthChecked
        ->and($events[1])->toBeInstanceOf(CollateralPriceUpdated::class)
        ->and($events[1]->newPrice)->toBe(2000.0)
        ->and($events[2])->toBeInstanceOf(CollateralHealthChecked::class);
});

it('issues margin call when health deteriorates', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    // Create position with marginal collateral
    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 1],
        BigDecimal::of('1500'), // Close to liquidation threshold
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    // Simulate price drop
    $aggregate->updatePrice(BigDecimal::of('1600')); // ETH price drops

    $events = $aggregate->getRecordedEvents();
    $aggregate->persist();

    $marginCallEvents = array_filter($events, fn ($e) => $e instanceof MarginCallIssued);

    expect($marginCallEvents)->not->toBeEmpty();
});

it('starts liquidation when position becomes critical', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 1],
        BigDecimal::of('2000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->startLiquidation();

    $events = $aggregate->getRecordedEvents();
    $aggregate->persist();

    expect($events)->toHaveCount(2)
        ->and($events[1])->toBeInstanceOf(CollateralLiquidationStarted::class)
        ->and($events[1]->positionId)->toBe($this->positionId)
        ->and($events[1]->ownerId)->toBe($this->ownerId);
});

it('can rebalance collateral allocation', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2, 'BTC' => 0.5],
        BigDecimal::of('10000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $newAllocation = ['ETH' => 1.5, 'BTC' => 0.7, 'USDC' => 1000];
    $aggregate->rebalanceCollateral($newAllocation);

    $events = $aggregate->getRecordedEvents();
    $aggregate->persist();

    expect($events)->toHaveCount(2)
        ->and($events[1])->toBeInstanceOf(CollateralRebalanced::class)
        ->and($events[1]->oldAllocation)->toBe(['ETH' => 2, 'BTC' => 0.5])
        ->and($events[1]->newAllocation)->toBe($newAllocation);
});

it('cannot rebalance during liquidation', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2],
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->startLiquidation();

    expect(fn () => $aggregate->rebalanceCollateral(['ETH' => 1, 'BTC' => 0.5]))
        ->toThrow(DomainException::class, 'Cannot rebalance during liquidation');
});

it('can close position', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2],
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->closePosition('User requested closure');

    $events = $aggregate->getRecordedEvents();
    $aggregate->persist();

    expect($events)->toHaveCount(2)
        ->and($events[1])->toBeInstanceOf(EnhancedCollateralPositionClosed::class)
        ->and($events[1]->closureReason)->toBe('User requested closure');
});

it('cannot close already closed position', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2],
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->closePosition('First closure');

    expect(fn () => $aggregate->closePosition('Second closure'))
        ->toThrow(DomainException::class, 'Position already closed');
});

it('returns correct state', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 2, 'BTC' => 0.5],
        BigDecimal::of('5000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $state = $aggregate->getState();

    expect($state)->toBeArray()
        ->and($state['positionId'])->toBe($this->positionId)
        ->and($state['ownerId'])->toBe($this->ownerId)
        ->and($state['collateral'])->toBe(['ETH' => 2, 'BTC' => 0.5])
        ->and($state['totalDebt'])->toBe(5000.0)
        ->and($state['isActive'])->toBeTrue()
        ->and($state['isUnderMarginCall'])->toBeFalse()
        ->and($state['isBeingLiquidated'])->toBeFalse();
});

it('uses separate collateral_position_events table for event storage', function () {
    $aggregate = CollateralPositionAggregate::retrieve($this->positionId);

    $aggregate->createPosition(
        $this->positionId,
        $this->ownerId,
        ['ETH' => 1],
        BigDecimal::of('1000'),
        CollateralType::CRYPTO,
        new LiquidationThreshold(150)
    );

    $aggregate->persist();

    // Verify events are stored in collateral_position_events table
    $this->assertDatabaseHas('collateral_position_events', [
        'aggregate_uuid' => $this->positionId,
        'event_class'    => 'enhanced_collateral_position_created',
    ]);

    // Verify events are NOT in stablecoin_events table
    $this->assertDatabaseMissing('stablecoin_events', [
        'aggregate_uuid' => $this->positionId,
    ]);
});
