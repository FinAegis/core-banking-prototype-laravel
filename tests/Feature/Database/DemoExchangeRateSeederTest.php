<?php

declare(strict_types=1);

use App\Domain\Asset\Models\ExchangeRate;
use Database\Seeders\DemoExchangeRateSeeder;

/*
 * The demo site has no scheduler to refresh rates, and rates fetched by the
 * service expire after an hour, so currency conversions failed there. These
 * fixed demonstration rates do not expire.
 */

it('seeds demonstration rates that do not expire', function (): void {
    $this->seed(DemoExchangeRateSeeder::class);

    $rate = ExchangeRate::query()->where('from_asset_code', 'USD')->where('to_asset_code', 'EUR')->first();

    expect($rate)->not->toBeNull()
        ->and($rate?->expires_at)->toBeNull()
        ->and(ExchangeRate::getRate('USD', 'EUR'))->toBeGreaterThan(0.0)
        ->and(ExchangeRate::getRate('EUR', 'USD'))->toBeGreaterThan(1.0);
});

it('can run again without adding rates', function (): void {
    $this->seed(DemoExchangeRateSeeder::class);
    $count = ExchangeRate::query()->count();

    $this->seed(DemoExchangeRateSeeder::class);

    expect(ExchangeRate::query()->count())->toBe($count);
});

it('refuses to seed demonstration rates in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect(fn () => (new DemoExchangeRateSeeder())->run())->toThrow(RuntimeException::class);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
