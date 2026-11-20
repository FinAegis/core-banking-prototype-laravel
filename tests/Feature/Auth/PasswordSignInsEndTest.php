<?php

declare(strict_types=1);

use App\Domain\Mobile\Models\MobileDevice;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/*
| A password reset ends the mobile device credentials (passkey and biometric
| keys, device sessions) and the API keys, like every other sign-in. A
| password change ends the device credentials but keeps the API keys and
| the browser session that made the change.
*/

const PSE_NEW_PASSWORD = 'N3w-Strong-Passw0rd!pse';

beforeEach(function (): void {
    config(['session.driver' => 'database']);
    // Password::uncompromised() asks the Pwned Passwords range API.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

/**
 * A device with passkey and biometric keys and a live device session, and
 * an active API key.
 *
 * @return array{device_id: string, device_session_id: string, api_key_id: int|string}
 */
function pseDeviceAccess(User $user): array
{
    $device = MobileDevice::factory()->create([
        'user_id'               => $user->id,
        'biometric_enabled'     => true,
        'biometric_public_key'  => 'pse-biometric-key',
        'biometric_key_id'      => 'pse-biometric-id-' . $user->id,
        'passkey_enabled'       => true,
        'passkey_credential_id' => 'pse-credential-' . $user->id,
        'passkey_public_key'    => 'pse-passkey-key',
    ]);

    $sessionId = (string) Str::uuid();
    DB::table('mobile_device_sessions')->insert([
        'id'                   => $sessionId,
        'mobile_device_id'     => $device->getKey(),
        'user_id'              => $user->id,
        'session_token'        => Str::random(64),
        'last_activity_at'     => now(),
        'expires_at'           => now()->addDay(),
        'is_biometric_session' => true,
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);

    $apiKey = ApiKey::createForUser($user, ['name' => 'Integration key'])['api_key'];

    return [
        'device_id'         => (string) $device->getKey(),
        'device_session_id' => $sessionId,
        'api_key_id'        => $apiKey->getKey(),
    ];
}

/**
 * @param  array{device_id: string, device_session_id: string, api_key_id: int|string}  $access
 */
function pseDevicesEnded(array $access): bool
{
    return ! MobileDevice::query()->whereKey($access['device_id'])->exists()
        && ! DB::table('mobile_device_sessions')->where('id', $access['device_session_id'])->exists();
}

/**
 * @param  array{device_id: string, device_session_id: string, api_key_id: int|string}  $access
 */
function pseDevicesKept(array $access): bool
{
    $device = MobileDevice::query()->find($access['device_id']);

    return $device instanceof MobileDevice
        && $device->passkey_enabled
        && $device->biometric_enabled
        && DB::table('mobile_device_sessions')->where('id', $access['device_session_id'])->exists();
}

/**
 * @param  array{device_id: string, device_session_id: string, api_key_id: int|string}  $access
 */
function pseApiKeyActive(array $access): bool
{
    return (bool) ApiKey::query()->whereKey($access['api_key_id'])->value('is_active');
}

describe('password reset', function (): void {
    it('ends mobile device credentials and API keys on a web password reset', function (): void {
        $user = User::factory()->create();
        $access = pseDeviceAccess($user);

        $this->post('/reset-password', [
            'token'                 => Password::broker()->createToken($user),
            'email'                 => $user->email,
            'password'              => PSE_NEW_PASSWORD,
            'password_confirmation' => PSE_NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        expect(pseDevicesEnded($access))->toBeTrue()
            ->and(pseApiKeyActive($access))->toBeFalse()
            ->and(MobileDevice::query()->where('user_id', $user->id)->count())->toBe(0);
    });

    it('ends mobile device credentials and API keys on an API password reset', function (): void {
        $user = User::factory()->create();
        $access = pseDeviceAccess($user);

        $this->postJson('/api/auth/reset-password', [
            'token'                 => Password::broker()->createToken($user),
            'email'                 => $user->email,
            'password'              => PSE_NEW_PASSWORD,
            'password_confirmation' => PSE_NEW_PASSWORD,
        ])->assertOk();

        expect(pseDevicesEnded($access))->toBeTrue()
            ->and(pseApiKeyActive($access))->toBeFalse();
    });

    it('leaves other users\' devices and API keys alone on a password reset', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherAccess = pseDeviceAccess($other);

        $this->post('/reset-password', [
            'token'                 => Password::broker()->createToken($user),
            'email'                 => $user->email,
            'password'              => PSE_NEW_PASSWORD,
            'password_confirmation' => PSE_NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        expect(pseDevicesKept($otherAccess))->toBeTrue()
            ->and(pseApiKeyActive($otherAccess))->toBeTrue();
    });

    it('changes nothing when the reset token is wrong', function (): void {
        $user = User::factory()->create();
        $access = pseDeviceAccess($user);

        $this->postJson('/api/auth/reset-password', [
            'token'                 => 'not-a-reset-token',
            'email'                 => $user->email,
            'password'              => PSE_NEW_PASSWORD,
            'password_confirmation' => PSE_NEW_PASSWORD,
        ])->assertStatus(422);

        expect(pseDevicesKept($access))->toBeTrue()
            ->and(pseApiKeyActive($access))->toBeTrue();
    });
});

describe('password change', function (): void {
    it('ends mobile device credentials but keeps API keys and the current browser session on a profile password change', function (): void {
        $user = User::factory()->create();
        $access = pseDeviceAccess($user);

        $this->actingAs($user)
            ->put('/user/password', [
                'current_password'      => 'password',
                'password'              => PSE_NEW_PASSWORD,
                'password_confirmation' => PSE_NEW_PASSWORD,
            ])->assertSessionHasNoErrors();

        expect(pseDevicesEnded($access))->toBeTrue()
            ->and(pseApiKeyActive($access))->toBeTrue();

        // The browser that changed the password stays signed in.
        $this->get('/.well-known/assetlinks.json')->assertOk();
        $this->assertAuthenticatedAs($user);
    });

    it('ends mobile device credentials but keeps API keys on an API password change, and the caller gets a working token', function (): void {
        $user = User::factory()->create();
        $access = pseDeviceAccess($user);
        $oldToken = $user->createToken('mobile', ['read', 'write', 'delete'])->plainTextToken;

        $response = $this->withToken($oldToken)
            ->postJson('/api/v2/auth/change-password', [
                'current_password'          => 'password',
                'new_password'              => PSE_NEW_PASSWORD,
                'new_password_confirmation' => PSE_NEW_PASSWORD,
            ])->assertOk();

        expect(pseDevicesEnded($access))->toBeTrue()
            ->and(pseApiKeyActive($access))->toBeTrue();

        $newToken = $response->json('new_token');
        expect($newToken)->toBeString();

        app('auth')->forgetGuards();
        $this->withToken((string) $newToken)->getJson('/api/auth/user')->assertOk();
    });

    it('changes nothing when the current password is wrong', function (): void {
        $user = User::factory()->create();
        $access = pseDeviceAccess($user);
        $token = $user->createToken('mobile', ['read', 'write', 'delete'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v2/auth/change-password', [
                'current_password'          => 'not-the-password',
                'new_password'              => PSE_NEW_PASSWORD,
                'new_password_confirmation' => PSE_NEW_PASSWORD,
            ])->assertStatus(422);

        expect(pseDevicesKept($access))->toBeTrue()
            ->and(pseApiKeyActive($access))->toBeTrue()
            ->and($user->tokens()->count())->toBe(1);
    });
});
