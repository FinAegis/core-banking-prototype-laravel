<?php

declare(strict_types=1);

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Services\SmsPricingService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'sms.pricing.margin_multiplier' => 1.15,
        'sms.pricing.eur_usd_rate'      => 1.08,
        'sms.pricing.fallback_usdc'     => 50000,
    ]);
});

it('converts a Vertex /sms/cost response to atomic USDC', function (): void {
    /** @var VertexSmsClient&Mockery\MockInterface $client */
    $client = Mockery::mock(VertexSmsClient::class);
    $service = new SmsPricingService($client);

    $result = $service->getPriceFromCostEstimate([
        'parts'              => 2,
        'price_per_part_eur' => 0.035,
        'total_price_eur'    => 0.070,
        'country_iso'        => 'LT',
        'mccmnc'             => '24601',
    ]);

    // 0.070 EUR × 1.08 USD/EUR × 1.15 margin = 0.08694 USD = 86_940 atomic (×1e6)
    expect($result['parts'])->toBe(2);
    expect($result['country_code'])->toBe('LT');
    expect($result['rate_eur'])->toBe('0.0350');
    expect((int) $result['amount_usdc'])->toBeGreaterThanOrEqual(86_000);
    expect((int) $result['amount_usdc'])->toBeLessThanOrEqual(87_000);
});

it('derives total from pricePerPart × parts when total is missing', function (): void {
    /** @var VertexSmsClient&Mockery\MockInterface $client */
    $client = Mockery::mock(VertexSmsClient::class);
    $service = new SmsPricingService($client);

    $result = $service->getPriceFromCostEstimate([
        'parts'              => 3,
        'price_per_part_eur' => 0.040,
        'total_price_eur'    => 0.0,
        'country_iso'        => 'DE',
        'mccmnc'             => null,
    ]);

    // 0.040 × 3 × 1.08 × 1.15 = 0.14904 USD → ~149_040 atomic
    expect($result['parts'])->toBe(3);
    expect($result['country_code'])->toBe('DE');
    expect((int) $result['amount_usdc'])->toBeGreaterThanOrEqual(149_000);
});

it('falls back to configured minimum when pricing data is empty', function (): void {
    /** @var VertexSmsClient&Mockery\MockInterface $client */
    $client = Mockery::mock(VertexSmsClient::class);
    $service = new SmsPricingService($client);

    $result = $service->getPriceFromCostEstimate([
        'parts'              => 2,
        'price_per_part_eur' => 0.0,
        'total_price_eur'    => 0.0,
        'country_iso'        => '',
        'mccmnc'             => null,
    ]);

    // Fallback × parts = 50_000 × 2 = 100_000
    expect($result['parts'])->toBe(2);
    expect($result['country_code'])->toBe('US');
    expect($result['amount_usdc'])->toBe('100000');
});
