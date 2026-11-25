<?php

declare(strict_types=1);

use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\User\Values\UserRoles;
use App\Filament\Auth\AdminLogin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

/*
| With password sign-in off, the admin panel's own password form is gone.
| An admin signs in with the email code on /login, and the web session it
| opens is the one /admin uses, so the admin is never locked out.
*/

uses(RefreshDatabase::class);

/**
 * An admin whose role is stored, so it survives a fresh load from the
 * database (the factory's withAdminRole() only sets the relation in memory).
 *
 * @param  array<string, mixed>  $attributes
 */
function aswpAdmin(array $attributes): User
{
    Role::firstOrCreate(['name' => UserRoles::ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create($attributes);
    $user->assignRole(UserRoles::ADMIN->value);

    return $user;
}

beforeEach(function (): void {
    config([
        'cache.default'           => 'array',
        'privy.app_id'            => 'test-app-id',
        'privy.app_secret'        => 'test-app-secret',
        'privy.web_login_enabled' => true,
    ]);
    Cache::store('array')->flush();
});

describe('password sign-in off', function (): void {
    beforeEach(function (): void {
        config(['auth.password_login_enabled' => false]);
    });

    it('opens /admin for an admin signed in through the web session', function (): void {
        $this->actingAs(aswpAdmin([]));

        $this->get('/admin')->assertOk();
    });

    it('sends a guest at /admin to the email-code login and remembers /admin', function (): void {
        $this->get('/admin')->assertRedirect(route('login'));

        expect(session('url.intended'))->toBe(url('/admin'));
    });

    it('sends a guest at /admin/login to the email-code login', function (): void {
        $this->get('/admin/login')->assertRedirect(route('login'));

        expect(session('url.intended'))->toBe(Filament::getPanel('admin')->getUrl());
    });

    it('still redirects a non-admin from /admin to the dashboard', function (): void {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertRedirect(route('dashboard'));
    });

    it('refuses the panel password form even when it is posted directly', function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = aswpAdmin(['password' => Hash::make('Adm1n-Passw0rd!asp')]);

        // The form was loaded while password sign-in was still on (or the
        // Livewire snapshot is replayed), so mount() did not redirect.
        config(['auth.password_login_enabled' => true]);
        $component = Livewire::test(AdminLogin::class)
            ->set('data.email', $admin->email)
            ->set('data.password', 'Adm1n-Passw0rd!asp');
        config(['auth.password_login_enabled' => false]);

        $component->call('authenticate')->assertHasErrors(['data.email']);

        $this->assertGuest();
    });

    it('lands an admin on /admin after the email code', function (): void {
        $admin = aswpAdmin(['email' => 'ops-admin@example.com']);

        /** @var PrivyEmailOtpClient&MockInterface $privy */
        $privy = Mockery::mock(PrivyEmailOtpClient::class);
        $privy->shouldReceive('loginWithCode')
            ->once()
            ->with('ops-admin@example.com', '123456')
            ->andReturn(['id' => 'did:privy:opsadmin', 'email' => 'ops-admin@example.com']);
        app()->instance(PrivyEmailOtpClient::class, $privy);

        $this->get('/admin')->assertRedirect(route('login'));

        $this->post(route('login.privy.verify'), ['email' => 'ops-admin@example.com', 'code' => '123456'])
            ->assertRedirect(url('/admin'));

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    });
});

describe('password sign-in on', function (): void {
    beforeEach(function (): void {
        config(['auth.password_login_enabled' => true]);
    });

    it('keeps the panel login page', function (): void {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    });

    it('signs an admin in with the panel password form', function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = aswpAdmin(['password' => Hash::make('Adm1n-Passw0rd!asp')]);

        Livewire::test(AdminLogin::class)
            ->set('data.email', $admin->email)
            ->set('data.password', 'Adm1n-Passw0rd!asp')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($admin);
    });
});
