<?php

declare(strict_types=1);

use App\Domain\Basket\Services\BasketRebalancingService;
use App\Domain\Basket\Services\BasketService;

// BasketService::rebalanceAllDynamicBaskets() filtered on a
// "rebalancing_enabled" column that basket_assets does not have and then
// called a rebalanceBasket() method that does not exist. It now hands over to
// BasketRebalancingService, which the baskets:rebalance command already uses.

it('hands dynamic basket rebalancing to the rebalancing service', function (): void {
    /** @var BasketRebalancingService&Mockery\MockInterface $rebalancing */
    $rebalancing = Mockery::mock(BasketRebalancingService::class);
    $rebalancing->shouldReceive('rebalanceAllDynamicBaskets')->once()->andReturn([]);
    app()->instance(BasketRebalancingService::class, $rebalancing);

    app(BasketService::class)->rebalanceAllDynamicBaskets();
});
