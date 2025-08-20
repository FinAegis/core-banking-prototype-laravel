<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Exchange\Events\OrderRouted;
use Illuminate\Support\Facades\Event;

echo "Testing event() helper with Event::fake()\n\n";

// First, fake events
Event::fake();

// Test 1: Dispatch using Event facade
echo "Test 1: Using Event facade\n";
Event::dispatch(new OrderRouted(
    orderId: 'facade-123',
    poolId: 'pool-facade',
    amount: 1.0,
    estimatedPrice: 50000,
    feeTier: 0.003,
    timestamp: now()
));

$facadeEvents = Event::dispatched(OrderRouted::class);
echo "  Events dispatched via facade: " . count($facadeEvents) . "\n";

// Test 2: Dispatch using event() helper
echo "\nTest 2: Using event() helper\n";
event(new OrderRouted(
    orderId: 'helper-123',
    poolId: 'pool-helper',
    amount: 2.0,
    estimatedPrice: 51000,
    feeTier: 0.003,
    timestamp: now()
));

$allEvents = Event::dispatched(OrderRouted::class);
echo "  Total events now: " . count($allEvents) . "\n";

// Check if both are captured
echo "\nEvent details:\n";
foreach ($allEvents as $idx => $evt) {
    if (is_array($evt)) {
        $evt = $evt[0];
    }
    echo "  Event $idx: OrderID=" . $evt->orderId . ", PoolID=" . $evt->poolId . "\n";
}
