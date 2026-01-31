<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mobile\Listeners;

use App\Domain\Mobile\Events\BiometricAuthFailed;
use App\Domain\Mobile\Events\BiometricAuthSucceeded;
use App\Domain\Mobile\Events\BiometricDeviceBlocked;
use App\Domain\Mobile\Events\MobileDeviceBlocked;
use App\Domain\Mobile\Events\MobileDeviceRegistered;
use App\Domain\Mobile\Events\MobileSessionCreated;
use App\Domain\Mobile\Listeners\LogMobileAuditEventListener;
use App\Domain\Mobile\Listeners\SendSecurityAlertListener;
use App\Domain\Mobile\Services\NotificationPreferenceService;
use App\Domain\Mobile\Services\PushNotificationService;
use Carbon\Carbon;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Unit tests for Mobile domain event listeners.
 *
 * These are pure unit tests that mock all dependencies.
 */
class MobileListenersTest extends TestCase
{
    protected string $deviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deviceId = Uuid::uuid4()->toString();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================
    // SendSecurityAlertListener Tests
    // =========================================================

    public function test_security_alert_listener_can_be_instantiated(): void
    {
        /** @var PushNotificationService&MockInterface $pushService */
        $pushService = Mockery::mock(PushNotificationService::class);
        /** @var NotificationPreferenceService&MockInterface $preferenceService */
        $preferenceService = Mockery::mock(NotificationPreferenceService::class);

        $listener = new SendSecurityAlertListener($pushService, $preferenceService);

        $this->assertInstanceOf(SendSecurityAlertListener::class, $listener);
    }

    public function test_security_alert_listener_subscribes_to_correct_events(): void
    {
        /** @var PushNotificationService&MockInterface $pushService */
        $pushService = Mockery::mock(PushNotificationService::class);
        /** @var NotificationPreferenceService&MockInterface $preferenceService */
        $preferenceService = Mockery::mock(NotificationPreferenceService::class);

        $listener = new SendSecurityAlertListener($pushService, $preferenceService);
        $events = $listener->subscribe();

        // Verify the listener subscribes to the expected events
        $this->assertIsArray($events);
        $this->assertArrayHasKey(MobileDeviceBlocked::class, $events);
        $this->assertArrayHasKey(BiometricDeviceBlocked::class, $events);
        $this->assertArrayHasKey(BiometricAuthFailed::class, $events);
    }

    // =========================================================
    // LogMobileAuditEventListener Tests
    // =========================================================

    public function test_audit_listener_can_be_instantiated(): void
    {
        $listener = new LogMobileAuditEventListener();

        $this->assertInstanceOf(LogMobileAuditEventListener::class, $listener);
    }

    public function test_audit_listener_subscribes_to_correct_events(): void
    {
        $listener = new LogMobileAuditEventListener();
        $events = $listener->subscribe();

        // Verify the listener subscribes to the expected events
        $this->assertIsArray($events);
        $this->assertArrayHasKey(MobileDeviceRegistered::class, $events);
        $this->assertArrayHasKey(BiometricAuthSucceeded::class, $events);
        $this->assertArrayHasKey(MobileSessionCreated::class, $events);
    }

    // =========================================================
    // Event Property Tests
    // =========================================================

    public function test_mobile_device_registered_event_has_correct_properties(): void
    {
        $now = Carbon::now();

        $event = new MobileDeviceRegistered(
            null, // tenantId
            '123', // userId
            'test-device-id', // deviceId
            'ios', // platform
            '1.0.0', // appVersion
            $now, // registeredAt
        );

        $this->assertEquals('123', $event->userId);
        $this->assertEquals('test-device-id', $event->deviceId);
        $this->assertEquals('ios', $event->platform);
        $this->assertEquals('1.0.0', $event->appVersion);
        $this->assertEquals($now, $event->registeredAt);
    }

    public function test_biometric_auth_succeeded_event_has_correct_properties(): void
    {
        $now = Carbon::now();

        $event = new BiometricAuthSucceeded(
            null, // tenantId
            $this->deviceId, // deviceId
            '456', // userId
            '192.168.1.1', // ipAddress
            $now, // authenticatedAt
        );

        $this->assertEquals($this->deviceId, $event->deviceId);
        $this->assertEquals('456', $event->userId);
        $this->assertEquals('192.168.1.1', $event->ipAddress);
        $this->assertEquals($now, $event->authenticatedAt);
    }

    public function test_mobile_session_created_event_has_correct_properties(): void
    {
        $createdAt = Carbon::now();
        $expiresAt = Carbon::now()->addHour();

        $event = new MobileSessionCreated(
            null, // tenantId
            'session-123', // sessionId
            $this->deviceId, // deviceId
            '789', // userId
            '192.168.1.1', // ipAddress
            $expiresAt, // expiresAt
            $createdAt, // createdAt
        );

        $this->assertEquals('session-123', $event->sessionId);
        $this->assertEquals($this->deviceId, $event->deviceId);
        $this->assertEquals('789', $event->userId);
        $this->assertEquals('192.168.1.1', $event->ipAddress);
        $this->assertEquals($expiresAt, $event->expiresAt);
        $this->assertEquals($createdAt, $event->createdAt);
    }

    public function test_mobile_device_blocked_event_has_correct_properties(): void
    {
        $now = Carbon::now();

        $event = new MobileDeviceBlocked(
            null, // tenantId
            $this->deviceId, // deviceId
            '123', // userId
            'Suspicious activity', // reason
            'admin-user', // blockedBy
            $now, // blockedAt
        );

        $this->assertEquals($this->deviceId, $event->deviceId);
        $this->assertEquals('123', $event->userId);
        $this->assertEquals('Suspicious activity', $event->reason);
        $this->assertEquals('admin-user', $event->blockedBy);
        $this->assertEquals($now, $event->blockedAt);
    }

    public function test_biometric_device_blocked_event_has_correct_properties(): void
    {
        $now = Carbon::now();
        $blockedUntil = Carbon::now()->addMinutes(30);

        $event = new BiometricDeviceBlocked(
            null, // tenantId
            $this->deviceId, // deviceId
            '456', // userId
            5, // failureCount
            $blockedUntil, // blockedUntil
            $now, // blockedAt
        );

        $this->assertEquals($this->deviceId, $event->deviceId);
        $this->assertEquals('456', $event->userId);
        $this->assertEquals(5, $event->failureCount);
        $this->assertEquals($blockedUntil, $event->blockedUntil);
        $this->assertEquals($now, $event->blockedAt);
    }

    public function test_biometric_auth_failed_event_has_correct_properties(): void
    {
        $now = Carbon::now();

        $event = new BiometricAuthFailed(
            null, // tenantId
            $this->deviceId, // deviceId
            '789', // userId
            'invalid_signature', // failureReason
            '192.168.1.1', // ipAddress
            3, // failureCount
            $now, // failedAt
        );

        $this->assertEquals($this->deviceId, $event->deviceId);
        $this->assertEquals('789', $event->userId);
        $this->assertEquals('invalid_signature', $event->failureReason);
        $this->assertEquals('192.168.1.1', $event->ipAddress);
        $this->assertEquals(3, $event->failureCount);
        $this->assertEquals($now, $event->failedAt);
    }
}
