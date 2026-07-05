# FinAegis Mobile Wallet Application - Technical Planning Document

**Version**: 2.2.0
**Repository**: `finaegis-mobile` (separate repository)
**Framework**: Expo (React Native) with EAS Build
**Platforms**: Android (primary), iOS

---

## Executive Summary

Build a production-grade mobile wallet application that connects to the FinAegis Core Banking API. The app provides standard digital wallet functionality including multi-asset balance management, top-ups, P2P transfers, and real-time notifications.

### Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Framework** | Expo (EAS) | Managed workflow, OTA updates, single codebase |
| **State Management** | Zustand + React Query | Lightweight, excellent caching |
| **Navigation** | Expo Router | File-based, familiar to web devs |
| **Styling** | NativeWind | Tailwind CSS for React Native |
| **Backend Connection** | REST + WebSocket | Existing API compatible |

---

## Part 1: Backend API Enhancements

The Core Banking platform already provides comprehensive APIs. The following enhancements are needed for optimal mobile support.

### 1.1 Mobile Device Management

**New Migration**: `database/migrations/2026_XX_XX_create_mobile_devices_table.php`

```php
Schema::create('mobile_devices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('device_id', 100)->unique();        // Device unique identifier
    $table->enum('platform', ['ios', 'android']);
    $table->string('push_token', 500)->nullable();      // FCM/APNS token
    $table->string('device_name', 100)->nullable();     // "John's iPhone"
    $table->string('device_model', 100)->nullable();    // "iPhone 15 Pro"
    $table->string('os_version', 50)->nullable();       // "iOS 17.2"
    $table->string('app_version', 20);                  // "1.0.0"
    $table->boolean('biometric_enabled')->default(false);
    $table->string('biometric_public_key', 1000)->nullable();  // For device-bound auth
    $table->timestamp('last_active_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'platform']);
    $table->index('push_token');
});
```

**New Model**: `app/Models/MobileDevice.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileDevice extends Model
{
    use HasUuids, UsesTenantConnection;

    protected $fillable = [
        'user_id',
        'device_id',
        'platform',
        'push_token',
        'device_name',
        'device_model',
        'os_version',
        'app_version',
        'biometric_enabled',
        'biometric_public_key',
        'last_active_at',
    ];

    protected $casts = [
        'biometric_enabled' => 'boolean',
        'last_active_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 1.2 Mobile API Endpoints

**New Controller**: `app/Http/Controllers/Api/MobileController.php`

```php
// Device Management
POST   /api/mobile/devices                     # Register device
GET    /api/mobile/devices                     # List user's devices
DELETE /api/mobile/devices/{device_id}         # Unregister device
PATCH  /api/mobile/devices/{device_id}/token   # Update push token

// Biometric Authentication
POST   /api/mobile/auth/biometric/enable       # Enable biometric for device
POST   /api/mobile/auth/biometric/verify       # Verify biometric signature
DELETE /api/mobile/auth/biometric/disable      # Disable biometric

// App Configuration
GET    /api/mobile/config                      # App configuration (feature flags, etc.)
```

**Request/Response Examples**:

```json
// POST /api/mobile/devices
Request:
{
    "device_id": "550e8400-e29b-41d4-a716-446655440000",
    "platform": "android",
    "push_token": "fMg5H...xyzABC",
    "device_name": "Pixel 8 Pro",
    "device_model": "Google Pixel 8 Pro",
    "os_version": "Android 14",
    "app_version": "1.0.0"
}

Response:
{
    "data": {
        "id": "uuid-here",
        "device_id": "550e8400-e29b-41d4-a716-446655440000",
        "biometric_enabled": false,
        "registered_at": "2026-01-30T12:00:00Z"
    }
}
```

```json
// POST /api/mobile/auth/biometric/enable
Request:
{
    "device_id": "550e8400-e29b-41d4-a716-446655440000",
    "public_key": "MFkwEwYHKoZIzj0CA..."  // ECDSA P-256 public key
}

Response:
{
    "data": {
        "enabled": true,
        "device_id": "550e8400-e29b-41d4-a716-446655440000"
    }
}
```

```json
// POST /api/mobile/auth/biometric/verify
Request:
{
    "device_id": "550e8400-e29b-41d4-a716-446655440000",
    "challenge": "random-challenge-from-server",
    "signature": "base64-encoded-signature"
}

Response:
{
    "data": {
        "token": "1|abc123...",
        "expires_at": "2026-01-31T12:00:00Z"
    }
}
```

### 1.3 Push Notification Service

**New Service**: `app/Services/PushNotificationService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MobileDevice;
use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class PushNotificationService
{
    private $messaging;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(
            config('services.firebase.credentials')
        );
        $this->messaging = $factory->createMessaging();
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $devices = $user->mobileDevices()
            ->whereNotNull('push_token')
            ->get();

        foreach ($devices as $device) {
            $this->sendToDevice($device, $title, $body, $data);
        }
    }

    public function sendToDevice(MobileDevice $device, string $title, string $body, array $data = []): void
    {
        $message = CloudMessage::withTarget('token', $device->push_token)
            ->withNotification([
                'title' => $title,
                'body' => $body,
            ])
            ->withData($data);

        try {
            $this->messaging->send($message);
        } catch (\Exception $e) {
            // Log and handle invalid tokens
            if (str_contains($e->getMessage(), 'not a valid FCM registration token')) {
                $device->update(['push_token' => null]);
            }
        }
    }
}
```

**Notification Types**:

| Type | Title | Body Template |
|------|-------|---------------|
| `transaction.received` | "Payment Received" | "You received {amount} {currency} from {sender}" |
| `transaction.sent` | "Payment Sent" | "You sent {amount} {currency} to {recipient}" |
| `transaction.failed` | "Transaction Failed" | "Your transfer of {amount} {currency} failed" |
| `balance.low` | "Low Balance Alert" | "Your {currency} balance is below {threshold}" |
| `kyc.approved` | "Verification Complete" | "Your identity verification has been approved" |
| `kyc.rejected` | "Verification Update" | "Your identity verification requires attention" |
| `security.new_login` | "New Login Detected" | "New login from {device_name} in {location}" |

### 1.4 WebSocket Broadcasting Activation

**Update**: `config/broadcasting.php`

```php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'host' => env('PUSHER_HOST', 'localhost'),
            'port' => env('PUSHER_PORT', 6001),
            'scheme' => env('PUSHER_SCHEME', 'http'),
            'useTLS' => env('PUSHER_SCHEME', 'http') === 'https',
        ],
    ],
],
```

**Environment Variables** (add to `.env.example`):

```env
# Push Notifications
FIREBASE_CREDENTIALS=storage/firebase-credentials.json
FIREBASE_PROJECT_ID=finaegis-mobile

# WebSocket Broadcasting (Soketi)
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=finaegis
PUSHER_APP_KEY=finaegis-key
PUSHER_APP_SECRET=finaegis-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
```

**Docker Compose for Soketi** (`docker-compose.websocket.yml`):

```yaml
version: '3.8'

services:
  soketi:
    image: quay.io/soketi/soketi:1.6-16-alpine
    ports:
      - "6001:6001"
      - "9601:9601"  # Metrics
    environment:
      SOKETI_DEBUG: '1'
      SOKETI_DEFAULT_APP_ID: '${PUSHER_APP_ID}'
      SOKETI_DEFAULT_APP_KEY: '${PUSHER_APP_KEY}'
      SOKETI_DEFAULT_APP_SECRET: '${PUSHER_APP_SECRET}'
      SOKETI_DEFAULT_APP_USER_AUTHENTICATION: 'true'
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://localhost:6001"]
      interval: 10s
      timeout: 5s
      retries: 3
```

---

## Part 2: Mobile App Technical Specification

### 2.1 Project Setup

```bash
# Create new Expo project
npx create-expo-app@latest finaegis-mobile --template tabs

cd finaegis-mobile

# Install dependencies
npx expo install expo-secure-store expo-local-authentication expo-notifications
npx expo install expo-camera expo-image-picker expo-document-picker
npm install zustand @tanstack/react-query axios
npm install nativewind tailwindcss
npm install react-hook-form zod @hookform/resolvers
npm install pusher-js
npm install victory-native react-native-svg
```

### 2.2 EAS Configuration

**eas.json**:

```json
{
  "cli": {
    "version": ">= 7.0.0"
  },
  "build": {
    "development": {
      "developmentClient": true,
      "distribution": "internal"
    },
    "preview": {
      "distribution": "internal",
      "android": {
        "buildType": "apk"
      }
    },
    "production": {
      "android": {
        "buildType": "app-bundle"
      }
    }
  },
  "submit": {
    "production": {
      "android": {
        "serviceAccountKeyPath": "./google-play-key.json",
        "track": "internal"
      }
    }
  }
}
```

**app.json** (key sections):

```json
{
  "expo": {
    "name": "FinAegis Wallet",
    "slug": "finaegis-wallet",
    "version": "1.0.0",
    "orientation": "portrait",
    "scheme": "finaegis",
    "plugins": [
      "expo-secure-store",
      "expo-local-authentication",
      [
        "expo-notifications",
        {
          "icon": "./assets/notification-icon.png",
          "color": "#1E40AF"
        }
      ],
      [
        "expo-camera",
        {
          "cameraPermission": "Allow FinAegis to access your camera for KYC verification"
        }
      ]
    ],
    "android": {
      "package": "org.finaegis.wallet",
      "adaptiveIcon": {
        "foregroundImage": "./assets/adaptive-icon.png",
        "backgroundColor": "#1E40AF"
      },
      "googleServicesFile": "./google-services.json"
    },
    "ios": {
      "bundleIdentifier": "org.finaegis.wallet",
      "supportsTablet": false,
      "infoPlist": {
        "NSFaceIDUsageDescription": "Use Face ID for quick and secure login"
      }
    }
  }
}
```

### 2.3 API Client

**services/api.ts**:

```typescript
import axios, { AxiosInstance } from 'axios';
import * as SecureStore from 'expo-secure-store';

const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL || 'https://api.zelta.app';

class ApiClient {
  private client: AxiosInstance;
  private token: string | null = null;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    this.client.interceptors.request.use(async (config) => {
      if (this.token) {
        config.headers.Authorization = `Bearer ${this.token}`;
      }
      return config;
    });

    this.client.interceptors.response.use(
      (response) => response,
      async (error) => {
        if (error.response?.status === 401) {
          await this.logout();
        }
        return Promise.reject(error);
      }
    );
  }

  async setToken(token: string) {
    this.token = token;
    await SecureStore.setItemAsync('auth_token', token);
  }

  async loadToken() {
    this.token = await SecureStore.getItemAsync('auth_token');
    return this.token;
  }

  async logout() {
    this.token = null;
    await SecureStore.deleteItemAsync('auth_token');
  }

  // Auth endpoints
  login(email: string, password: string) {
    return this.client.post('/api/auth/login', { email, password });
  }

  verify2FA(code: string) {
    return this.client.post('/api/auth/2fa/verify', { code });
  }

  // Account endpoints
  getAccounts() {
    return this.client.get('/api/accounts');
  }

  getAccountBalance(accountId: string) {
    return this.client.get(`/api/accounts/${accountId}/balances`);
  }

  getTransactions(accountId: string, page = 1) {
    return this.client.get(`/api/accounts/${accountId}/transactions`, {
      params: { page, per_page: 20 },
    });
  }

  // Transfer endpoints
  createTransfer(data: {
    from_account_uuid: string;
    to_account_uuid: string;
    amount: string;
    asset_code: string;
  }) {
    return this.client.post('/api/transfers', data);
  }

  // Device endpoints
  registerDevice(data: {
    device_id: string;
    platform: 'ios' | 'android';
    push_token?: string;
    device_name?: string;
    app_version: string;
  }) {
    return this.client.post('/api/mobile/devices', data);
  }

  updatePushToken(deviceId: string, pushToken: string) {
    return this.client.patch(`/api/mobile/devices/${deviceId}/token`, {
      push_token: pushToken,
    });
  }

  // Biometric endpoints
  enableBiometric(deviceId: string, publicKey: string) {
    return this.client.post('/api/mobile/auth/biometric/enable', {
      device_id: deviceId,
      public_key: publicKey,
    });
  }

  verifyBiometric(deviceId: string, challenge: string, signature: string) {
    return this.client.post('/api/mobile/auth/biometric/verify', {
      device_id: deviceId,
      challenge,
      signature,
    });
  }
}

export const api = new ApiClient();
```

### 2.4 Authentication Store

**stores/auth.ts**:

```typescript
import { create } from 'zustand';
import * as LocalAuthentication from 'expo-local-authentication';
import * as SecureStore from 'expo-secure-store';
import { api } from '../services/api';

interface User {
  id: number;
  email: string;
  name: string;
  kyc_level: string;
}

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  biometricAvailable: boolean;
  biometricEnabled: boolean;

  // Actions
  login: (email: string, password: string) => Promise<{ requires2FA: boolean }>;
  verify2FA: (code: string) => Promise<void>;
  loginWithBiometric: () => Promise<void>;
  logout: () => Promise<void>;
  checkBiometric: () => Promise<void>;
  enableBiometric: () => Promise<void>;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  isAuthenticated: false,
  isLoading: true,
  biometricAvailable: false,
  biometricEnabled: false,

  login: async (email, password) => {
    const response = await api.login(email, password);

    if (response.data.requires_2fa) {
      return { requires2FA: true };
    }

    await api.setToken(response.data.token);
    set({ user: response.data.user, isAuthenticated: true });
    return { requires2FA: false };
  },

  verify2FA: async (code) => {
    const response = await api.verify2FA(code);
    await api.setToken(response.data.token);
    set({ user: response.data.user, isAuthenticated: true });
  },

  loginWithBiometric: async () => {
    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: 'Login with biometrics',
      fallbackLabel: 'Use password',
    });

    if (!result.success) {
      throw new Error('Biometric authentication failed');
    }

    // Get stored device ID and verify with server
    const deviceId = await SecureStore.getItemAsync('device_id');
    // ... complete biometric verification flow
  },

  logout: async () => {
    await api.logout();
    set({ user: null, isAuthenticated: false });
  },

  checkBiometric: async () => {
    const compatible = await LocalAuthentication.hasHardwareAsync();
    const enrolled = await LocalAuthentication.isEnrolledAsync();
    const enabled = await SecureStore.getItemAsync('biometric_enabled') === 'true';

    set({
      biometricAvailable: compatible && enrolled,
      biometricEnabled: enabled,
    });
  },

  enableBiometric: async () => {
    // Implementation for enabling biometric
    await SecureStore.setItemAsync('biometric_enabled', 'true');
    set({ biometricEnabled: true });
  },
}));
```

### 2.5 Screen Examples

**app/(tabs)/index.tsx** (Home/Dashboard):

```tsx
import { View, Text, ScrollView, RefreshControl } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../services/api';
import { BalanceCard } from '../../components/BalanceCard';
import { RecentTransactions } from '../../components/RecentTransactions';
import { QuickActions } from '../../components/QuickActions';

export default function HomeScreen() {
  const { data: accounts, isLoading, refetch } = useQuery({
    queryKey: ['accounts'],
    queryFn: () => api.getAccounts(),
  });

  const primaryAccount = accounts?.data?.[0];

  return (
    <ScrollView
      className="flex-1 bg-gray-50"
      refreshControl={
        <RefreshControl refreshing={isLoading} onRefresh={refetch} />
      }
    >
      {primaryAccount && (
        <>
          <BalanceCard account={primaryAccount} />
          <QuickActions accountId={primaryAccount.uuid} />
          <RecentTransactions accountId={primaryAccount.uuid} />
        </>
      )}
    </ScrollView>
  );
}
```

**components/BalanceCard.tsx**:

```tsx
import { View, Text, TouchableOpacity } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { api } from '../services/api';
import { formatCurrency } from '../utils/formatters';

interface BalanceCardProps {
  account: { uuid: string; name: string };
}

export function BalanceCard({ account }: BalanceCardProps) {
  const { data: balances } = useQuery({
    queryKey: ['balances', account.uuid],
    queryFn: () => api.getAccountBalance(account.uuid),
  });

  const primaryBalance = balances?.data?.find(b => b.is_primary);

  return (
    <View className="mx-4 mt-4 p-6 bg-blue-600 rounded-2xl">
      <Text className="text-blue-100 text-sm">Total Balance</Text>
      <Text className="text-white text-4xl font-bold mt-1">
        {formatCurrency(primaryBalance?.balance || 0, primaryBalance?.asset_code)}
      </Text>

      <View className="flex-row mt-4">
        {balances?.data?.slice(0, 3).map((balance) => (
          <View key={balance.asset_code} className="mr-4">
            <Text className="text-blue-200 text-xs">{balance.asset_code}</Text>
            <Text className="text-white font-medium">
              {formatCurrency(balance.balance, balance.asset_code)}
            </Text>
          </View>
        ))}
      </View>
    </View>
  );
}
```

---

## Part 3: Implementation Timeline

### Phase 1: Backend Enhancements (Weeks 1-2)

| Task | Effort | Priority |
|------|--------|----------|
| Mobile device model & migration | 0.5 day | P0 |
| Mobile API controller | 1 day | P0 |
| Push notification service | 1 day | P0 |
| Biometric authentication flow | 1 day | P0 |
| Soketi configuration | 0.5 day | P1 |
| Event broadcasting wiring | 1 day | P1 |
| Tests for new endpoints | 1 day | P0 |

### Phase 2: Mobile App Foundation (Weeks 3-4)

| Task | Effort | Priority |
|------|--------|----------|
| Project setup & configuration | 0.5 day | P0 |
| Navigation structure | 0.5 day | P0 |
| API client implementation | 1 day | P0 |
| Authentication flow (login, 2FA) | 2 days | P0 |
| Biometric authentication | 1 day | P0 |
| Push notification setup | 1 day | P0 |
| Basic UI components | 2 days | P0 |

### Phase 3: Core Features (Weeks 5-8)

| Task | Effort | Priority |
|------|--------|----------|
| Dashboard/home screen | 1 day | P0 |
| Wallet & balance views | 2 days | P0 |
| Transaction history | 2 days | P0 |
| P2P transfers | 3 days | P0 |
| Top-up screens | 2 days | P0 |
| Receive/QR code | 1 day | P1 |
| Settings & profile | 1 day | P1 |
| KYC document upload | 2 days | P1 |

### Phase 4: Polish & Release (Weeks 9-10)

| Task | Effort | Priority |
|------|--------|----------|
| Error handling & edge cases | 2 days | P0 |
| Loading states & skeletons | 1 day | P1 |
| Offline support (basic) | 1 day | P2 |
| Analytics integration | 0.5 day | P1 |
| App store assets | 1 day | P0 |
| Beta testing | 3 days | P0 |
| Bug fixes | 2 days | P0 |

---

## Part 4: Security Considerations

### 4.1 Token Storage
- Use `expo-secure-store` for tokens (Keychain on iOS, EncryptedSharedPreferences on Android)
- Never store tokens in AsyncStorage

### 4.2 Biometric Flow
- Device-bound keypair stored in secure enclave
- Server verifies signature, not biometric result
- Fallback to password always available

### 4.3 Certificate Pinning
- Implement SSL pinning for production
- Use `expo-certificate-pinning` or native modules

### 4.4 Data Protection
- Clear sensitive data on logout
- Implement app lock after inactivity
- Mask sensitive data by default (tap to reveal)

---

## Part 5: Testing Strategy

### Unit Tests (Jest)
- API client functions
- Store actions
- Utility functions

### Component Tests (React Native Testing Library)
- Screen rendering
- User interactions
- Form validation

### E2E Tests (Detox)
- Login flow
- Transfer flow
- KYC upload flow

### Manual Testing Checklist
- [ ] Fresh install experience
- [ ] Login with 2FA
- [ ] Biometric setup and login
- [ ] Send transfer (various amounts)
- [ ] Receive push notification
- [ ] Background/foreground transitions
- [ ] Network offline handling
- [ ] Session expiry handling

---

## Appendix: API Endpoints Summary

### Existing Endpoints (Ready to Use)

| Category | Endpoints |
|----------|-----------|
| **Auth** | POST /api/auth/login, /register, /2fa/verify, /logout |
| **Accounts** | GET /api/accounts, GET /api/accounts/{id}/balances |
| **Transactions** | GET /api/accounts/{id}/transactions |
| **Transfers** | POST /api/transfers |
| **Exchange** | GET /api/exchange-rates/{from}/{to}, POST /api/exchange/convert |
| **GCU** | POST /api/v2/gcu/buy, /sell, GET /api/v2/gcu/quote |
| **KYC** | GET /api/compliance/kyc/status, POST /api/compliance/kyc/submit |

### New Endpoints (To Be Built)

| Category | Endpoints |
|----------|-----------|
| **Device** | POST /api/mobile/devices, DELETE /api/mobile/devices/{id} |
| **Biometric** | POST /api/mobile/auth/biometric/enable, /verify |
| **Config** | GET /api/mobile/config |

---

*Document Version: 1.0*
*Created: January 30, 2026*
*Author: FinAegis Development Team*
