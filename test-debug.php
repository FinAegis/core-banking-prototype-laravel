<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Events\OrderRouted;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Sagas\OrderRoutingSaga;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\Services\OrderService;
use Illuminate\Support\Facades\Event;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create test event
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

// Create mock pool
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
    ->once()
    ->andReturn(null);

// Create saga with mocks
$saga = new OrderRoutingSaga($poolService, $orderService);

// Fake events
Event::fake();

// Act
echo "Calling onOrderPlaced...\n";
$saga->onOrderPlaced($event);

// Check if event was dispatched
$dispatched = Event::dispatched(OrderRouted::class);
echo "Event dispatched: " . (count($dispatched) > 0 ? 'Yes' : 'No') . "\n";

if (count($dispatched) > 0) {
    echo "Dispatched event details:\n";
    var_dump($dispatched[0]);
}

Mockery::close();