<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/*
 * Which web middleware the app adds depends on APP_ENV, so these tests boot
 * a fresh application under the environment they check.
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
 * Write an env file with the test settings and the given APP_ENV, and take
 * APP_ENV out of the process environment, as on the server where APP_ENV is
 * only in `.env`. (Laravel's env repository lets Dotenv overwrite variables
 * it loaded before, so setting APP_ENV in the process is not reliable here.).
 */
function makeEnvDirFor(string $environment): string
{
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');

    $envDir = sys_get_temp_dir() . '/demo-env-' . bin2hex(random_bytes(6));
    mkdir($envDir);
    $settings = (string) preg_replace('/^APP_ENV=.*$/m', '', (string) file_get_contents(base_path('.env.testing')));
    file_put_contents($envDir . '/.env', "APP_ENV={$environment}\n" . $settings);

    return $envDir;
}

function removeEnvDir(string $envDir): void
{
    @unlink($envDir . '/.env');
    @rmdir($envDir);
}

/**
 * @param  array<string, mixed>  $config
 */
function bootApplicationIn(string $environment, array $config = []): Application
{
    $envDir = makeEnvDirFor($environment);

    try {
        /** @var Application $app */
        $app = require base_path('bootstrap/app.php');
        $app->useEnvironmentPath($envDir);

        $app->afterBootstrapping(
            LoadConfiguration::class,
            static function (Application $app) use ($config): void {
                foreach ($config as $key => $value) {
                    $app['config']->set($key, $value);
                }
            }
        );

        $app->make(Kernel::class)->bootstrap();
    } finally {
        removeEnvDir($envDir);
    }

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

/**
 * Serve one web request the way public/index.php does on the server: the
 * HTTP kernel is resolved before `.env` is loaded, and APP_ENV exists only
 * in the env file, not in the process environment.
 */
function serveFromEnvFile(string $environment): Response
{
    $envDir = makeEnvDirFor($environment);

    try {
        /** @var Application $app */
        $app = require base_path('bootstrap/app.php');
        $app->useEnvironmentPath($envDir);
        $app->booted(static function (Application $app): void {
            $app['router']->middleware('web')->get('/__demo-banner-probe', static fn () => '<html><head></head><body><p>probe</p></body></html>');
        });

        return $app->make(HttpKernel::class)->handle(Request::create('/__demo-banner-probe'));
    } finally {
        removeEnvDir($envDir);
    }
}

it('adds demo mode and the banner when APP_ENV=demo comes only from the env file', function (): void {
    $response = serveFromEnvFile('demo');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('X-Demo-Mode'))->toBe('true')
        ->and((string) $response->getContent())->toContain('id="demo-banner"');
});

it('adds neither when APP_ENV=production comes only from the env file', function (): void {
    $response = serveFromEnvFile('production');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->has('X-Demo-Mode'))->toBeFalse()
        ->and((string) $response->getContent())->not->toContain('demo-banner');
});
