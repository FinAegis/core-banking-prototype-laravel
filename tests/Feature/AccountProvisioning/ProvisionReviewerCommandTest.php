<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate([
        'name'       => UserRoles::ADMIN->value,
        'guard_name' => 'web',
    ]);
    $this->operator = User::factory()->create(['email' => 'op@finaegis.com']);
    $this->operator->assignRole(UserRoles::ADMIN->value);
});

it('creates a reviewer user with flags and sub-seeded content (happy path)', function () {
    $exit = $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
        '--note'           => 'App Store review 2026-Q2',
    ])->run();

    expect($exit)->toBe(0);

    $user = User::where('email', 'appreview@finaegis.com')->firstOrFail();

    $flag = AccountFlag::where('user_id', $user->id)->first();
    expect($flag)->not->toBeNull();
    expect($flag->is_review_account)->toBeTrue();
    expect($flag->bypass_device_attestation)->toBeTrue();
    expect($flag->note)->toBe('App Store review 2026-Q2');
    expect($flag->created_by)->toBe((int) $this->operator->id);

    // Sub-seeders ran (wallets create BlockchainAddress rows).
    expect(BlockchainAddress::where('user_uuid', $user->uuid)->count())->toBe(2);
});

it('is idempotent across repeated invocations', function () {
    $args = [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ];

    $this->artisan('account:provision-reviewer', $args)->assertExitCode(0);
    $this->artisan('account:provision-reviewer', $args)->assertExitCode(0);

    $user = User::where('email', 'appreview@finaegis.com')->firstOrFail();
    expect(AccountFlag::where('user_id', $user->id)->count())->toBe(1);
    expect(BlockchainAddress::where('user_uuid', $user->uuid)->count())->toBe(2);
});

it('exits 1 when operator is not an admin', function () {
    $nonAdmin = User::factory()->create(['email' => 'plain@finaegis.com']);

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => $nonAdmin->email,
    ])->assertExitCode(1);
});

it('exits 1 in production without --allow-production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(1);
});

it('exits 1 on email collision with non-review user', function () {
    User::factory()->create(['email' => 'existing@finaegis.com']);

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'existing@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(1);
});

it('exits 1 when --force-convert is passed in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('account:provision-reviewer', [
        '--email'            => 'appreview@finaegis.com',
        '--operator-email'   => 'op@finaegis.com',
        '--allow-production' => true,
        '--force-convert'    => true,
    ])->assertExitCode(1);
});

it('exits 1 when --expires-days exceeds the 90-day hard cap', function () {
    $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
        '--expires-days'   => 365,
    ])->assertExitCode(1);
});

it('issues a mobile token when --issue-mobile-token is set', function () {
    $exit = Artisan::call('account:provision-reviewer', [
        '--email'              => 'appreview@finaegis.com',
        '--operator-email'     => 'op@finaegis.com',
        '--expires-days'       => 30,
        '--issue-mobile-token' => true,
    ]);

    expect($exit)->toBe(0);

    $user = User::where('email', 'appreview@finaegis.com')->firstOrFail();

    // Single token was written, named `reviewer-mobile`, with read/write/delete.
    $tokens = DB::table('personal_access_tokens')
        ->where('tokenable_id', $user->id)
        ->where('name', 'reviewer-mobile')
        ->get();
    expect($tokens)->toHaveCount(1);

    $token = $tokens->first();
    expect($token)->not->toBeNull();
    if ($token === null) {
        return;
    }
    expect($token->expires_at)->not->toBeNull();
    /** @var array<int, string> $abilities */
    $abilities = (array) json_decode((string) $token->abilities, true);
    expect($abilities)->toBe(['read', 'write', 'delete']);

    // Token expires near the reviewer flag expiry (within 1s).
    $flag = AccountFlag::where('user_id', $user->id)->firstOrFail();
    expect($flag->expires_at)->not->toBeNull();
    if ($flag->expires_at !== null) {
        $tokenExpiresAt = \Carbon\CarbonImmutable::parse((string) $token->expires_at);
        expect(abs($tokenExpiresAt->diffInSeconds($flag->expires_at)))->toBeLessThan(2);
    }

    // Output JSON includes the plain-text token under `mobile_token`.
    $output = Artisan::output();
    $jsonStart = (int) strpos($output, '{');
    $payload = json_decode(substr($output, $jsonStart), true);
    expect($payload)->toBeArray();
    expect($payload)->toHaveKey('mobile_token');
    expect($payload['mobile_token'])->toBeString();
    expect($payload['mobile_token'])->not->toBe('(would-be-issued)');
    expect(strlen((string) $payload['mobile_token']))->toBeGreaterThan(20);
});

it('does NOT issue a token by default (no --issue-mobile-token)', function () {
    $exit = Artisan::call('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ]);

    expect($exit)->toBe(0);
    expect(DB::table('personal_access_tokens')->count())->toBe(0);

    $output = Artisan::output();
    $jsonStart = (int) strpos($output, '{');
    $payload = json_decode(substr($output, $jsonStart), true);
    expect($payload)->toBeArray();
    expect($payload)->not->toHaveKey('mobile_token');
});

it('does NOT actually issue a token in --dry-run, but previews it in the output', function () {
    $exit = Artisan::call('account:provision-reviewer', [
        '--email'              => 'appreview@finaegis.com',
        '--operator-email'     => 'op@finaegis.com',
        '--issue-mobile-token' => true,
        '--dry-run'            => true,
    ]);

    expect($exit)->toBe(0);
    expect(DB::table('personal_access_tokens')->count())->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('"mobile_token": "(would-be-issued)"');
});

it('issues a 90-day token when --expires-days=0 and --issue-mobile-token is set', function () {
    $exit = Artisan::call('account:provision-reviewer', [
        '--email'              => 'appreview@finaegis.com',
        '--operator-email'     => 'op@finaegis.com',
        '--expires-days'       => 0,
        '--issue-mobile-token' => true,
    ]);

    expect($exit)->toBe(0);

    $user = User::where('email', 'appreview@finaegis.com')->firstOrFail();
    $token = DB::table('personal_access_tokens')
        ->where('tokenable_id', $user->id)
        ->where('name', 'reviewer-mobile')
        ->first();
    expect($token)->not->toBeNull();
    if ($token === null) {
        return;
    }
    expect($token->expires_at)->not->toBeNull();

    $tokenExpiresAt = \Carbon\CarbonImmutable::parse((string) $token->expires_at);
    $expected = \Carbon\CarbonImmutable::now()->addDays(90);
    // 5-second tolerance covers test runtime; 90d is the hard cap.
    expect(abs($tokenExpiresAt->diffInSeconds($expected)))->toBeLessThan(5);
});
