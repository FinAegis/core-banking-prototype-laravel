<?php

declare(strict_types=1);

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/*
| App-store reviewer and demo accounts use .invalid addresses that never
| receive the email code, so they keep their password sign-in while
| auth.password_login_enabled is off. Only an active reviewer flag counts:
| a regular account, a disabled flag and an expired flag are refused.
*/

uses(RefreshDatabase::class);

const PLB_PASSWORD = 'Rev1ewer-Passw0rd!plb';

beforeEach(function (): void {
    config(['cache.default' => 'array', 'auth.password_login_enabled' => false]);
    Cache::store('array')->flush();
});

/**
 * @param  array<string, mixed>|null  $flag  account_flags columns, or null for no row
 */
function plbAccount(?array $flag): User
{
    $user = User::factory()->create([
        'email'    => 'appreview-plb@example-reviewer.invalid',
        'password' => Hash::make(PLB_PASSWORD),
    ]);

    if ($flag !== null) {
        $row = new AccountFlag();
        $row->setAttribute('user_id', $user->id);
        foreach ($flag as $column => $value) {
            $row->setAttribute($column, $value);
        }
        $row->save();
    }

    return $user;
}

it('lets an active reviewer account sign in with its password through the API', function (): void {
    plbAccount(['is_review_account' => true, 'expires_at' => now()->addDays(30)]);
    $logSpy = Log::spy();

    $this->postJson('/api/auth/login', ['email' => 'appreview-plb@example-reviewer.invalid', 'password' => PLB_PASSWORD])
        ->assertOk()
        ->assertJsonPath('success', true);

    $logSpy->shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context): bool => $message === 'bypass.fired' && ($context['bypass'] ?? null) === 'password_login'
    )->atLeast()->once();
});

it('lets an active reviewer account sign in with its password on the web', function (): void {
    $user = plbAccount(['is_review_account' => true]);

    $this->post('/login', ['email' => 'APPREVIEW-plb@example-reviewer.invalid', 'password' => PLB_PASSWORD])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('refuses the same password sign-in without an active reviewer flag', function (?array $flag): void {
    plbAccount($flag);

    $this->postJson('/api/auth/login', ['email' => 'appreview-plb@example-reviewer.invalid', 'password' => PLB_PASSWORD])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'ERR_AUTH_008');

    $this->post('/login', ['email' => 'appreview-plb@example-reviewer.invalid', 'password' => PLB_PASSWORD])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([
    'no flag row'    => [fn (): ?array => null],
    'not a reviewer' => [fn (): array => ['is_review_account' => false, 'bypass_rate_limit' => true]],
    'disabled flag'  => [fn (): array => ['is_review_account' => true, 'disabled_at' => now()->subDay()]],
    'expired flag'   => [fn (): array => ['is_review_account' => true, 'expires_at' => now()->subDay()]],
]);

it('does not open password reset for a reviewer account', function (): void {
    plbAccount(['is_review_account' => true]);

    $this->postJson('/api/auth/forgot-password', ['email' => 'appreview-plb@example-reviewer.invalid'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'ERR_AUTH_008');
});
