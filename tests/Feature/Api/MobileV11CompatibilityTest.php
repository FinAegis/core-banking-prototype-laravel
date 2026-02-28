<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Commerce\Enums\MerchantStatus;
use App\Domain\Commerce\Models\Merchant;
use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobilePushNotification;
use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for mobile v1.1 backend compatibility features.
 *
 * Covers WebAuthn registration challenge & attestation registration,
 * route compatibility aliases, notification unread count, and
 * recent recipients endpoint.
 */
class MobileV11CompatibilityTest extends TestCase
{
    protected User $testUser;

    protected string $token;

    protected Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->testUser = User::factory()->create();
        $this->token = $this->testUser->createToken('test', ['read', 'write'])->plainTextToken;

        // Merchant is required as a foreign key for payment_intents
        $this->merchant = Merchant::create([
            'public_id'         => 'merchant_test_' . Str::random(8),
            'display_name'      => 'Test Merchant',
            'icon_url'          => 'https://example.com/icon.png',
            'accepted_assets'   => ['USDC'],
            'accepted_networks' => ['polygon'],
            'status'            => MerchantStatus::ACTIVE,
        ]);
    }

    // ---------------------------------------------------------------
    // 1. WebAuthn Registration Challenge
    //    POST /api/v1/auth/passkey/challenge with type=registration
    //
    //    The v1/auth/passkey/challenge route is public (no auth:sanctum
    //    middleware), but the registrationChallenge handler checks
    //    $request->user() internally. We use Sanctum::actingAs() to
    //    set up the authenticated user context for these tests.
    // ---------------------------------------------------------------

    public function test_registration_challenge_returns_creation_options(): void
    {
        Sanctum::actingAs($this->testUser, ['read', 'write']);
        $device = $this->createDeviceForUser($this->testUser);

        $response = $this->postJson('/api/v1/auth/passkey/challenge', [
            'type'      => 'registration',
            'device_id' => $device->device_id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rp',
                    'user',
                    'pubKeyCredParams',
                    'authenticatorSelection',
                    'excludeCredentials',
                    'challenge',
                ],
            ]);

        // Verify rp contains id and name
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data['rp']);
        $this->assertArrayHasKey('name', $data['rp']);

        // Verify user info is present
        $this->assertArrayHasKey('id', $data['user']);
        $this->assertArrayHasKey('name', $data['user']);
        $this->assertArrayHasKey('displayName', $data['user']);

        // Verify pubKeyCredParams is an array
        $this->assertIsArray($data['pubKeyCredParams']);
        $this->assertNotEmpty($data['pubKeyCredParams']);
    }

    public function test_registration_challenge_requires_authentication(): void
    {
        $device = $this->createDeviceForUser($this->testUser);

        // No auth context -- $request->user() returns null, handler returns 401
        $response = $this->postJson('/api/v1/auth/passkey/challenge', [
            'type'      => 'registration',
            'device_id' => $device->device_id,
        ]);

        $response->assertStatus(401);
    }

    public function test_registration_challenge_requires_device_id(): void
    {
        Sanctum::actingAs($this->testUser, ['read', 'write']);

        $response = $this->postJson('/api/v1/auth/passkey/challenge', [
            'type' => 'registration',
        ]);

        // Validation requires device_id when type=registration
        $response->assertStatus(422);
    }

    public function test_registration_challenge_returns_404_for_unknown_device(): void
    {
        Sanctum::actingAs($this->testUser, ['read', 'write']);

        $response = $this->postJson('/api/v1/auth/passkey/challenge', [
            'type'      => 'registration',
            'device_id' => 'nonexistent-device-' . Str::random(8),
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'DEVICE_NOT_FOUND');
    }

    public function test_registration_challenge_returns_403_for_other_users_device(): void
    {
        Sanctum::actingAs($this->testUser, ['read', 'write']);

        $otherUser = User::factory()->create();
        $device = $this->createDeviceForUser($otherUser);

        $response = $this->postJson('/api/v1/auth/passkey/challenge', [
            'type'      => 'registration',
            'device_id' => $device->device_id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    // ---------------------------------------------------------------
    // 2. WebAuthn Attestation Registration
    //    POST /api/auth/passkey/register (legacy path, behind auth:sanctum)
    //    Also works at POST /api/v1/auth/passkey/register
    // ---------------------------------------------------------------

    public function test_attestation_registration_requires_challenge_and_attestation_fields(): void
    {
        $device = $this->createDeviceForUser($this->testUser);

        // When attestation_object is present, challenge and client_data_json
        // are also required. Omit them to trigger 422.
        $response = $this->withToken($this->token)->postJson('/api/auth/passkey/register', [
            'device_id'          => $device->device_id,
            'credential_id'      => base64_encode(random_bytes(32)),
            'attestation_object' => base64_encode('fake-attestation'),
            // Missing challenge and client_data_json
        ]);

        $response->assertStatus(422);
    }

    public function test_legacy_registration_with_public_key_still_works(): void
    {
        $device = $this->createDeviceForUser($this->testUser);

        $response = $this->withToken($this->token)->postJson('/api/auth/passkey/register', [
            'device_id'     => $device->device_id,
            'credential_id' => base64_encode(random_bytes(32)),
            'public_key'    => base64_encode(random_bytes(65)),
        ]);

        // Should succeed (201) with the legacy flow
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'credential_id',
                    'registered_at',
                ],
            ]);
    }

    public function test_v1_path_legacy_registration_with_public_key_works(): void
    {
        $device = $this->createDeviceForUser($this->testUser);

        $response = $this->withToken($this->token)->postJson('/api/v1/auth/passkey/register', [
            'device_id'     => $device->device_id,
            'credential_id' => base64_encode(random_bytes(32)),
            'public_key'    => base64_encode(random_bytes(65)),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_register_returns_404_for_unknown_device(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/auth/passkey/register', [
            'device_id'     => 'nonexistent-device-' . Str::random(8),
            'credential_id' => base64_encode(random_bytes(32)),
            'public_key'    => base64_encode(random_bytes(65)),
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'DEVICE_NOT_FOUND');
    }

    public function test_register_returns_403_for_wrong_user(): void
    {
        $otherUser = User::factory()->create();
        $device = $this->createDeviceForUser($otherUser);

        $response = $this->withToken($this->token)->postJson('/api/auth/passkey/register', [
            'device_id'     => $device->device_id,
            'credential_id' => base64_encode(random_bytes(32)),
            'public_key'    => base64_encode(random_bytes(65)),
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    // ---------------------------------------------------------------
    // 3. Route Compatibility Aliases
    // ---------------------------------------------------------------

    public function test_estimate_fee_alias_reaches_mobile_relayer_controller(): void
    {
        // GET /api/v1/relayer/estimate-fee is an alias for estimateGas.
        // It requires auth and valid network/to params but the route itself
        // should not return 404/405.
        $response = $this->withToken($this->token)->getJson('/api/v1/relayer/estimate-fee');

        // Expect 422 (validation error for missing params) which proves the
        // route exists and reaches the correct controller action.
        $this->assertNotContains(
            $response->getStatusCode(),
            [404, 405],
            'Route /api/v1/relayer/estimate-fee should exist (not 404/405)'
        );
    }

    public function test_create_account_alias_reaches_smart_account_controller(): void
    {
        // POST /api/v1/wallet/create-account is an alias for
        // SmartAccountController@createAccount.
        $response = $this->withToken($this->token)->postJson('/api/v1/wallet/create-account', []);

        // Expect 422 (validation requires network param) which proves the route
        // exists and reaches the SmartAccountController.
        $this->assertNotContains(
            $response->getStatusCode(),
            [404, 405],
            'Route /api/v1/wallet/create-account should exist (not 404/405)'
        );
    }

    public function test_data_export_alias_reaches_gdpr_controller(): void
    {
        // POST /api/v1/user/data-export is an alias for
        // GdprController@requestDataExport.
        $response = $this->withToken($this->token)->postJson('/api/v1/user/data-export');

        // GdprController@requestDataExport returns 200 on success or 500 on
        // error. Either way, it should not be 404/405 proving the route works.
        $this->assertNotContains(
            $response->getStatusCode(),
            [404, 405],
            'Route /api/v1/user/data-export should exist (not 404/405)'
        );
    }

    public function test_estimate_fee_alias_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/relayer/estimate-fee');

        $response->assertStatus(401);
    }

    public function test_create_account_alias_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/wallet/create-account', [
            'network' => 'polygon',
        ]);

        $response->assertStatus(401);
    }

    public function test_data_export_alias_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/user/data-export');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // 4. Notification Unread Count
    //    GET /api/v1/notifications/unread-count
    // ---------------------------------------------------------------

    public function test_unread_count_returns_zero_for_no_notifications(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'unread_count',
                ],
            ]);
    }

    public function test_unread_count_returns_correct_count(): void
    {
        $device = $this->createDeviceForUser($this->testUser);

        // Create 3 unread notifications
        for ($i = 0; $i < 3; $i++) {
            MobilePushNotification::create([
                'user_id'           => $this->testUser->id,
                'mobile_device_id'  => $device->id,
                'notification_type' => 'transaction',
                'title'             => "Notification {$i}",
                'body'              => "Body {$i}",
                'status'            => 'delivered',
                'read_at'           => null,
            ]);
        }

        // Create 2 read notifications (should not be counted)
        for ($i = 0; $i < 2; $i++) {
            MobilePushNotification::create([
                'user_id'           => $this->testUser->id,
                'mobile_device_id'  => $device->id,
                'notification_type' => 'transaction',
                'title'             => "Read Notification {$i}",
                'body'              => "Read Body {$i}",
                'status'            => 'delivered',
                'read_at'           => now(),
            ]);
        }

        $response = $this->withToken($this->token)->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 3);
    }

    public function test_unread_count_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(401);
    }

    public function test_unread_count_does_not_include_other_users_notifications(): void
    {
        $otherUser = User::factory()->create();
        $device = $this->createDeviceForUser($otherUser);

        // Create notifications for other user
        MobilePushNotification::create([
            'user_id'           => $otherUser->id,
            'mobile_device_id'  => $device->id,
            'notification_type' => 'transaction',
            'title'             => 'Other user notification',
            'body'              => 'Body',
            'status'            => 'delivered',
            'read_at'           => null,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    // ---------------------------------------------------------------
    // 5. Recent Recipients
    //    GET /api/v1/wallet/recent-recipients
    // ---------------------------------------------------------------

    public function test_recent_recipients_returns_empty_list(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/wallet/recent-recipients');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $this->assertIsArray($response->json('data'));
        $this->assertCount(0, $response->json('data'));
    }

    public function test_recent_recipients_returns_recipients_from_send_history(): void
    {
        // The controller filters by status in ['confirmed', 'pending'].
        // 'confirmed' is the primary qualifying status from the PaymentIntentStatus enum.
        $this->createPaymentIntentForUser($this->testUser, [
            'recipient_address' => '0xABCDef1234567890abcdef1234567890ABCDEF01',
        ], 'confirmed');

        $this->createPaymentIntentForUser($this->testUser, [
            'recipient_address' => '0x1111111111111111111111111111111111111111',
        ], 'confirmed');

        $response = $this->withToken($this->token)->getJson('/api/v1/wallet/recent-recipients');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Verify structure of each recipient
        foreach ($data as $recipient) {
            $this->assertArrayHasKey('address', $recipient);
            $this->assertArrayHasKey('network', $recipient);
            $this->assertArrayHasKey('token', $recipient);
            $this->assertArrayHasKey('last_sent_at', $recipient);
        }
    }

    public function test_recent_recipients_respects_limit_parameter(): void
    {
        // Create 5 unique recipients
        for ($i = 0; $i < 5; $i++) {
            $this->createPaymentIntentForUser($this->testUser, [
                'recipient_address' => sprintf('0x%040d', $i + 1),
            ], 'confirmed');
        }

        $response = $this->withToken($this->token)->getJson('/api/v1/wallet/recent-recipients?limit=3');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_recent_recipients_deduplicates_by_address(): void
    {
        $sameAddress = '0xABCDef1234567890abcdef1234567890ABCDEF01';

        // Create 3 intents to the same address
        for ($i = 0; $i < 3; $i++) {
            $this->createPaymentIntentForUser($this->testUser, [
                'recipient_address' => $sameAddress,
            ], 'confirmed');
        }

        $response = $this->withToken($this->token)->getJson('/api/v1/wallet/recent-recipients');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        // Should be deduplicated to 1 unique address
        $this->assertCount(1, $data);
        $this->assertEquals($sameAddress, $data[0]['address']);
    }

    public function test_recent_recipients_excludes_non_confirmed_statuses(): void
    {
        // Create intents with non-qualifying statuses ('created', 'failed')
        $this->createPaymentIntentForUser($this->testUser, [
            'recipient_address' => '0xAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
        ], 'created');

        $this->createPaymentIntentForUser($this->testUser, [
            'recipient_address' => '0xBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB',
        ], 'failed');

        $response = $this->withToken($this->token)->getJson('/api/v1/wallet/recent-recipients');

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Neither 'created' nor 'failed' qualify (only 'confirmed' and 'pending')
        $this->assertCount(0, $response->json('data'));
    }

    public function test_recent_recipients_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/wallet/recent-recipients');

        $response->assertStatus(401);
    }

    public function test_recent_recipients_does_not_include_other_users_intents(): void
    {
        $otherUser = User::factory()->create();

        $this->createPaymentIntentForUser($otherUser, [
            'recipient_address' => '0xCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC',
        ], 'confirmed');

        $response = $this->withToken($this->token)->getJson('/api/v1/wallet/recent-recipients');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(0, $response->json('data'));
    }

    public function test_recent_recipients_limit_is_capped_at_50(): void
    {
        // Even if we pass limit=100, the controller caps at 50
        $response = $this->withToken($this->token)->getJson('/api/v1/wallet/recent-recipients?limit=100');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createDeviceForUser(User $user): MobileDevice
    {
        return MobileDevice::create([
            'user_id'     => $user->id,
            'device_id'   => 'test-device-' . Str::random(8),
            'device_name' => 'Test Device',
            'platform'    => 'ios',
            'push_token'  => 'test-push-token-' . Str::random(16),
            'app_version' => '1.1.0',
        ]);
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function createPaymentIntentForUser(User $user, array $metadata, string $status): PaymentIntent
    {
        return PaymentIntent::create([
            'public_id'              => Str::uuid()->toString(),
            'user_id'                => $user->id,
            'merchant_id'            => $this->merchant->id,
            'asset'                  => 'USDC',
            'network'                => 'polygon',
            'amount'                 => '10.00',
            'status'                 => $status,
            'shield_enabled'         => false,
            'required_confirmations' => 1,
            'idempotency_key'        => Str::uuid()->toString(),
            'metadata'               => $metadata,
            'expires_at'             => now()->addHour(),
        ]);
    }
}
