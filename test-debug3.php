<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Exchange\Projections\LiquidityPool;

// Create pool
$pool1 = new LiquidityPool();
$pool1->pool_id = 'pool-1';
$pool1->base_currency = 'BTC';
$pool1->quote_currency = 'USDT';
$pool1->base_reserve = '10';
$pool1->quote_reserve = '500000';
$pool1->is_active = true;
$pool1->metadata = ['fee_tier' => 0.003];

// Test the filtering logic
$basePrice = 50000;
$quotePrice = 1;
$liquidity = ((float) $pool1->base_reserve * $basePrice) + ((float) $pool1->quote_reserve * $quotePrice);

echo "Pool data:\n";
echo "  base_reserve: " . $pool1->base_reserve . "\n";
echo "  quote_reserve: " . $pool1->quote_reserve . "\n";
echo "  is_active: " . ($pool1->is_active ? 'true' : 'false') . "\n";
echo "  Calculated liquidity: $liquidity\n";
echo "  Passes filter (is_active && liquidity > 1000): " . ($pool1->is_active && $liquidity > 1000 ? 'YES' : 'NO') . "\n";

// Test filtering an array
$pools = [$pool1];
$filtered = array_filter($pools, function ($pool) {
    $basePrice = 50000;
    $quotePrice = 1;
    $liquidity = ((float) $pool->base_reserve * $basePrice) + ((float) $pool->quote_reserve * $quotePrice);
    return $pool->is_active && $liquidity > 1000;
});

echo "\nArray filtering:\n";
echo "  Input pools: " . count($pools) . "\n";
echo "  Filtered pools: " . count($filtered) . "\n";

if (count($filtered) > 0) {
    echo "  Filtered pool IDs: ";
    foreach ($filtered as $p) {
        echo $p->pool_id . " ";
    }
    echo "\n";
}
