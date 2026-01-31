<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mobile\Services;

use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Services\MobileDeviceService;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class MobileDeviceServiceTest extends TestCase
{
    protected MobileDeviceService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MobileDeviceService();
        $this->user = User::factory()->create();
    }

    public function test_can_register_new_device(): void
    {
        $device = $this->service->registerDevice(
            $this->user,
            'test-device-id',
            'android',
            '1.0.0',
            'fcm-token',
            'Test Phone',
            'Pixel 8',
            'Android 14'
        );

        $this->assertInstanceOf(MobileDevice::class, $device);
        $this->assertEquals('test-device-id', $device->device_id);
        $this->assertEquals('android', $device->platform);
        $this->assertEquals($this->user->id, $device->user_id);
    }

    public function test_updates_existing_device_on_reregistration(): void
    {
        // First registration
        $device1 = $this->service->registerDevice(
            $this->user,
            'same-device-id',
            'android',
            '1.0.0'
        );

        // Second registration
        $device2 = $this->service->registerDevice(
            $this->user,
            'same-device-id',
            'android',
            '2.0.0'
        );

        $this->assertEquals($device1->id, $device2->id);
        $this->assertEquals('2.0.0', $device2->app_version);
    }

    public function test_removes_oldest_device_when_limit_reached(): void
    {
        // Create max devices (5) with time travel for different timestamps
        Carbon::setTestNow(now());
        for ($i = 1; $i <= 5; $i++) {
            $this->service->registerDevice(
                $this->user,
                "device-{$i}",
                'android',
                '1.0.0'
            );
            // Advance time instead of sleeping to ensure different timestamps
            Carbon::setTestNow(now()->addSecond());
        }
        Carbon::setTestNow(); // Reset time

        $this->assertEquals(5, MobileDevice::where('user_id', $this->user->id)->count());

        // Register 6th device
        $this->service->registerDevice(
            $this->user,
            'device-6',
            'android',
            '1.0.0'
        );

        // Should still have 5 devices
        $this->assertEquals(5, MobileDevice::where('user_id', $this->user->id)->count());

        // First device should be removed
        $this->assertDatabaseMissing('mobile_devices', ['device_id' => 'device-1']);
        $this->assertDatabaseHas('mobile_devices', ['device_id' => 'device-6']);
    }

    public function test_can_update_push_token(): void
    {
        $device = $this->service->registerDevice(
            $this->user,
            'device-id',
            'android',
            '1.0.0',
            'old-token'
        );

        $updatedDevice = $this->service->updatePushToken($device, 'new-token');

        $this->assertEquals('new-token', $updatedDevice->push_token);
    }

    public function test_clears_duplicate_push_token(): void
    {
        $device1 = $this->service->registerDevice(
            $this->user,
            'device-1',
            'android',
            '1.0.0',
            'shared-token'
        );

        $device2 = $this->service->registerDevice(
            $this->user,
            'device-2',
            'android',
            '1.0.0'
        );

        // Update device2 with device1's token
        $this->service->updatePushToken($device2, 'shared-token');

        $device1->refresh();
        $device2->refresh();

        // Device1 should have null token
        $this->assertNull($device1->push_token);
        // Device2 should have the token
        $this->assertEquals('shared-token', $device2->push_token);
    }

    public function test_can_unregister_device(): void
    {
        $device = $this->service->registerDevice(
            $this->user,
            'device-to-delete',
            'android',
            '1.0.0'
        );

        $this->service->unregisterDevice($device);

        $this->assertDatabaseMissing('mobile_devices', ['device_id' => 'device-to-delete']);
    }

    public function test_can_get_user_devices(): void
    {
        $this->service->registerDevice($this->user, 'device-1', 'android', '1.0.0');
        $this->service->registerDevice($this->user, 'device-2', 'ios', '1.0.0');

        $devices = $this->service->getUserDevices($this->user);

        $this->assertCount(2, $devices);
    }

    public function test_can_get_active_devices_only(): void
    {
        $this->service->registerDevice($this->user, 'active-device', 'android', '1.0.0');

        $blockedDevice = MobileDevice::create([
            'user_id'     => $this->user->id,
            'device_id'   => 'blocked-device',
            'platform'    => 'ios',
            'app_version' => '1.0.0',
            'is_blocked'  => true,
            'blocked_at'  => now(),
        ]);

        $activeDevices = $this->service->getActiveDevices($this->user);

        $this->assertCount(1, $activeDevices);
        $firstActive = $activeDevices->first();
        $this->assertNotNull($firstActive);
        $this->assertEquals('active-device', $firstActive->device_id);
    }

    public function test_can_get_push_enabled_devices(): void
    {
        $this->service->registerDevice($this->user, 'with-token', 'android', '1.0.0', 'fcm-token');
        $this->service->registerDevice($this->user, 'without-token', 'android', '1.0.0');

        $pushDevices = $this->service->getPushEnabledDevices($this->user);

        $this->assertCount(1, $pushDevices);
        $firstPush = $pushDevices->first();
        $this->assertNotNull($firstPush);
        $this->assertEquals('with-token', $firstPush->device_id);
    }

    public function test_can_block_device(): void
    {
        $device = $this->service->registerDevice(
            $this->user,
            'device-to-block',
            'android',
            '1.0.0'
        );

        $this->service->blockDevice($device, 'Suspicious activity');

        $device->refresh();

        $this->assertTrue($device->is_blocked);
        $this->assertEquals('Suspicious activity', $device->blocked_reason);
        $this->assertNotNull($device->blocked_at);
    }

    public function test_can_unblock_device(): void
    {
        $device = MobileDevice::create([
            'user_id'        => $this->user->id,
            'device_id'      => 'blocked-device',
            'platform'       => 'android',
            'app_version'    => '1.0.0',
            'is_blocked'     => true,
            'blocked_at'     => now(),
            'blocked_reason' => 'Test block',
        ]);

        $this->service->unblockDevice($device);

        $device->refresh();

        $this->assertFalse($device->is_blocked);
        $this->assertNull($device->blocked_reason);
        $this->assertNull($device->blocked_at);
    }

    public function test_can_trust_device(): void
    {
        $device = $this->service->registerDevice(
            $this->user,
            'device-to-trust',
            'android',
            '1.0.0'
        );

        $this->service->trustDevice($device, 'admin@example.com');

        $device->refresh();

        $this->assertTrue($device->is_trusted);
        $this->assertEquals('admin@example.com', $device->trusted_by);
        $this->assertNotNull($device->trusted_at);
    }

    public function test_can_find_by_device_id(): void
    {
        $this->service->registerDevice($this->user, 'find-me', 'android', '1.0.0');

        $found = $this->service->findByDeviceId('find-me');

        $this->assertNotNull($found);
        $this->assertEquals('find-me', $found->device_id);
    }

    public function test_find_by_device_id_returns_null_for_unknown(): void
    {
        $found = $this->service->findByDeviceId('nonexistent');

        $this->assertNull($found);
    }

    public function test_can_cleanup_stale_devices(): void
    {
        // Create active device
        $this->service->registerDevice($this->user, 'active', 'android', '1.0.0');

        // Create stale device
        $staleDevice = MobileDevice::create([
            'user_id'        => $this->user->id,
            'device_id'      => 'stale',
            'platform'       => 'android',
            'app_version'    => '1.0.0',
            'last_active_at' => now()->subDays(100),
        ]);

        $cleaned = $this->service->cleanupStaleDevices(90);

        $this->assertEquals(1, $cleaned);
        $this->assertDatabaseMissing('mobile_devices', ['device_id' => 'stale']);
        $this->assertDatabaseHas('mobile_devices', ['device_id' => 'active']);
    }

    public function test_can_get_statistics(): void
    {
        $this->service->registerDevice($this->user, 'android-1', 'android', '1.0.0', 'token');
        $this->service->registerDevice($this->user, 'ios-1', 'ios', '1.0.0');

        $device = MobileDevice::where('device_id', 'android-1')->firstOrFail();
        $device->update(['biometric_enabled' => true, 'biometric_public_key' => 'key']);

        $stats = $this->service->getStatistics();

        $this->assertEquals(2, $stats['total_devices']);
        $this->assertEquals(2, $stats['active_devices']);
        $this->assertEquals(0, $stats['blocked_devices']);
        $this->assertEquals(1, $stats['ios_devices']);
        $this->assertEquals(1, $stats['android_devices']);
        $this->assertEquals(1, $stats['biometric_enabled']);
        $this->assertEquals(1, $stats['with_push_token']);
    }
}
