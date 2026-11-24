<?php

declare(strict_types=1);

use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Models\User;
use App\Support\RecentSignIn;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;

/*
 * The profile page deletes an account through the same GdprService erasure
 * as the app. Users who sign in to the website with an email code have no
 * password they know, so they confirm by typing DELETE and must have signed
 * in within the last 15 minutes. Users with a password still confirm with
 * it.
 */

beforeEach(function (): void {
    Http::fake();

    /** @var PrivyEmailOtpClient&MockInterface $privy */
    $privy = Mockery::mock(PrivyEmailOtpClient::class);
    $privy->shouldReceive('deleteUser')->byDefault();
    app()->instance(PrivyEmailOtpClient::class, $privy);
});

function webDeletionEmailCodeUser(): User
{
    config(['privy.web_login_enabled' => true]);

    return User::factory()->create([
        'name'            => 'Katherine Johnson',
        'privy_user_id'   => 'did:privy:web-deletion',
        'privy_linked_at' => now(),
    ]);
}

describe('a user who signs in with a password', function (): void {
    it('erases the account after the right password', function (): void {
        $user = User::factory()->create(['name' => 'Dorothy Vaughan']);
        $this->actingAs($user);

        Livewire::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $erased = $user->fresh();
        expect($erased)->not->toBeNull()
            ->and($erased?->name)->toStartWith('ANONYMIZED_');
        $this->assertGuest();
    });

    it('keeps the account after a wrong password', function (): void {
        $user = User::factory()->create(['name' => 'Dorothy Vaughan']);
        $this->actingAs($user);

        Livewire::test('profile.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        expect($user->fresh()?->name)->toBe('Dorothy Vaughan');
    });
});

describe('a user who signs in with an email code', function (): void {
    it('erases the account after typing DELETE with a recent sign-in', function (): void {
        $user = webDeletionEmailCodeUser();
        $this->actingAs($user);
        session()->put(RecentSignIn::SESSION_KEY, now()->subMinutes(5)->getTimestamp());

        Livewire::test('profile.delete-user-form')
            ->assertSee('Type DELETE to confirm')
            ->set('confirmation', 'DELETE')
            ->call('deleteUser')
            ->assertHasNoErrors()
            ->assertRedirect();

        expect($user->fresh()?->name)->toStartWith('ANONYMIZED_')
            ->and($user->fresh()?->privy_user_id)->toBeNull();
        $this->assertGuest();
    });

    it('keeps the account when the typed word is wrong', function (): void {
        $user = webDeletionEmailCodeUser();
        $this->actingAs($user);
        session()->put(RecentSignIn::SESSION_KEY, now()->getTimestamp());

        Livewire::test('profile.delete-user-form')
            ->set('confirmation', 'delete me')
            ->call('deleteUser')
            ->assertHasErrors(['confirmation']);

        expect($user->fresh()?->name)->toBe('Katherine Johnson');
    });

    it('asks for a new sign-in when the last one is older than 15 minutes', function (?int $minutesAgo): void {
        $user = webDeletionEmailCodeUser();
        $this->actingAs($user);
        if ($minutesAgo !== null) {
            session()->put(RecentSignIn::SESSION_KEY, now()->subMinutes($minutesAgo)->getTimestamp());
        }

        Livewire::test('profile.delete-user-form')
            ->assertSee('Sign in again')
            ->set('confirmation', 'DELETE')
            ->call('deleteUser')
            ->assertHasErrors(['confirmation']);

        expect($user->fresh()?->name)->toBe('Katherine Johnson');
        $this->assertAuthenticatedAs($user);
    })->with(['20 minutes ago' => [20], 'never recorded' => [null]]);

    it('signs out and returns to the profile page after signing in again', function (): void {
        $user = webDeletionEmailCodeUser();
        $this->actingAs($user);

        Livewire::test('profile.delete-user-form')
            ->call('signInAgain')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        expect(session('url.intended'))->toBe(route('profile.show'));
    });

    it('records the sign-in time when the email code sign-in succeeds', function (): void {
        $user = webDeletionEmailCodeUser();
        config(['privy.web_verify_min_duration_ms' => 0, 'cache.default' => 'array']);

        /** @var PrivyEmailOtpClient&MockInterface $privy */
        $privy = Mockery::mock(PrivyEmailOtpClient::class);
        $privy->shouldReceive('loginWithCode')
            ->once()
            ->andReturn(['id' => 'did:privy:web-deletion', 'email' => $user->email]);
        app()->instance(PrivyEmailOtpClient::class, $privy);

        $this->post(route('login.privy.verify'), ['email' => $user->email, 'code' => '123456'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        expect(RecentSignIn::isRecent())->toBeTrue();
    });
});
