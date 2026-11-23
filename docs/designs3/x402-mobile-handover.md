# x402 Protocol — Mobile App Developer Handover

> **For**: React Native / Expo mobile app team
> **Backend Version**: FinAegis v5.2.0 (upcoming)
> **Protocol**: x402 v2 (HTTP-native micropayments)
> **Date**: 2026-02-22
>
> **Status at v7.18.1**: This handover was written before v5.2.0 shipped and is kept as a record. Check three points against the current backend. Server-side signing is gone. The `/api/auth/sign-userop` endpoint was removed in v7.12.0, and the device signs through Privy. The spending limit routes take an agent id (`/api/v1/x402/spending-limits/{agentId}`), and there is no `/me` route. `/api/v1/ai/query` is only an example of a paid endpoint, and the list of real paid endpoints comes from `GET /api/v1/x402/endpoints`.

---

## Table of Contents

1. [Overview for Mobile](#1-overview-for-mobile)
2. [How x402 Works (Mobile Perspective)](#2-how-x402-works-mobile-perspective)
3. [HTTP Client Interceptor](#3-http-client-interceptor)
4. [Wallet Signature Service](#4-wallet-signature-service)
5. [UI Components](#5-ui-components)
6. [Agent Spending Limits](#6-agent-spending-limits)
7. [Micropayment Activity Feed](#7-micropayment-activity-feed)
8. [Backend API Reference](#8-backend-api-reference)
9. [Testing Guide](#9-testing-guide)
10. [Security Considerations](#10-security-considerations)

---

## 1. Overview for Mobile

The x402 protocol allows any HTTP endpoint to require payment before serving a response. When the mobile app calls a paid API, it receives an HTTP 402 status with payment instructions. The app then signs a USDC transfer authorization (without sending any on-chain transaction) and retries the request with the signed payment attached.

**Key points:**
- No gas fees for the user (the facilitator handles on-chain settlement)
- Payment is a cryptographic signature, not a transaction — instant and free to create
- Uses USDC (stablecoin) on Base L2 — cheap and fast settlement
- The user never leaves the app; payment happens transparently in HTTP headers

### What the Mobile App Needs to Do

1. **Intercept 402 responses** from any FinAegis API call
2. **Parse payment requirements** from the `PAYMENT-REQUIRED` header
3. **Sign an EIP-3009 authorization** using the user's wallet key
4. **Retry the request** with the signed payload in the `PAYMENT-SIGNATURE` header
5. **Show UI** for manual approval when needed (large amounts or first-time)
6. **Track micropayments** in the activity feed

---

## 2. How x402 Works (Mobile Perspective)

```
Mobile App                    FinAegis API                  Blockchain
    │                              │                            │
    │── GET /api/v1/ai/query ─────▶│                            │
    │                              │                            │
    │◀── 402 Payment Required ─────│                            │
    │    Header: PAYMENT-REQUIRED  │                            │
    │    (base64 JSON with price)  │                            │
    │                              │                            │
    │ [Check spending limit]       │                            │
    │ [Auto-sign or show modal]    │                            │
    │                              │                            │
    │── GET /api/v1/ai/query ─────▶│                            │
    │    Header: PAYMENT-SIGNATURE │                            │
    │    (base64 JSON with sig)    │── verify + settle ────────▶│
    │                              │◀── tx hash ────────────────│
    │                              │                            │
    │◀── 200 OK + data ───────────│                            │
    │    Header: PAYMENT-RESPONSE  │                            │
    │    (base64 JSON with tx)     │                            │
```

### Headers

| Header | Direction | When | Contains |
|--------|-----------|------|----------|
| `PAYMENT-REQUIRED` | Response (402) | No payment provided | Price, network, token, recipient |
| `PAYMENT-SIGNATURE` | Request | Retrying with payment | Signed authorization |
| `PAYMENT-RESPONSE` | Response (200) | Payment successful | Transaction hash, settlement proof |

All header values are **Base64-encoded JSON**.

---

## 3. HTTP Client Interceptor

### 3.1 Axios Interceptor Setup

Add a global response interceptor that catches 402 responses:

```typescript
// src/services/api/x402Interceptor.ts
import axios, { AxiosResponse, AxiosError, InternalAxiosRequestConfig } from 'axios';
import { X402WalletService } from '../wallet/x402WalletService';
import { X402SpendingLimitService } from '../wallet/x402SpendingLimitService';
import { X402PaymentModal } from '../../components/ui/X402PaymentModal';

interface PaymentRequired {
  x402Version: number;
  error?: string;
  resource: {
    url: string;
    description: string;
    mimeType: string;
  };
  accepts: PaymentRequirements[];
  extensions?: Record<string, unknown>;
}

interface PaymentRequirements {
  scheme: string;        // "exact"
  network: string;       // "eip155:8453"
  asset: string;         // USDC contract address
  amount: string;        // Atomic units (e.g., "1000" = $0.001 USDC)
  payTo: string;         // Recipient wallet address
  maxTimeoutSeconds: number;
  extra: Record<string, unknown>;  // EIP-712 domain info
}

export function setupX402Interceptor(apiClient: typeof axios) {
  apiClient.interceptors.response.use(
    (response: AxiosResponse) => response,
    async (error: AxiosError) => {
      if (error.response?.status !== 402) {
        return Promise.reject(error);
      }

      const originalRequest = error.config as InternalAxiosRequestConfig & { _x402Retry?: boolean };

      // Prevent infinite retry loops
      if (originalRequest._x402Retry) {
        return Promise.reject(error);
      }

      try {
        // 1. Decode PAYMENT-REQUIRED header
        const paymentRequiredHeader = error.response.headers['payment-required'];
        if (!paymentRequiredHeader) {
          return Promise.reject(error);
        }

        const paymentRequired: PaymentRequired = JSON.parse(
          atob(paymentRequiredHeader)
        );

        // 2. Select the first supported payment option
        const selected = selectPaymentOption(paymentRequired.accepts);
        if (!selected) {
          return Promise.reject(new Error('No supported payment method'));
        }

        // 3. Check if auto-pay is allowed
        const canAutoPay = await X402SpendingLimitService.canAutoPay(
          selected.amount,
          selected.network,
        );

        let approved = canAutoPay;

        // 4. If not auto-payable, show payment modal
        if (!canAutoPay) {
          approved = await X402PaymentModal.show({
            amount: selected.amount,
            asset: selected.asset,
            network: selected.network,
            description: paymentRequired.resource.description,
          });
        }

        if (!approved) {
          return Promise.reject(new Error('Payment declined by user'));
        }

        // 5. Sign the payment
        const paymentSignature = await X402WalletService.signPayment(
          paymentRequired,
          selected,
        );

        // 6. Retry the original request with payment header
        originalRequest._x402Retry = true;
        originalRequest.headers['PAYMENT-SIGNATURE'] = paymentSignature;

        const response = await apiClient.request(originalRequest);

        // 7. Record the payment in local history
        const paymentResponse = response.headers['payment-response'];
        if (paymentResponse) {
          await X402SpendingLimitService.recordPayment(
            selected.amount,
            selected.network,
            JSON.parse(atob(paymentResponse)),
          );
        }

        return response;
      } catch (paymentError) {
        return Promise.reject(paymentError);
      }
    },
  );
}

function selectPaymentOption(accepts: PaymentRequirements[]): PaymentRequirements | null {
  // Prefer Base mainnet, then Base Sepolia (testnet), then others
  const preferred = ['eip155:8453', 'eip155:84532', 'eip155:1'];

  for (const network of preferred) {
    const option = accepts.find(
      (a) => a.network === network && a.scheme === 'exact'
    );
    if (option) return option;
  }

  // Fallback to first EVM option
  return accepts.find((a) => a.network.startsWith('eip155:')) ?? null;
}
```

### 3.2 Integration with Existing API Client

```typescript
// src/services/api/client.ts
import axios from 'axios';
import { setupX402Interceptor } from './x402Interceptor';

const apiClient = axios.create({
  baseURL: process.env.EXPO_PUBLIC_API_URL,
  // ... existing config
});

// Add x402 interceptor AFTER auth interceptor
setupX402Interceptor(apiClient);

export default apiClient;
```

---

## 4. Wallet Signature Service

### 4.1 EIP-3009 Signing

```typescript
// src/services/wallet/x402WalletService.ts
import { ethers } from 'ethers';
import * as SecureStore from 'expo-secure-store';
import * as LocalAuthentication from 'expo-local-authentication';

interface SignedPaymentPayload {
  x402Version: number;
  resource: ResourceInfo;
  accepted: PaymentRequirements;
  payload: {
    signature: string;      // 0x-prefixed hex
    authorization: {
      from: string;
      to: string;
      value: string;
      validAfter: string;
      validBefore: string;
      nonce: string;        // 0x-prefixed 32-byte hex
    };
  };
}

export class X402WalletService {
  /**
   * Sign an x402 payment. Returns a base64-encoded PAYMENT-SIGNATURE header value.
   */
  static async signPayment(
    paymentRequired: PaymentRequired,
    selected: PaymentRequirements,
  ): Promise<string> {
    // 1. Get the user's wallet
    const wallet = await this.getWallet();

    // 2. Build EIP-712 typed data for TransferWithAuthorization
    const nonce = ethers.hexlify(ethers.randomBytes(32));
    const validAfter = '0';
    const validBefore = String(
      Math.floor(Date.now() / 1000) + selected.maxTimeoutSeconds
    );

    const domain = {
      name: (selected.extra?.name as string) ?? 'USD Coin',
      version: (selected.extra?.version as string) ?? '2',
      chainId: this.getChainId(selected.network),
      verifyingContract: selected.asset,
    };

    const types = {
      TransferWithAuthorization: [
        { name: 'from', type: 'address' },
        { name: 'to', type: 'address' },
        { name: 'value', type: 'uint256' },
        { name: 'validAfter', type: 'uint256' },
        { name: 'validBefore', type: 'uint256' },
        { name: 'nonce', type: 'bytes32' },
      ],
    };

    const message = {
      from: wallet.address,
      to: selected.payTo,
      value: selected.amount,
      validAfter,
      validBefore,
      nonce,
    };

    // 3. Sign with EIP-712
    const signature = await wallet.signTypedData(domain, types, message);

    // 4. Build the PaymentPayload
    const paymentPayload: SignedPaymentPayload = {
      x402Version: 2,
      resource: paymentRequired.resource,
      accepted: selected,
      payload: {
        signature,
        authorization: {
          from: wallet.address,
          to: selected.payTo,
          value: selected.amount,
          validAfter,
          validBefore,
          nonce,
        },
      },
    };

    // 5. Base64-encode for the HTTP header
    return btoa(JSON.stringify(paymentPayload));
  }

  /**
   * Get the user's wallet from secure storage.
   * Requires biometric authentication for key access.
   */
  private static async getWallet(): Promise<ethers.Wallet> {
    // Require biometric auth before accessing keys
    const authResult = await LocalAuthentication.authenticateAsync({
      promptMessage: 'Authenticate to authorize payment',
      fallbackLabel: 'Use passcode',
    });

    if (!authResult.success) {
      throw new Error('Biometric authentication required for payment');
    }

    const privateKey = await SecureStore.getItemAsync('wallet_private_key');
    if (!privateKey) {
      throw new Error('No wallet configured');
    }

    return new ethers.Wallet(privateKey);
  }

  /**
   * Extract chain ID from CAIP-2 network identifier.
   * "eip155:8453" → 8453
   */
  private static getChainId(network: string): number {
    const [, chainId] = network.split(':');
    return parseInt(chainId, 10);
  }

  /**
   * Format atomic USDC amount to human-readable USD string.
   * "1000" → "$0.001" (USDC has 6 decimals)
   */
  static formatUsdcAmount(atomicAmount: string): string {
    const amount = parseInt(atomicAmount, 10) / 1_000_000;
    return `$${amount.toFixed(amount < 0.01 ? 4 : 2)}`;
  }

  /**
   * Get the network display name from CAIP-2 identifier.
   */
  static getNetworkName(network: string): string {
    const names: Record<string, string> = {
      'eip155:8453': 'Base',
      'eip155:84532': 'Base Sepolia',
      'eip155:1': 'Ethereum',
      'eip155:11155111': 'Sepolia',
    };
    return names[network] ?? network;
  }
}
```

### 4.2 Secure Key Storage Notes

- Use `expo-secure-store` with biometric protection (`requireAuthentication: true`)
- For higher security, integrate with the existing `BiometricJWTService` backend:
  - The private key can be stored server-side (encrypted with biometric JWT)
  - Signing requests go through the backend's `UserOperationSigningService`
  - This keeps private keys off the device entirely

> Removed in v7.12.0. The backend no longer stores keys or signs for the user, because the sign-userop endpoint was removed with the move to non-custodial wallets. Keys stay with Privy and the device.

### 4.3 Hardware Wallet Support (Future)

If the user has a hardware wallet connected:
- Use `@ethersproject/hardware-wallets` or WalletConnect for Ledger/Trezor
- The EIP-712 signing prompt will appear on the hardware device
- No code change needed in the x402 flow — just swap the wallet/signer instance

---

## 5. UI Components

### 5.1 Payment Approval Modal

```typescript
// src/components/ui/X402PaymentModal.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import BottomSheet from '@gorhom/bottom-sheet';
import { X402WalletService } from '../../services/wallet/x402WalletService';

interface PaymentModalProps {
  amount: string;          // Atomic USDC units
  asset: string;           // Token contract address
  network: string;         // CAIP-2 network ID
  description: string;     // What the user is paying for
}

interface PaymentModalResult {
  approved: boolean;
}

// Singleton promise resolver for imperative usage
let resolvePayment: ((approved: boolean) => void) | null = null;

export class X402PaymentModal {
  /**
   * Show the payment modal imperatively. Returns true if approved.
   */
  static show(props: PaymentModalProps): Promise<boolean> {
    return new Promise((resolve) => {
      resolvePayment = resolve;
      // Trigger modal via global state/event
      X402PaymentModalState.show(props);
    });
  }
}

// React component for the modal
export function X402PaymentModalComponent() {
  const { visible, props, dismiss } = useX402PaymentModalState();

  if (!visible || !props) return null;

  const formattedAmount = X402WalletService.formatUsdcAmount(props.amount);
  const networkName = X402WalletService.getNetworkName(props.network);

  const handleApprove = async () => {
    resolvePayment?.(true);
    resolvePayment = null;
    dismiss();
  };

  const handleDecline = () => {
    resolvePayment?.(false);
    resolvePayment = null;
    dismiss();
  };

  return (
    <BottomSheet snapPoints={['35%']} onClose={handleDecline}>
      <View style={styles.container}>
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.title}>Payment Required</Text>
          <Text style={styles.subtitle}>{props.description}</Text>
        </View>

        {/* Amount */}
        <View style={styles.amountContainer}>
          <Text style={styles.amount}>{formattedAmount}</Text>
          <Text style={styles.token}>USDC on {networkName}</Text>
        </View>

        {/* Actions */}
        <View style={styles.actions}>
          <SwipeToPayButton onComplete={handleApprove} />
          <TouchableOpacity onPress={handleDecline}>
            <Text style={styles.cancelText}>Cancel</Text>
          </TouchableOpacity>
        </View>
      </View>
    </BottomSheet>
  );
}

// SwipeToPayButton: A swipe-to-confirm button that triggers biometric auth
function SwipeToPayButton({ onComplete }: { onComplete: () => void }) {
  // Implement a horizontal swipe gesture that:
  // 1. Shows "Swipe to Pay" with a slider thumb
  // 2. On complete swipe, triggers biometric authentication
  // 3. On biometric success, calls onComplete
  // Use react-native-gesture-handler for the swipe
  return (
    <View style={styles.swipeButton}>
      <Text>Swipe to Pay →</Text>
    </View>
  );
}
```

### 5.2 Design Specifications

**Payment Modal Layout:**

```
┌──────────────────────────────┐
│   Payment Required           │
│   "AI query endpoint"        │
│                              │
│        $0.01                 │
│     USDC on Base             │
│                              │
│   ╔══════════════════════╗   │
│   ║ → Swipe to Pay       ║   │
│   ╚══════════════════════╝   │
│                              │
│         Cancel               │
└──────────────────────────────┘
```

**Key UX decisions:**
- Use bottom sheet (not full modal) — less intrusive for micropayments
- Show human-readable USD amount, not atomic units
- "Swipe to Pay" for amounts under the auto-pay limit adds a friction layer
- Biometric auth triggers automatically on swipe completion
- For amounts above the approval threshold, show a confirmation dialog first

### 5.3 Inline Payment Badge (Optional)

For UI elements that trigger paid API calls, show a small price badge:

```typescript
// src/components/ui/X402PriceBadge.tsx
export function X402PriceBadge({ price }: { price: string }) {
  return (
    <View style={styles.badge}>
      <Text style={styles.badgeText}>{price} USDC</Text>
    </View>
  );
}
```

---

## 6. Agent Spending Limits

### 6.1 Local Spending Tracker

```typescript
// src/services/wallet/x402SpendingLimitService.ts
import AsyncStorage from '@react-native-async-storage/async-storage';

interface SpendingState {
  spentToday: number;       // Atomic USDC units spent today
  transactionCount: number;
  lastResetDate: string;    // ISO date string
  history: PaymentRecord[];
}

interface PaymentRecord {
  id: string;
  amount: string;
  network: string;
  endpoint: string;
  transactionHash?: string;
  timestamp: string;
}

interface SpendingLimits {
  dailyLimit: number;              // From backend settings
  perTransactionLimit: number;     // Max per single payment
  autoPayThreshold: number;        // Below this, auto-approve
}

export class X402SpendingLimitService {
  private static STORAGE_KEY = 'x402_spending_state';
  private static LIMITS_KEY = 'x402_spending_limits';

  /**
   * Check if a payment can be auto-approved without user interaction.
   */
  static async canAutoPay(
    amount: string,
    network: string,
  ): Promise<boolean> {
    const limits = await this.getLimits();
    const state = await this.getState();
    const amountNum = parseInt(amount, 10);

    // Reset daily counter if needed
    if (this.shouldResetDaily(state)) {
      await this.resetDaily();
      state.spentToday = 0;
    }

    return (
      amountNum <= limits.autoPayThreshold &&
      amountNum <= limits.perTransactionLimit &&
      state.spentToday + amountNum <= limits.dailyLimit
    );
  }

  /**
   * Record a completed payment and update spending state.
   */
  static async recordPayment(
    amount: string,
    network: string,
    settleResponse: any,
  ): Promise<void> {
    const state = await this.getState();
    const amountNum = parseInt(amount, 10);

    state.spentToday += amountNum;
    state.transactionCount += 1;
    state.history.unshift({
      id: settleResponse.transaction ?? crypto.randomUUID(),
      amount,
      network,
      endpoint: '', // populated from request context
      transactionHash: settleResponse.transaction,
      timestamp: new Date().toISOString(),
    });

    // Keep only last 100 records locally
    state.history = state.history.slice(0, 100);

    await AsyncStorage.setItem(this.STORAGE_KEY, JSON.stringify(state));
  }

  /**
   * Get current spending limits (synced from backend).
   */
  static async getLimits(): Promise<SpendingLimits> {
    const stored = await AsyncStorage.getItem(this.LIMITS_KEY);
    if (stored) {
      return JSON.parse(stored);
    }
    // Defaults (sync from backend on app startup)
    return {
      dailyLimit: 5_000_000,       // $5.00 USDC
      perTransactionLimit: 1_000_000, // $1.00 USDC
      autoPayThreshold: 100_000,     // $0.10 USDC
    };
  }

  /**
   * Sync spending limits from the backend.
   * Call this on app startup and when settings change.
   */
  static async syncLimitsFromBackend(): Promise<void> {
    try {
      const response = await apiClient.get('/api/v1/x402/spending-limits/me');
      await AsyncStorage.setItem(
        this.LIMITS_KEY,
        JSON.stringify(response.data),
      );
    } catch {
      // Use cached limits
    }
  }

  // ... helper methods: getState, shouldResetDaily, resetDaily
}
```

### 6.2 Settings UI

```typescript
// In src/flows/settings/security.tsx or similar

// Add a new section for x402 spending controls:

interface X402SpendingSettings {
  autoPayEnabled: boolean;          // Global toggle
  autoPayThreshold: number;         // Auto-approve below this (in cents)
  dailyLimit: number;               // Daily spending cap (in cents)
  requireBiometric: boolean;        // Always require biometric for payment
  allowedNetworks: string[];        // Whitelist of CAIP-2 networks
}

// Settings panel items:
// [Toggle] Enable automatic micropayments
// [Slider] Auto-pay limit: $0.01 — $1.00
// [Input]  Daily spending cap: $5.00
// [Toggle] Always require biometric confirmation
// [Multi]  Allowed networks: Base, Ethereum
```

---

## 7. Micropayment Activity Feed

### 7.1 Grouping Strategy

x402 micropayments happen frequently and should not clutter the main transaction feed. Group them intelligently:

```typescript
// src/services/x402/paymentGrouper.ts

interface PaymentGroup {
  date: string;           // "Today", "Yesterday", "Feb 21"
  totalAmount: string;    // Sum in human-readable USD
  count: number;          // Number of payments in group
  payments: PaymentRecord[];
  isExpanded: boolean;
}

export function groupMicropayments(payments: PaymentRecord[]): PaymentGroup[] {
  // Group by date
  const grouped = new Map<string, PaymentRecord[]>();

  for (const payment of payments) {
    const date = formatDate(payment.timestamp);
    if (!grouped.has(date)) {
      grouped.set(date, []);
    }
    grouped.get(date)!.push(payment);
  }

  return Array.from(grouped.entries()).map(([date, records]) => ({
    date,
    totalAmount: formatUsdcTotal(records),
    count: records.length,
    payments: records,
    isExpanded: false,
  }));
}
```

### 7.2 Activity Feed Integration

In the existing activity tab (`src/tabs/activity.tsx`), add an x402 section:

```typescript
// Collapsed view (default):
// ┌─────────────────────────────────┐
// │ ⚡ 12 micropayments today       │
// │    Total: $0.15 USDC            │
// │    Tap to expand ▼              │
// └─────────────────────────────────┘

// Expanded view:
// ┌─────────────────────────────────┐
// │ ⚡ 12 micropayments today       │
// │    Total: $0.15 USDC  ▲        │
// │                                 │
// │  10:42 AM  AI Query    $0.01   │
// │  10:41 AM  AI Query    $0.01   │
// │  10:38 AM  Weather     $0.001  │
// │  ...                           │
// └─────────────────────────────────┘
```

### 7.3 Detailed Payment View

Tapping a single payment shows:

```
┌──────────────────────────────┐
│  Payment Details             │
│                              │
│  Amount:    $0.01 USDC       │
│  Network:   Base             │
│  Endpoint:  /api/v1/ai/query │
│  Status:    Settled ✓        │
│  TX Hash:   0xabc1...def2    │
│  Time:      10:42 AM         │
│                              │
│  [View on BaseScan]          │
└──────────────────────────────┘
```

---

## 8. Backend API Reference

### 8.1 Spending Limits API

**GET `/api/v1/x402/spending-limits/me`**
```json
{
  "daily_limit": "5000000",
  "per_transaction_limit": "1000000",
  "auto_pay_threshold": "100000",
  "spent_today": "250000",
  "transaction_count_today": 12,
  "limit_resets_at": "2026-02-23T00:00:00Z"
}
```

**PUT `/api/v1/x402/spending-limits/me`**
```json
{
  "daily_limit": "10000000",
  "per_transaction_limit": "2000000",
  "auto_pay_threshold": "500000",
  "auto_pay_enabled": true
}
```

### 8.2 Payment History API

**GET `/api/v1/x402/payments?page=1&per_page=20`**
```json
{
  "data": [
    {
      "id": "uuid",
      "amount": "10000",
      "network": "eip155:8453",
      "asset": "USDC",
      "status": "settled",
      "endpoint_path": "/api/v1/ai/query",
      "transaction_hash": "0x...",
      "created_at": "2026-02-22T10:42:00Z",
      "settled_at": "2026-02-22T10:42:03Z"
    }
  ],
  "meta": { "total": 142, "current_page": 1 }
}
```

### 8.3 Payment Stats API

**GET `/api/v1/x402/payments/stats?period=day`**
```json
{
  "total_payments": 142,
  "total_revenue": "1420000",
  "total_revenue_usd": "$1.42",
  "average_payment": "10000",
  "average_payment_usd": "$0.01",
  "active_endpoints": 5,
  "period": "day"
}
```

### 8.4 x402 Response Headers (for any monetized endpoint)

When any API call returns 402, the response includes:

**Response Header: `PAYMENT-REQUIRED`** (Base64 decoded):
```json
{
  "x402Version": 2,
  "resource": {
    "url": "https://api.finaegis.com/api/v1/ai/query",
    "description": "AI transaction query",
    "mimeType": "application/json"
  },
  "accepts": [
    {
      "scheme": "exact",
      "network": "eip155:8453",
      "asset": "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913",
      "amount": "10000",
      "payTo": "0xFinAegisWalletAddress",
      "maxTimeoutSeconds": 60,
      "extra": {
        "name": "USD Coin",
        "version": "2"
      }
    }
  ]
}
```

**Request Header: `PAYMENT-SIGNATURE`** (Base64 decoded — what the app sends):
```json
{
  "x402Version": 2,
  "resource": {
    "url": "https://api.finaegis.com/api/v1/ai/query",
    "description": "AI transaction query",
    "mimeType": "application/json"
  },
  "accepted": {
    "scheme": "exact",
    "network": "eip155:8453",
    "asset": "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913",
    "amount": "10000",
    "payTo": "0xFinAegisWalletAddress",
    "maxTimeoutSeconds": 60,
    "extra": { "name": "USD Coin", "version": "2" }
  },
  "payload": {
    "signature": "0x1234...abcd",
    "authorization": {
      "from": "0xUserWalletAddress",
      "to": "0xFinAegisWalletAddress",
      "value": "10000",
      "validAfter": "0",
      "validBefore": "1740268800",
      "nonce": "0xdeadbeef..."
    }
  }
}
```

**Response Header: `PAYMENT-RESPONSE`** (Base64 decoded — returned on success):
```json
{
  "success": true,
  "payer": "0xUserWalletAddress",
  "transaction": "0xTransactionHash...",
  "network": "eip155:8453"
}
```

---

## 9. Testing Guide

### 9.1 Testnet Configuration

Use Base Sepolia testnet for development:

| Setting | Value |
|---------|-------|
| Network | `eip155:84532` (Base Sepolia) |
| USDC Address | `0x036CbD53842c5426634e7929541eC2318f3dCF7e` |
| Facilitator | `https://x402.org/facilitator` (testnet) |
| Faucet | [Base Sepolia USDC Faucet](https://faucet.circle.com/) |

### 9.2 Test Wallet Setup

1. Create a test wallet in the app (or use a hardcoded test key in dev builds)
2. Get testnet USDC from Circle faucet
3. Ensure the backend is configured with `X402_DEFAULT_NETWORK=eip155:84532`

### 9.3 Manual Test Flow

1. Call a monetized endpoint without payment → Expect 402
2. Parse the `PAYMENT-REQUIRED` header → Verify structure
3. Sign and retry → Expect 200 with `PAYMENT-RESPONSE`
4. Check that spending limit was updated
5. Exceed the auto-pay threshold → Expect modal to appear
6. Decline payment → Verify graceful failure

### 9.4 Mock Mode

For unit testing without blockchain interaction, the backend will expose a mock facilitator mode:

```
X402_FACILITATOR_URL=mock  // Uses in-memory mock facilitator
```

This always returns `{ isValid: true }` for verify and `{ success: true, transaction: "0xmock..." }` for settle.

> Not built. At v7.18.1 the only facilitator client is `HttpFacilitatorClient`, and `X402_FACILITATOR_URL=mock` is not recognised.

---

## 10. Security Considerations

### Key Storage
- **Never** store private keys in AsyncStorage (unencrypted)
- Use `expo-secure-store` with `requireAuthentication: true`
- For production, prefer server-side signing via `UserOperationSigningService` (removed in v7.12.0; the device signs through Privy)

### Payment Validation
- Always verify the `PAYMENT-REQUIRED` header comes from a trusted domain
- Check that `payTo` address matches expected FinAegis address
- Never auto-approve payments to unknown recipients

### Network Validation
- Only accept payment requirements for whitelisted networks
- Reject unknown CAIP-2 identifiers

### Amount Display
- Always show amounts in human-readable USD, not atomic units
- Double-check decimal conversion: USDC uses 6 decimals (not 18)
- `1000000` atomic = `$1.00` USD
- `1000` atomic = `$0.001` USD

### Rate Limiting
- Don't retry 402 more than once per request
- Implement exponential backoff if facilitator returns errors
- Track payment attempts to detect potential abuse

---

## Appendix: Key Types Reference

```typescript
// CAIP-2 Network Identifiers
type Network = 'eip155:8453' | 'eip155:84532' | 'eip155:1' | 'eip155:11155111';

// Human-readable network names
const NETWORK_NAMES: Record<string, string> = {
  'eip155:8453': 'Base',
  'eip155:84532': 'Base Sepolia (Testnet)',
  'eip155:1': 'Ethereum',
  'eip155:11155111': 'Sepolia (Testnet)',
};

// USDC contract addresses per network
const USDC_ADDRESSES: Record<string, string> = {
  'eip155:8453': '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
  'eip155:84532': '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
};

// USDC always has 6 decimals
const USDC_DECIMALS = 6;

// Convert atomic to USD: amount / (10 ^ USDC_DECIMALS)
// Convert USD to atomic: amount * (10 ^ USDC_DECIMALS)
```
