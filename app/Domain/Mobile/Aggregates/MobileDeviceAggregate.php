<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Aggregates;

use App\Domain\Mobile\Events\BiometricAuthFailed;
use App\Domain\Mobile\Events\BiometricAuthSucceeded;
use App\Domain\Mobile\Events\BiometricDeviceBlocked;
use App\Domain\Mobile\Events\BiometricDisabled;
use App\Domain\Mobile\Events\BiometricEnabled;
use App\Domain\Mobile\Events\MobileDeviceBlocked;
use App\Domain\Mobile\Events\MobileDeviceRegistered;
use App\Domain\Mobile\Events\MobileDeviceTrusted;
use App\Domain\Mobile\Events\MobileSessionCreated;
use App\Domain\Mobile\Repositories\MobileEventRepository;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class MobileDeviceAggregate extends AggregateRoot
{
    protected string $deviceId = '';

    protected string $userId = '';

    protected string $platform = '';

    protected bool $isBlocked = false;

    protected bool $isTrusted = false;

    protected bool $biometricEnabled = false;

    protected int $biometricFailures = 0;

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(MobileEventRepository::class);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function registerDevice(
        string $userId,
        string $deviceId,
        string $platform,
        string $appVersion,
        ?string $tenantId = null,
        array $metadata = [],
    ): static {
        $this->recordThat(new MobileDeviceRegistered(
            tenantId: $tenantId ?? $this->getTenantId(),
            userId: $userId,
            deviceId: $deviceId,
            platform: $platform,
            appVersion: $appVersion,
            registeredAt: now(),
            metadata: $metadata,
        ));

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function blockDevice(
        string $reason,
        ?string $blockedBy = null,
        array $metadata = [],
    ): static {
        $this->recordThat(new MobileDeviceBlocked(
            tenantId: $this->getTenantId(),
            deviceId: $this->deviceId,
            userId: $this->userId,
            reason: $reason,
            blockedBy: $blockedBy,
            blockedAt: now(),
            metadata: $metadata,
        ));

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function trustDevice(
        ?string $trustedBy = null,
        array $metadata = [],
    ): static {
        $this->recordThat(new MobileDeviceTrusted(
            tenantId: $this->getTenantId(),
            deviceId: $this->deviceId,
            userId: $this->userId,
            trustedAt: now(),
            trustedBy: $trustedBy,
            metadata: $metadata,
        ));

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function enableBiometric(
        string $biometricType,
        array $metadata = [],
    ): static {
        $this->recordThat(new BiometricEnabled(
            tenantId: $this->getTenantId(),
            deviceId: $this->deviceId,
            userId: $this->userId,
            biometricType: $biometricType,
            enabledAt: now(),
            metadata: $metadata,
        ));

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function disableBiometric(
        string $reason,
        ?string $disabledBy = null,
        array $metadata = [],
    ): static {
        $this->recordThat(new BiometricDisabled(
            tenantId: $this->getTenantId(),
            deviceId: $this->deviceId,
            userId: $this->userId,
            reason: $reason,
            disabledBy: $disabledBy,
            disabledAt: now(),
            metadata: $metadata,
        ));

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function recordBiometricSuccess(
        ?string $ipAddress = null,
        array $metadata = [],
    ): static {
        $this->recordThat(new BiometricAuthSucceeded(
            tenantId: $this->getTenantId(),
            deviceId: $this->deviceId,
            userId: $this->userId,
            ipAddress: $ipAddress,
            authenticatedAt: now(),
            metadata: $metadata,
        ));

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function recordBiometricFailure(
        string $failureReason,
        ?string $ipAddress = null,
        array $metadata = [],
    ): static {
        $failureCount = $this->biometricFailures + 1;

        $this->recordThat(new BiometricAuthFailed(
            tenantId: $this->getTenantId(),
            deviceId: $this->deviceId,
            userId: $this->userId,
            failureReason: $failureReason,
            ipAddress: $ipAddress,
            failureCount: $failureCount,
            failedAt: now(),
            metadata: $metadata,
        ));

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function blockBiometric(
        int $failureCount,
        \Carbon\Carbon $blockedUntil,
        array $metadata = [],
    ): static {
        $this->recordThat(new BiometricDeviceBlocked(
            tenantId: $this->getTenantId(),
            deviceId: $this->deviceId,
            userId: $this->userId,
            failureCount: $failureCount,
            blockedUntil: $blockedUntil,
            blockedAt: now(),
            metadata: $metadata,
        ));

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createSession(
        string $sessionId,
        ?string $ipAddress,
        \Carbon\Carbon $expiresAt,
        array $metadata = [],
    ): static {
        $this->recordThat(new MobileSessionCreated(
            tenantId: $this->getTenantId(),
            sessionId: $sessionId,
            deviceId: $this->deviceId,
            userId: $this->userId,
            ipAddress: $ipAddress,
            expiresAt: $expiresAt,
            createdAt: now(),
            metadata: $metadata,
        ));

        return $this;
    }

    // Apply methods

    protected function applyMobileDeviceRegistered(MobileDeviceRegistered $event): void
    {
        $this->deviceId = $event->deviceId;
        $this->userId = $event->userId;
        $this->platform = $event->platform;
        $this->isBlocked = false;
        $this->isTrusted = false;
        $this->biometricEnabled = false;
        $this->biometricFailures = 0;
    }

    protected function applyMobileDeviceBlocked(MobileDeviceBlocked $event): void
    {
        $this->isBlocked = true;
    }

    protected function applyMobileDeviceTrusted(MobileDeviceTrusted $event): void
    {
        $this->isTrusted = true;
    }

    protected function applyBiometricEnabled(BiometricEnabled $event): void
    {
        $this->biometricEnabled = true;
        $this->biometricFailures = 0;
    }

    protected function applyBiometricDisabled(BiometricDisabled $event): void
    {
        $this->biometricEnabled = false;
    }

    protected function applyBiometricAuthSucceeded(BiometricAuthSucceeded $event): void
    {
        $this->biometricFailures = 0;
    }

    protected function applyBiometricAuthFailed(BiometricAuthFailed $event): void
    {
        $this->biometricFailures = $event->failureCount;
    }

    protected function applyBiometricDeviceBlocked(BiometricDeviceBlocked $event): void
    {
        $this->biometricFailures = 0;
    }

    protected function applyMobileSessionCreated(MobileSessionCreated $event): void
    {
        // Session state is managed externally
    }

    // Getters

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function isTrusted(): bool
    {
        return $this->isTrusted;
    }

    public function isBiometricEnabled(): bool
    {
        return $this->biometricEnabled;
    }

    public function getBiometricFailures(): int
    {
        return $this->biometricFailures;
    }

    private function getTenantId(): ?string
    {
        if (function_exists('tenant') && tenant()) {
            return (string) tenant()->getTenantKey();
        }

        return null;
    }
}
