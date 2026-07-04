<?php

declare(strict_types=1);

use App\Values\EventQueues;

/**
 * Queue-worker coverage guard (mirrors tests/Feature/Compliance/GdprCoverageGuardTest.php).
 *
 * Every queue application code dispatches to MUST (1) be declared in
 * config('queue.managed_queues') and (2) have a consuming worker in
 * etc/supervisor.conf — the production runner. Catches the class of bug where
 * a new listener/job queues to a name no worker consumes (e.g. push
 * notifications on the 'mobile' queue, or transfer projections on 'transfers',
 * silently never processed).
 *
 * Dispatched queues are discovered from: (a) every App\Values\EventQueues case
 * (the event-sourcing source of truth — events set $queue = EventQueues::X->value),
 * and (b) quoted ->onQueue('x') / $queue = 'x' literals across app/.
 */

/** @return array<string,string> queue name => where it is dispatched from */
function dispatchedQueues(): array
{
    $found = [];

    // (a) event-sourcing queues — the enum is the canonical source of truth
    foreach (EventQueues::cases() as $case) {
        $found[$case->value] = 'App\\Values\\EventQueues';
    }

    // (b) quoted onQueue('x') / $queue = 'x' targets across app/
    $appDir = dirname(__DIR__, 3) . '/app';
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
    );
    /** @var SplFileInfo $file */
    foreach ($rii as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        if (preg_match_all('/onQueue\(\s*[\'"]([a-z0-9_\-]+)[\'"]\s*\)/i', $code, $m)) {
            foreach ($m[1] as $q) {
                $found[$q] ??= $file->getPathname();
            }
        }
        if (preg_match_all('/\$queue\s*=\s*[\'"]([a-z0-9_\-]+)[\'"]/', $code, $m)) {
            foreach ($m[1] as $q) {
                $found[$q] ??= $file->getPathname();
            }
        }
    }

    return $found;
}

/** @return array<int,string> queue names consumed by etc/supervisor.conf */
function supervisorConsumedQueues(): array
{
    $conf = (string) file_get_contents(dirname(__DIR__, 3) . '/etc/supervisor.conf');
    $queues = [];
    foreach (preg_split('/\R/', $conf) ?: [] as $line) {
        if (! str_contains($line, 'queue:work')) {
            continue;
        }
        if (preg_match('/--queue=([a-z0-9_,\-]+)/i', $line, $m)) {
            foreach (explode(',', $m[1]) as $q) {
                $queues[] = $q;
            }
        } else {
            $queues[] = 'default'; // a bare `queue:work` consumes the 'default' queue
        }
    }

    return array_values(array_unique($queues));
}

it('declares every dispatched queue in config queue.managed_queues', function () {
    /** @var array<int,string> $managed */
    $managed = config('queue.managed_queues');
    expect($managed)->toBeArray();
    expect($managed)->toContain('default');

    $undeclared = array_keys(array_filter(
        dispatchedQueues(),
        fn (string $file, string $queue): bool => ! in_array($queue, $managed, true),
        ARRAY_FILTER_USE_BOTH
    ));

    if ($undeclared !== []) {
        $this->fail('Queues dispatched to but missing from config(queue.managed_queues): ' . implode(', ', $undeclared));
    }
    expect($undeclared)->toBe([]);
});

it('has a supervisor worker for every managed queue', function () {
    /** @var array<int,string> $managed */
    $managed = config('queue.managed_queues');
    $consumed = supervisorConsumedQueues();

    $uncovered = array_values(array_diff($managed, $consumed));

    if ($uncovered !== []) {
        $this->fail('Managed queues with NO worker in etc/supervisor.conf (jobs queue forever): ' . implode(', ', $uncovered));
    }
    expect($uncovered)->toBe([]);
});
