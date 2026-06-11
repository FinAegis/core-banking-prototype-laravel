<?php

/**
 * SubscriptionRevenueStatsWidget — bcmath-exact revenue math and
 * module-visibility gating.
 */

declare(strict_types=1);

use App\Filament\Admin\Widgets\SubscriptionRevenueStatsWidget;
use Database\Factories\Domain\Subscription\Models\IapSubscriptionFactory;
use Database\Factories\Domain\Subscription\Models\RevenueEventFactory;
use Filament\Widgets\StatsOverviewWidget\Stat;

afterEach(function (): void {
    config(['brand.admin_modules' => null]);
});

/**
 * @return array<Stat>
 */
function widgetStats(): array
{
    $widget = new SubscriptionRevenueStatsWidget();

    $method = new ReflectionMethod(SubscriptionRevenueStatsWidget::class, 'getStats');
    $method->setAccessible(true);

    /** @var array<Stat> $stats */
    $stats = $method->invoke($widget);

    return $stats;
}

it('counts active subscribers by tier and trialing separately', function (): void {
    IapSubscriptionFactory::new()->count(2)->create(['tier' => 'pro']);
    IapSubscriptionFactory::new()->trialing()->createOne(['tier' => 'pro']);
    IapSubscriptionFactory::new()->expired()->createOne(['tier' => 'pro']);

    [$active, $trialing] = widgetStats();

    // 2 active + 1 trialing are "alive"; the expired row is not.
    expect($active->getLabel())->toBe('Active subscribers')
        ->and($active->getValue())->toBe('3')
        ->and($active->getDescription())->toBe('By tier: pro 3');

    expect($trialing->getLabel())->toBe('Trialing')
        ->and($trialing->getValue())->toBe('1');
});

it('sums this-month revenue per denomination with bcmath exactness', function (): void {
    // This month, EUR (decimals=2): 999 + 999 - 500 = 1498 minor units = 14.98.
    RevenueEventFactory::new()->createOne(['amount' => 999, 'emitted_at' => now()]);
    RevenueEventFactory::new()->createOne(['amount' => 999, 'emitted_at' => now()]);
    RevenueEventFactory::new()->refund()->createOne(['amount' => -500, 'emitted_at' => now()]);

    // This month, USDC (decimals=6): 1_500_000 minor units = 1.50.
    RevenueEventFactory::new()->createOne([
        'amount'              => 1500000,
        'amount_decimals'     => 6,
        'amount_denomination' => 'USDC',
        'emitted_at'          => now(),
    ]);

    // Last month — excluded from the sum.
    RevenueEventFactory::new()->createOne([
        'amount'     => 99999,
        'emitted_at' => now()->startOfMonth()->subDay(),
    ]);

    $revenue = widgetStats()[2];

    expect($revenue->getLabel())->toBe('Revenue this month')
        ->and($revenue->getValue())->toBe('14.98 EUR · 1.50 USDC')
        ->and($revenue->getDescription())->toBe('Net of refunds, from 4 revenue event(s)');
});

it('reports zero revenue when no events exist this month', function (): void {
    $revenue = widgetStats()[2];

    expect($revenue->getValue())->toBe('0.00');
});

it('declares the Subscriptions admin module', function (): void {
    $property = new ReflectionProperty(SubscriptionRevenueStatsWidget::class, 'adminModule');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Subscriptions');
});

it('is gated by ADMIN_MODULES like every other widget', function (): void {
    config(['brand.admin_modules' => null]);
    expect(SubscriptionRevenueStatsWidget::canView())->toBeTrue();

    config(['brand.admin_modules' => ['Subscriptions']]);
    expect(SubscriptionRevenueStatsWidget::canView())->toBeTrue();

    config(['brand.admin_modules' => ['Banking']]);
    expect(SubscriptionRevenueStatsWidget::canView())->toBeFalse();
});
