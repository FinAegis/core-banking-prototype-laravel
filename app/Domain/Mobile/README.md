# Mobile Domain

The Mobile domain provides complete backend infrastructure for mobile wallet applications, including device management, biometric authentication, push notifications, and real-time event streaming.

## Overview

This domain was introduced in v2.2.0 to support native iOS and Android mobile applications with enterprise-grade security features.

### Key Features

- **Device Management**: Registration, blocking, trust levels, multi-device support
- **Biometric Authentication**: ECDSA P-256 signature verification with challenge-response
- **Push Notifications**: Firebase Cloud Messaging integration with preference management
- **Session Management**: Device-bound sessions with automatic expiration
- **Event Sourcing**: Full audit trail via Spatie Event Sourcing
- **Multi-Tenancy**: Complete tenant isolation via `TenantAwareJob` trait

## Directory Structure

```
Mobile/
├── Aggregates/
│   └── MobileDeviceAggregate.php     # Event-sourced device aggregate
├── Events/
│   ├── BiometricAuthFailed.php       # Failed biometric attempt
│   ├── BiometricAuthSucceeded.php    # Successful biometric auth
│   ├── BiometricDeviceBlocked.php    # Device biometric locked
│   ├── BiometricDisabled.php         # Biometric disabled on device
│   ├── BiometricEnabled.php          # Biometric enabled on device
│   ├── MobileDeviceBlocked.php       # Device blocked
│   ├── MobileDeviceRegistered.php    # New device registered
│   ├── MobileDeviceTrusted.php       # Device marked trusted
│   ├── MobileSessionCreated.php      # New session created
│   └── PushNotificationSent.php      # Push notification dispatched
├── Jobs/
│   ├── CleanupExpiredChallenges.php  # Cleanup expired biometric challenges
│   ├── CleanupStaleDevices.php       # Remove inactive devices
│   ├── ProcessScheduledNotifications.php  # Send scheduled push notifications
│   └── RetryFailedNotifications.php  # Retry failed push notifications
├── Listeners/
│   ├── LogMobileAuditEventListener.php        # Audit logging for compliance
│   ├── SendSecurityAlertListener.php          # Security event notifications
│   └── SendTransactionPushNotificationListener.php  # Transaction notifications
├── Models/
│   ├── BiometricChallenge.php        # Challenge-response model
│   ├── MobileDevice.php              # Device registration model
│   ├── MobileDeviceSession.php       # Device-bound session model
│   ├── MobileNotificationPreference.php  # User notification preferences
│   └── MobilePushNotification.php    # Push notification queue model
└── Services/
    ├── BiometricAuthenticationService.php  # Biometric auth flow
    ├── MobileDeviceService.php             # Device CRUD operations
    ├── MobileSessionService.php            # Session management
    ├── NotificationPreferenceService.php   # Preference management
    └── PushNotificationService.php         # FCM integration
```

## Services

### MobileDeviceService

Handles device registration, blocking, and management.

```php
// Register a new device
$device = $deviceService->registerDevice($user, [
    'device_id' => 'unique-device-id',
    'platform' => 'ios',
    'app_version' => '1.0.0',
    'device_name' => 'iPhone 15 Pro',
]);

// Block a device
$deviceService->blockDevice($device, 'suspicious_activity');

// Get user's active devices
$devices = $deviceService->getUserDevices($user);
```

### BiometricAuthenticationService

Manages ECDSA P-256 biometric authentication flow.

```php
// Enable biometric for a device
$authService->enableBiometric($device, $publicKeyPem);

// Create a challenge
$challenge = $authService->createChallenge($device, $ipAddress);

// Verify signature and create session
$session = $authService->verifyAndCreateSession(
    $device,
    $challenge->challenge,
    $signature,
    $ipAddress
);
```

### PushNotificationService

Firebase Cloud Messaging integration.

```php
// Send notification to user
$pushService->sendToUser($user, 'alert', 'Title', 'Body', ['key' => 'value']);

// Send transaction notification
$pushService->sendTransactionReceived($user, '100.00', 'GCU', 'John Doe');
```

## API Endpoints

All endpoints require `auth:sanctum` middleware unless noted as public.

### Device Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/mobile/devices` | Register device |
| GET | `/api/mobile/devices` | List user devices |
| GET | `/api/mobile/devices/{id}` | Get device details |
| DELETE | `/api/mobile/devices/{id}` | Unregister device |
| PATCH | `/api/mobile/devices/{id}/token` | Update push token |
| POST | `/api/mobile/devices/{id}/block` | Block device |
| POST | `/api/mobile/devices/{id}/unblock` | Unblock device |
| POST | `/api/mobile/devices/{id}/trust` | Mark device trusted |

### Biometric Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/mobile/auth/biometric/enable` | Enable biometric |
| DELETE | `/api/mobile/auth/biometric/disable` | Disable biometric |
| POST | `/api/mobile/auth/biometric/challenge` | Request challenge (public) |
| POST | `/api/mobile/auth/biometric/verify` | Verify signature (public) |

### Session Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/mobile/sessions` | List active sessions |
| DELETE | `/api/mobile/sessions/{id}` | Revoke session |
| DELETE | `/api/mobile/sessions` | Revoke all sessions |
| POST | `/api/mobile/auth/refresh` | Refresh token |

### Notifications

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/mobile/notifications` | List notifications |
| POST | `/api/mobile/notifications/{id}/read` | Mark as read |
| POST | `/api/mobile/notifications/read-all` | Mark all read |
| GET | `/api/mobile/notifications/preferences` | Get preferences |
| PUT | `/api/mobile/notifications/preferences` | Update preferences |

## Configuration

Configuration is located in `config/mobile.php`:

```php
return [
    'min_app_version' => env('MOBILE_MIN_APP_VERSION', '1.0.0'),
    'latest_app_version' => env('MOBILE_LATEST_APP_VERSION', '1.0.0'),
    'force_update' => env('MOBILE_FORCE_UPDATE', false),

    'devices' => [
        'max_per_user' => env('MOBILE_MAX_DEVICES_PER_USER', 5),
        'session_duration_minutes' => 60,
        'trusted_session_duration_minutes' => 480,
    ],

    'biometric' => [
        'challenge_ttl_seconds' => 300,
        'max_failed_attempts' => 5,
    ],

    'push' => [
        'batch_size' => 500,
        'retry_attempts' => 3,
    ],
];
```

## Security Features

### Device Takeover Prevention

When a device ID is registered by a different user:
1. Existing biometric keys are invalidated
2. All active sessions are terminated
3. Device is marked as untrusted
4. Security event is logged

### Biometric Security

- ECDSA P-256 signatures verified server-side
- Challenges expire after 5 minutes
- Failed attempts tracked with automatic lockout (5 attempts = 30 min block)
- IP network validation for challenge responses

### Rate Limiting

- Biometric endpoints: 10 requests/minute
- Push notification creation: 100/minute
- Device registration: 5/minute

## Events

All domain events extend `ShouldBeStored` for event sourcing and implement `ShouldBroadcast` for real-time updates.

Events are broadcast on tenant-specific channels:
- `tenant.{tenantId}.mobile` - Device and biometric events

## Background Jobs

Jobs run on the `mobile` queue with tenant awareness:

| Job | Schedule | Description |
|-----|----------|-------------|
| `ProcessScheduledNotifications` | Every minute | Send pending notifications |
| `RetryFailedNotifications` | Every 5 minutes | Retry failed sends |
| `CleanupExpiredChallenges` | Every 5 minutes | Remove expired challenges |
| `CleanupStaleDevices` | Daily | Remove inactive devices (90 days) |

## Testing

```bash
# Run Mobile domain tests
./vendor/bin/pest tests/Unit/Domain/Mobile/
./vendor/bin/pest tests/Feature/Api/MobileControllerTest.php

# Run with coverage
./vendor/bin/pest tests/Domain/Mobile/ --coverage
```

## Related Documentation

- [Mobile App Planning](../../docs/06-DEVELOPMENT/MOBILE_APP_PLANNING.md)
- [Mobile Backend Implementation Plan](../../docs/plans/MOBILE_BACKEND_IMPLEMENTATION_PLAN.md)
