<?php

declare(strict_types=1);

use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobilePushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Response Shape Verification Tests
|--------------------------------------------------------------------------
|
| These tests verify that all mobile-facing API endpoints return
| correctly-shaped JSON responses. They check:
|
| 1. snake_case keys across all response payloads
| 2. Consistent pagination format (data + meta)
| 3. Standard error format (error.code + error.message)
| 4. Auth response shape (token, token_type, etc.)
|
*/

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

/**
 * Recursively assert that all keys in a JSON structure are snake_case.
 *
 * @param array<string|int, mixed> $data
 */
function assertKeysAreSnakeCase(array $data, string $path = ''): void
{
    foreach ($data as $key => $value) {
        // Skip numeric (array) keys
        if (is_int($key)) {
            if (is_array($value)) {
                assertKeysAreSnakeCase($value, "{$path}[{$key}]");
            }

            continue;
        }

        $currentPath = $path ? "{$path}.{$key}" : $key;

        // A snake_case key is lowercase and may contain underscores and digits;
        // it must NOT contain uppercase letters.
        expect($key)
            ->not->toMatch('/[A-Z]/', "Key '{$currentPath}' is not snake_case");

        if (is_array($value)) {
            assertKeysAreSnakeCase($value, $currentPath);
        }
    }
}

/**
 * Create an authenticated user and return [user, token].
 *
 * @return array{0: User, 1: string}
 */
function createShapeTestUser(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test-token', ['read', 'write'])->plainTextToken;

    return [$user, $token];
}

// --------------------------------------------------------------------------
// Setup
// --------------------------------------------------------------------------

beforeEach(function () {
    Cache::flush();
});

// ==========================================================================
//  1. AUTH ENDPOINTS
// ==========================================================================

describe('Auth endpoints - POST /api/login', function () {
    test('login returns correct auth response shape', function () {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();

        $json = $response->json();

        // Must have success flag
        expect($json)->toHaveKey('success', true);

        // Must have data envelope
        expect($json)->toHaveKey('data');

        $data = $json['data'];

        // Auth response must include token fields
        expect($data)->toHaveKey('access_token');
        expect($data)->toHaveKey('token_type', 'Bearer');
        expect($data)->toHaveKey('expires_in');
        expect($data)->toHaveKey('refresh_token');
        expect($data)->toHaveKey('refresh_expires_in');
        expect($data)->toHaveKey('user');

        // All keys should be snake_case
        assertKeysAreSnakeCase($json);
    });

    test('login error returns standard validation error shape', function () {
        $response = $this->postJson('/api/login', [
            'email'    => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable();

        $json = $response->json();

        // Laravel validation returns message + errors
        expect($json)->toHaveKey('message');
        expect($json)->toHaveKey('errors');

        assertKeysAreSnakeCase($json);
    });

    test('login with missing fields returns validation error', function () {
        $response = $this->postJson('/api/login', []);

        $response->assertUnprocessable();

        $json = $response->json();
        expect($json)->toHaveKey('message');
        expect($json)->toHaveKey('errors');
    });
});

describe('Auth endpoints - POST /api/register', function () {
    test('register returns correct auth response shape', function () {
        // Fake HTTP to bypass HaveIBeenPwned API check in password validation
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);

        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => 'newuser@example.com',
            'password'              => 'Xk9#mP2$vL7wQ!nR',
            'password_confirmation' => 'Xk9#mP2$vL7wQ!nR',
        ]);

        $response->assertCreated();

        $json = $response->json();

        expect($json)->toHaveKey('success', true);
        expect($json)->toHaveKey('data');

        $data = $json['data'];

        expect($data)->toHaveKey('access_token');
        expect($data)->toHaveKey('refresh_token');
        expect($data)->toHaveKey('token_type', 'Bearer');
        expect($data)->toHaveKey('expires_in');
        expect($data)->toHaveKey('refresh_expires_in');
        expect($data)->toHaveKey('user');

        assertKeysAreSnakeCase($json);
    });

    test('register with duplicate email returns validation error', function () {
        $existing = User::factory()->create();

        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => $existing->email,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable();

        $json = $response->json();
        expect($json)->toHaveKey('message');
        expect($json)->toHaveKey('errors');
        expect($json['errors'])->toHaveKey('email');
    });
});

// ==========================================================================
//  2. MOBILE DEVICE MANAGEMENT
// ==========================================================================

describe('Mobile config - GET /api/mobile/config', function () {
    test('config response has correct shape with snake_case keys', function () {
        $response = $this->getJson('/api/mobile/config');

        $response->assertOk();

        $json = $response->json();

        expect($json)->toHaveKey('data');

        $data = $json['data'];
        expect($data)->toHaveKey('min_app_version');
        expect($data)->toHaveKey('latest_app_version');
        expect($data)->toHaveKey('force_update');
        expect($data)->toHaveKey('maintenance_mode');
        expect($data)->toHaveKey('features');
        expect($data)->toHaveKey('websocket');

        // Verify features sub-keys
        expect($data['features'])->toHaveKey('biometric_auth');
        expect($data['features'])->toHaveKey('push_notifications');

        assertKeysAreSnakeCase($json);
    });
});

describe('Mobile app status - GET /api/v1/app/status', function () {
    test('app status response has correct shape', function () {
        $response = $this->getJson('/api/v1/app/status');

        $response->assertOk();

        $json = $response->json();

        expect($json)->toHaveKey('success', true);
        expect($json)->toHaveKey('data');

        $data = $json['data'];
        expect($data)->toHaveKey('min_version');
        expect($data)->toHaveKey('latest_version');
        expect($data)->toHaveKey('force_update');
        expect($data)->toHaveKey('maintenance');

        assertKeysAreSnakeCase($json);
    });
});

describe('Mobile device endpoints', function () {
    test('GET /api/mobile/devices returns data array envelope', function () {
        [$user, $token] = createShapeTestUser();

        MobileDevice::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->withToken($token)->getJson('/api/mobile/devices');

        $response->assertOk();

        $json = $response->json();

        // Must have data envelope as array
        expect($json)->toHaveKey('data');
        expect($json['data'])->toBeArray();
        expect($json['data'])->toHaveCount(2);

        // Each device must have snake_case keys
        $device = $json['data'][0];
        expect($device)->toHaveKey('device_id');
        expect($device)->toHaveKey('platform');
        expect($device)->toHaveKey('device_name');
        expect($device)->toHaveKey('biometric_enabled');
        expect($device)->toHaveKey('is_trusted');
        expect($device)->toHaveKey('is_blocked');
        expect($device)->toHaveKey('has_push_token');
        expect($device)->toHaveKey('is_current');
        expect($device)->toHaveKey('created_at');

        assertKeysAreSnakeCase($json);
    });

    test('POST /api/mobile/devices returns created device shape', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->postJson('/api/mobile/devices', [
            'device_id'    => 'test-shape-device-001',
            'platform'     => 'ios',
            'app_version'  => '1.0.0',
            'push_token'   => 'fcm_test_token_shape',
            'device_name'  => 'iPhone Test',
            'device_model' => 'iPhone15,3',
            'os_version'   => '17.0',
        ]);

        $response->assertCreated();

        $json = $response->json();

        expect($json)->toHaveKey('data');
        expect($json)->toHaveKey('message');

        $data = $json['data'];
        expect($data)->toHaveKey('id');
        expect($data)->toHaveKey('device_id');
        expect($data)->toHaveKey('platform');
        expect($data)->toHaveKey('device_name');
        expect($data)->toHaveKey('biometric_enabled');
        expect($data)->toHaveKey('is_trusted');
        expect($data)->toHaveKey('is_blocked');
        expect($data)->toHaveKey('has_push_token');
        expect($data)->toHaveKey('created_at');

        assertKeysAreSnakeCase($json);
    });

    test('POST /api/mobile/devices validation error returns structured error', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->postJson('/api/mobile/devices', []);

        $response->assertUnprocessable();

        $json = $response->json();

        // Mobile endpoints use structured error format
        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code', 'VALIDATION_ERROR');
        expect($json['error'])->toHaveKey('message');
        expect($json['error'])->toHaveKey('details');

        assertKeysAreSnakeCase($json);
    });

    test('GET /api/mobile/devices requires authentication', function () {
        $response = $this->getJson('/api/mobile/devices');

        $response->assertUnauthorized();
    });

    test('POST /api/mobile/devices requires authentication', function () {
        $response = $this->postJson('/api/mobile/devices', [
            'device_id'   => 'test',
            'platform'    => 'ios',
            'app_version' => '1.0.0',
        ]);

        $response->assertUnauthorized();
    });
});

// ==========================================================================
//  3. MOBILE AUTH - BIOMETRIC
// ==========================================================================

describe('Mobile biometric auth endpoints', function () {
    test('POST /api/mobile/auth/biometric/challenge returns challenge shape', function () {
        [$user, $token] = createShapeTestUser();

        $device = MobileDevice::factory()->biometricEnabled()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->postJson('/api/mobile/auth/biometric/challenge', [
            'device_id' => $device->device_id,
        ]);

        $response->assertOk();

        $json = $response->json();

        expect($json)->toHaveKey('data');
        expect($json['data'])->toHaveKey('challenge');
        expect($json['data'])->toHaveKey('expires_at');

        assertKeysAreSnakeCase($json);
    });

    test('POST /api/mobile/auth/biometric/challenge with unknown device returns error shape', function () {
        $response = $this->postJson('/api/mobile/auth/biometric/challenge', [
            'device_id' => 'nonexistent-device-xyz',
        ]);

        $response->assertNotFound();

        $json = $response->json();

        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code', 'NOT_FOUND');
        expect($json['error'])->toHaveKey('message');

        assertKeysAreSnakeCase($json);
    });

    test('POST /api/mobile/auth/biometric/challenge with non-biometric device returns error', function () {
        [$user, $token] = createShapeTestUser();

        $device = MobileDevice::factory()->create([
            'user_id'           => $user->id,
            'biometric_enabled' => false,
        ]);

        $response = $this->postJson('/api/mobile/auth/biometric/challenge', [
            'device_id' => $device->device_id,
        ]);

        $response->assertBadRequest();

        $json = $response->json();

        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code', 'BIOMETRIC_NOT_AVAILABLE');
        expect($json['error'])->toHaveKey('message');

        assertKeysAreSnakeCase($json);
    });

    test('POST /api/mobile/auth/biometric/verify with unknown device returns error shape', function () {
        $response = $this->postJson('/api/mobile/auth/biometric/verify', [
            'device_id' => 'nonexistent-device-xyz',
            'challenge' => 'fake-challenge',
            'signature' => 'fake-signature',
        ]);

        $response->assertNotFound();

        $json = $response->json();

        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code', 'NOT_FOUND');
        expect($json['error'])->toHaveKey('message');

        assertKeysAreSnakeCase($json);
    });

    test('POST /api/mobile/auth/biometric/verify with invalid signature returns auth failed error', function () {
        [$user, $token] = createShapeTestUser();

        $device = MobileDevice::factory()->biometricEnabled()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->postJson('/api/mobile/auth/biometric/verify', [
            'device_id' => $device->device_id,
            'challenge' => 'fake-challenge',
            'signature' => base64_encode('fake-signature'),
        ]);

        $response->assertUnauthorized();

        $json = $response->json();

        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code', 'AUTHENTICATION_FAILED');
        expect($json['error'])->toHaveKey('message');

        assertKeysAreSnakeCase($json);
    });

    test('POST /api/mobile/auth/biometric/challenge validation error has correct format', function () {
        $response = $this->postJson('/api/mobile/auth/biometric/challenge', []);

        $response->assertUnprocessable();

        $json = $response->json();

        // Mobile BaseMobileRequest returns structured error format
        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code', 'VALIDATION_ERROR');
        expect($json['error'])->toHaveKey('message');
    });
});

// ==========================================================================
//  4. MOBILE NOTIFICATIONS
// ==========================================================================

describe('Mobile notifications - GET /api/mobile/notifications', function () {
    test('notifications response has data array and meta with unread_count', function () {
        [$user, $token] = createShapeTestUser();

        MobilePushNotification::create([
            'user_id'           => $user->id,
            'notification_type' => 'transaction.received',
            'title'             => 'Payment Received',
            'body'              => 'You received $50',
            'status'            => 'delivered',
        ]);

        MobilePushNotification::create([
            'user_id'           => $user->id,
            'notification_type' => 'security.login',
            'title'             => 'New Login',
            'body'              => 'Login detected',
            'status'            => 'sent',
        ]);

        $response = $this->withToken($token)->getJson('/api/mobile/notifications');

        $response->assertOk();

        $json = $response->json();

        // Must have data array
        expect($json)->toHaveKey('data');
        expect($json['data'])->toBeArray();
        expect($json['data'])->toHaveCount(2);

        // Must have meta with unread_count
        expect($json)->toHaveKey('meta');
        expect($json['meta'])->toHaveKey('unread_count');

        // Each notification item shape
        $notification = $json['data'][0];
        expect($notification)->toHaveKey('id');
        expect($notification)->toHaveKey('type');
        expect($notification)->toHaveKey('title');
        expect($notification)->toHaveKey('body');
        expect($notification)->toHaveKey('status');
        expect($notification)->toHaveKey('created_at');

        assertKeysAreSnakeCase($json);
    });

    test('notifications response with empty data returns correct shape', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/mobile/notifications');

        $response->assertOk();

        $json = $response->json();

        expect($json)->toHaveKey('data');
        expect($json['data'])->toBeArray();
        expect($json['data'])->toBeEmpty();
        expect($json)->toHaveKey('meta');
        expect($json['meta'])->toHaveKey('unread_count');
    });

    test('notifications endpoint requires authentication', function () {
        $response = $this->getJson('/api/mobile/notifications');

        $response->assertUnauthorized();
    });
});

// ==========================================================================
//  5. MOBILE PAYMENTS
// ==========================================================================

describe('Mobile payments - POST /api/v1/payments/intents', function () {
    test('payment intent validation error returns structured error', function () {
        [$user, $token] = createShapeTestUser();

        // Missing required fields
        $response = $this->withToken($token)->postJson('/api/v1/payments/intents', []);

        $response->assertUnprocessable();

        $json = $response->json();

        // Standard Laravel validation error
        expect($json)->toHaveKey('message');
        expect($json)->toHaveKey('errors');
    });

    test('payment intent requires authentication', function () {
        $response = $this->postJson('/api/v1/payments/intents', [
            'merchantId'       => 'merchant_test',
            'amount'           => 25.50,
            'asset'            => 'USDC',
            'preferredNetwork' => 'SOLANA',
        ]);

        $response->assertUnauthorized();
    });
});

describe('Mobile payments - GET /api/v1/activity', function () {
    test('activity feed returns success and data envelope', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/v1/activity');

        $response->assertOk();

        $json = $response->json();

        expect($json)->toHaveKey('success', true);
        expect($json)->toHaveKey('data');

        assertKeysAreSnakeCase($json);
    });

    test('activity feed requires authentication', function () {
        $response = $this->getJson('/api/v1/activity');

        $response->assertUnauthorized();
    });
});

// ==========================================================================
//  6. WALLET ENDPOINTS
// ==========================================================================

describe('Wallet receive - GET /api/v1/wallet/receive', function () {
    test('wallet receive returns success and data envelope', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/v1/wallet/receive?asset=USDC&network=SOLANA');

        $response->assertOk();

        $json = $response->json();

        expect($json)->toHaveKey('success', true);
        expect($json)->toHaveKey('data');

        assertKeysAreSnakeCase($json);
    });

    test('wallet receive with invalid params returns validation error', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/v1/wallet/receive');

        $response->assertUnprocessable();

        $json = $response->json();
        expect($json)->toHaveKey('message');
        expect($json)->toHaveKey('errors');
    });

    test('wallet receive requires authentication', function () {
        $response = $this->getJson('/api/v1/wallet/receive?asset=USDC&network=SOLANA');

        $response->assertUnauthorized();
    });
});

describe('Network status - GET /api/v1/networks/status', function () {
    test('network status returns success with networks array', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/v1/networks/status');

        $response->assertOk();

        $json = $response->json();

        expect($json)->toHaveKey('success', true);
        expect($json)->toHaveKey('data');
        expect($json['data'])->toHaveKey('networks');
        expect($json['data']['networks'])->toBeArray();

        assertKeysAreSnakeCase($json);
    });

    test('network status requires authentication', function () {
        $response = $this->getJson('/api/v1/networks/status');

        $response->assertUnauthorized();
    });
});

// ==========================================================================
//  7. CARD ISSUANCE
// ==========================================================================

describe('Card issuance - GET /api/v1/cards', function () {
    test('card list returns success with data array', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/v1/cards');

        $response->assertOk();

        $json = $response->json();

        expect($json)->toHaveKey('success', true);
        expect($json)->toHaveKey('data');
        expect($json['data'])->toBeArray();

        assertKeysAreSnakeCase($json);
    });

    test('card list requires authentication', function () {
        $response = $this->getJson('/api/v1/cards');

        $response->assertUnauthorized();
    });
});

describe('Card issuance - POST /api/v1/cards', function () {
    test('card creation returns success with data object', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->postJson('/api/v1/cards', [
            'cardholder_name' => 'Test User',
            'currency'        => 'USD',
            'network'         => 'visa',
        ]);

        // Accept either 201 (created) or 400 (external service unavailable in test)
        // The shape verification is the important part
        $status = $response->getStatusCode();
        expect($status)->toBeIn([201, 400]);

        $json = $response->json();

        // Both success and error responses must have 'success' boolean
        expect($json)->toHaveKey('success');

        if ($status === 201) {
            expect($json['success'])->toBeTrue();
            expect($json)->toHaveKey('data');
        } else {
            expect($json['success'])->toBeFalse();
            expect($json)->toHaveKey('error');
            expect($json['error'])->toHaveKey('code');
            expect($json['error'])->toHaveKey('message');
        }

        assertKeysAreSnakeCase($json);
    });

    test('card creation requires authentication', function () {
        $response = $this->postJson('/api/v1/cards', [
            'cardholder_name' => 'Test User',
            'currency'        => 'USD',
        ]);

        $response->assertUnauthorized();
    });
});

// ==========================================================================
//  8. CROSS-CUTTING: snake_case VERIFICATION
// ==========================================================================

describe('Cross-cutting: all public endpoints use snake_case keys', function () {
    test('GET /api/mobile/config uses only snake_case keys', function () {
        $response = $this->getJson('/api/mobile/config');
        $response->assertOk();
        assertKeysAreSnakeCase($response->json());
    });

    test('GET /api/v1/app/status uses only snake_case keys', function () {
        $response = $this->getJson('/api/v1/app/status');
        $response->assertOk();
        assertKeysAreSnakeCase($response->json());
    });
});

describe('Cross-cutting: authenticated endpoints use snake_case keys', function () {
    test('GET /api/mobile/devices uses only snake_case keys', function () {
        [$user, $token] = createShapeTestUser();
        MobileDevice::factory()->create(['user_id' => $user->id]);

        $response = $this->withToken($token)->getJson('/api/mobile/devices');
        $response->assertOk();
        assertKeysAreSnakeCase($response->json());
    });

    test('GET /api/mobile/notifications uses only snake_case keys', function () {
        [$user, $token] = createShapeTestUser();

        MobilePushNotification::create([
            'user_id'           => $user->id,
            'notification_type' => 'test.event',
            'title'             => 'Shape Test',
            'body'              => 'Checking keys',
            'status'            => 'delivered',
        ]);

        $response = $this->withToken($token)->getJson('/api/mobile/notifications');
        $response->assertOk();
        assertKeysAreSnakeCase($response->json());
    });

    test('GET /api/v1/activity uses only snake_case keys', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/v1/activity');
        $response->assertOk();
        assertKeysAreSnakeCase($response->json());
    });

    test('GET /api/v1/networks/status uses only snake_case keys', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/v1/networks/status');
        $response->assertOk();
        assertKeysAreSnakeCase($response->json());
    });

    test('GET /api/v1/cards uses only snake_case keys', function () {
        [$user, $token] = createShapeTestUser();

        $response = $this->withToken($token)->getJson('/api/v1/cards');
        $response->assertOk();
        assertKeysAreSnakeCase($response->json());
    });
});

// ==========================================================================
//  9. ERROR RESPONSE CONSISTENCY
// ==========================================================================

describe('Error response format consistency', function () {
    test('unauthenticated requests return standard error shape', function () {
        $endpoints = [
            ['GET', '/api/mobile/devices'],
            ['GET', '/api/mobile/notifications'],
            ['GET', '/api/v1/activity'],
            ['GET', '/api/v1/networks/status'],
            ['GET', '/api/v1/cards'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);

            $response->assertUnauthorized();

            $json = $response->json();

            // Sanctum returns { "message": "Unauthenticated." }
            expect($json)->toHaveKey('message');
        }
    });

    test('mobile validation errors use structured error envelope', function () {
        [$user, $token] = createShapeTestUser();

        // RegisterDeviceRequest extends BaseMobileRequest
        $response = $this->withToken($token)->postJson('/api/mobile/devices', []);

        $response->assertUnprocessable();

        $json = $response->json();

        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code');
        expect($json['error'])->toHaveKey('message');
        expect($json['error']['code'])->toBe('VALIDATION_ERROR');
    });

    test('biometric challenge not found error has error envelope', function () {
        $response = $this->postJson('/api/mobile/auth/biometric/challenge', [
            'device_id' => 'nonexistent',
        ]);

        $response->assertNotFound();

        $json = $response->json();

        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code');
        expect($json['error'])->toHaveKey('message');
    });

    test('biometric verify not found error has error envelope', function () {
        $response = $this->postJson('/api/mobile/auth/biometric/verify', [
            'device_id' => 'nonexistent',
            'challenge' => 'fake',
            'signature' => 'fake',
        ]);

        $response->assertNotFound();

        $json = $response->json();

        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code', 'NOT_FOUND');
        expect($json['error'])->toHaveKey('message');
    });
});

// ==========================================================================
// 10. RESPONSE ENVELOPE PATTERNS
// ==========================================================================

describe('Response envelope patterns', function () {
    test('successful data responses use data envelope', function () {
        [$user, $token] = createShapeTestUser();

        // Each of these should have a 'data' key
        $endpoints = [
            ['GET', '/api/mobile/config'],
            ['GET', '/api/v1/app/status'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertOk();
            $json = $response->json();
            expect(array_key_exists('data', $json))->toBeTrue("Missing 'data' key in response for {$method} {$url}");
        }
    });

    test('authenticated data responses use data envelope', function () {
        [$user, $token] = createShapeTestUser();

        $endpoints = [
            ['GET', '/api/mobile/devices'],
            ['GET', '/api/mobile/notifications'],
            ['GET', '/api/v1/activity'],
            ['GET', '/api/v1/networks/status'],
            ['GET', '/api/v1/cards'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->withToken($token)->json($method, $url);
            $response->assertOk();
            $json = $response->json();
            // Check that either 'data' or 'success'+'data' pattern is used
            $hasData = array_key_exists('data', $json);
            $hasSuccess = array_key_exists('success', $json);
            expect($hasData || $hasSuccess)->toBeTrue("Response for {$method} {$url} must have 'data' or 'success' key");
        }
    });
});
