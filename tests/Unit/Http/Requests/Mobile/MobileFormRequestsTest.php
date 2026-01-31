<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Mobile;

use App\Http\Requests\Mobile\BlockDeviceRequest;
use App\Http\Requests\Mobile\DeviceIdRequest;
use App\Http\Requests\Mobile\EnableBiometricRequest;
use App\Http\Requests\Mobile\RegisterDeviceRequest;
use App\Http\Requests\Mobile\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Mobile\UpdatePushTokenRequest;
use App\Http\Requests\Mobile\VerifyBiometricRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Tests for Mobile FormRequest classes.
 */
class MobileFormRequestsTest extends TestCase
{
    // =========================================================
    // RegisterDeviceRequest Tests
    // =========================================================

    public function test_register_device_request_requires_device_id(): void
    {
        $validator = Validator::make(
            ['platform' => 'ios', 'app_version' => '1.0.0'],
            (new RegisterDeviceRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('device_id', $validator->errors()->toArray());
    }

    public function test_register_device_request_requires_valid_platform(): void
    {
        $validator = Validator::make(
            ['device_id' => 'test-device', 'platform' => 'windows', 'app_version' => '1.0.0'],
            (new RegisterDeviceRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform', $validator->errors()->toArray());
    }

    public function test_register_device_request_accepts_valid_data(): void
    {
        $validator = Validator::make(
            [
                'device_id'   => 'test-device-123',
                'platform'    => 'ios',
                'app_version' => '1.0.0',
                'push_token'  => 'fcm-token-123',
                'device_name' => 'iPhone 15',
            ],
            (new RegisterDeviceRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_register_device_request_enforces_max_lengths(): void
    {
        $validator = Validator::make(
            [
                'device_id'   => str_repeat('a', 101), // Max 100
                'platform'    => 'ios',
                'app_version' => '1.0.0',
            ],
            (new RegisterDeviceRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('device_id', $validator->errors()->toArray());
    }

    // =========================================================
    // UpdatePushTokenRequest Tests
    // =========================================================

    public function test_update_push_token_request_requires_token(): void
    {
        $validator = Validator::make(
            [],
            (new UpdatePushTokenRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('push_token', $validator->errors()->toArray());
    }

    public function test_update_push_token_request_accepts_valid_token(): void
    {
        $validator = Validator::make(
            ['push_token' => 'fcm-token-abcdef123456'],
            (new UpdatePushTokenRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }

    // =========================================================
    // EnableBiometricRequest Tests
    // =========================================================

    public function test_enable_biometric_request_requires_device_id_and_public_key(): void
    {
        $validator = Validator::make(
            [],
            (new EnableBiometricRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('device_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('public_key', $validator->errors()->toArray());
    }

    public function test_enable_biometric_request_accepts_valid_data(): void
    {
        $validator = Validator::make(
            [
                'device_id'  => 'test-device',
                'public_key' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE...',
                'key_id'     => 'key-123',
            ],
            (new EnableBiometricRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }

    // =========================================================
    // DeviceIdRequest Tests
    // =========================================================

    public function test_device_id_request_requires_device_id(): void
    {
        $validator = Validator::make(
            [],
            (new DeviceIdRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('device_id', $validator->errors()->toArray());
    }

    public function test_device_id_request_accepts_valid_device_id(): void
    {
        $validator = Validator::make(
            ['device_id' => 'my-unique-device-id'],
            (new DeviceIdRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }

    // =========================================================
    // VerifyBiometricRequest Tests
    // =========================================================

    public function test_verify_biometric_request_requires_all_fields(): void
    {
        $validator = Validator::make(
            [],
            (new VerifyBiometricRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('device_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('challenge', $validator->errors()->toArray());
        $this->assertArrayHasKey('signature', $validator->errors()->toArray());
    }

    public function test_verify_biometric_request_accepts_valid_data(): void
    {
        $validator = Validator::make(
            [
                'device_id' => 'test-device',
                'challenge' => 'random-challenge-string',
                'signature' => 'base64-encoded-signature',
            ],
            (new VerifyBiometricRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }

    // =========================================================
    // BlockDeviceRequest Tests
    // =========================================================

    public function test_block_device_request_accepts_optional_reason(): void
    {
        $validator = Validator::make(
            ['reason' => 'Suspicious activity detected'],
            (new BlockDeviceRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_block_device_request_enforces_reason_max_length(): void
    {
        $validator = Validator::make(
            ['reason' => str_repeat('a', 256)], // Max 255
            (new BlockDeviceRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('reason', $validator->errors()->toArray());
    }

    public function test_block_device_request_get_block_reason_returns_default(): void
    {
        $request = new BlockDeviceRequest();
        $this->assertEquals('User requested block', $request->getBlockReason());
    }

    // =========================================================
    // UpdateNotificationPreferencesRequest Tests
    // =========================================================

    public function test_update_notification_preferences_requires_preferences_array(): void
    {
        $validator = Validator::make(
            [],
            (new UpdateNotificationPreferencesRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('preferences', $validator->errors()->toArray());
    }

    public function test_update_notification_preferences_accepts_valid_data(): void
    {
        $validator = Validator::make(
            [
                'preferences' => [
                    'transaction_received' => ['push_enabled' => true, 'email_enabled' => false],
                    'security_alert'       => ['push_enabled' => true, 'email_enabled' => true],
                ],
            ],
            (new UpdateNotificationPreferencesRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_update_notification_preferences_validates_device_id_as_uuid(): void
    {
        $validator = Validator::make(
            [
                'preferences' => ['alert' => ['push_enabled' => true]],
                'device_id'   => 'not-a-uuid',
            ],
            (new UpdateNotificationPreferencesRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('device_id', $validator->errors()->toArray());
    }

    // =========================================================
    // Authorization Tests
    // =========================================================

    public function test_register_device_request_requires_authentication(): void
    {
        $request = new RegisterDeviceRequest();
        $this->assertFalse($request->authorize());
    }

    public function test_register_device_request_authorizes_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = RegisterDeviceRequest::create('/api/mobile/devices', 'POST');
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    public function test_verify_biometric_request_is_public(): void
    {
        $request = new VerifyBiometricRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_device_id_request_is_public(): void
    {
        $request = new DeviceIdRequest();
        $this->assertTrue($request->authorize());
    }
}
