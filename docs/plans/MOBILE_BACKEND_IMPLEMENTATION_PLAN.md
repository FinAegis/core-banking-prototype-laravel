# Mobile Backend Implementation Plan v2.2.0

**Version**: 2.2.0
**Author**: Architecture Team
**Date**: January 30, 2026
**Status**: **REVISED** - Security & Architecture Review Complete

---

## Executive Summary

This plan addresses critical gaps in the mobile backend infrastructure identified during code review, **plus security vulnerabilities and architecture issues identified in subsequent reviews**. The implementation focuses on enterprise-grade features required for a financial application: event-driven architecture, security hardening, background job processing, and API completeness.

---

## Review Findings Summary

### Security Review (CRITICAL)
| Issue | Severity | Status |
|-------|----------|--------|
| Device takeover vulnerability | **CRITICAL** | Must Fix |
| Biometric rate limiting insufficient | **CRITICAL** | Must Fix |
| FCM server key exposure | HIGH | Must Fix |
| Missing CORS/Origin validation | HIGH | Must Fix |
| No encryption of public keys at rest | MEDIUM | Should Fix |
| Push notification data injection | MEDIUM | Should Fix |

### Architecture Review (CRITICAL)
| Issue | Severity | Status |
|-------|----------|--------|
| Events missing Event Sourcing integration | **CRITICAL** | Must Fix |
| Missing tenant awareness in jobs/events | **CRITICAL** | Must Fix |
| API versioning not addressed | **CRITICAL** | Must Fix |
| Event loop risk not addressed | HIGH | Must Fix |

---

## REVISED Implementation Phases

## Phase 0: Security Critical Fixes (P0-SECURITY)
**Must complete before any other work**

### 0.1 Fix Device Takeover Vulnerability

**Current Code (VULNERABLE)**:
```php
// MobileDeviceService::registerDevice() - REASSIGNS device to attacker
if ($existingDevice && $existingDevice->user_id !== $user->id) {
    $existingDevice->disableBiometric();
    $existingDevice->update(['user_id' => $user->id]); // VULNERABILITY!
}
```

**Fixed Code**:
```php
// app/Domain/Mobile/Services/MobileDeviceService.php
public function registerDevice(User $user, array $data): MobileDevice
{
    $existingDevice = MobileDevice::where('device_id', $data['device_id'])->first();

    if ($existingDevice) {
        // SECURITY FIX: Reject reassignment, don't allow takeover
        if ($existingDevice->user_id !== $user->id) {
            throw new DeviceTakeoverAttemptException(
                'Device already registered to another user. ' .
                'Contact support if you believe this is an error.'
            );
        }
        // Same user - allow update
        return $this->updateDevice($existingDevice, $data);
    }

    // New device registration...
    return MobileDevice::create([...]);
}
```

### 0.2 Implement Per-Device Biometric Rate Limiting

**Migration**:
```php
// database/migrations/2026_01_31_000001_add_biometric_security_tracking.php
Schema::create('biometric_failures', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('mobile_device_id');
    $table->string('ip_address', 45)->nullable();
    $table->string('failure_reason', 100);
    $table->timestamps();

    $table->foreign('mobile_device_id')
        ->references('id')->on('mobile_devices')
        ->onDelete('cascade');

    $table->index(['mobile_device_id', 'created_at']);
    $table->index('created_at'); // For cleanup
});

Schema::table('mobile_devices', function (Blueprint $table) {
    $table->unsignedSmallInteger('biometric_failure_count')->default(0);
    $table->timestamp('biometric_blocked_until')->nullable()->index();
});
```

**Service Enhancement**:
```php
// app/Domain/Mobile/Services/BiometricAuthenticationService.php
public function verifyAndCreateSession(
    MobileDevice $device,
    string $challenge,
    string $signature,
    ?string $ipAddress = null
): ?array {
    // CHECK 1: Device temporarily blocked?
    if ($this->isDeviceBlocked($device)) {
        throw new BiometricBlockedException($device->biometric_blocked_until);
    }

    // CHECK 2: Per-device rate limit (stricter than global)
    $recentFailures = BiometricFailure::where('mobile_device_id', $device->id)
        ->where('created_at', '>', now()->subMinutes(10))
        ->count();

    if ($recentFailures >= config('mobile.security.max_biometric_failures', 3)) {
        $this->blockDevice($device);
        throw new BiometricBlockedException($device->biometric_blocked_until);
    }

    // CHECK 3: IP address validation (same network)
    $biometricChallenge = $this->findPendingChallenge($device, $challenge);
    if ($biometricChallenge && !$this->validateIpNetwork(
        $biometricChallenge->ip_address,
        $ipAddress
    )) {
        $this->recordFailure($device, 'ip_mismatch', $ipAddress);
        return null;
    }

    // Verify signature
    if (!$this->verifySignature($challenge, $signature, $device->biometric_public_key)) {
        $this->recordFailure($device, 'signature_invalid', $ipAddress);
        return null;
    }

    // SUCCESS: Reset failure count
    $device->update(['biometric_failure_count' => 0]);
    $biometricChallenge->markAsVerified();

    return $this->createSession($device, $ipAddress);
}

private function recordFailure(MobileDevice $device, string $reason, ?string $ip): void
{
    BiometricFailure::create([
        'mobile_device_id' => $device->id,
        'ip_address' => $ip,
        'failure_reason' => $reason,
    ]);

    $device->increment('biometric_failure_count');

    Log::warning('Biometric authentication failed', [
        'device_id' => $device->id,
        'user_id' => $device->user_id,
        'reason' => $reason,
        'failure_count' => $device->biometric_failure_count,
    ]);
}

private function blockDevice(MobileDevice $device): void
{
    $blockMinutes = config('mobile.security.biometric_block_minutes', 30);

    $device->update([
        'biometric_blocked_until' => now()->addMinutes($blockMinutes),
        'biometric_failure_count' => 0,
    ]);

    event(new BiometricDeviceBlocked($device));

    Log::critical('Device blocked due to biometric failures', [
        'device_id' => $device->id,
        'user_id' => $device->user_id,
        'blocked_until' => $device->biometric_blocked_until,
    ]);
}

private function validateIpNetwork(?string $challengeIp, ?string $verifyIp): bool
{
    if (!$challengeIp || !$verifyIp) {
        return true; // Skip if IPs not available
    }

    // Compare /24 networks for IPv4
    $challengeNetwork = implode('.', array_slice(explode('.', $challengeIp), 0, 3));
    $verifyNetwork = implode('.', array_slice(explode('.', $verifyIp), 0, 3));

    return $challengeNetwork === $verifyNetwork;
}
```

### 0.3 Add User-Agent Validation for Biometric Endpoints

```php
// app/Http/Controllers/Api/MobileController.php
public function getBiometricChallenge(Request $request): JsonResponse
{
    // Validate mobile client
    $userAgent = $request->header('User-Agent', '');
    $platform = $request->input('platform');

    if (!$this->isValidMobileClient($userAgent, $platform)) {
        return response()->json([
            'error' => [
                'code' => 'INVALID_CLIENT',
                'message' => 'Biometric challenges only available from mobile apps',
            ],
        ], 403);
    }

    // Per-device rate limit (stricter)
    $deviceId = $request->input('device_id');
    $key = 'biometric:challenge:' . hash('sha256', $deviceId);

    if (!RateLimiter::attempt($key, 3, fn() => true, 300)) {
        return response()->json([
            'error' => [
                'code' => 'RATE_LIMITED',
                'message' => 'Too many challenge requests. Try again later.',
            ],
        ], 429);
    }

    // Existing logic...
}

private function isValidMobileClient(string $userAgent, ?string $platform): bool
{
    $patterns = [
        'ios' => '/iPhone|iPad|iOS|CFNetwork/',
        'android' => '/Android|okhttp/',
    ];

    $pattern = $patterns[$platform] ?? '';
    return !empty($pattern) && preg_match($pattern, $userAgent);
}
```

---

## Phase 1: Event Sourcing Integration (P0-ARCHITECTURE)

### 1.1 Create Mobile Aggregate Root

```php
// app/Domain/Mobile/Aggregates/MobileDeviceAggregate.php
<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Aggregates;

use App\Domain\Mobile\Events\MobileDeviceRegistered;
use App\Domain\Mobile\Events\MobileDeviceBlocked;
use App\Domain\Mobile\Events\MobileDeviceTrusted;
use App\Domain\Mobile\Events\BiometricEnabled;
use App\Domain\Mobile\Events\BiometricDisabled;
use App\Domain\Mobile\Events\BiometricAuthSucceeded;
use App\Domain\Mobile\Events\BiometricAuthFailed;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class MobileDeviceAggregate extends AggregateRoot
{
    private string $deviceId;
    private int $userId;
    private string $platform;
    private bool $isBlocked = false;
    private bool $biometricEnabled = false;
    private int $biometricFailures = 0;

    public function registerDevice(
        int $userId,
        string $deviceId,
        string $platform,
        string $appVersion,
        ?string $tenantId = null
    ): static {
        $this->recordThat(new MobileDeviceRegistered(
            tenantId: $tenantId ?? tenant()->getTenantKey(),
            userId: $userId,
            deviceId: $deviceId,
            platform: $platform,
            appVersion: $appVersion,
            registeredAt: now(),
        ));

        return $this;
    }

    public function blockDevice(string $reason, ?string $blockedBy = null): static
    {
        $this->recordThat(new MobileDeviceBlocked(
            tenantId: tenant()->getTenantKey(),
            deviceId: $this->deviceId,
            userId: $this->userId,
            reason: $reason,
            blockedBy: $blockedBy,
            blockedAt: now(),
        ));

        return $this;
    }

    // Apply methods
    protected function applyMobileDeviceRegistered(MobileDeviceRegistered $event): void
    {
        $this->deviceId = $event->deviceId;
        $this->userId = $event->userId;
        $this->platform = $event->platform;
    }

    protected function applyMobileDeviceBlocked(MobileDeviceBlocked $event): void
    {
        $this->isBlocked = true;
    }
}
```

### 1.2 Event Sourced Events

```php
// app/Domain/Mobile/Events/MobileDeviceRegistered.php
<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Events;

use App\Broadcasting\TenantBroadcastEvent;
use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MobileDeviceRegistered extends ShouldBeStored
{
    use TenantBroadcastEvent;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $userId,
        public readonly string $deviceId,
        public readonly string $platform,
        public readonly string $appVersion,
        public readonly Carbon $registeredAt,
    ) {}

    protected function tenantChannelSuffix(): string
    {
        return 'mobile';
    }

    public function broadcastAs(): string
    {
        return 'device.registered';
    }
}
```

---

## Phase 2: Tenant-Aware Jobs (P0-ARCHITECTURE)

### 2.1 Base Job with Tenant Context

```php
// app/Domain/Mobile/Jobs/ProcessScheduledNotifications.php
<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Jobs;

use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\Shared\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledNotifications implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAwareJob;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct()
    {
        $this->onQueue(config('mobile.queue.name', 'mobile'));
        $this->initializeTenantAwareJob();
    }

    public function handle(PushNotificationService $service): void
    {
        $this->verifyTenantContext();

        $count = $service->processScheduledNotifications();

        Log::info('Processed scheduled mobile notifications', [
            'count' => $count,
            'tenant' => $this->dispatchedTenantId ?? 'global',
        ]);
    }

    public function tags(): array
    {
        return array_merge(
            ['mobile', 'notifications', 'scheduled'],
            $this->tenantTags()
        );
    }
}
```

---

## Phase 3: API Versioning & New Endpoints

### 3.1 Versioned Routes

```php
// routes/api.php - Mobile section
Route::prefix('mobile')->name('api.mobile.')->group(function () {
    // Public endpoints (no auth)
    Route::get('/config', [MobileController::class, 'getConfig'])->name('config');

    // Biometric auth (rate limited, user-agent validated)
    Route::prefix('auth/biometric')
        ->middleware(['throttle:10,1'])
        ->group(function () {
            Route::post('/challenge', [MobileController::class, 'getBiometricChallenge']);
            Route::post('/verify', [MobileController::class, 'verifyBiometric']);
        });

    // Protected endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        // Device management
        Route::prefix('devices')->group(function () {
            Route::get('/', [MobileController::class, 'listDevices']);
            Route::post('/', [MobileController::class, 'registerDevice']);
            Route::get('/{id}', [MobileController::class, 'getDevice']);
            Route::delete('/{id}', [MobileController::class, 'unregisterDevice']);
            Route::patch('/{id}/token', [MobileController::class, 'updatePushToken']);

            // NEW: Device security actions
            Route::post('/{id}/block', [MobileController::class, 'blockDevice']);
            Route::post('/{id}/unblock', [MobileController::class, 'unblockDevice'])
                ->middleware('password.confirm:api'); // Require password re-entry
            Route::post('/{id}/trust', [MobileController::class, 'trustDevice']);
        });

        // Biometric management (auth required)
        Route::post('/auth/biometric/enable', [MobileController::class, 'enableBiometric']);
        Route::delete('/auth/biometric/disable', [MobileController::class, 'disableBiometric']);

        // NEW: Session management
        Route::prefix('sessions')->group(function () {
            Route::get('/', [MobileController::class, 'listSessions']);
            Route::delete('/{id}', [MobileController::class, 'revokeSession']);
            Route::delete('/', [MobileController::class, 'revokeAllSessions']);
        });

        // NEW: Token refresh
        Route::post('/auth/refresh', [MobileController::class, 'refreshToken']);

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [MobileController::class, 'getNotifications']);
            Route::post('/{id}/read', [MobileController::class, 'markNotificationRead']);
            Route::post('/read-all', [MobileController::class, 'markAllNotificationsRead']);

            // NEW: Preferences
            Route::get('/preferences', [MobileController::class, 'getNotificationPreferences']);
            Route::put('/preferences', [MobileController::class, 'updateNotificationPreferences']);
        });
    });
});
```

### 3.2 Notification Preferences

```php
// database/migrations/2026_01_31_000002_create_notification_preferences.php
Schema::create('mobile_notification_preferences', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->uuid('mobile_device_id')->nullable();
    $table->string('notification_type', 50);
    $table->boolean('push_enabled')->default(true);
    $table->boolean('email_enabled')->default(false);
    $table->timestamps();

    $table->unique(['user_id', 'mobile_device_id', 'notification_type'], 'uniq_user_device_type');
    $table->index(['user_id', 'notification_type']);

    $table->foreign('mobile_device_id')
        ->references('id')->on('mobile_devices')
        ->nullOnDelete();
});
```

---

## Phase 4: Event Listeners for Cross-Domain Integration

### 4.1 Event Deduplication Service

```php
// app/Domain/Mobile/Services/EventDeduplicationService.php
<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Services;

use Illuminate\Support\Facades\Cache;

class EventDeduplicationService
{
    public function shouldProcess(string $eventId, int $userId, string $eventType): bool
    {
        $cacheKey = "mobile:dedupe:{$eventType}:{$eventId}:{$userId}";

        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->addMinutes(60));
        return true;
    }

    public function markProcessed(string $eventId, int $userId, string $eventType): void
    {
        $cacheKey = "mobile:dedupe:{$eventType}:{$eventId}:{$userId}";
        Cache::put($cacheKey, true, now()->addMinutes(60));
    }
}
```

### 4.2 Transaction Notification Listener

```php
// app/Domain/Mobile/Listeners/SendTransactionNotification.php
<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Listeners;

use App\Domain\Account\Events\TransactionCompleted;
use App\Domain\Mobile\Services\EventDeduplicationService;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTransactionNotification implements ShouldQueue
{
    public string $queue = 'mobile';

    public function __construct(
        private readonly PushNotificationService $pushService,
        private readonly EventDeduplicationService $deduplication,
    ) {}

    public function handle(TransactionCompleted $event): void
    {
        // Deduplicate
        if (!$this->deduplication->shouldProcess(
            $event->aggregateRootUuid(),
            $event->senderUserId,
            'transaction_notification'
        )) {
            return;
        }

        // Avoid loop: don't notify if event originated from mobile
        if (($event->metadata['origin'] ?? null) === 'mobile_app') {
            return;
        }

        // Send to recipient
        if ($event->recipientUserId) {
            $recipient = User::find($event->recipientUserId);
            if ($recipient) {
                $this->pushService->sendTransactionReceived(
                    $recipient,
                    $event->amount,
                    $event->currency,
                    $event->senderName ?? 'Someone',
                );
            }
        }

        // Send to sender
        $sender = User::find($event->senderUserId);
        if ($sender) {
            $this->pushService->sendTransactionSent(
                $sender,
                $event->amount,
                $event->currency,
                $event->recipientName ?? 'Recipient',
            );
        }
    }
}
```

---

## Phase 5: FormRequest Classes

```php
// app/Http/Requests/Mobile/RegisterDeviceRequest.php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'device_id' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9\-_:]+$/',
            ],
            'platform' => [
                'required',
                'in:ios,android',
            ],
            'app_version' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d+\.\d+(\.\d+)?$/',
            ],
            'push_token' => ['nullable', 'string', 'max:500'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'os_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_id.regex' => 'Device ID contains invalid characters.',
            'app_version.regex' => 'App version must follow semantic versioning.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('platform')) {
            $this->merge(['platform' => strtolower($this->platform)]);
        }
    }
}
```

---

## Phase 6: Console Commands

```php
// app/Console/Commands/Mobile/CleanupMobileDataCommand.php
<?php

declare(strict_types=1);

namespace App\Console\Commands\Mobile;

use App\Domain\Mobile\Services\BiometricAuthenticationService;
use App\Domain\Mobile\Services\MobileDeviceService;
use App\Domain\Mobile\Services\PushNotificationService;
use Illuminate\Console\Command;

class CleanupMobileDataCommand extends Command
{
    protected $signature = 'mobile:cleanup
                            {--challenges : Cleanup expired challenges}
                            {--devices : Cleanup stale devices}
                            {--notifications : Cleanup old notifications}
                            {--failures : Cleanup old failure records}
                            {--all : Run all cleanup tasks}';

    protected $description = 'Cleanup expired mobile data';

    public function handle(
        BiometricAuthenticationService $biometricService,
        MobileDeviceService $deviceService,
        PushNotificationService $notificationService,
    ): int {
        $runAll = $this->option('all');

        if ($runAll || $this->option('challenges')) {
            $count = $biometricService->cleanupExpiredChallenges();
            $this->info("Cleaned up {$count} expired challenges");
        }

        if ($runAll || $this->option('devices')) {
            $days = config('mobile.cleanup.stale_devices_days', 90);
            $count = $deviceService->cleanupStaleDevices($days);
            $this->info("Cleaned up {$count} stale devices");
        }

        if ($runAll || $this->option('notifications')) {
            $days = config('mobile.cleanup.old_notifications_days', 30);
            $count = $notificationService->cleanupOldNotifications($days);
            $this->info("Cleaned up {$count} old notifications");
        }

        if ($runAll || $this->option('failures')) {
            $count = $this->cleanupFailures();
            $this->info("Cleaned up {$count} old failure records");
        }

        return Command::SUCCESS;
    }

    private function cleanupFailures(): int
    {
        return \App\Domain\Mobile\Models\BiometricFailure::where(
            'created_at',
            '<',
            now()->subDays(config('mobile.cleanup.failures_days', 7))
        )->delete();
    }
}
```

---

## Configuration Updates

```php
// config/mobile.php - COMPLETE
return [
    // Version control
    'min_version' => env('MOBILE_MIN_VERSION', '1.0.0'),
    'latest_version' => env('MOBILE_LATEST_VERSION', '1.0.0'),
    'force_update' => env('MOBILE_FORCE_UPDATE', false),

    // Security
    'security' => [
        'max_biometric_failures' => env('MOBILE_MAX_BIOMETRIC_FAILURES', 3),
        'biometric_block_minutes' => env('MOBILE_BIOMETRIC_BLOCK_MINUTES', 30),
        'max_devices_per_user' => env('MOBILE_MAX_DEVICES_PER_USER', 5),
        'stale_device_days' => env('MOBILE_STALE_DEVICE_DAYS', 90),
    ],

    // Session
    'session' => [
        'duration_minutes' => env('MOBILE_SESSION_DURATION', 60),
        'trusted_duration_minutes' => env('MOBILE_TRUSTED_SESSION_DURATION', 480),
        'biometric_challenge_ttl' => env('MOBILE_CHALLENGE_TTL', 120), // Reduced from 300
    ],

    // Rate limiting
    'rate_limits' => [
        'biometric_challenge' => '3,5', // 3 per 5 minutes per device
        'biometric_verify' => '5,1',
        'device_block' => '10,1',
        'session_revoke' => '20,1',
    ],

    // Queue
    'queue' => [
        'name' => env('MOBILE_QUEUE_NAME', 'mobile'),
        'notifications_batch' => env('MOBILE_NOTIFICATION_BATCH', 100),
        'retry_attempts' => env('MOBILE_RETRY_ATTEMPTS', 3),
    ],

    // Cleanup
    'cleanup' => [
        'challenges_days' => env('MOBILE_CLEANUP_CHALLENGES', 1),
        'stale_devices_days' => env('MOBILE_CLEANUP_DEVICES', 90),
        'old_notifications_days' => env('MOBILE_CLEANUP_NOTIFICATIONS', 30),
        'failures_days' => env('MOBILE_CLEANUP_FAILURES', 7),
    ],

    // Features
    'features' => [
        'biometric' => env('MOBILE_FEATURE_BIOMETRIC', true),
        'push' => env('MOBILE_FEATURE_PUSH', true),
        'gcu_trading' => env('MOBILE_FEATURE_GCU_TRADING', true),
        'p2p_transfers' => env('MOBILE_FEATURE_P2P', true),
    ],
];
```

---

## File Structure (Final)

```
app/Domain/Mobile/
├── Aggregates/
│   └── MobileDeviceAggregate.php
├── Events/
│   ├── MobileDeviceRegistered.php
│   ├── MobileDeviceBlocked.php
│   ├── MobileDeviceTrusted.php
│   ├── BiometricEnabled.php
│   ├── BiometricDisabled.php
│   ├── BiometricAuthSucceeded.php
│   ├── BiometricAuthFailed.php
│   ├── BiometricDeviceBlocked.php
│   ├── MobileSessionCreated.php
│   └── PushNotificationSent.php
├── Exceptions/
│   ├── BiometricBlockedException.php
│   ├── DeviceTakeoverAttemptException.php
│   ├── DeviceNotFoundException.php
│   └── MaxDevicesExceededException.php
├── Jobs/
│   ├── ProcessScheduledNotifications.php
│   ├── RetryFailedNotifications.php
│   ├── CleanupExpiredChallenges.php
│   └── CleanupStaleDevices.php
├── Listeners/
│   ├── SendTransactionNotification.php
│   ├── SendBalanceAlert.php
│   └── SendSecurityLoginNotification.php
├── Models/
│   ├── BiometricFailure.php
│   └── MobileNotificationPreference.php
└── Services/
    ├── EventDeduplicationService.php
    └── MobileSessionService.php

app/Http/Requests/Mobile/
├── RegisterDeviceRequest.php
├── EnableBiometricRequest.php
├── VerifyBiometricRequest.php
└── UpdateNotificationPreferencesRequest.php

app/Console/Commands/Mobile/
├── CleanupMobileDataCommand.php
└── MobileStatsCommand.php

database/
├── factories/Mobile/
│   ├── MobileDeviceFactory.php
│   ├── BiometricChallengeFactory.php
│   └── MobilePushNotificationFactory.php
└── migrations/
    ├── 2026_01_31_000001_add_biometric_security_tracking.php
    └── 2026_01_31_000002_create_notification_preferences.php
```

---

## Implementation Checklist

### Phase 0: Security Critical ☐
- [ ] Fix device takeover vulnerability in MobileDeviceService
- [ ] Create BiometricFailure model and migration
- [ ] Implement per-device rate limiting
- [ ] Add IP network validation
- [ ] Add user-agent validation for biometric endpoints
- [ ] Create BiometricBlockedException
- [ ] Create DeviceTakeoverAttemptException
- [ ] Write security tests

### Phase 1: Event Sourcing ☐
- [ ] Create MobileDeviceAggregate
- [ ] Create 10 event classes with TenantBroadcastEvent
- [ ] Register events in EventServiceProvider
- [ ] Write aggregate tests

### Phase 2: Tenant-Aware Jobs ☐
- [ ] Create 4 job classes with TenantAwareJob trait
- [ ] Register in Kernel scheduler
- [ ] Configure Horizon queue

### Phase 3: API Completeness ☐
- [ ] Add device block/unblock/trust endpoints
- [ ] Add session management endpoints
- [ ] Add token refresh endpoint
- [ ] Add notification preferences endpoints
- [ ] Create notification_preferences migration

### Phase 4: Event Listeners ☐
- [ ] Create EventDeduplicationService
- [ ] Create SendTransactionNotification listener
- [ ] Create SendBalanceAlert listener
- [ ] Register listeners

### Phase 5: FormRequests ☐
- [ ] Create RegisterDeviceRequest
- [ ] Create EnableBiometricRequest
- [ ] Create VerifyBiometricRequest
- [ ] Create UpdateNotificationPreferencesRequest
- [ ] Update controller to use FormRequests

### Phase 6: Console Commands ☐
- [ ] Create CleanupMobileDataCommand
- [ ] Create MobileStatsCommand
- [ ] Register commands

---

## Acceptance Criteria

1. **Security**: Device takeover prevented, biometric brute force blocked
2. **Architecture**: Events are event-sourced with tenant awareness
3. **Jobs**: All jobs use TenantAwareJob trait
4. **API**: All endpoints versioned and backward compatible
5. **Tests**: 80%+ coverage, all security scenarios tested
6. **CI/CD**: All GitHub Actions pass

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking existing clients | Maintain backward compatibility, version new endpoints |
| Event loops | EventDeduplicationService, origin tracking |
| Biometric brute force | Per-device rate limiting, auto-block |
| Device takeover | Reject reassignment, require support intervention |
