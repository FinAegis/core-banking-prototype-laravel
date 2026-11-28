<?php

/**
 * The transaction limiter read its counters, checked them, and added to them
 * only later. In between it may sleep for up to five seconds (the progressive
 * delay), so requests sent at the same moment all passed a limit that only
 * one of them fitted under. The counters also used get + put, which loses
 * counts under concurrency.
 *
 * The limiter now takes its share of each counter first (Cache::add +
 * increment), compares, and gives it back when it refuses. The tests send a
 * second request at the point where the progressive delay runs.
 */

declare(strict_types=1);

use App\Http\Middleware\TransactionRateLimitMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config(['rate_limiting.force_in_tests' => true, 'cache.default' => 'array']);
    Cache::flush();
    $this->user = User::factory()->create();
});

function limiterRequest(User $user, string $amount): Request
{
    $request = Request::create('/api/transfers', 'POST', ['amount' => $amount]);
    $request->setUserResolver(fn () => $user);

    return $request;
}

function limiterStatus(User $user, string $amount): int
{
    return app(TransactionRateLimitMiddleware::class)
        ->handle(limiterRequest($user, $amount), fn () => response('ok'), 'transfer')
        ->getStatusCode();
}

/**
 * Runs a first request and, while it is in the progressive delay, a second
 * one. Returns both statuses.
 *
 * @return array{int, int}
 */
function limiterTwoAtOnce(User $user, string $amount): array
{
    // More than three recent transfers turns the progressive delay on.
    Cache::put("tx_recent:{$user->id}:transfer", 4, 300);
    $second = null;

    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($user, $amount, &$second): void {
        if ($second === null && $event->message === 'Progressive delay applied') {
            $second = 0;
            $second = limiterStatus($user, $amount);
        }
    });

    $first = limiterStatus($user, $amount);

    return [$first, (int) $second];
}

it('lets only one of two requests at the same moment take the last hourly transfer', function (): void {
    Cache::put("tx_rate_limit:{$this->user->id}:transfer:hourly", 14, 3600);

    [$first, $second] = limiterTwoAtOnce($this->user, '1.00');

    expect([$first, $second])->toEqualCanonicalizing([200, 429])
        ->and((int) Cache::get("tx_rate_limit:{$this->user->id}:transfer:hourly"))->toBe(15);
});

it('lets only one of two requests at the same moment take the last of the hourly amount', function (): void {
    Cache::put("tx_amount_limit:{$this->user->id}:transfer:hourly", 199_000, 3600);

    [$first, $second] = limiterTwoAtOnce($this->user, '10.00');

    expect([$first, $second])->toEqualCanonicalizing([200, 429])
        ->and((int) Cache::get("tx_amount_limit:{$this->user->id}:transfer:hourly"))->toBe(200_000);
});

it('does not count a request that a later limit refuses', function (): void {
    Cache::put("tx_rate_limit:{$this->user->id}:transfer:hourly", 3, 3600);
    Cache::put("tx_amount_limit:{$this->user->id}:transfer:hourly", 199_000, 3600);

    expect(limiterStatus($this->user, '20.00'))->toBe(429)
        ->and((int) Cache::get("tx_rate_limit:{$this->user->id}:transfer:hourly"))->toBe(3)
        ->and((int) Cache::get("tx_rate_limit:{$this->user->id}:transfer:daily", 0))->toBe(0)
        ->and((int) Cache::get("tx_amount_limit:{$this->user->id}:transfer:hourly"))->toBe(199_000);
});

it('keeps the counts right when consume() is refused by the daily limit', function (): void {
    Cache::put("tx_rate_limit:{$this->user->id}:transfer:daily", 100, 86400);

    expect(TransactionRateLimitMiddleware::consume((int) $this->user->id, 'transfer'))->toBeFalse()
        ->and((int) Cache::get("tx_rate_limit:{$this->user->id}:transfer:hourly", 0))->toBe(0)
        ->and((int) Cache::get("tx_rate_limit:{$this->user->id}:transfer:daily"))->toBe(100);
});

it('still counts an allowed request once on every counter', function (): void {
    expect(limiterStatus($this->user, '12.34'))->toBe(200)
        ->and((int) Cache::get("tx_rate_limit:{$this->user->id}:transfer:hourly"))->toBe(1)
        ->and((int) Cache::get("tx_rate_limit:{$this->user->id}:transfer:daily"))->toBe(1)
        ->and((int) Cache::get("tx_amount_limit:{$this->user->id}:transfer:hourly"))->toBe(1234);
});
