<?php

declare(strict_types=1);

use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\Compliance\Services\GdprService;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

/*
 * POST /api/compliance/gdpr/delete erases the account straight away, so the
 * reply says so, and the user's tokens are revoked once the erasure has
 * succeeded. A failed erasure keeps the tokens.
 */

beforeEach(function (): void {
    Http::fake();

    /** @var PrivyEmailOtpClient&MockInterface $privy */
    $privy = Mockery::mock(PrivyEmailOtpClient::class);
    $privy->shouldReceive('deleteUser')->byDefault();
    app()->instance(PrivyEmailOtpClient::class, $privy);
});

it('erases the account now, says so and revokes the tokens', function (): void {
    $user = User::factory()->create(['name' => 'Grace Hopper']);
    $token = $user->createToken('client', ['read', 'write', 'delete'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/compliance/gdpr/delete', ['confirm' => true]);

    $response->assertOk()
        ->assertJsonPath('message', 'Your account is closed and your personal data has been erased. We keep some records where the law requires it.');
    expect((string) $response->getContent())->not->toContain('30 days');

    expect($user->fresh()?->name)->toStartWith('ANONYMIZED_')
        ->and($user->tokens()->count())->toBe(0);
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('keeps the tokens when the erasure fails', function (): void {
    $user = User::factory()->create(['name' => 'Grace Hopper']);
    $token = $user->createToken('client', ['read', 'write', 'delete'])->plainTextToken;

    /** @var GdprService&MockInterface $gdpr */
    $gdpr = Mockery::mock(GdprService::class);
    $gdpr->shouldReceive('canDeleteUserData')->andReturn(['can_delete' => true, 'reasons' => [], 'reason_codes' => []]);
    $gdpr->shouldReceive('deleteUserData')->once()->andThrow(new RuntimeException('database went away'));
    app()->instance(GdprService::class, $gdpr);

    $this->withToken($token)
        ->postJson('/api/compliance/gdpr/delete', ['confirm' => true])
        ->assertStatus(500);

    expect($user->tokens()->count())->toBe(1)
        ->and($user->fresh()?->name)->toBe('Grace Hopper');
});
