<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mobile\Aggregates;

use App\Domain\Mobile\Aggregates\MobileDeviceAggregate;
use App\Domain\Mobile\Events\BiometricAuthFailed;
use App\Domain\Mobile\Events\BiometricAuthSucceeded;
use App\Domain\Mobile\Events\BiometricDeviceBlocked;
use App\Domain\Mobile\Events\BiometricDisabled;
use App\Domain\Mobile\Events\BiometricEnabled;
use App\Domain\Mobile\Events\MobileDeviceBlocked;
use App\Domain\Mobile\Events\MobileDeviceRegistered;
use App\Domain\Mobile\Events\MobileDeviceTrusted;
use App\Domain\Mobile\Events\MobileSessionCreated;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class MobileDeviceAggregateTest extends DomainTestCase
{
    use RefreshDatabase;

    private string $deviceId;

    private string $userId;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceId = Str::uuid()->toString();
        $this->userId = Str::uuid()->toString();
        $this->freezeTime();
        $this->now = now();
    }

    #[Test]
    public function test_can_register_device(): void
    {
        MobileDeviceAggregate::fake()
            ->given([])
            ->when(function (MobileDeviceAggregate $aggregate) {
                $aggregate->registerDevice(
                    $this->userId,
                    $this->deviceId,
                    'ios',
                    '1.0.0',
                );
            })
            ->assertRecorded([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_can_block_device(): void
    {
        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) {
                $aggregate->blockDevice('suspicious_activity', 'admin-user');
            })
            ->assertRecorded([
                new MobileDeviceBlocked(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    reason: 'suspicious_activity',
                    blockedBy: 'admin-user',
                    blockedAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_can_trust_device(): void
    {
        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'android',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) {
                $aggregate->trustDevice('user-verified');
            })
            ->assertRecorded([
                new MobileDeviceTrusted(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    trustedAt: $this->now,
                    trustedBy: 'user-verified',
                ),
            ]);
    }

    #[Test]
    public function test_can_enable_biometric(): void
    {
        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) {
                $aggregate->enableBiometric('face_id');
            })
            ->assertRecorded([
                new BiometricEnabled(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    biometricType: 'face_id',
                    enabledAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_can_disable_biometric(): void
    {
        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'android',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
                new BiometricEnabled(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    biometricType: 'fingerprint',
                    enabledAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) {
                $aggregate->disableBiometric('user_request', 'self');
            })
            ->assertRecorded([
                new BiometricDisabled(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    reason: 'user_request',
                    disabledBy: 'self',
                    disabledAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_can_record_biometric_success(): void
    {
        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
                new BiometricEnabled(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    biometricType: 'face_id',
                    enabledAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) {
                $aggregate->recordBiometricSuccess('192.168.1.100');
            })
            ->assertRecorded([
                new BiometricAuthSucceeded(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    ipAddress: '192.168.1.100',
                    authenticatedAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_can_record_biometric_failure(): void
    {
        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'android',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
                new BiometricEnabled(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    biometricType: 'fingerprint',
                    enabledAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) {
                $aggregate->recordBiometricFailure('signature_invalid', '10.0.0.1');
            })
            ->assertRecorded([
                new BiometricAuthFailed(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    failureReason: 'signature_invalid',
                    ipAddress: '10.0.0.1',
                    failureCount: 1,
                    failedAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_failure_count_increments(): void
    {
        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
                new BiometricAuthFailed(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    failureReason: 'signature_invalid',
                    ipAddress: null,
                    failureCount: 1,
                    failedAt: $this->now,
                ),
                new BiometricAuthFailed(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    failureReason: 'signature_invalid',
                    ipAddress: null,
                    failureCount: 2,
                    failedAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) {
                $aggregate->recordBiometricFailure('signature_invalid');
            })
            ->assertRecorded([
                new BiometricAuthFailed(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    failureReason: 'signature_invalid',
                    ipAddress: null,
                    failureCount: 3,
                    failedAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_can_block_biometric(): void
    {
        $blockedUntil = $this->now->copy()->addMinutes(30);

        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) use ($blockedUntil) {
                $aggregate->blockBiometric(3, $blockedUntil);
            })
            ->assertRecorded([
                new BiometricDeviceBlocked(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    failureCount: 3,
                    blockedUntil: $blockedUntil,
                    blockedAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_can_create_session(): void
    {
        $sessionId = Str::uuid()->toString();
        $expiresAt = $this->now->copy()->addHours(1);

        MobileDeviceAggregate::fake()
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'android',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $aggregate) use ($sessionId, $expiresAt) {
                $aggregate->createSession($sessionId, '192.168.1.50', $expiresAt);
            })
            ->assertRecorded([
                new MobileSessionCreated(
                    tenantId: null,
                    sessionId: $sessionId,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    ipAddress: '192.168.1.50',
                    expiresAt: $expiresAt,
                    createdAt: $this->now,
                ),
            ]);
    }

    #[Test]
    public function test_biometric_success_resets_failure_count(): void
    {
        $aggregate = MobileDeviceAggregate::fake();

        $aggregate
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
                new BiometricAuthFailed(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    failureReason: 'signature_invalid',
                    ipAddress: null,
                    failureCount: 2,
                    failedAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $agg) {
                $agg->recordBiometricSuccess();
            });

        // After success, the aggregate's internal failure count should be 0
        /** @var MobileDeviceAggregate $root */
        $root = $aggregate->aggregateRoot();
        $this->assertEquals(0, $root->getBiometricFailures());
    }

    #[Test]
    public function test_aggregate_state_after_registration(): void
    {
        $aggregate = MobileDeviceAggregate::fake();

        $aggregate
            ->given([])
            ->when(function (MobileDeviceAggregate $agg) {
                $agg->registerDevice(
                    $this->userId,
                    $this->deviceId,
                    'ios',
                    '2.0.0',
                );
            });

        /** @var MobileDeviceAggregate $root */
        $root = $aggregate->aggregateRoot();

        $this->assertEquals($this->deviceId, $root->getDeviceId());
        $this->assertEquals($this->userId, $root->getUserId());
        $this->assertFalse($root->isBlocked());
        $this->assertFalse($root->isTrusted());
        $this->assertFalse($root->isBiometricEnabled());
        $this->assertEquals(0, $root->getBiometricFailures());
    }

    #[Test]
    public function test_aggregate_state_after_blocking(): void
    {
        $aggregate = MobileDeviceAggregate::fake();

        $aggregate
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'android',
                    appVersion: '1.5.0',
                    registeredAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $agg) {
                $agg->blockDevice('policy_violation');
            });

        /** @var MobileDeviceAggregate $root */
        $root = $aggregate->aggregateRoot();
        $this->assertTrue($root->isBlocked());
    }

    #[Test]
    public function test_aggregate_state_after_trusting(): void
    {
        $aggregate = MobileDeviceAggregate::fake();

        $aggregate
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $agg) {
                $agg->trustDevice();
            });

        /** @var MobileDeviceAggregate $root */
        $root = $aggregate->aggregateRoot();
        $this->assertTrue($root->isTrusted());
    }

    #[Test]
    public function test_biometric_enabled_state(): void
    {
        $aggregate = MobileDeviceAggregate::fake();

        $aggregate
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'ios',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $agg) {
                $agg->enableBiometric('face_id');
            });

        /** @var MobileDeviceAggregate $root */
        $root = $aggregate->aggregateRoot();
        $this->assertTrue($root->isBiometricEnabled());
    }

    #[Test]
    public function test_biometric_disabled_state(): void
    {
        $aggregate = MobileDeviceAggregate::fake();

        $aggregate
            ->given([
                new MobileDeviceRegistered(
                    tenantId: null,
                    userId: $this->userId,
                    deviceId: $this->deviceId,
                    platform: 'android',
                    appVersion: '1.0.0',
                    registeredAt: $this->now,
                ),
                new BiometricEnabled(
                    tenantId: null,
                    deviceId: $this->deviceId,
                    userId: $this->userId,
                    biometricType: 'fingerprint',
                    enabledAt: $this->now,
                ),
            ])
            ->when(function (MobileDeviceAggregate $agg) {
                $agg->disableBiometric('security_concern');
            });

        /** @var MobileDeviceAggregate $root */
        $root = $aggregate->aggregateRoot();
        $this->assertFalse($root->isBiometricEnabled());
    }
}
