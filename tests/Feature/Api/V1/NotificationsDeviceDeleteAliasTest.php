<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\Mobile\Models\MobileDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for the DELETE /api/v1/notifications/register-device/{deviceId} alias.
 *
 * Mobile sign-out flow calls this URL pattern to drop the device's push
 * notification routing token. The existing endpoint was /unregister-device
 * (no path param) which broke the controller — this alias wires the correct
 * {id} segment to MobileController::unregisterDevice.
 *
 * @see app/Domain/Mobile/Routes/api.php  (api.v1.notifications.device.unregister-alias)
 */
class NotificationsDeviceDeleteAliasTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->deleteJson('/api/v1/notifications/register-device/some-device-id')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_delete_own_device(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $device = MobileDevice::create([
            'user_id'     => $this->user->id,
            'device_id'   => 'sign-out-device-123',
            'platform'    => 'android',
            'app_version' => '2.0.0',
            'push_token'  => 'fcm-token-abc',
        ]);

        $this->deleteJson("/api/v1/notifications/register-device/{$device->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Device unregistered successfully');

        $this->assertDatabaseMissing('mobile_devices', ['id' => $device->id]);
    }

    public function test_deleting_another_users_device_returns_404(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $otherUser = User::factory()->create();
        $device = MobileDevice::create([
            'user_id'     => $otherUser->id,
            'device_id'   => 'other-user-device',
            'platform'    => 'ios',
            'app_version' => '1.5.0',
        ]);

        $this->deleteJson("/api/v1/notifications/register-device/{$device->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_deleting_non_existent_device_returns_404(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $this->deleteJson('/api/v1/notifications/register-device/99999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_route_is_named_unregister_alias(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('api.v1.notifications.device.unregister-alias'),
            'Route api.v1.notifications.device.unregister-alias must be registered'
        );
    }
}
