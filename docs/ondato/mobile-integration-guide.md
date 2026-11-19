# Ondato KYC — Mobile Integration Guide

## Overview

FinAegis includes an integration adapter for the Ondato identity-verification API (no partnership or endorsement implied), using a mobile SDK flow:
1. **Backend** creates an identity verification session
2. **Mobile SDK** captures documents/selfie using the returned `identityVerificationId`
3. **Ondato** processes the verification
4. **Webhooks** notify the backend of status changes
5. **Backend** updates the user's KYC status

---

## API Endpoints

### 1. Start Verification Session

```
POST /api/compliance/kyc/ondato/start
Authorization: Bearer <access_token>
Content-Type: application/json
```

**Request body** (all fields optional):
```json
{
  "first_name": "John",
  "last_name": "Doe"
}
```

**Success response** (200):
```json
{
  "identity_verification_id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "verification_id": "9c1a2b3d-4e5f-6789-abcd-ef0123456789",
  "status": "pending"
}
```

**Error responses:**
- `400` — `{ "error": "KYC already approved" }` (user already verified)
- `401` — Unauthenticated
- `500` — `{ "error": "Failed to start Ondato verification" }`

### 2. Check Verification Status

```
GET /api/compliance/kyc/ondato/status/{verificationId}
Authorization: Bearer <access_token>
```

**Success response** (200):
```json
{
  "verification_id": "9c1a2b3d-4e5f-6789-abcd-ef0123456789",
  "status": "completed",
  "provider": "ondato",
  "confidence_score": 95.00,
  "failure_reason": null,
  "completed_at": "2026-02-25T14:30:00+00:00"
}
```

**Status values:** `pending`, `in_progress`, `completed`, `failed`, `expired`

**Error responses:**
- `401` — Unauthenticated
- `404` — Verification not found (wrong ID or belongs to another user)

---

## React Native SDK Integration

### Installation

```bash
npm install @ondato/ondato-sdk-react-native
# or
yarn add @ondato/ondato-sdk-react-native
```

### Usage Flow

```typescript
import { OndatoSdk, OndatoEnvironment } from '@ondato/ondato-sdk-react-native';

async function startKycVerification(accessToken: string) {
  // Step 1: Create session via your backend
  const response = await fetch(`${API_BASE_URL}/api/compliance/kyc/ondato/start`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${accessToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      first_name: user.firstName,
      last_name: user.lastName,
    }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to start verification');
  }

  const { identity_verification_id, verification_id } = await response.json();

  // Step 2: Initialize Ondato SDK with the identity verification ID
  try {
    const result = await OndatoSdk.startIdentityVerification({
      identityVerificationId: identity_verification_id,
      environment: __DEV__ ? OndatoEnvironment.SANDBOX : OndatoEnvironment.PRODUCTION,
      // Optional: customize appearance
      appearance: {
        primaryColor: '#1A73E8',
        // ... other appearance options
      },
    });

    // Step 3: SDK completed — poll for backend status
    return await pollVerificationStatus(accessToken, verification_id);
  } catch (sdkError) {
    // User cancelled or SDK error
    console.error('Ondato SDK error:', sdkError);
    throw sdkError;
  }
}
```

### Status Polling

```typescript
async function pollVerificationStatus(
  accessToken: string,
  verificationId: string,
  maxAttempts = 30,
  intervalMs = 2000
): Promise<VerificationResult> {
  for (let i = 0; i < maxAttempts; i++) {
    const response = await fetch(
      `${API_BASE_URL}/api/compliance/kyc/ondato/status/${verificationId}`,
      {
        headers: { 'Authorization': `Bearer ${accessToken}` },
      }
    );

    if (!response.ok) throw new Error('Status check failed');

    const result = await response.json();

    // Terminal states — stop polling
    if (['completed', 'failed', 'expired'].includes(result.status)) {
      return result;
    }

    // Still processing — wait and retry
    await new Promise(resolve => setTimeout(resolve, intervalMs));
  }

  throw new Error('Verification timed out');
}
```

**Recommended polling strategy:**
- Start polling after SDK `onComplete` callback
- Poll every 2 seconds
- Max 30 attempts (60 seconds)
- Show a "Processing..." spinner during polling
- Ondato typically processes within 5–30 seconds

---

## Status Flow

```
pending → in_progress → completed  (happy path)
pending → in_progress → failed     (document/selfie rejected)
pending → in_progress → expired    (user abandoned)
pending → expired                  (session timed out)
```

---

## Error Handling

| Scenario | What happens | Mobile action |
|----------|-------------|---------------|
| User already KYC approved | `POST /start` returns 400 | Show "Already verified" message |
| Ondato API unavailable | `POST /start` returns 500 | Show retry button |
| SDK cancelled by user | SDK throws cancellation error | Return to KYC prompt |
| Verification rejected | Status becomes `failed` with `failure_reason` | Show reason, offer retry |
| Verification expired | Status becomes `expired` | Offer to start new session |
| Network error during polling | Fetch throws | Retry polling with backoff |

### failure_reason examples:
- `"Document expired"`
- `"BLURRY_IMAGE, FACE_NOT_VISIBLE"`
- `"Verification rejected by Ondato"`

---

## Sandbox Testing

### Environment Configuration

The backend defaults to sandbox mode (`ONDATO_SANDBOX=true`). In sandbox:
- API calls go to `https://sandbox-kycapi.ondato.com`
- Webhook signature validation is relaxed (passthrough when no secret set)
- Use Ondato sandbox credentials for SDK initialization

### Test Scenarios

Use Ondato's sandbox test documents:
- **Approved flow:** Use a valid test document from Ondato's sandbox docs
- **Rejected flow:** Use an expired or invalid test document
- **Expired flow:** Start a session and wait for timeout

### Webhook Testing

Ondato sandbox sends real webhooks. Configure your webhook URL:
```
POST https://your-ngrok-url.ngrok.io/api/webhooks/ondato/identity-verification
POST https://your-ngrok-url.ngrok.io/api/webhooks/ondato/identification
```

---

## Backend Environment Variables

```env
ONDATO_APPLICATION_ID=your-app-id
ONDATO_SECRET=your-secret
ONDATO_SETUP_ID=your-setup-id
ONDATO_SANDBOX=true
ONDATO_WEBHOOK_SECRET=your-webhook-secret
ONDATO_KYC_API_URL=https://sandbox-kycapi.ondato.com
ONDATO_VERIFID_API_URL=https://verifid.ondato.com
```

Production URLs (set `ONDATO_SANDBOX=false`):
```env
ONDATO_KYC_API_URL=https://kycapi.ondato.com
ONDATO_VERIFID_API_URL=https://verifid.ondato.com
```
