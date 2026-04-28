<?php

declare(strict_types=1);

use App\Domain\MCP\Policy\SpendingLimitService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    DB::table('mcp_token_policies')->insert([
        'token_id'              => 'tok_a',
        'daily_limit_minor'     => 50000, // $500.00
        'daily_limit_currency'  => 'USD',
        'daily_spend_minor'     => 0,
        'daily_window_start_at' => now(),
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
});

it('allows spend within limit and increments counter', function () {
    $svc = new SpendingLimitService();
    $result = $svc->reserve('tok_a', 10000, 'USD');

    expect($result['allowed'])->toBeTrue();
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(10000);
});

it('rejects spend that would exceed limit and reports remaining', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update(['daily_spend_minor' => 45000]);
    $svc = new SpendingLimitService();
    $result = $svc->reserve('tok_a', 10000, 'USD');

    expect($result['allowed'])->toBeFalse();
    expect($result)->toHaveKey('limit_remaining_minor');
    expect($result)->toHaveKey('window_resets_at');
    expect($result['limit_remaining_minor'] ?? null)->toBe(5000);
});

it('rolls the window after 24 hours and resets the counter', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update([
        'daily_spend_minor'     => 49000,
        'daily_window_start_at' => now()->subHours(25),
    ]);
    $svc = new SpendingLimitService();
    $result = $svc->reserve('tok_a', 10000, 'USD');

    expect($result['allowed'])->toBeTrue();
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(10000);
});

it('rejects with NO_POLICY when token has no policy row', function () {
    $svc = new SpendingLimitService();
    $result = $svc->reserve('tok_unknown', 100, 'USD');

    expect($result['allowed'])->toBeFalse();
    expect($result['error_code'] ?? null)->toBe('NO_POLICY');
});

it('rejects with CURRENCY_MISMATCH when currency differs from policy', function () {
    $svc = new SpendingLimitService();
    $result = $svc->reserve('tok_a', 1000, 'EUR');

    expect($result['allowed'])->toBeFalse();
    expect($result['error_code'] ?? null)->toBe('CURRENCY_MISMATCH');
});

it('allows spend exactly at the remaining limit', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update(['daily_spend_minor' => 40000]);
    $svc = new SpendingLimitService();
    $result = $svc->reserve('tok_a', 10000, 'USD');

    expect($result['allowed'])->toBeTrue();
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(50000);
});

it('release() compensates a prior reserve by subtracting the amount', function () {
    $svc = new SpendingLimitService();
    $svc->reserve('tok_a', 10000, 'USD');
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(10000);

    $svc->release('tok_a', 10000, 'USD');
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(0);
});

it('release() floors at 0 when amount exceeds current spend', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update(['daily_spend_minor' => 5000]);
    $svc = new SpendingLimitService();
    $svc->release('tok_a', 50000, 'USD');

    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(0);
});

it('release() is a no-op when currency mismatches policy', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update(['daily_spend_minor' => 10000]);
    $svc = new SpendingLimitService();
    $svc->release('tok_a', 5000, 'EUR');

    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(10000);
});

it('release() is a no-op when the daily window has already rolled', function () {
    // Stale window: counter has already been (or would be) reset; releasing the
    // old reservation would push the new fresh window below 0.
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update([
        'daily_spend_minor'     => 0,
        'daily_window_start_at' => now()->subHours(25),
    ]);
    $svc = new SpendingLimitService();
    $svc->release('tok_a', 5000, 'USD');

    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(0);
});

it('release() is a no-op when no policy row exists', function () {
    $svc = new SpendingLimitService();
    $svc->release('tok_unknown', 5000, 'USD');
    // Nothing to assert — just shouldn't throw.
    expect(true)->toBeTrue();
});

it('release() is a no-op for non-positive amounts', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update(['daily_spend_minor' => 5000]);
    $svc = new SpendingLimitService();
    $svc->release('tok_a', 0, 'USD');
    $svc->release('tok_a', -100, 'USD');

    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(5000);
});
