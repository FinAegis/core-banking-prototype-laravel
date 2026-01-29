<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Treasury\Services\Broadcast;

use App\Domain\Account\Events\Broadcast\BalanceUpdated;
use App\Domain\Treasury\Events\Broadcast\NavCalculated;
use App\Domain\Treasury\Events\Broadcast\PortfolioValueUpdated;
use App\Domain\Treasury\Services\Broadcast\PortfolioBroadcastService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortfolioBroadcastServiceTest extends TestCase
{
    private PortfolioBroadcastService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Force array cache driver for tests
        config(['cache.default' => 'array']);

        $this->service = new PortfolioBroadcastService();

        // Clear cache before each test
        Cache::flush();

        // Fake events
        Event::fake([
            PortfolioValueUpdated::class,
            NavCalculated::class,
            BalanceUpdated::class,
        ]);
    }

    #[Test]
    public function it_broadcasts_portfolio_update(): void
    {
        config(['websocket.enabled' => true]);

        $this->service->broadcastPortfolioUpdate(
            tenantId: '1',
            portfolioId: 'portfolio-123',
            portfolioName: 'Main Investment Portfolio',
            totalValue: '1000000.00',
            previousValue: '995000.00',
            changeAmount: '5000.00',
            changePercentage: '0.50',
            currency: 'EUR',
            holdings: [
                'EUR' => ['quantity' => '500000', 'price' => '1.00', 'value' => '500000', 'allocation' => '50.0'],
                'USD' => ['quantity' => '540540', 'price' => '0.9259', 'value' => '500000', 'allocation' => '50.0'],
            ],
            performance: [
                'daily'   => '0.50',
                'weekly'  => '1.25',
                'monthly' => '3.50',
                'ytd'     => '8.75',
            ],
        );

        Event::assertDispatched(PortfolioValueUpdated::class, function ($event) {
            return $event->tenantId === '1'
                && $event->portfolioId === 'portfolio-123'
                && $event->portfolioName === 'Main Investment Portfolio'
                && $event->totalValue === '1000000.00'
                && $event->changePercentage === '0.50'
                && count($event->holdings) === 2
                && count($event->performance) === 4;
        });
    }

    #[Test]
    public function it_broadcasts_nav_calculated(): void
    {
        config(['websocket.enabled' => true]);

        $this->service->broadcastNavCalculated(
            tenantId: '1',
            portfolioId: 'portfolio-123',
            portfolioName: 'Main Investment Portfolio',
            nav: '1000000.00',
            previousNav: '998000.00',
            changeAmount: '2000.00',
            changePercentage: '0.20',
            navPerShare: '10.00',
            totalShares: '100000',
            totalAssets: '1050000.00',
            totalLiabilities: '50000.00',
            currency: 'EUR',
            breakdown: [
                'cash'     => '200000.00',
                'equities' => '500000.00',
                'bonds'    => '300000.00',
                'other'    => '50000.00',
            ],
        );

        Event::assertDispatched(NavCalculated::class, function ($event) {
            return $event->tenantId === '1'
                && $event->portfolioId === 'portfolio-123'
                && $event->nav === '1000000.00'
                && $event->navPerShare === '10.00'
                && $event->totalShares === '100000'
                && $event->totalAssets === '1050000.00'
                && $event->totalLiabilities === '50000.00'
                && count($event->breakdown) === 4;
        });
    }

    #[Test]
    public function it_broadcasts_balance_update(): void
    {
        config(['websocket.enabled' => true]);

        $this->service->broadcastBalanceUpdate(
            tenantId: '1',
            accountId: 'account-123',
            accountName: 'Main Operating Account',
            accountType: 'checking',
            totalBalance: '50000.00',
            availableBalance: '45000.00',
            pendingBalance: '3000.00',
            reservedBalance: '2000.00',
            currency: 'EUR',
            previousTotalBalance: '48000.00',
            changeAmount: '2000.00',
            changeReason: 'deposit',
            transactionId: 'tx-456',
        );

        Event::assertDispatched(BalanceUpdated::class, function ($event) {
            return $event->tenantId === '1'
                && $event->accountId === 'account-123'
                && $event->accountName === 'Main Operating Account'
                && $event->accountType === 'checking'
                && $event->totalBalance === '50000.00'
                && $event->availableBalance === '45000.00'
                && $event->pendingBalance === '3000.00'
                && $event->reservedBalance === '2000.00'
                && $event->changeReason === 'deposit'
                && $event->transactionId === 'tx-456';
        });
    }

    #[Test]
    public function it_respects_rate_limits_for_portfolio(): void
    {
        config([
            'websocket.enabled'                                => true,
            'websocket.rate_limiting.portfolio.max_per_second' => 1,
        ]);

        // First should succeed
        $this->service->broadcastPortfolioUpdate(
            tenantId: '1',
            portfolioId: 'portfolio-123',
            portfolioName: 'Test Portfolio',
            totalValue: '1000000.00',
            previousValue: '995000.00',
            changeAmount: '5000.00',
            changePercentage: '0.50',
            currency: 'EUR',
            holdings: [],
            performance: [],
        );

        // Second should be rate limited
        $this->service->broadcastPortfolioUpdate(
            tenantId: '1',
            portfolioId: 'portfolio-123',
            portfolioName: 'Test Portfolio',
            totalValue: '1005000.00',
            previousValue: '1000000.00',
            changeAmount: '5000.00',
            changePercentage: '0.50',
            currency: 'EUR',
            holdings: [],
            performance: [],
        );

        Event::assertDispatchedTimes(PortfolioValueUpdated::class, 1);
    }

    #[Test]
    public function it_respects_rate_limits_for_balance(): void
    {
        config([
            'websocket.enabled'                              => true,
            'websocket.rate_limiting.balance.max_per_second' => 2,
        ]);

        // First two should succeed
        $this->service->broadcastBalanceUpdate(
            tenantId: '1',
            accountId: 'account-123',
            accountName: 'Test Account',
            accountType: 'checking',
            totalBalance: '1000.00',
            availableBalance: '1000.00',
            pendingBalance: '0.00',
            reservedBalance: '0.00',
            currency: 'EUR',
            previousTotalBalance: '900.00',
            changeAmount: '100.00',
            changeReason: 'deposit',
        );

        $this->service->broadcastBalanceUpdate(
            tenantId: '1',
            accountId: 'account-123',
            accountName: 'Test Account',
            accountType: 'checking',
            totalBalance: '1100.00',
            availableBalance: '1100.00',
            pendingBalance: '0.00',
            reservedBalance: '0.00',
            currency: 'EUR',
            previousTotalBalance: '1000.00',
            changeAmount: '100.00',
            changeReason: 'deposit',
        );

        // Third should be rate limited
        $this->service->broadcastBalanceUpdate(
            tenantId: '1',
            accountId: 'account-123',
            accountName: 'Test Account',
            accountType: 'checking',
            totalBalance: '1200.00',
            availableBalance: '1200.00',
            pendingBalance: '0.00',
            reservedBalance: '0.00',
            currency: 'EUR',
            previousTotalBalance: '1100.00',
            changeAmount: '100.00',
            changeReason: 'deposit',
        );

        Event::assertDispatchedTimes(BalanceUpdated::class, 2);
    }

    #[Test]
    public function it_does_not_broadcast_when_disabled(): void
    {
        config(['websocket.enabled' => false]);

        $this->service->broadcastPortfolioUpdate(
            tenantId: '1',
            portfolioId: 'portfolio-123',
            portfolioName: 'Test Portfolio',
            totalValue: '1000000.00',
            previousValue: '995000.00',
            changeAmount: '5000.00',
            changePercentage: '0.50',
            currency: 'EUR',
            holdings: [],
            performance: [],
        );

        $this->service->broadcastNavCalculated(
            tenantId: '1',
            portfolioId: 'portfolio-123',
            portfolioName: 'Test Portfolio',
            nav: '1000000.00',
            previousNav: '998000.00',
            changeAmount: '2000.00',
            changePercentage: '0.20',
            navPerShare: '10.00',
            totalShares: '100000',
            totalAssets: '1050000.00',
            totalLiabilities: '50000.00',
            currency: 'EUR',
            breakdown: [],
        );

        $this->service->broadcastBalanceUpdate(
            tenantId: '1',
            accountId: 'account-123',
            accountName: 'Test Account',
            accountType: 'checking',
            totalBalance: '1000.00',
            availableBalance: '1000.00',
            pendingBalance: '0.00',
            reservedBalance: '0.00',
            currency: 'EUR',
            previousTotalBalance: '900.00',
            changeAmount: '100.00',
            changeReason: 'deposit',
        );

        Event::assertNotDispatched(PortfolioValueUpdated::class);
        Event::assertNotDispatched(NavCalculated::class);
        Event::assertNotDispatched(BalanceUpdated::class);
    }
}
