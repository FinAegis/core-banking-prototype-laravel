<?php

declare(strict_types=1);

use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Clear cache to prevent test conflicts
    Cache::flush();
    // Assets are already seeded in migrations, no need to create duplicates
});

it('can get existing exchange rate', function () {
    $service = app(ExchangeRateService::class);

    ExchangeRate::factory()
        ->between('USD', 'EUR')
        ->create([
            'rate'       => 0.85,
            'valid_at'   => now()->subMinutes(10),
            'expires_at' => now()->addHours(1),
            'is_active'  => true,
        ]);

    $rate = $service->getRate('USD', 'EUR');

    expect($rate)->not->toBeNull();
    expect($rate->rate)->toBe('0.8500000000');
});

it('returns identity rate for same asset', function () {
    $service = app(ExchangeRateService::class);

    $rate = $service->getRate('USD', 'USD');

    expect($rate)->not->toBeNull();
    expect((float) $rate->rate)->toBe(1.0);
    expect($rate->from_asset_code)->toBe('USD');
    expect($rate->to_asset_code)->toBe('USD');
});

it('can convert amounts between assets', function () {
    $service = app(ExchangeRateService::class);

    ExchangeRate::factory()
        ->between('USD', 'EUR')
        ->create([
            'rate'       => 0.85,
            'valid_at'   => now()->subMinutes(5),
            'expires_at' => now()->addHours(2),
            'is_active'  => true,
        ]);

    $convertedAmount = $service->convert(10000, 'USD', 'EUR'); // $100.00

    expect($convertedAmount)->toBe(8500); // €85.00
});

it('returns null for unavailable rates', function () {
    $service = app(ExchangeRateService::class);

    $rate = $service->getRate('USD', 'UNKNOWN');

    expect($rate)->toBeNull();
});

it('can store new exchange rate', function () {
    $service = app(ExchangeRateService::class);

    // Use unique timestamp to avoid constraint violations
    $uniqueTime = now()->addMinutes(rand(1, 1000));

    $rate = ExchangeRate::create([
        'from_asset_code' => 'USD',
        'to_asset_code'   => 'EUR',
        'rate'            => 0.85,
        'source'          => ExchangeRate::SOURCE_MANUAL,
        'valid_at'        => $uniqueTime,
        'expires_at'      => $uniqueTime->addHours(1),
        'is_active'       => true,
        'metadata'        => ['test' => true],
    ]);

    expect($rate)->toBeInstanceOf(ExchangeRate::class);
    expect($rate->from_asset_code)->toBe('USD');
    expect($rate->to_asset_code)->toBe('EUR');
    expect($rate->rate)->toBe('0.8500000000');
    expect($rate->source)->toBe(ExchangeRate::SOURCE_MANUAL);
    expect($rate->metadata['test'])->toBeTrue();
});

it('can get available rates for an asset', function () {
    $service = app(ExchangeRateService::class);

    ExchangeRate::factory()->between('USD', 'EUR')->create([
        'valid_at'   => now()->subMinutes(1),
        'expires_at' => now()->addHours(1),
        'is_active'  => true,
    ]);
    ExchangeRate::factory()->between('USD', 'BTC')->create([
        'valid_at'   => now()->subMinutes(2),
        'expires_at' => now()->addHours(1),
        'is_active'  => true,
    ]);
    ExchangeRate::factory()->between('EUR', 'USD')->create([
        'valid_at'   => now()->subMinutes(3),
        'expires_at' => now()->addHours(1),
        'is_active'  => true,
    ]);

    $rates = $service->getAvailableRatesFor('USD');

    expect($rates)->toHaveCount(3);
});

it('can get rate history', function () {
    $service = app(ExchangeRateService::class);

    // Create historical rates with unique timestamps
    for ($i = 1; $i <= 5; $i++) {
        ExchangeRate::factory()
            ->between('USD', 'EUR')
            ->create([
                'valid_at'   => now()->subDays($i),
                'expires_at' => now()->subDays($i)->addHours(1),
            ]);
    }

    $history = $service->getRateHistory('USD', 'EUR', 30);

    expect($history)->toHaveCount(5);
    expect($history->first()->valid_at->isAfter($history->last()->valid_at))->toBeTrue();
});
