<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
| The legacy password sign-up (POST /api/auth/register, /api/v2/auth/register)
| and the legacy social sign-up have their own switch,
| REGISTRATION_PASSWORD_API_ENABLED, so production can keep Privy sign-ups
| open (REGISTRATION_API_ENABLED=true) while the password path stays closed.
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array']);
});

/**
 * Evaluate config/registration.php with the given environment variables
 * (null = unset) and restore the process environment afterwards.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function pbgRegistrationConfigUnderEnv(array $env): array
{
    $saved = [];
    foreach ($env as $key => $value) {
        $saved[$key] = [array_key_exists($key, $_SERVER), $_SERVER[$key] ?? null, array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/registration.php');

        return $config;
    } finally {
        foreach ($saved as $key => [$hasServer, $server, $hasEnv, $envValue, $getenv]) {
            if ($hasServer) {
                $_SERVER[$key] = $server;
            } else {
                unset($_SERVER[$key]);
            }
            if ($hasEnv) {
                $_ENV[$key] = $envValue;
            } else {
                unset($_ENV[$key]);
            }
            if ($getenv === false) {
                putenv($key);
            } else {
                putenv("{$key}={$getenv}");
            }
        }
    }
}

/**
 * @return array<string, mixed>
 */
function pbgRegisterBody(string $email): array
{
    return [
        'name'                  => 'Probe Person',
        'email'                 => $email,
        'password'              => 'Sup3r-Secret-Pass!',
        'password_confirmation' => 'Sup3r-Secret-Pass!',
    ];
}

function pbgFakeSocialUser(string $email, string $id): void
{
    $socialUser = (new SocialiteUser())->map(['id' => $id, 'name' => 'Social Person', 'email' => $email, 'avatar' => null]);

    $provider = Mockery::mock();
    /** @var Mockery\Expectation $stateless */
    $stateless = $provider->shouldReceive('stateless');
    $stateless->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

describe('legacy password sign-up switch defaults', function (): void {
    it('is off in production, staging, a typo or an unset APP_ENV', function (): void {
        foreach (['production', 'staging', 'Production', null] as $appEnv) {
            $config = pbgRegistrationConfigUnderEnv(['APP_ENV' => $appEnv, 'REGISTRATION_PASSWORD_API_ENABLED' => null]);

            expect($config)->toHaveKey('password_api_enabled')
                ->and($config['password_api_enabled'])->toBeFalse();
        }
    });

    it('is on in local, testing and demo', function (string $appEnv): void {
        $config = pbgRegistrationConfigUnderEnv(['APP_ENV' => $appEnv, 'REGISTRATION_PASSWORD_API_ENABLED' => null]);

        expect($config['password_api_enabled'] ?? null)->toBeTrue();
    })->with(['local', 'testing', 'demo']);

    it('stays off in production when only Privy sign-ups are opened', function (): void {
        $config = pbgRegistrationConfigUnderEnv([
            'APP_ENV'                           => 'production',
            'REGISTRATION_API_ENABLED'          => 'true',
            'REGISTRATION_PASSWORD_API_ENABLED' => null,
        ]);

        expect($config['api_enabled'])->toBeTrue()
            ->and($config['password_api_enabled'] ?? null)->toBeFalse();
    });

    it('lets an explicit env value win', function (): void {
        $config = pbgRegistrationConfigUnderEnv(['APP_ENV' => 'production', 'REGISTRATION_PASSWORD_API_ENABLED' => 'true']);
        expect($config['password_api_enabled'] ?? null)->toBeTrue();

        $config = pbgRegistrationConfigUnderEnv(['APP_ENV' => 'demo', 'REGISTRATION_PASSWORD_API_ENABLED' => 'false']);
        expect($config['password_api_enabled'] ?? null)->toBeFalse();
    });
});

describe('legacy password sign-up while Privy sign-ups are open', function (): void {
    it('answers 403 ERR_REG_001 and creates no account while the password switch is off', function (string $path): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false, 'registration.password_api_enabled' => false]);

        $this->postJson($path, pbgRegisterBody('probe@example.com'))
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ERR_REG_001')
            ->assertJsonPath('error.reason', 'REGISTRATION_CLOSED');

        expect(User::where('email', 'probe@example.com')->exists())->toBeFalse();
    })->with(['/api/auth/register', '/api/v2/auth/register']);

    it('does not reveal a taken email while the password switch is off', function (): void {
        config(['registration.api_enabled' => true, 'registration.password_api_enabled' => false]);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', pbgRegisterBody('taken@example.com'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_001');
    });

    it('stays closed while all API sign-ups are closed, even with the password switch on', function (): void {
        config(['registration.api_enabled' => false, 'registration.password_api_enabled' => true]);

        $this->postJson('/api/auth/register', pbgRegisterBody('both@example.com'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_001');

        expect(User::where('email', 'both@example.com')->exists())->toBeFalse();
    });

    it('registers when both switches are on', function (string $path): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false, 'registration.password_api_enabled' => true]);

        $this->postJson($path, pbgRegisterBody('open@example.com'))->assertCreated();

        expect(User::where('email', 'open@example.com')->exists())->toBeTrue();
    })->with(['/api/auth/register', '/api/v2/auth/register']);

    it('blocks the legacy social sign-up of a new account while the password switch is off', function (): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false, 'registration.password_api_enabled' => false]);

        pbgFakeSocialUser('socialprobe@example.com', 'g-probe');
        $this->postJson('/api/auth/social/google/callback', ['code' => 'abc'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_001');

        expect(User::where('email', 'socialprobe@example.com')->exists())->toBeFalse();
    });

    it('keeps a linked social user signing in while the password switch is off', function (): void {
        config(['registration.api_enabled' => true, 'registration.password_api_enabled' => false]);

        User::factory()->create(['email' => 'socialold@example.com', 'oauth_provider' => 'google', 'oauth_id' => 'g-old']);
        pbgFakeSocialUser('socialold@example.com', 'g-old');

        $this->postJson('/api/auth/social/google/callback', ['code' => 'abc'])->assertOk();
    });
});
