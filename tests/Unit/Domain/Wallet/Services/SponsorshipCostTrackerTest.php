<?php

declare(strict_types=1);

use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    // Array cache: isolated per process, no cross-test interference.
    config(['cache.default' => 'array']);
    Cache::flush();
});

/**
 * @return ExchangeRateService&Mockery\MockInterface
 */
function mockRateService(?string $rate): ExchangeRateService
{
    /** @var ExchangeRateService&Mockery\MockInterface $service */
    $service = Mockery::mock(ExchangeRateService::class);

    if ($rate === null) {
        $service->shouldReceive('getRate')->andReturn(null);
    } else {
        $model = new ExchangeRate();
        $model->rate = $rate;
        $service->shouldReceive('getRate')->andReturn($model);
    }

    return $service;
}

it('converts hex quantities to decimal strings without overflowing on 256-bit values', function (): void {
    expect(SponsorshipCostTracker::hexToDecimalString('0x5208'))->toBe('21000')
        ->and(SponsorshipCostTracker::hexToDecimalString('0x3b9aca00'))->toBe('1000000000')
        ->and(SponsorshipCostTracker::hexToDecimalString('5208'))->toBe('21000')
        ->and(SponsorshipCostTracker::hexToDecimalString('0x0'))->toBe('0')
        ->and(SponsorshipCostTracker::hexToDecimalString(''))->toBe('0')
        ->and(SponsorshipCostTracker::hexToDecimalString('not-hex'))->toBe('0')
        // 2^256 - 1: hexdec() would lose precision here; bcmath must not.
        ->and(SponsorshipCostTracker::hexToDecimalString(
            '0xffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff'
        ))->toBe('115792089237316195423570985008687907853269984665640564039457584007913129639935');
});

it('computes gasUsed * effectiveGasPrice with bcmath (21000 gas at 1 gwei)', function (): void {
    $wei = bcmul(
        SponsorshipCostTracker::hexToDecimalString('0x5208'),
        SponsorshipCostTracker::hexToDecimalString('0x3b9aca00'),
        0,
    );

    expect($wei)->toBe('21000000000000');
});

it('maps networks to fee assets and rate symbols', function (): void {
    expect(SponsorshipCostTracker::feeAssetForNetwork('solana'))->toBe('SOL')
        ->and(SponsorshipCostTracker::feeAssetForNetwork('polygon'))->toBe('MATIC')
        ->and(SponsorshipCostTracker::feeAssetForNetwork('base'))->toBe('ETH-BASE')
        ->and(SponsorshipCostTracker::feeAssetForNetwork('arbitrum'))->toBe('ETH-ARB')
        ->and(SponsorshipCostTracker::feeAssetForNetwork('ethereum'))->toBe('ETH')
        ->and(SponsorshipCostTracker::feeAssetForNetwork('tron'))->toBeNull()
        ->and(SponsorshipCostTracker::rateSymbolForNetwork('base'))->toBe('ETH')
        ->and(SponsorshipCostTracker::feeDecimalsForNetwork('solana'))->toBe(9)
        ->and(SponsorshipCostTracker::feeDecimalsForNetwork('polygon'))->toBe(18);
});

it('increments the daily USD budget counter from a Solana fee (5000 lamports at $200/SOL = 1000 micro-USD)', function (): void {
    $tracker = new SponsorshipCostTracker(mockRateService('200'));

    $tracker->recordSpendUsd('solana', '5000');

    expect($tracker->todaysSpendUsdMicro())->toBe(1000);
});

it('rounds dust fees UP to a whole micro-USD so they are never counted as zero', function (): void {
    $tracker = new SponsorshipCostTracker(mockRateService('100'));

    // 1 lamport at $100/SOL = 0.0000001 USD = 0.1 micro-USD → ceil → 1
    $tracker->recordSpendUsd('solana', '1');

    expect($tracker->todaysSpendUsdMicro())->toBe(1);
});

it('skips the budget increment (without throwing) when no USD rate is available', function (): void {
    $tracker = new SponsorshipCostTracker(mockRateService(null));

    $tracker->recordSpendUsd('polygon', '200000000000');

    expect($tracker->todaysSpendUsdMicro())->toBe(0);
});

it('ignores non-numeric and non-positive fee inputs', function (): void {
    $tracker = new SponsorshipCostTracker(mockRateService('200'));

    $tracker->recordSpendUsd('solana', 'not-a-number');
    $tracker->recordSpendUsd('solana', '0');

    expect($tracker->todaysSpendUsdMicro())->toBe(0);
});

it('reports the budget exhausted only when the counter reaches the configured USD cap', function (): void {
    config(['wallet.sponsorship.daily_budget_usd' => '10']);

    $tracker = new SponsorshipCostTracker(mockRateService(null));
    $key = SponsorshipCostTracker::budgetCacheKey();

    Cache::add($key, 0, now()->addDay());
    Cache::increment($key, 9_999_999);
    expect($tracker->isDailyBudgetExhausted())->toBeFalse();

    Cache::increment($key, 1); // exactly $10.000000
    expect($tracker->isDailyBudgetExhausted())->toBeTrue();
});

it('never reports the budget exhausted when no budget is configured', function (): void {
    config(['wallet.sponsorship.daily_budget_usd' => null]);

    $tracker = new SponsorshipCostTracker(mockRateService(null));
    $key = SponsorshipCostTracker::budgetCacheKey();

    Cache::add($key, 0, now()->addDay());
    Cache::increment($key, 999_999_999);

    expect($tracker->isDailyBudgetExhausted())->toBeFalse()
        ->and($tracker->dailyBudgetUsd())->toBeNull();
});

it('treats a non-numeric or non-positive configured budget as disabled', function (): void {
    $tracker = new SponsorshipCostTracker(mockRateService(null));

    config(['wallet.sponsorship.daily_budget_usd' => 'banana']);
    expect($tracker->dailyBudgetUsd())->toBeNull();

    config(['wallet.sponsorship.daily_budget_usd' => '0']);
    expect($tracker->dailyBudgetUsd())->toBeNull();

    config(['wallet.sponsorship.daily_budget_usd' => '25.50']);
    expect($tracker->dailyBudgetUsd())->toBe('25.500000');
});
