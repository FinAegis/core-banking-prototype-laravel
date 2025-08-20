<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Events\OrderRouted;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Sagas\OrderRoutingSaga;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\Services\OrderService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

// Override event() helper to see what's being dispatched
$originalEventFunction = 'event';
$eventsDispatched = [];

// Fake events
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

// Create mocks
$poolService = Mockery::mock(LiquidityPoolService::class);
$poolService->shouldReceive('getPoolsForPair')
    ->with('BTC', 'USDT')
    ->andReturn([$pool1]);

$orderService = Mockery::mock(OrderService::class);
$orderService->shouldReceive('updateOrderRouting')
    ->andReturnUsing(function($orderId, $poolId, $price) {
        echo "updateOrderRouting called with:\n";
        echo "  orderId: $orderId\n";
        echo "  poolId: $poolId\n";
        echo "  price: $price\n";
        return null;
    });

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

// Also mock Log to see what's logged
Log::shouldReceive('info')
    ->andReturnUsing(function($message, $context = []) {
        echo "Log::info: $message\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context) . "\n";
        }
    });

Log::shouldReceive('error')
    ->andReturnUsing(function($message, $context = []) {
        echo "Log::error: $message\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context) . "\n";
        }
    });

echo "Calling onOrderPlaced...\n";
$saga->onOrderPlaced($event);

// Check Event::fake() dispatched events
echo "\nChecking Event facade:\n";
$allEvents = Event::dispatched(OrderRouted::class);
echo "OrderRouted events: " . count($allEvents) . "\n";

foreach ($allEvents as $evt) {
    echo "  - OrderID: {$evt->orderId}, PoolID: {$evt->poolId}\n";
}

Mockery::close();
