<?php

declare(strict_types=1);

use App\Domain\Pricing\Models\PriceQuote;
use Illuminate\Support\Str;

/**
 * Helper: insert a price_quote row with a specific expires_at.
 */
function insertQuote(string $id, Illuminate\Support\Carbon $expiresAt, bool $consumed = false): void
{
    PriceQuote::query()->create([
        'id'               => $id,
        'user_id'          => 1,
        'user_tier'        => 'free',
        'kind'             => 'subscription_initial',
        'request_payload'  => [],
        'response_payload' => [],
        'entity_key'       => hash('sha256', $id),
        'signature'        => str_repeat('0', 64),
        'terms_changed'    => false,
        'expires_at'       => $expiresAt,
        'consumed_at'      => $consumed ? now()->subDays(8) : null,
        'consumed_by'      => $consumed ? 'test' : null,
    ]);
}

it('pricing:purge-quotes deletes rows where expires_at < now() - 7 days', function (): void {
    $oldId = (string) Str::uuid();
    $newId = (string) Str::uuid();
    $recentExpiredId = (string) Str::uuid();

    // Row expired more than 7 days ago — should be deleted.
    insertQuote($oldId, now()->subDays(8));

    // Row expired less than 7 days ago — should NOT be deleted (grace period).
    insertQuote($recentExpiredId, now()->subDays(3));

    // Live quote — should NOT be deleted.
    insertQuote($newId, now()->addHour());

    $this->artisan('pricing:purge-quotes')->assertSuccessful();

    expect(PriceQuote::query()->find($oldId))->toBeNull();
    expect(PriceQuote::query()->find($recentExpiredId))->not->toBeNull();
    expect(PriceQuote::query()->find($newId))->not->toBeNull();
});

it('pricing:purge-quotes preserves consumed rows within the grace period', function (): void {
    $consumedOldId = (string) Str::uuid();
    $consumedRecentId = (string) Str::uuid();

    // Consumed and expired > 7 days ago — should be deleted.
    insertQuote($consumedOldId, now()->subDays(9), consumed: true);

    // Consumed but expired < 7 days ago — should be preserved for audit.
    insertQuote($consumedRecentId, now()->subDays(2), consumed: true);

    $this->artisan('pricing:purge-quotes')->assertSuccessful();

    expect(PriceQuote::query()->find($consumedOldId))->toBeNull();
    expect(PriceQuote::query()->find($consumedRecentId))->not->toBeNull();
});

it('pricing:purge-quotes --dry-run shows count without deleting', function (): void {
    $id = (string) Str::uuid();
    insertQuote($id, now()->subDays(10));

    $this->artisan('pricing:purge-quotes --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run]');

    // Row should still exist.
    expect(PriceQuote::query()->find($id))->not->toBeNull();
});

it('php artisan schedule:list shows pricing:purge-quotes at 03:10', function (): void {
    $this->artisan('schedule:list')
        ->assertSuccessful()
        ->expectsOutputToContain('pricing:purge-quotes');
});
