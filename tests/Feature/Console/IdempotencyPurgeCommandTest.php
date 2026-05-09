<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function makeIdempotencyRow(Carbon $expiresAt, array $overrides = []): void
{
    DB::table('idempotency_keys')->insert(array_replace([
        'user_id'          => 'user-1',
        'idempotency_key'  => 'key-' . uniqid('', true),
        'request_hash'     => str_repeat('a', 64),
        'response_status'  => 200,
        'response_body'    => '{"ok":true}',
        'response_headers' => '{}',
        'expires_at'       => $expiresAt,
        'created_at'       => Carbon::now(),
    ], $overrides));
}

it('purges expired rows', function (): void {
    makeIdempotencyRow(Carbon::now()->subHour());
    makeIdempotencyRow(Carbon::now()->subDay());

    expect(DB::table('idempotency_keys')->count())->toBe(2);

    $exit = Artisan::call('idempotency:purge');

    expect($exit)->toBe(0)
        ->and(DB::table('idempotency_keys')->count())->toBe(0);
});

it('preserves non-expired rows', function (): void {
    makeIdempotencyRow(Carbon::now()->addHour());
    makeIdempotencyRow(Carbon::now()->addHours(23));

    $exit = Artisan::call('idempotency:purge');

    expect($exit)->toBe(0)
        ->and(DB::table('idempotency_keys')->count())->toBe(2);
});

it('reports nothing-to-do when no expired rows', function (): void {
    makeIdempotencyRow(Carbon::now()->addHour());

    $exit = Artisan::call('idempotency:purge');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('No expired idempotency keys');
});

it('does not delete rows in --dry-run mode', function (): void {
    makeIdempotencyRow(Carbon::now()->subHour());
    makeIdempotencyRow(Carbon::now()->subDay());

    $exit = Artisan::call('idempotency:purge', ['--dry-run' => true]);

    expect($exit)->toBe(0)
        ->and(DB::table('idempotency_keys')->count())->toBe(2);
});
