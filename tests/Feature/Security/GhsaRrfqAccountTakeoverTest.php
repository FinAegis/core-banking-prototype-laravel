<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\WaterlineServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

/*
| GHSA-rrfq-93h6-29wj, account takeover hardening: a password reset or
| change ends the user's other sign-ins, social login links an existing
| account only through an email the provider verified, and the Horizon,
| Telescope and Waterline dashboards are for admins, not for an email list.
*/

const GAT_NEW_PASSWORD = 'N3w-Strong-Passw0rd!x';

beforeEach(function (): void {
    // Password::uncompromised() asks the Pwned Passwords range API.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

/**
 * @param  list<string>  $ids
 */
function gatStoredSessions(User $user, array $ids): void
{
    foreach ($ids as $id) {
        DB::table((string) config('session.table', 'sessions'))->insert([
            'id'            => $id,
            'user_id'       => $user->id,
            'ip_address'    => '203.0.113.9',
            'user_agent'    => 'Other browser',
            'payload'       => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }
}

function gatOauthGrant(User $user, string $id): void
{
    DB::table('oauth_access_tokens')->insert([
        'id'         => $id,
        'user_id'    => $user->id,
        'client_id'  => (string) Str::uuid(),
        'name'       => null,
        'scopes'     => '[]',
        'revoked'    => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addDay(),
    ]);
    DB::table('oauth_refresh_tokens')->insert([
        'id'              => $id . '-refresh',
        'access_token_id' => $id,
        'revoked'         => false,
        'expires_at'      => now()->addMonth(),
    ]);
}

function gatGrantRevoked(string $id): bool
{
    return (bool) DB::table('oauth_access_tokens')->where('id', $id)->value('revoked')
        && (bool) DB::table('oauth_refresh_tokens')->where('id', $id . '-refresh')->value('revoked');
}

function gatStoredSessionCount(User $user): int
{
    return DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->count();
}

/**
 * @param  array<string, mixed>  $raw
 */
function gatSocialUser(string $provider, string $id, ?string $email, array $raw = []): void
{
    $socialUser = (new SocialiteUser())
        ->setRaw(array_merge(['id' => $id, 'email' => $email], $raw))
        ->map(['id' => $id, 'name' => 'Social Person', 'email' => $email, 'avatar' => null]);

    $driver = Mockery::mock();
    /** @var Mockery\Expectation $stateless */
    $stateless = $driver->shouldReceive('stateless');
    $stateless->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}

function gatStaff(string $role): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

describe('password reset and change end the other sign-ins', function (): void {
    it('ends API tokens, OAuth grants, the remember token and stored sessions on a web password reset', function (): void {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $plainToken = $user->createToken('mobile', ['read', 'write', 'delete'])->plainTextToken;
        gatOauthGrant($user, 'grant-victim');
        gatStoredSessions($user, ['sess-attacker-1', 'sess-attacker-2']);

        $this->post('/reset-password', [
            'token'                 => Password::broker()->createToken($user),
            'email'                 => $user->email,
            'password'              => GAT_NEW_PASSWORD,
            'password_confirmation' => GAT_NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        expect(Hash::check(GAT_NEW_PASSWORD, $user->password))->toBeTrue()
            ->and($user->tokens()->count())->toBe(0)
            ->and(gatGrantRevoked('grant-victim'))->toBeTrue()
            ->and(gatStoredSessionCount($user))->toBe(0)
            ->and($user->remember_token)->not->toBe('old-remember-token');

        app('auth')->forgetGuards();
        $this->withToken($plainToken)->getJson('/api/auth/user')->assertUnauthorized();
    });

    it('leaves other users\' tokens, grants and sessions alone on a password reset', function (): void {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherToken = $other->createToken('mobile', ['read', 'write', 'delete'])->plainTextToken;
        gatOauthGrant($other, 'grant-other');
        gatStoredSessions($other, ['sess-other']);

        $this->post('/reset-password', [
            'token'                 => Password::broker()->createToken($user),
            'email'                 => $user->email,
            'password'              => GAT_NEW_PASSWORD,
            'password_confirmation' => GAT_NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        expect($other->tokens()->count())->toBe(1)
            ->and(gatGrantRevoked('grant-other'))->toBeFalse()
            ->and(gatStoredSessionCount($other))->toBe(1);

        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/auth/user')->assertOk();
    });

    it('keeps the current web session but ends the other sign-ins when the password is changed in the profile', function (): void {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $user->createToken('mobile', ['read', 'write', 'delete']);
        gatOauthGrant($user, 'grant-profile');
        gatStoredSessions($user, ['sess-elsewhere']);

        $this->actingAs($user)
            ->put('/user/password', [
                'current_password'      => 'password',
                'password'              => GAT_NEW_PASSWORD,
                'password_confirmation' => GAT_NEW_PASSWORD,
            ])->assertSessionHasNoErrors();

        $user->refresh();
        expect($user->tokens()->count())->toBe(0)
            ->and(gatGrantRevoked('grant-profile'))->toBeTrue()
            ->and(DB::table('sessions')->where('id', 'sess-elsewhere')->exists())->toBeFalse();

        // The browser that changed the password stays signed in.
        $this->get('/.well-known/assetlinks.json')->assertOk();
        $this->assertAuthenticatedAs($user);
    });

    it('signs out a session that still carries the old password on its next request', function (): void {
        $user = User::factory()->create();
        $oldHash = $user->getAuthPassword();
        $user->forceFill(['password' => Hash::make(GAT_NEW_PASSWORD)])->save();

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $oldHash])
            ->get('/.well-known/assetlinks.json')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });

    it('keeps a session whose password is current', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $user->getAuthPassword()])
            ->get('/.well-known/assetlinks.json')
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    });
});

describe('social login links an existing account only through a verified email', function (): void {
    it('refuses to sign in to an existing account by an email the provider did not mark as verified', function (string $provider, array $raw): void {
        $victim = User::factory()->create(['email' => 'victim@example.com']);
        gatSocialUser($provider, 'attacker-1', 'victim@example.com', $raw);

        $this->postJson("/api/auth/social/{$provider}/callback", ['code' => 'abc'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'EMAIL_ALREADY_EXISTS')
            ->assertJsonMissingPath('token');

        $victim->refresh();
        expect($victim->tokens()->count())->toBe(0)
            ->and($victim->oauth_provider)->toBeNull()
            ->and($victim->oauth_id)->toBeNull();
    })->with([
        'github, no flag'      => ['github', []],
        'facebook, no flag'    => ['facebook', []],
        'google, flag false'   => ['google', ['email_verified' => false]],
        'google, flag missing' => ['google', []],
    ]);

    it('refuses to sign in to an account linked to another provider by email alone', function (): void {
        $victim = User::factory()->create(['email' => 'linked@example.com', 'oauth_provider' => 'google', 'oauth_id' => 'g-1']);
        gatSocialUser('github', 'gh-attacker', 'linked@example.com');

        $this->postJson('/api/auth/social/github/callback', ['code' => 'abc'])->assertStatus(409);

        expect($victim->fresh()?->tokens()->count())->toBe(0)
            ->and($victim->fresh()?->oauth_provider)->toBe('google');
    });

    it('does not attach a social identity to a Privy account without a verified email', function (): void {
        $privyUser = User::factory()->create(['email' => 'privy@example.com', 'privy_user_id' => 'did:privy:abc']);
        gatSocialUser('github', 'gh-x', 'privy@example.com');

        $this->postJson('/api/auth/social/github/callback', ['code' => 'abc'])->assertStatus(409);

        $privyUser->refresh();
        expect($privyUser->privy_user_id)->toBe('did:privy:abc')
            ->and($privyUser->oauth_provider)->toBeNull()
            ->and($privyUser->tokens()->count())->toBe(0);
    });

    it('links and signs in an existing account when the provider verified the email', function (): void {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        gatSocialUser('google', 'g-owner', 'owner@example.com', ['email_verified' => true]);

        $this->postJson('/api/auth/social/google/callback', ['code' => 'abc'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        expect($user->fresh()?->oauth_provider)->toBe('google')
            ->and($user->fresh()?->oauth_id)->toBe('g-owner')
            ->and($user->fresh()?->tokens()->count())->toBe(1);
    });

    it('signs in an already linked identity without a verified flag', function (): void {
        $user = User::factory()->create(['email' => 'gh@example.com', 'oauth_provider' => 'github', 'oauth_id' => 'gh-1']);
        gatSocialUser('github', 'gh-1', 'gh@example.com');

        $this->postJson('/api/auth/social/github/callback', ['code' => 'abc'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    });

    it('still creates a new account when registration is open, verified only when the provider says so', function (): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false]);

        gatSocialUser('github', 'gh-new', 'new-gh@example.com');
        $this->postJson('/api/auth/social/github/callback', ['code' => 'abc'])->assertOk();

        $created = User::query()->where('email', 'new-gh@example.com')->first();
        expect($created)->not->toBeNull()
            ->and($created?->oauth_provider)->toBe('github')
            ->and($created?->email_verified_at)->toBeNull();
    });
});

describe('Horizon, Telescope and Waterline gates', function (): void {
    beforeEach(function (): void {
        foreach ([HorizonServiceProvider::class, TelescopeServiceProvider::class, WaterlineServiceProvider::class] as $provider) {
            $instance = new $provider(app());
            $instance->register();
            $instance->boot();
        }
    });

    it('lets platform admins open the dashboards', function (string $role): void {
        $admin = gatStaff($role);

        foreach (['viewHorizon', 'viewTelescope', 'viewWaterline'] as $gate) {
            expect(Gate::forUser($admin)->allows($gate))->toBeTrue("{$role} should pass {$gate}");
        }
    })->with(['admin', 'super_admin']);

    it('keeps everyone else out, whatever their email address', function (string $role): void {
        $user = $role === 'customer' ? User::factory()->create(['email' => 'ops@example.com']) : gatStaff($role);

        foreach (['viewHorizon', 'viewTelescope', 'viewWaterline'] as $gate) {
            expect(Gate::forUser($user)->allows($gate))->toBeFalse("{$role} should fail {$gate}");
        }
    })->with(['customer', 'bank_admin', 'compliance_officer']);
});
