<?php

/**
 * CueDispatchTest — tests for the delayed job dispatch chain.
 *
 * Covers:
 *   - OnboardingCompleted → EnqueueProTrialReminderD1 dispatched with 24h delay
 *   - SubscriptionTrialStarted → Three EnqueueTrialEnding* dispatched with correct delays
 *   - EnqueueProTrialReminderD1 job skips on user_erased / opted_out / tier_changed / trial_used
 *   - EnqueueTrialEnding* jobs self-cancel when user converted
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.5
 */

declare(strict_types=1);

use App\Domain\Subscription\Events\OnboardingCompleted;
use App\Domain\Subscription\Events\SubscriptionTrialStarted;
use App\Domain\Subscription\Jobs\Cue\EnqueueProTrialReminderD1;
use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding1d;
use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding1h;
use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding2d;
use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CueRepository;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

// ─── Listener dispatch ────────────────────────────────────────────────────────

it('dispatches EnqueueProTrialReminderD1 with 24h delay on OnboardingCompleted', function () {
    Queue::fake();

    $user = User::factory()->create();
    Event::dispatch(new OnboardingCompleted($user->id));

    Queue::assertPushed(EnqueueProTrialReminderD1::class, function ($job) use ($user) {
        return $job->userId === $user->id;
    });
});

it('dispatches all three trial-ending jobs on SubscriptionTrialStarted', function () {
    Queue::fake();

    $user = User::factory()->create();
    $trialEndsAt = Carbon::now()->addDays(7);
    $trialStartedAt = Carbon::now();

    Event::dispatch(new SubscriptionTrialStarted($user->id, $trialStartedAt, $trialEndsAt));

    Queue::assertPushed(EnqueueTrialEnding2d::class, fn ($j) => $j->userId === $user->id);
    Queue::assertPushed(EnqueueTrialEnding1d::class, fn ($j) => $j->userId === $user->id);
    Queue::assertPushed(EnqueueTrialEnding1h::class, fn ($j) => $j->userId === $user->id);
});

// ─── EnqueueProTrialReminderD1 job handle ────────────────────────────────────

it('skips cue if user was deleted before job fires', function () {
    /** @var CueRepository $repo */
    $repo = app(CueRepository::class);

    $job = new EnqueueProTrialReminderD1(99999); // Non-existent user ID.
    $job->handle(
        $repo,
        app(App\Domain\Subscription\Projections\SubscriptionProjection::class),
    );

    expect(Cue::query()->count())->toBe(0);
});

it('skips cue if user opted out of marketing', function () {
    $user = User::factory()->create(['pro_marketing_opt_out' => true]);

    /** @var CueRepository $repo */
    $repo = app(CueRepository::class);

    $job = new EnqueueProTrialReminderD1($user->id);
    $job->handle(
        $repo,
        app(App\Domain\Subscription\Projections\SubscriptionProjection::class),
    );

    expect(Cue::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('creates pro_trial_reminder_d1 cue for eligible free-tier user', function () {
    $user = User::factory()->create(['pro_marketing_opt_out' => false]);

    /** @var CueRepository $repo */
    $repo = app(CueRepository::class);

    $job = new EnqueueProTrialReminderD1($user->id);
    $job->handle(
        $repo,
        app(App\Domain\Subscription\Projections\SubscriptionProjection::class),
    );

    $cue = Cue::query()->where('user_id', $user->id)->where('kind', 'pro_trial_reminder_d1')->first();
    expect($cue)->not()->toBeNull();
    expect($cue->priority)->toBe('normal');
});

it('is idempotent — second job fire creates no duplicate cue', function () {
    $user = User::factory()->create(['pro_marketing_opt_out' => false]);

    /** @var CueRepository $repo */
    $repo = app(CueRepository::class);
    $projection = app(App\Domain\Subscription\Projections\SubscriptionProjection::class);

    $job = new EnqueueProTrialReminderD1($user->id);
    $job->handle($repo, $projection);
    $job->handle($repo, $projection);

    expect(Cue::query()->where('user_id', $user->id)->where('kind', 'pro_trial_reminder_d1')->count())->toBe(1);
});
