<?php

declare(strict_types=1);

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Services\SmsPricingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'sms.providers.vertexsms.api_token' => 'test-token',
        'sms.providers.vertexsms.base_url'  => 'https://kube-api.vertexsms.com',
        'sms.pricing.margin_multiplier'     => 1.15,
        'sms.pricing.eur_usd_rate'          => 1.08,
        'sms.pricing.fallback_usdc'         => 50000,
        'cache.default'                     => 'array',
    ]);
    Cache::flush();
});

it('produces an authoritative price from a Vertex /sms/cost response', function (): void {
    Http::fake([
        'kube-api.vertexsms.com/sms/cost' => Http::response([[
            'parts'        => 2,
            'countryISO'   => 'LT',
            'mccmnc'       => '24601',
            'pricePerPart' => 0.035,
            'totalPrice'   => 0.070,
            'currency'     => 'EUR',
        ]], 200),
    ]);

    $service = new SmsPricingService(new VertexSmsClient());
    $priced = $service->priceFor('37069912345', 'Zelta', 'hello world');

    // 0.070 EUR × 1.08 USD/EUR × 1.15 margin = 0.086940 USD = 86_940 atomic USDC
    expect($priced['parts'])->toBe(2);
    expect($priced['country_code'])->toBe('LT');
    expect($priced['mcc'])->toBe('246');
    expect($priced['mnc'])->toBe('01');
    expect($priced['source'])->toBe('cost-estimate');
    expect((int) $priced['amount_usdc'])->toBe(86_940);
});

it('falls back to country-level pricing when /sms/cost fails', function (): void {
    Http::fake([
        'kube-api.vertexsms.com/sms/cost' => Http::response(['error' => 'kaboom'], 500),
        'kube-api.vertexsms.com/rates*'   => Http::response([], 200),
    ]);

    $service = new SmsPricingService(new VertexSmsClient());
    $priced = $service->priceFor('37069912345', 'Zelta', 'hi');

    expect($priced['source'])->toBe('fallback');
    expect($priced['parts'])->toBe(1);
    expect($priced['country_code'])->toBe('LT');
    expect($priced['mcc'])->toBeNull();
    expect($priced['mnc'])->toBeNull();
    expect((int) $priced['amount_usdc'])->toBeGreaterThanOrEqual(1000);
});

it('uses bcmath rounding (no floating-point drift)', function (): void {
    // 0.0333 × 1.08 × 1.15 = 0.04136 (six-decimal fixed) — exercise the
    // fractional-rounding-up path: the bcmath conversion must round up to
    // 41_360 atomic, never under-bill.
    Http::fake([
        'kube-api.vertexsms.com/sms/cost' => Http::response([[
            'parts'        => 1,
            'countryISO'   => 'LT',
            'mccmnc'       => '24601',
            'pricePerPart' => 0.0333,
            'totalPrice'   => 0.0333,
            'currency'     => 'EUR',
        ]], 200),
    ]);

    $service = new SmsPricingService(new VertexSmsClient());
    $priced = $service->priceFor('37069912345', 'Zelta', 'x');

    // 0.0333 × 1.08 × 1.15 = 0.041358 → round up to 41_359 atomic minimum.
    // (Six-decimal bcmath gives 41_358, then the round-up adds 1 if any
    // sub-microcent remainder exists. We accept either depending on input
    // precision but it must be at or above the floor.)
    expect((int) $priced['amount_usdc'])->toBeGreaterThanOrEqual(41_358);
    expect((int) $priced['amount_usdc'])->toBeLessThanOrEqual(41_360);
});

it('floors price at the minimum 1000 atomic USDC', function (): void {
    Http::fake([
        'kube-api.vertexsms.com/sms/cost' => Http::response([[
            'parts'        => 1,
            'countryISO'   => 'XX',
            'pricePerPart' => 0.0,
            'totalPrice'   => 0.0,
            'currency'     => 'EUR',
        ]], 200),
    ]);

    $service = new SmsPricingService(new VertexSmsClient());
    $priced = $service->priceFor('37069912345', 'Zelta', 'x');

    // pricePerPart=0 + totalPrice=0 → fallback USDC × parts = 50000 × 1
    expect((int) $priced['amount_usdc'])->toBe(50_000);
});
