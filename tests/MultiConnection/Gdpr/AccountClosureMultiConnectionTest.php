<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Gdpr;

use App\Domain\Account\Models\Account;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Models\KycDocument;
use App\Domain\Compliance\Services\AccountClosureService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Mockery;

// A user deletes their own account, so the request is authenticated as that
// user. Audit rows then carry the user's uuid, and audit_logs.user_uuid has a
// foreign key to users.uuid. AuditLog, KycDocument, Account and Cardholder
// live on the TENANT connection while GdprService anonymises the users row
// inside a DEFAULT-connection transaction. On real MySQL a tenant-side write
// that must check or lock that users row inside the transaction would wait for
// it and self-deadlock until innodb_lock_wait_timeout. SQLite would not notice.
// The service is called directly (not through the HTTP kernel), like the other
// multi-connection tests, so a deadlock or error surfaces instead of a 500.
it('closes an account for the signed-in user without deadlock under real multi-session topology', function () {
    Http::fake();
    $privy = Mockery::mock(PrivyEmailOtpClient::class);
    $privy->shouldReceive('deleteUser')->once();
    app()->instance(PrivyEmailOtpClient::class, $privy);

    $user = User::factory()->create(['privy_user_id' => 'did:privy:mc-closure', 'privy_linked_at' => now()]);
    $user->createToken('mobile', ['read', 'write', 'delete']);
    Account::factory()->forUser($user)->create(['balance' => 0]);
    KycDocument::factory()->create(['user_uuid' => $user->uuid, 'file_path' => null]);

    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email'   => 'ada@example.com', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345',
    ]);
    $cardholder->save();

    Auth::setUser($user);

    $started = microtime(true);
    app(AccountClosureService::class)->close($user, AccountClosureService::SOURCE_APP);
    $seconds = microtime(true) - $started;

    $erased = User::query()->find($user->id);
    expect($seconds)->toBeLessThan(20.0)
        ->and($erased?->name)->toStartWith('ANONYMIZED_')
        ->and($erased?->privy_user_id)->toBeNull()
        ->and($user->tokens()->count())->toBe(0)
        ->and(KycDocument::query()->where('user_uuid', $user->uuid)->count())->toBe(0)
        ->and(Cardholder::query()->whereKey($cardholder->id)->first()?->first_name)->toBe('Erased')
        ->and(AuditLog::query()->where('action', 'gdpr.deletion_completed')->where('user_uuid', $user->uuid)->exists())->toBeTrue();
});
