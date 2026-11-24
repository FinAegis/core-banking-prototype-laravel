<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Route;

/*
 * bootstrap/app.php decides which web middleware to add from APP_ENV, so
 * these tests boot a fresh application under the environment they check.
 *
 * Before the fix the demo banner middleware was never registered, the
 * DemoMode middleware read an undefined `demo.mode` key (so it did nothing
 * on the demo), the banner read `demo.features.show_demo_banner` instead of
 * `demo.ui.show_banner`, and the cleanup schedule read `demo.cleanup.time`
 * instead of `demo.cleanup.schedule_time`.
 */

beforeEach(function (): void {
    $this->originalAppEnv = [
        '_ENV'    => $_ENV['APP_ENV'] ?? null,
        '_SERVER' => $_SERVER['APP_ENV'] ?? null,
        'putenv'  => getenv('APP_ENV'),
    ];
});

afterEach(function (): void {
    restoreAppEnv($this->originalAppEnv);
});

/**
 * @param  array{_ENV: mixed, _SERVER: mixed, putenv: string|false}  $original
 */
function restoreAppEnv(array $original): void
{
    foreach (['_ENV', '_SERVER'] as $superglobal) {
        if ($original[$superglobal] === null) {
            unset($GLOBALS[$superglobal]['APP_ENV']);
        } else {
            $GLOBALS[$superglobal]['APP_ENV'] = $original[$superglobal];
        }
    }

    putenv($original['putenv'] === false ? 'APP_ENV' : 'APP_ENV=' . $original['putenv']);
}

/**
 * @param  array<string, mixed>  $config
 */
function bootApplicationIn(string $environment, array $config = []): Application
{
    $_ENV['APP_ENV'] = $environment;
    $_SERVER['APP_ENV'] = $environment;
    putenv('APP_ENV=' . $environment);

    /** @var Application $app */
    $app = require base_path('bootstrap/app.php');

    // Laravel would load `.env.{APP_ENV}` (for example `.env.demo`); keep the
    // test settings (in-memory database, app key) the normal test app uses.
    $app->loadEnvironmentFrom('.env.testing');

    $app->afterBootstrapping(
        LoadConfiguration::class,
        static function (Application $app) use ($config): void {
            foreach ($config as $key => $value) {
                $app['config']->set($key, $value);
            }
        }
    );

    $app->make(Kernel::class)->bootstrap();

    Route::middleware('web')->get('/__demo-banner-probe', static fn () => '<html><head></head><body><p>probe</p></body></html>');

    return $app;
}

it('shows the demo banner and turns on demo mode in the demo environment', function (): void {
    $this->app = bootApplicationIn('demo');

    expect(config('demo.mode'))->toBeTrue();

    $this->get('/__demo-banner-probe')
        ->assertOk()
        ->assertSee('id="demo-banner"', false)
        ->assertHeader('X-Demo-Mode', 'true');
});

it('shows no demo banner and no demo mode in production', function (): void {
    $this->app = bootApplicationIn('production');

    expect(config('demo.mode'))->toBeFalse();

    $this->get('/__demo-banner-probe')
        ->assertOk()
        ->assertDontSee('demo-banner', false)
        ->assertHeaderMissing('X-Demo-Mode');
});

it('hides the demo banner when the demo banner setting is off', function (): void {
    $this->app = bootApplicationIn('demo', ['demo.ui.show_banner' => false]);

    $this->get('/__demo-banner-probe')
        ->assertOk()
        ->assertDontSee('demo-banner', false);
});

it('schedules the demo cleanup at the configured time', function (): void {
    $this->app = bootApplicationIn('demo', ['demo.cleanup.schedule_time' => '04:30']);

    $cleanup = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'demo:cleanup'));

    expect($cleanup)->not->toBeNull()
        ->and($cleanup?->expression)->toBe('30 4 * * *');
});
