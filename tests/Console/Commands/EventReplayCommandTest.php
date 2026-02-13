<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

describe('EventReplayCommand', function () {
    it('shows dry run output for all events', function () {
        $this->artisan('event:replay --dry-run')
            ->expectsOutput('DRY RUN - No events will be replayed.')
            ->expectsOutput('Dry run complete. No events were replayed.')
            ->assertSuccessful();
    });

    it('shows dry run output for specific domain', function () {
        $this->artisan('event:replay --domain=Account --dry-run')
            ->expectsOutput('DRY RUN - No events will be replayed.')
            ->expectsOutput('Domain: Account')
            ->expectsOutput('Event table: account_events')
            ->expectsOutput('Dry run complete. No events were replayed.')
            ->assertSuccessful();
    });

    it('shows dry run with date range', function () {
        $this->artisan('event:replay --domain=Account --from=2024-01-01 --to=2026-12-31 --dry-run')
            ->expectsOutput('DRY RUN - No events will be replayed.')
            ->expectsOutput('Domain: Account')
            ->expectsOutput('From: 2024-01-01')
            ->expectsOutput('To: 2026-12-31')
            ->assertSuccessful();
    });

    it('rejects projector outside App namespace', function () {
        $this->artisan('event:replay', ['--projector' => 'SomeProjector', '--dry-run' => true])
            ->expectsOutput('Invalid projector class: SomeProjector (must be in App\\ namespace)')
            ->assertFailed();
    });

    it('rejects non-existent projector class', function () {
        $this->artisan('event:replay', ['--projector' => 'App\\Domain\\Fake\\Projectors\\FakeProjector', '--dry-run' => true])
            ->expectsOutput('Projector class not found: App\\Domain\\Fake\\Projectors\\FakeProjector')
            ->assertFailed();
    });

    it('fails for unknown domain', function () {
        $this->artisan('event:replay --domain=NonExistent')
            ->expectsOutput('Unknown domain: NonExistent')
            ->assertFailed();
    });

    it('has correct command signature', function () {
        $command = new App\Console\Commands\EventReplayCommand();

        expect($command->getName())->toBe('event:replay');
        expect($command->getDescription())->toBe('Replay stored events through projectors to rebuild read models');
    });
});
