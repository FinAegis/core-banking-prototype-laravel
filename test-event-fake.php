<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Exchange\Events\OrderRouted;
use Illuminate\Support\Facades\Event;

// Fake events
Event::fake();

// Dispatch an event using event() helper
event(new OrderRouted(
    orderId: 'test-123',
    poolId: 'pool-test',
    amount: 1.5,
    estimatedPrice: 50000,
    feeTier: 0.003,
    timestamp: now()
));

// Check what was dispatched
$dispatched = Event::dispatched(OrderRouted::class);
echo "Dispatched count: " . count($dispatched) . "\n";
echo "Dispatched type: " . gettype($dispatched) . "\n";

if (count($dispatched) > 0) {
    echo "First item type: " . gettype($dispatched[0]) . "\n";
    echo "First item class: " . (is_object($dispatched[0]) ? get_class($dispatched[0]) : 'not an object') . "\n";
    
    // Try the assertion
    $found = false;
    Event::assertDispatched(OrderRouted::class, function ($event) use (&$found) {
        echo "Assertion callback called!\n";
        echo "  Event type: " . gettype($event) . "\n";
        echo "  Event class: " . get_class($event) . "\n";
        echo "  OrderId: " . $event->orderId . "\n";
        $found = true;
        return $event->orderId === 'test-123';
    });
    
    echo "Assertion passed: " . ($found ? 'YES' : 'NO') . "\n";
}
