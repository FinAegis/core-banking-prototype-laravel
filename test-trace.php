<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Sagas\OrderRoutingSaga;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\Services\OrderService;
use Illuminate\Support\Facades\Event;
use ReflectionMethod;

// Create pool
$pool1 = new LiquidityPool();
$pool1->pool_id = 'pool-1';
$pool1->base_currency = 'BTC';
$pool1->quote_currency = 'USDT';
$pool1->base_reserve = '10';
$pool1->quote_reserve = '500000';
$pool1->is_active = true;
$pool1->metadata = ['fee_tier' => 0.003];

// Create mocks
$poolService = Mockery::mock(LiquidityPoolService::class);
$poolService->shouldReceive('getPoolsForPair')
    ->with('BTC', 'USDT')
    ->andReturn([$pool1]);

$orderService = Mockery::mock(OrderService::class);
$orderService->shouldReceive('updateOrderRouting')
    ->andReturnNull();

// Create saga
$saga = new OrderRoutingSaga($poolService, $orderService);

// Test getAvailablePools
$reflection = new ReflectionMethod($saga, 'getAvailablePools');
$reflection->setAccessible(true);
$availablePools = $reflection->invoke($saga, 'BTC', 'USDT');

echo "Available pools: " . count($availablePools) . "\n";
foreach ($availablePools as $pool) {
    echo "  Pool ID: " . $pool->pool_id . "\n";
}

// Now test calculateOptimalRouting
$event = new OrderPlaced(
    orderId: 'order-123',
    accountId: 'user-456',
    type: 'buy',
    orderType: 'market',
    baseCurrency: 'BTC',
    quoteCurrency: 'USDT',
    amount: '0.5',
    price: null
);

$calculateRouting = new ReflectionMethod($saga, 'calculateOptimalRouting');
$calculateRouting->setAccessible(true);
$routingStrategy = $calculateRouting->invoke($saga, $event, $availablePools);

echo "\nRouting strategy:\n";
echo "  split_required: " . ($routingStrategy['split_required'] ? 'true' : 'false') . "\n";
if (isset($routingStrategy['primary_route'])) {
    echo "  primary_route pool_id: " . $routingStrategy['primary_route']['pool_id'] . "\n";
}
echo "  routes count: " . count($routingStrategy['routes']) . "\n";

Mockery::close();
