<?php

declare(strict_types=1);

use App\Domain\Compliance\Aggregates\AmlScreeningAggregate;
use App\Domain\Compliance\Events\AmlScreeningReviewed;
use App\Domain\Compliance\Models\AmlScreening;
use App\Domain\Compliance\Services\AmlScreeningService;
use App\Models\User;
use Illuminate\Support\Str;

it('records the review on the screening aggregate with the reviewer id as a string', function (): void {
    // AmlScreeningService declares strict types, so passing the integer user
    // id to AmlScreeningAggregate::reviewScreening(string $reviewedBy) threw a
    // TypeError. That is an Error, not an Exception, so the legacy fallback
    // never ran and every aggregate-backed review failed.
    $reviewer = User::factory()->create();

    $screening = new AmlScreening();
    $screening->id = (string) Str::uuid();
    $screening->entity_type = 'organisation';

    app(AmlScreeningService::class)->reviewScreening($screening, 'clear', 'No true match.', $reviewer);

    $recorded = AmlScreeningAggregate::retrieve($screening->id)->getAppliedEvents();
    $reviewed = collect($recorded)->first(fn (object $event): bool => $event instanceof AmlScreeningReviewed);

    expect($reviewed)->toBeInstanceOf(AmlScreeningReviewed::class)
        ->and($reviewed->reviewedBy)->toBe((string) $reviewer->id)
        ->and($reviewed->decision)->toBe('clear');
});
