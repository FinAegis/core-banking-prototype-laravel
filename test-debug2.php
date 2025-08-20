<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Now run the test scenario
use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Events\OrderRouted;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Sagas\OrderRoutingSaga;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\Services\OrderService;
use Illuminate\Support\Facades\Event;

// Fake events FIRST
Event::fake();

// Create pools
$pool1 = new LiquidityPool();
$pool1->pool_id = 'pool-1';
$pool1->base_currency = 'BTC';
$pool1->quote_currency = 'USDT';
$pool1->base_reserve = '10';
$pool1->quote_reserve = '500000';
$pool1->is_active = true;
$pool1->metadata = ['fee_tier' => 0.003];

$pool2 = new LiquidityPool();
$pool2->pool_id = 'pool-2';
$pool2->base_currency = 'BTC';
$pool2->quote_currency = 'USDT';
$pool2->base_reserve = '5';
$pool2->quote_reserve = '250000';
$pool2->is_active = true;
$pool2->metadata = ['fee_tier' => 0.003];

echo "Pool 1 liquidity: " . ((float)$pool1->base_reserve * 50000 + (float)$pool1->quote_reserve) . "\n";
echo "Pool 2 liquidity: " . ((float)$pool2->base_reserve * 50000 + (float)$pool2->quote_reserve) . "\n";

// Create mocks
$poolService = Mockery::mock(LiquidityPoolService::class);
$poolService->shouldReceive('getPoolsForPair')
    ->with('BTC', 'USDT')
    ->andReturn([$pool1, $pool2]);

$orderService = Mockery::mock(OrderService::class);
$orderService->shouldReceive('updateOrderRouting')
    ->once()
    ->andReturn(null);

// Create saga
$saga = new OrderRoutingSaga($poolService, $orderService);

// Create event
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

echo "\nCalling onOrderPlaced...\n";
try {
    $saga->onOrderPlaced($event);
    echo "Success!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

// Check dispatched events
$dispatched = Event::dispatched(OrderRouted::class);
echo "\nOrderRouted events dispatched: " . count($dispatched) . "\n";

if (count($dispatched) > 0) {
    foreach ($dispatched as $idx => $event) {
        echo "Event $idx:\n";
        echo "  Order ID: " . $event->orderId . "\n";
        echo "  Pool ID: " . $event->poolId . "\n";
        echo "  Amount: " . $event->amount . "\n";
    }
}

Mockery::close();