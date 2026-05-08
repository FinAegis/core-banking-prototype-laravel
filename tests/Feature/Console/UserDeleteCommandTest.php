<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\User\Values\UserRoles;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Build a test admin operator. The default factory state assigns the
 * `private` role only via afterMaking; we need to actually persist the
 * admin role via Spatie so `hasRole(UserRoles::ADMIN->value)` returns true.
 */
function makeAdminOperator(string $email = 'admin-operator@example.test'): User
{
    /** @var User $admin */
    $admin = User::factory()->create(['email' => $email]);
    $admin->assignRole(UserRoles::ADMIN->value);

    return $admin;
}

function makeRegularUser(string $email): User
{
    /** @var User $user */
    $user = User::factory()->create([
        'email'      => $email,
        'kyc_status' => 'not_started',
    ]);

    return $user;
}

it('requires --confirm', function (): void {
    $admin = makeAdminOperator();
    $target = makeRegularUser('drop-me-1@example.test');

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->not->toBe(0);
    expect(User::where('email', $target->email)->exists())->toBeTrue();
});

it('refuses to delete in production without --allow-production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $admin = makeAdminOperator();
    $target = makeRegularUser('drop-me-prod@example.test');

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->not->toBe(0);
    expect(Artisan::output())->toContain('Production guard');
    expect(User::where('email', $target->email)->exists())->toBeTrue();
});

it('refuses without an admin operator', function (): void {
    /** @var User $nonAdmin */
    $nonAdmin = User::factory()->create(['email' => 'not-an-admin@example.test']);
    // Default factory assigns the `private` role on make only; no DB role is
    // attached here, so hasRole(ADMIN) is false.

    $target = makeRegularUser('drop-me-2@example.test');

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--confirm'        => true,
        '--operator-email' => $nonAdmin->email,
    ]);

    expect($exitCode)->not->toBe(0);
    expect(Artisan::output())->toContain('not found or not an admin');
    expect(User::where('email', $target->email)->exists())->toBeTrue();
});

it('refuses to delete a missing user', function (): void {
    $admin = makeAdminOperator();

    $exitCode = Artisan::call('user:delete', [
        '--email'          => 'never-existed@example.test',
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->not->toBe(0);
    expect(Artisan::output())->toContain('User never-existed@example.test not found');
});

it('refuses to delete an admin user', function (): void {
    $admin = makeAdminOperator('admin-op-1@example.test');

    /** @var User $targetAdmin */
    $targetAdmin = User::factory()->create(['email' => 'other-admin@example.test']);
    $targetAdmin->assignRole(UserRoles::ADMIN->value);

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $targetAdmin->email,
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->not->toBe(0);
    expect(Artisan::output())->toContain('Refusing to delete admin user');
    expect(User::where('email', $targetAdmin->email)->exists())->toBeTrue();
});

it('refuses to delete a KYC-approved user', function (): void {
    $admin = makeAdminOperator();

    /** @var User $target */
    $target = User::factory()->create([
        'email'      => 'kyc-approved@example.test',
        'kyc_status' => 'approved',
    ]);

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->not->toBe(0);
    expect(Artisan::output())->toContain('KYC-approved');
    expect(User::where('email', $target->email)->exists())->toBeTrue();
});

it('refuses if the user has a non-zero account balance', function (): void {
    $admin = makeAdminOperator();
    $target = makeRegularUser('balance-holder@example.test');

    Account::create([
        'uuid'      => (string) Str::uuid(),
        'name'      => 'Primary',
        'user_uuid' => $target->uuid,
        'balance'   => 5000,
        'frozen'    => false,
    ]);

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->not->toBe(0);
    expect(Artisan::output())->toContain('non-zero account balance');
    expect(User::where('email', $target->email)->exists())->toBeTrue();
});

it('refuses if the user has wallet send history', function (): void {
    $admin = makeAdminOperator();
    $target = makeRegularUser('has-sends@example.test');

    WalletSendRecord::create([
        'public_id'         => 'pi_send_' . Str::random(16),
        'user_id'           => $target->id,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.0',
        'sender_address'    => 'SoMeBaSe58SeNdEr',
        'recipient_address' => 'SoMeBaSe58RcPnT',
        'status'            => 'confirmed',
    ]);

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->not->toBe(0);
    expect(Artisan::output())->toContain('wallet send history');
    expect(User::where('email', $target->email)->exists())->toBeTrue();
});

it('cleans up Sanctum tokens explicitly on delete', function (): void {
    $admin = makeAdminOperator();
    $target = makeRegularUser('token-holder@example.test');

    $target->createToken('mobile-app', ['read', 'write']);
    expect(DB::table('personal_access_tokens')
        ->where('tokenable_id', $target->id)
        ->where('tokenable_type', $target->getMorphClass())
        ->count())->toBe(1);

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->toBe(0);
    expect(DB::table('personal_access_tokens')
        ->where('tokenable_id', $target->id)
        ->where('tokenable_type', $target->getMorphClass())
        ->count())->toBe(0);
    expect(User::where('email', $target->email)->exists())->toBeFalse();
});

it('cascades to user-keyed tables', function (): void {
    $admin = makeAdminOperator();
    $target = makeRegularUser('cascade-target@example.test');

    $account = Account::create([
        'uuid'      => (string) Str::uuid(),
        'name'      => 'Primary',
        'user_uuid' => $target->uuid,
        'balance'   => 0,
        'frozen'    => false,
    ]);

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->toBe(0);
    expect(User::where('email', $target->email)->exists())->toBeFalse();
    expect(Account::where('uuid', $account->uuid)->exists())->toBeFalse();
});

it('succeeds with all guards passing on staging', function (): void {
    $admin = makeAdminOperator();
    $target = makeRegularUser('happy-path@example.test');
    $targetId = $target->id;

    $exitCode = Artisan::call('user:delete', [
        '--email'          => $target->email,
        '--confirm'        => true,
        '--operator-email' => $admin->email,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain("deleted: {$target->email} (user_id={$targetId})");
    expect(User::where('email', $target->email)->exists())->toBeFalse();
});
