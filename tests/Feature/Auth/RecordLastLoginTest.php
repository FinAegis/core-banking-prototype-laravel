<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Login;

/*
 * users.last_login_at feeds the compliance access review (inactive accounts)
 * and the AI risk saga. Every way of signing in records it.
 */

it('records the time of a web sign-in without touching updated_at', function (): void {
    $user = User::factory()->create(['updated_at' => now()->subDay()]);
    $updatedAt = $user->fresh()?->updated_at?->toIso8601String();

    event(new Login('web', $user, false));

    $fresh = $user->fresh();
    expect($fresh?->last_login_at)->not->toBeNull()
        ->and($fresh?->last_login_at?->diffInSeconds(now()))->toBeLessThan(5)
        ->and($fresh?->updated_at?->toIso8601String())->toBe($updatedAt);
});

it('records the time of an API password sign-in', function (): void {
    $user = User::factory()->create(['password' => bcrypt('correct-horse-battery')]);

    $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'correct-horse-battery',
        'device_name' => 'test',
    ])->assertOk();

    expect($user->fresh()?->last_login_at)->not->toBeNull();
});

it('does not record a failed sign-in', function (): void {
    $user = User::factory()->create(['password' => bcrypt('correct-horse-battery')]);

    $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'wrong',
        'device_name' => 'test',
    ])->assertStatus(422);

    expect($user->fresh()?->last_login_at)->toBeNull();
});
