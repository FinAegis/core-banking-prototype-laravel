<?php

declare(strict_types=1);

use App\Console\Commands\PerformSystemHealthChecks;

it('maps disk usage to a storage status', function (float $usedPercentage, string $expected): void {
    $command = app(PerformSystemHealthChecks::class);
    $method = new ReflectionMethod($command, 'storageStatus');

    expect($method->invoke($command, $usedPercentage))->toBe($expected);
})->with([
    'plenty of space' => [50.0, 'operational'],
    'exactly 90%'     => [90.0, 'operational'],
    'above 90%'       => [92.5, 'degraded'],
    'exactly 95%'     => [95.0, 'degraded'],
    // The "> 90" branch used to be checked first, so this case never got "down".
    'above 95%'       => [97.0, 'down'],
    'completely full' => [100.0, 'down'],
]);
