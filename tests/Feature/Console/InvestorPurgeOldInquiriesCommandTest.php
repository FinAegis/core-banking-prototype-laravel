<?php

declare(strict_types=1);

use App\Models\InvestorInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Build a row with a back-dated created_at. We hit the DB directly because
 * Eloquent's mass-assign clobbers `created_at` with `now()` even when it's
 * passed via create().
 *
 * @param  array<string, mixed>  $overrides
 */
function makeInquiryAt(Carbon $createdAt, array $overrides = []): InvestorInquiry
{
    /** @var InvestorInquiry $inquiry */
    $inquiry = InvestorInquiry::create(array_replace([
        'name'             => 'Test Investor',
        'email'            => 'investor@example.com',
        'linkedin_url'     => 'https://linkedin.com/in/test',
        'investing_as'     => 'angel',
        'path_of_interest' => 'licensed',
        'check_size_range' => '25k_100k',
        'questions'        => null,
        'ip_hash'          => str_repeat('a', 64),
        'user_agent'       => 'Test',
    ], $overrides));

    // Force the timestamp; the table only has created_at.
    InvestorInquiry::query()
        ->where('id', $inquiry->id)
        ->update(['created_at' => $createdAt]);

    return $inquiry->fresh() ?? $inquiry;
}

it('deletes inquiries older than 24 months', function (): void {
    $oldOne = makeInquiryAt(Carbon::now()->subMonths(25));
    $oldTwo = makeInquiryAt(Carbon::now()->subYears(3));

    expect(InvestorInquiry::query()->count())->toBe(2);

    $exit = Artisan::call('investor:purge-old-inquiries');

    expect($exit)->toBe(0)
        ->and(InvestorInquiry::query()->count())->toBe(0)
        ->and(InvestorInquiry::query()->find($oldOne->id))->toBeNull()
        ->and(InvestorInquiry::query()->find($oldTwo->id))->toBeNull();
});

it('preserves inquiries that are 23 months old', function (): void {
    $recent = makeInquiryAt(Carbon::now()->subMonths(23));
    $brandNew = makeInquiryAt(Carbon::now()->subDay());

    $exit = Artisan::call('investor:purge-old-inquiries');

    expect($exit)->toBe(0)
        ->and(InvestorInquiry::query()->count())->toBe(2)
        ->and(InvestorInquiry::query()->find($recent->id))->not->toBeNull()
        ->and(InvestorInquiry::query()->find($brandNew->id))->not->toBeNull();
});

it('reports nothing-to-do when the table has no old rows', function (): void {
    makeInquiryAt(Carbon::now()->subDay());

    $exit = Artisan::call('investor:purge-old-inquiries');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('No investor inquiries older than 24 months');
});

it('does not delete in --dry-run mode', function (): void {
    makeInquiryAt(Carbon::now()->subYears(3));
    makeInquiryAt(Carbon::now()->subMonths(25));

    $exit = Artisan::call('investor:purge-old-inquiries', ['--dry-run' => true]);

    expect($exit)->toBe(0)
        ->and(InvestorInquiry::query()->count())->toBe(2);
});
