<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Mobile\Models\BiometricChallenge;
use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobilePushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write'])->plainTextToken;
    }

    public function test_can_get_mobile_config_without_auth(): void
    {
        $response = $this->getJson('/api/mobile/config');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'min_app_version',
                    'latest_app_version',
                    'force_update',
                    'maintenance_mode',
                    'features' => [
                        'biometric_auth',
                        'push_notifications',
                        'gcu_trading',
                        'p2p_transfers',
                    ],
                    'websocket',
                ],
            ]);
    }

    public function test_can_register_mobile_device(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/mobile/devices', [
            'device_id'    => 'test-device-123',
            'platform'     => 'android',
            'app_version'  => '1.0.0',
            'push_token'   => 'fcm-token-abc123',
            'device_name'  => 'Test Phone',
            'device_model' => 'Pixel 8',
            'os_version'   => 'Android 14',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'device_id',
                    'platform',
                    'device_name',
                    'biometric_enabled',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('mobile_devices', [
            'device_id' => 'test-device-123',
            'platform'  => 'android',
            'user_id'   => $this->user->id,
        ]);
    }

    public function test_register_device_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/mobile/devices', []);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => ['device_id', 'platform', 'app_version'],
                ],
            ]);
    }

    public function test_register_device_validates_platform(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/mobile/devices', [
            'device_id'   => 'test-device',
            'platform'    => 'windows', // Invalid
            'app_version' => '1.0.0',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => ['platform'],
                ],
            ]);
    }

    public function test_can_list_user_devices(): void
    {
        MobileDevice::create([
            'user_id'     => $this->user->id,
            'device_id'   => 'device-1',
            'platform'    => 'ios',
            'app_version' => '1.0.0',
        ]);

        MobileDevice::create([
            'user_id'     => $this->user->id,
            'device_id'   => 'device-2',
            'platform'    => 'android',
            'app_version' => '1.0.0',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/mobile/devices');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_specific_device(): void
    {
        $device = MobileDevice::create([
            'user_id'     => $this->user->id,
            'device_id'   => 'device-1',
            'platform'    => 'ios',
            'app_version' => '1.0.0',
        ]);

        $response = $this->withToken($this->token)->getJson("/api/mobile/devices/{$device->id}");

        $response->assertOk()
            ->assertJsonPath('data.device_id', 'device-1');
    }

    public function test_cannot_get_another_users_device(): void
    {
        $otherUser = User::factory()->create();
        $device = MobileDevice::create([
            'user_id'     => $otherUser->id,
            'device_id'   => 'other-device',
            'platform'    => 'ios',
            'app_version' => '1.0.0',
        ]);

        $response = $this->withToken($this->token)->getJson("/api/mobile/devices/{$device->id}");

        $response->assertNotFound();
    }

    public function test_can_unregister_device(): void
    {
        $device = MobileDevice::create([
            'user_id'     => $this->user->id,
            'device_id'   => 'device-to-delete',
            'platform'    => 'android',
            'app_version' => '1.0.0',
        ]);

        $response = $this->withToken($this->token)->deleteJson("/api/mobile/devices/{$device->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('mobile_devices', ['id' => $device->id]);
    }

    public function test_can_update_push_token(): void
    {
        $device = MobileDevice::create([
            'user_id'     => $this->user->id,
            'device_id'   => 'device-1',
            'platform'    => 'android',
            'app_version' => '1.0.0',
            'push_token'  => 'old-token',
        ]);

        $response = $this->withToken($this->token)->patchJson("/api/mobile/devices/{$device->id}/token", [
            'push_token' => 'new-fcm-token',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('mobile_devices', [
            'id'         => $device->id,
            'push_token' => 'new-fcm-token',
        ]);
    }

    public function test_can_enable_biometric_authentication(): void
    {
        $device = MobileDevice::create([
            'user_id'     => $this->user->id,
            'device_id'   => 'biometric-device',
            'platform'    => 'ios',
            'app_version' => '1.0.0',
        ]);

        // Generate a test ECDSA P-256 key pair
        $keyConfig = [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        $keyResource = openssl_pkey_new($keyConfig);
        $this->assertNotFalse($keyResource, 'Failed to generate test key');
        $keyDetails = openssl_pkey_get_details($keyResource);
        $this->assertIsArray($keyDetails, 'Failed to get key details');
        $publicKeyPem = $keyDetails['key'];

        $response = $this->withToken($this->token)->postJson('/api/mobile/auth/biometric/enable', [
            'device_id'  => 'biometric-device',
            'public_key' => $publicKeyPem,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true);

        $this->assertDatabaseHas('mobile_devices', [
            'id'                => $device->id,
            'biometric_enabled' => true,
        ]);
    }

    public function test_cannot_enable_biometric_for_blocked_device(): void
    {
        $device = MobileDevice::create([
            'user_id'        => $this->user->id,
            'device_id'      => 'blocked-device',
            'platform'       => 'android',
            'app_version'    => '1.0.0',
            'is_blocked'     => true,
            'blocked_at'     => now(),
            'blocked_reason' => 'Suspicious activity',
        ]);

        $response = $this->withToken($this->token)->postJson('/api/mobile/auth/biometric/enable', [
            'device_id'  => 'blocked-device',
            'public_key' => 'test-key',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'DEVICE_BLOCKED');
    }

    public function test_can_disable_biometric_authentication(): void
    {
        $device = MobileDevice::create([
            'user_id'              => $this->user->id,
            'device_id'            => 'biometric-device',
            'platform'             => 'ios',
            'app_version'          => '1.0.0',
            'biometric_enabled'    => true,
            'biometric_public_key' => 'test-key',
        ]);

        $response = $this->withToken($this->token)->deleteJson('/api/mobile/auth/biometric/disable', [
            'device_id' => 'biometric-device',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('mobile_devices', [
            'id'                => $device->id,
            'biometric_enabled' => false,
        ]);
    }

    public function test_can_get_biometric_challenge(): void
    {
        $device = MobileDevice::create([
            'user_id'              => $this->user->id,
            'device_id'            => 'challenge-device',
            'platform'             => 'android',
            'app_version'          => '1.0.0',
            'biometric_enabled'    => true,
            'biometric_public_key' => 'test-public-key',
        ]);

        $response = $this->postJson('/api/mobile/auth/biometric/challenge', [
            'device_id' => 'challenge-device',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'challenge',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseHas('biometric_challenges', [
            'mobile_device_id' => $device->id,
            'status'           => BiometricChallenge::STATUS_PENDING,
        ]);
    }

    public function test_cannot_get_challenge_for_device_without_biometric(): void
    {
        MobileDevice::create([
            'user_id'           => $this->user->id,
            'device_id'         => 'no-biometric-device',
            'platform'          => 'android',
            'app_version'       => '1.0.0',
            'biometric_enabled' => false,
        ]);

        $response = $this->postJson('/api/mobile/auth/biometric/challenge', [
            'device_id' => 'no-biometric-device',
        ]);

        $response->assertBadRequest()
            ->assertJsonPath('error.code', 'BIOMETRIC_NOT_AVAILABLE');
    }

    public function test_can_get_notification_history(): void
    {
        MobilePushNotification::create([
            'user_id'           => $this->user->id,
            'notification_type' => 'transaction.received',
            'title'             => 'Payment Received',
            'body'              => 'You received $100',
            'status'            => 'delivered',
        ]);

        MobilePushNotification::create([
            'user_id'           => $this->user->id,
            'notification_type' => 'security.login',
            'title'             => 'New Login',
            'body'              => 'New login detected',
            'status'            => 'sent',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/mobile/notifications');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'title', 'body', 'status', 'created_at'],
                ],
                'meta' => [
                    'unread_count',
                ],
            ]);
    }

    public function test_can_mark_notification_as_read(): void
    {
        $notification = MobilePushNotification::create([
            'user_id'           => $this->user->id,
            'notification_type' => 'transaction.received',
            'title'             => 'Payment Received',
            'body'              => 'You received $100',
            'status'            => 'delivered',
        ]);

        $response = $this->withToken($this->token)->postJson("/api/mobile/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertDatabaseHas('mobile_push_notifications', [
            'id'     => $notification->id,
            'status' => 'read',
        ]);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        MobilePushNotification::create([
            'user_id'           => $this->user->id,
            'notification_type' => 'transaction.received',
            'title'             => 'Test 1',
            'body'              => 'Body 1',
            'status'            => 'delivered',
        ]);

        MobilePushNotification::create([
            'user_id'           => $this->user->id,
            'notification_type' => 'transaction.sent',
            'title'             => 'Test 2',
            'body'              => 'Body 2',
            'status'            => 'delivered',
        ]);

        $response = $this->withToken($this->token)->postJson('/api/mobile/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->assertEquals(0, MobilePushNotification::where('user_id', $this->user->id)->unread()->count());
    }

    public function test_device_registration_replaces_existing_device(): void
    {
        // First registration
        $this->withToken($this->token)->postJson('/api/mobile/devices', [
            'device_id'   => 'reused-device',
            'platform'    => 'android',
            'app_version' => '1.0.0',
            'device_name' => 'Old Name',
        ]);

        // Second registration with same device_id
        $response = $this->withToken($this->token)->postJson('/api/mobile/devices', [
            'device_id'   => 'reused-device',
            'platform'    => 'android',
            'app_version' => '2.0.0',
            'device_name' => 'New Name',
        ]);

        $response->assertCreated();

        // Should only have one device
        $this->assertEquals(1, MobileDevice::where('device_id', 'reused-device')->count());
        $this->assertDatabaseHas('mobile_devices', [
            'device_id'   => 'reused-device',
            'app_version' => '2.0.0',
            'device_name' => 'New Name',
        ]);
    }

    public function test_requires_authentication_for_protected_endpoints(): void
    {
        $this->getJson('/api/mobile/devices')->assertUnauthorized();
        $this->postJson('/api/mobile/devices', [])->assertUnauthorized();
        $this->getJson('/api/mobile/notifications')->assertUnauthorized();
        $this->postJson('/api/mobile/auth/biometric/enable', [])->assertUnauthorized();
    }
}
