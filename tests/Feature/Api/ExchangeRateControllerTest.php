<?php

declare(strict_types=1);

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;

// Assets are already seeded in migrations

it('can get current exchange rate', function () {
    $rate = ExchangeRate::factory()
        ->between('USD', 'EUR')
        ->valid()
        ->create(['rate' => 0.85]);

    $response = $this->getJson('/api/v1/exchange-rates/USD/EUR');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'from_asset',
                'to_asset',
                'rate',
                'inverse_rate',
                'source',
                'valid_at',
                'expires_at',
                'is_active',
                'age_minutes',
                'metadata',
            ],
        ]);

    expect($response->json('data.from_asset'))->toBe('USD');
    expect($response->json('data.to_asset'))->toBe('EUR');
    expect($response->json('data.rate'))->toBe('0.8500000000');
});

it('returns 404 for non-existent exchange rate', function () {
    $response = $this->getJson('/api/v1/exchange-rates/USD/UNKNOWN');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Exchange rate not found',
            'error' => 'No active exchange rate found for the specified asset pair',
        ]);
});

it('can convert amount between assets', function () {
    ExchangeRate::factory()
        ->between('USD', 'EUR')
        ->valid()
        ->create(['rate' => 0.85]);

    $response = $this->getJson('/api/v1/exchange-rates/USD/EUR/convert?amount=10000');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'from_asset',
                'to_asset',
                'from_amount',
                'to_amount',
                'from_formatted',
                'to_formatted',
                'rate',
                'rate_age_minutes',
            ],
        ]);

    expect($response->json('data.from_asset'))->toBe('USD');
    expect($response->json('data.to_asset'))->toBe('EUR');
    expect($response->json('data.from_amount'))->toBe(10000);
    expect($response->json('data.to_amount'))->toBe(8500);
    expect($response->json('data.from_formatted'))->toBe('100.00 USD');
    expect($response->json('data.to_formatted'))->toBe('85.00 EUR');
});

it('validates amount parameter for conversion', function () {
    ExchangeRate::factory()
        ->between('USD', 'EUR')
        ->valid()
        ->create();

    $response = $this->getJson('/api/v1/exchange-rates/USD/EUR/convert');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('can list exchange rates with filters', function () {
    ExchangeRate::factory()->between('USD', 'EUR')->valid()->create();
    ExchangeRate::factory()->between('USD', 'GBP')->valid()->create();
    ExchangeRate::factory()->between('EUR', 'USD')->valid()->create();

    $response = $this->getJson('/api/v1/exchange-rates');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'from_asset',
                    'to_asset',
                    'rate',
                    'inverse_rate',
                    'source',
                    'valid_at',
                    'expires_at',
                    'is_active',
                    'age_minutes',
                ],
            ],
            'meta' => [
                'total',
                'valid',
                'stale',
            ],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

it('can filter exchange rates by source asset', function () {
    ExchangeRate::factory()->between('USD', 'EUR')->valid()->create();
    ExchangeRate::factory()->between('GBP', 'EUR')->valid()->create();

    $response = $this->getJson('/api/v1/exchange-rates?from=USD');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.from_asset'))->toBe('USD');
});

it('can filter exchange rates by target asset', function () {
    ExchangeRate::factory()->between('USD', 'EUR')->valid()->create();
    ExchangeRate::factory()->between('USD', 'GBP')->valid()->create();

    $response = $this->getJson('/api/v1/exchange-rates?to=EUR');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.to_asset'))->toBe('EUR');
});

it('can filter exchange rates by source', function () {
    ExchangeRate::factory()->valid()->create(['source' => 'manual']);
    ExchangeRate::factory()->valid()->create(['source' => 'api']);

    $response = $this->getJson('/api/v1/exchange-rates?source=manual');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.source'))->toBe('manual');
});

it('can filter exchange rates by validity', function () {
    ExchangeRate::factory()->valid()->create();
    ExchangeRate::factory()->expired()->create();

    $response = $this->getJson('/api/v1/exchange-rates?valid=1');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('can limit number of results', function () {
    // Create exchange rates with different timestamps to avoid unique constraint
    $baseTime = now();
    foreach (range(1, 5) as $i) {
        ExchangeRate::factory()->valid()->create([
            'valid_at' => $baseTime->copy()->addSeconds($i)
        ]);
    }

    $response = $this->getJson('/api/v1/exchange-rates?limit=3');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
});

it('validates limit parameter', function () {
    $response = $this->getJson('/api/v1/exchange-rates?limit=150');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['limit']);
});