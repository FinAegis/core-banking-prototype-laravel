<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Auth\Services\ErasedPrivyIdentities;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\Commerce\Models\Merchant;
use App\Domain\Compliance\Models\KycDocument;
use App\Domain\Compliance\Services\GdprService;
use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

/*
 * The app deletes an account with POST /api/auth/delete-account. The
 * endpoint erases the account through GdprService, like the GDPR deletion
 * endpoint: personal data is erased, money records stay, and the user row
 * stays as an anonymous entry. Sign-ins end only after the erasure has
 * succeeded, so a failed erasure never leaves the user signed out with half
 * a deletion. The request and the success response keep the shape the app
 * depends on.
 */

function deletionEndpointUser(): User
{
    $user = User::factory()->create([
        'name'            => 'Ada Lovelace',
        'email'           => 'ada@example.com',
        'privy_user_id'   => 'did:privy:deletion-endpoint',
        'privy_linked_at' => now(),
    ]);

    return $user;
}

function deletionEndpointToken(User $user): string
{
    return $user->createToken('mobile', ['read', 'write', 'delete'])->plainTextToken;
}

beforeEach(function (): void {
    Storage::fake('private');
    Http::fake();

    /** @var PrivyEmailOtpClient&MockInterface $privy */
    $privy = Mockery::mock(PrivyEmailOtpClient::class);
    $privy->shouldReceive('deleteUser')->byDefault();
    app()->instance(PrivyEmailOtpClient::class, $privy);
});

it('erases the account, keeps the money records and ends the sign-ins', function (): void {
    $user = deletionEndpointUser();
    $token = deletionEndpointToken($user);

    $account = Account::factory()->forUser($user)->create(['balance' => 0]);
    $send = WalletSendRecord::create([
        'public_id'         => 'send_deletion_1',
        'user_id'           => $user->id,
        'network'           => 'SOLANA',
        'asset'             => 'USDC',
        'amount'            => '1.5000',
        'sender_address'    => 'So1anaSender',
        'recipient_address' => 'So1anaRecipient',
        'status'            => 'confirmed',
        'tx_hash'           => 'sig-deletion-1',
        'metadata'          => ['device_id' => 'dev-1'],
        'error_message'     => 'transient failure detail',
    ]);
    $merchant = Merchant::create([
        'public_id'         => 'merch_deletion_1',
        'display_name'      => 'Coffee Corner',
        'accepted_assets'   => ['USDC'],
        'accepted_networks' => ['SOLANA'],
    ]);
    $intent = PaymentIntent::create([
        'public_id'   => 'pi_deletion_1',
        'user_id'     => $user->id,
        'merchant_id' => $merchant->id,
        'asset'       => 'USDC',
        'network'     => 'SOLANA',
        'amount'      => '2.5000',
        'status'      => 'confirmed',
        'metadata'    => ['table' => '12'],
        'expires_at'  => now()->addMinutes(15),
    ]);
    $document = KycDocument::factory()->create([
        'user_uuid' => $user->uuid,
        'file_path' => 'kyc/' . $user->uuid . '/passport.pdf',
    ]);
    Storage::disk('private')->put((string) $document->file_path, 'scan');

    $response = $this->withToken($token)->postJson('/api/auth/delete-account', ['confirmation' => 'DELETE']);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'data' => ['message', 'deleted_at']]);

    // The user row stays as an anonymous entry.
    $erased = User::query()->find($user->id);
    expect($erased)->not->toBeNull()
        ->and($erased?->name)->toStartWith('ANONYMIZED_')
        ->and($erased?->email)->toBe('deleted-' . $user->uuid . '@anonymized.local')
        ->and($erased?->privy_user_id)->toBeNull();

    // The sign-in identity is remembered only as a keyed hash, so an old
    // session on a phone cannot open a new account with it.
    $tombstones = DB::table(ErasedPrivyIdentities::TABLE)->get();
    expect($tombstones)->toHaveCount(1)
        ->and((string) $tombstones->first()?->identity_hash)->not->toContain('did:privy')
        ->and(ErasedPrivyIdentities::erasedSince('did:privy:deletion-endpoint', now()->subMinute()))->toBeTrue()
        ->and(ErasedPrivyIdentities::erasedSince('did:privy:someone-else', now()->subMinute()))->toBeFalse();

    // Money records stay; their personal fields are erased.
    expect(Account::query()->where('uuid', $account->uuid)->exists())->toBeTrue();
    $keptSend = WalletSendRecord::query()->find($send->id);
    $keptAmount = (string) $keptSend?->amount;
    expect($keptSend)->not->toBeNull()
        ->and(is_numeric($keptAmount) && bccomp($keptAmount, '1.5', 8) === 0)->toBeTrue()
        ->and($keptSend?->tx_hash)->toBe('sig-deletion-1')
        ->and($keptSend?->metadata)->toBeNull()
        ->and($keptSend?->error_message)->toBeNull();
    $keptIntent = PaymentIntent::query()->find($intent->id);
    expect($keptIntent)->not->toBeNull()
        ->and($keptIntent?->metadata)->toBeNull();

    // Identity documents are deleted with their files.
    expect(KycDocument::query()->where('user_uuid', $user->uuid)->count())->toBe(0);
    Storage::disk('private')->assertMissing((string) $document->file_path);

    // Every token is revoked, so the app's token no longer works.
    expect($user->tokens()->count())->toBe(0);
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('keeps the tokens and answers an error when the erasure fails', function (): void {
    $user = deletionEndpointUser();
    $token = deletionEndpointToken($user);

    /** @var GdprService&MockInterface $gdpr */
    $gdpr = Mockery::mock(GdprService::class);
    $gdpr->shouldReceive('canDeleteUserData')->andReturn(['can_delete' => true, 'reasons' => [], 'reason_codes' => []]);
    $gdpr->shouldReceive('deleteUserData')->once()->andThrow(new RuntimeException('database went away'));
    app()->instance(GdprService::class, $gdpr);

    $this->withToken($token)
        ->postJson('/api/auth/delete-account', ['confirmation' => 'DELETE'])
        ->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'ACCOUNT_DELETION_FAILED');

    expect($user->tokens()->count())->toBe(1)
        ->and($user->fresh()?->name)->toBe('Ada Lovelace');
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertOk();
});

it('keeps the account and the tokens when a rule blocks the deletion', function (): void {
    $user = deletionEndpointUser();
    $token = deletionEndpointToken($user);
    Account::factory()->forUser($user)->create(['balance' => 5000]);

    $this->withToken($token)
        ->postJson('/api/auth/delete-account', ['confirmation' => 'DELETE'])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'ACCOUNT_DELETION_BLOCKED')
        ->assertJsonPath('error.reason_codes', ['positive_balance'])
        ->assertJsonPath('error.reasons', ['User has active accounts with positive balance']);

    expect($user->tokens()->count())->toBe(1)
        ->and($user->fresh()?->name)->toBe('Ada Lovelace');
});

it('still requires the DELETE confirmation', function (): void {
    $user = deletionEndpointUser();
    $token = deletionEndpointToken($user);

    $this->withToken($token)
        ->postJson('/api/auth/delete-account', ['confirmation' => 'delete'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['confirmation']);

    expect($user->tokens()->count())->toBe(1)
        ->and($user->fresh()?->name)->toBe('Ada Lovelace');
});
