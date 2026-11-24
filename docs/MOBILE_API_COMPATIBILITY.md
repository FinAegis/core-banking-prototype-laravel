# Mobile App API Compatibility Report

**Audited**: 2026-03-28
**Backend version at audit**: v7.0.0 (commit `91505783`)
**Routes re-checked**: 2026-11-23 against v7.18.0 (v7.18.1 changes no routes)
**Mobile app**: `finaegis-mobile` (Expo/React Native)
**Base URL**: `https://zelta.app` (configurable via `EXPO_PUBLIC_API_URL`)

This report compares the endpoints that the Zelta mobile app calls with the routes that the backend registers. The first audit ran on 2026-03-28 against backend v7.0.0. The backend routes were checked again on 2026-11-23 against v7.18.0. The tables below still show the March audit, and the status column marks every endpoint that has changed since then. The mobile source code was not audited again, so the mobile service names are the ones found in March.

---

## Changes since the audit (checked at v7.18.0)

### Removed endpoints

These endpoints no longer exist. A call to any of them returns 404.

| Endpoint | Method | Removed in | What to use instead |
|----------|--------|------------|---------------------|
| `/api/auth/sign-userop` | POST | v7.12.0 | Nothing. The device signs every transaction through Privy, and the backend no longer holds signing material. |
| `/api/v1/wallet/transactions/send` | POST | v7.12.0 | `POST /api/v1/wallet/transactions/prepare`, then `POST /api/v1/wallet/transactions/submit`. |
| `/api/v1/wallet/recovery-shard-backup` | GET, POST, DELETE | v7.12.0 | Nothing. The custodial recovery shard backup was removed with the move to non-custodial wallets. |
| `/api/v1/wallet/recovery-shard-backup/retrieve` | GET | v7.12.0 | Nothing, for the same reason. |
| `/api/v1/rewards/quests/{id}/complete` | POST | v7.13.0 | Nothing. Quests now complete automatically when the underlying action happens, so the app cannot award points itself. |

### Wallet send contract (v7.12.0 and later)

Wallet send is non-custodial. Privy holds the keys and the device signs every transaction.

1. `POST /api/v1/wallet/transactions/quote` returns a quote. Its `data.quote_id` goes into the next step.
2. `POST /api/v1/wallet/transactions/prepare` returns the unsigned payload in `data.payload` and the id of the send in `data.intentId`.
3. The device signs the payload and sends the signature to `POST /api/v1/wallet/transactions/submit`, with that id in the `intent_id` field.
4. `GET /api/v1/wallet/transactions/by-intent/{intentId}` reports the state of the send.

The request bodies use snake_case `quote_id` and `intent_id`, although the prepare response still names the id `intentId`. The older camelCase `quoteId` and `intentId` are still accepted for older app builds. `Idempotency-Key` is an HTTP header, not a body field. A repeated prepare with the same key and the same body returns the same intent, and the same key with a different body returns `409 IDEMPOTENCY_CONFLICT`.

Since v7.18.0, a send whose outcome is unclear after a network timeout or a server error stays `submitted` until the chain settles it. The app should keep polling instead of showing the send as failed. A second submit for the same intent while the first is still running returns `409 SUBMIT_IN_PROGRESS`, and the app should poll the intent instead of retrying.

Sends on Ethereum mainnet are disabled by default, because `WALLET_SEND_EVM_NETWORKS` defaults to `polygon,base,arbitrum`. A send on a disabled network returns `422 NETWORK_DISABLED`. Sponsored sends are capped per user and across the platform each day. When a cap is reached the backend returns `429` with `SEND_DAILY_LIMIT_REACHED` or `SEND_TEMPORARILY_UNAVAILABLE`. The `network` value is case-sensitive and must match the value returned by the quote (`SOLANA`, `TRON`, `polygon`, `base`, `arbitrum`, `ethereum`).

### Newer mobile endpoints

The app uses these endpoints, which were added after the audit. They are listed here so the mobile team can find them. This report does not audit them.

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/auth/privy-login` | POST | Exchanges a Privy token for a backend token. |
| `/api/v1/wallet/addresses` | POST | Registers the wallet addresses that the device controls. |
| `/api/v1/wallet/transactions/prepare` | POST | Builds the unsigned send payload. |
| `/api/v1/wallet/transactions/submit` | POST | Accepts the signed payload and broadcasts it. |
| `/api/v1/wallet/transactions/by-intent/{intentId}` | GET | Returns the state of one send. |
| `/api/v1/notifications/register-device` | POST, DELETE `{id}` | Registers and removes a push notification device. |
| `/api/v1/subscription/iap/verify` | POST | Verifies an App Store or Google Play purchase. |
| `/api/v1/user/bridge-setup-status` | GET | Reports the bank transfer setup state. |
| `/api/v1/user/bridge-kyc-link` | POST | Returns the identity check link for bank transfers. |
| `/api/v1/privacy/wallet/register` | POST | Registers the public RAILGUN address that the device holds. |
| `/api/v1/privacy/engine-config` | GET | Returns the settings the on-device RAILGUN engine starts with. |
| `/api/v1/cards/issued` and the routes under it | GET, POST | Virtual cards from v7.18.0. In production only users on the pilot list get a card. |

The privacy endpoints are described in [RAILGUN_MOBILE_INTEGRATION.md](RAILGUN_MOBILE_INTEGRATION.md). The card, identity check and bank transfer endpoints are described in [BACKEND_HANDOVER_CARDS_KYC_RAMP.md](BACKEND_HANDOVER_CARDS_KYC_RAMP.md) and [FINCARD_MOBILE_INTEGRATION.md](FINCARD_MOBILE_INTEGRATION.md).

---

## Summary at the March audit

| Category | Count |
|----------|-------|
| Compatible Endpoints | 97 |
| Potentially Breaking | 5 |
| Missing from Backend | 1 |
| **Total endpoints audited** | **103** |

---

## Compatible Endpoints (97)

### Authentication (12)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/auth/login` | POST | `authService.login` | OK |
| `/api/auth/register` | POST | `authService.register` | OK |
| `/api/auth/user` | GET | `authService.getCurrentUser` | OK |
| `/api/auth/logout` | POST | `authService.logout` | OK |
| `/api/auth/refresh` | POST | `authService.refreshTokens` / token interceptor | OK |
| `/api/auth/delete-account` | POST | `authService.deleteAccount` | OK |
| `/api/auth/sign-userop` | POST | `authService.signUserOp` | Removed in v7.12.0 |
| `/api/v1/auth/passkey/challenge` | POST | `authService.getPasskeyChallenge` | OK |
| `/api/v1/auth/passkey/authenticate` | POST | `authService.verifyPasskey` | OK |
| `/api/v1/auth/passkey/register` | POST | `authService.registerPasskey` | OK |
| `/api/v1/auth/passkey/register-challenge` | POST | `authService.getRegistrationChallenge` | OK |
| `/broadcasting/auth` | POST | `wsService` (Pusher authorizer) | OK |

### Wallet (14)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/wallet/tokens` | GET | `walletService.getTokens` | OK |
| `/api/v1/wallet/balances` | GET | `walletService.getBalances` | OK |
| `/api/v1/wallet/state` | GET | `walletService.getWalletState` | OK |
| `/api/v1/wallet/addresses` | GET | `walletService.getAddresses` | OK |
| `/api/v1/wallet/transactions` | GET | `walletService.getTransactions` | OK |
| `/api/v1/wallet/transactions/{id}` | GET | `walletService.getTransaction` | OK |
| `/api/v1/wallet/transactions/send` | POST | `walletService.sendTransaction` | Removed in v7.12.0, use prepare and submit |
| `/api/v1/wallet/transactions/quote` | POST | `walletService.getTransactionQuote` | OK |
| `/api/v1/wallet/validate-address` | GET | `walletService.validateAddress` | OK |
| `/api/v1/wallet/resolve-name` | POST | `walletService.resolveName` | OK |
| `/api/v1/wallet/quote` | POST | `walletService.getQuote` | OK |
| `/api/v1/wallet/recent-recipients` | GET | `walletService.getRecentRecipients` | OK |
| `/api/v1/wallet/recovery-shard-backup` | POST | `recoveryBackupService.createOrUpdate` | Removed in v7.12.0 |
| `/api/v1/wallet/recovery-shard-backup` | GET | `recoveryBackupService.list` | Removed in v7.12.0 |

### Wallet (Recovery Shard - continued) (2)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/wallet/recovery-shard-backup` | DELETE | `recoveryBackupService.remove` | Removed in v7.12.0 |
| `/api/v1/wallet/recovery-shard-backup/retrieve` | GET | `recoveryBackupService.retrieve` | Removed in v7.12.0 |

### Payment Intents (4)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/payments/intents` | POST | `paymentsService.createIntent` | OK |
| `/api/v1/payments/intents/{intentId}` | GET | `paymentsService.getIntentStatus` | OK |
| `/api/v1/payments/intents/{intentId}/submit` | POST | `paymentsService.submitIntent` | OK |
| `/api/v1/payments/intents/{intentId}/cancel` | POST | `paymentsService.cancelIntent` | OK |

### Activity & Receipts (3)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/activity` | GET | `paymentsService.getActivity` | OK |
| `/api/v1/transactions/{txId}/receipt` | GET | `paymentsService.getReceipt` | OK |
| `/api/v1/transactions/{txId}/receipt` | POST | `walletService.generateReceipt` | OK |

### Network Status (1)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/networks/{network}/status` | GET | `paymentsService.getNetworkStatus` | OK |

### Cards (8)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/cards` | GET | `cardService.getCards` | OK |
| `/api/v1/cards` | POST | `cardService.createCard` | OK |
| `/api/v1/cards/{cardId}` | GET | `cardService.getCard` | OK |
| `/api/v1/cards/{cardId}` | DELETE | `cardService.cancelCard` | OK |
| `/api/v1/cards/{cardId}/transactions` | GET | `cardService.getCardTransactions` | OK |
| `/api/v1/cards/{cardId}/freeze` | POST | `cardService.freezeCard` | OK |
| `/api/v1/cards/{cardId}/freeze` | DELETE | `cardService.unfreezeCard` | OK |
| `/api/v1/cards/provision` | POST | `cardService.provisionCard` | OK |

### Rewards (5)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/rewards/profile` | GET | `rewardsService.getProfile` | OK |
| `/api/v1/rewards/quests` | GET | `rewardsService.getQuests` | OK |
| `/api/v1/rewards/quests/{id}/complete` | POST | `rewardsService.completeQuest` | Removed in v7.13.0 |
| `/api/v1/rewards/shop` | GET | `rewardsService.getShopItems` | OK |
| `/api/v1/rewards/shop/{id}/redeem` | POST | `rewardsService.redeemItem` | OK |

### Privacy (17)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/privacy/balances` | GET | `privacyService.getShieldedBalances` | OK |
| `/api/v1/privacy/total-balance` | GET | `privacyService.getTotalShieldedBalance` | OK |
| `/api/v1/privacy/transactions` | GET | `privacyService.getPrivacyTransactions` | OK |
| `/api/v1/privacy/merkle-root` | GET | `privacyService.getMerkleRoot` | OK |
| `/api/v1/privacy/merkle-path` | POST | `privacyService.getMerklePath` | OK |
| `/api/v1/privacy/shield` | POST | `privacyService.shield` | OK |
| `/api/v1/privacy/unshield` | POST | `privacyService.unshield` | OK |
| `/api/v1/privacy/transfer` | POST | `privacyService.privateTransfer` | OK |
| `/api/v1/privacy/viewing-key` | GET | `privacyService.getViewingKey` | OK |
| `/api/v1/privacy/proof-of-innocence` | POST | `privacyService.generateProofOfInnocence` | OK |
| `/api/v1/privacy/proof-of-innocence/{proofId}/verify` | GET | `privacyService.verifyProofOfInnocence` | OK |
| `/api/v1/privacy/srs-url` | GET | `privacyService.getSRSDownloadUrl` | OK |
| `/api/v1/privacy/srs-status` | GET | `privacyService.checkSRSStatus` | OK |
| `/api/v1/privacy/srs-manifest` | GET | `privacyService.getSRSManifest` | OK |
| `/api/v1/privacy/delegated-proof` | POST | `privacyService.requestDelegatedProof` | OK |
| `/api/v1/privacy/delegated-proof/{proofId}` | GET | `privacyService.getDelegatedProofStatus` | OK |
| `/api/v1/privacy/networks` | GET | `privacyService.getPrivacyNetworks` | OK |

### Privacy (continued) (3)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/privacy/transaction-calldata/{txHash}` | GET | `privacyService.getTransactionCalldata` | OK |
| `/api/v1/privacy/transactions/{transactionId}/tx-hash` | PUT | `privacyService.updateTransactionHash` | OK |
| `/api/v1/privacy/pool-stats` | GET | `privacyService.getPoolStats` | OK |

### Commerce (8)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/commerce/merchants` | GET | `commerceService.getMerchants` | OK |
| `/api/v1/commerce/merchants/{merchantId}` | GET | `commerceService.getMerchant` | OK |
| `/api/v1/commerce/parse-qr` | POST | `commerceService.parseQRCode` | OK |
| `/api/v1/commerce/payment-requests` | POST | `commerceService.createPaymentRequest` | OK |
| `/api/v1/commerce/payment-requests/{paymentId}` | GET | `commerceService.getPaymentRequest` | OK |
| `/api/v1/commerce/payment-requests/{paymentId}/cancel` | POST | `commerceService.cancelPayment` | OK |
| `/api/v1/commerce/payments` | POST | `commerceService.processPayment` | OK |
| `/api/v1/commerce/payments/recent` | GET | `commerceService.getRecentPayments` | OK |

### Commerce (continued) (1)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/commerce/generate-qr` | POST | `commerceService.generateQRCode` | OK |

### Ramp (On/Off) (5)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/ramp/supported` | GET | `rampService.getSupported` | OK |
| `/api/v1/ramp/quotes` | GET | `rampService.getQuotes` | OK |
| `/api/v1/ramp/session` | POST | `rampService.createSession` | OK |
| `/api/v1/ramp/session/{id}` | GET | `rampService.getSession` | OK |
| `/api/v1/ramp/sessions` | GET | `rampService.listSessions` | OK |

### Banners (2)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/banners` | GET | `bannerService.getBanners` | OK |
| `/api/v1/banners/{id}/dismiss` | POST | `bannerService.dismissBanner` | OK |

### Referrals (4)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/referrals/my-code` | GET | `referralService.getMyCode` | OK |
| `/api/v1/referrals/apply` | POST | `referralService.applyCode` | OK |
| `/api/v1/referrals` | GET | `referralService.listReferrals` | OK |
| `/api/v1/referrals/stats` | GET | `referralService.getStats` | OK |

### Sponsorship (1)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/sponsorship/status` | GET | `sponsorshipService.getStatus` | OK |

### TrustCert / KYC (11)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/trustcert/current` | GET | `trustCertService.getCurrentCert` | OK |
| `/api/v1/trustcert/requirements` | GET | `trustCertService.getLevelRequirements` | OK |
| `/api/v1/trustcert/requirements/{level}` | GET | `trustCertService.getLevelRequirement` | OK |
| `/api/v1/trustcert/limits` | GET | `trustCertService.getTransactionLimits` | OK |
| `/api/v1/trustcert/check-limit` | POST | `trustCertService.checkTransactionAllowed` | OK |
| `/api/v1/trustcert/applications` | POST | `trustCertService.startApplication` | OK |
| `/api/v1/trustcert/applications/current` | GET | `trustCertService.getCurrentApplication` | OK |
| `/api/v1/trustcert/applications/{id}` | GET | `trustCertService.getApplication` | OK |
| `/api/v1/trustcert/applications/{id}/documents` | POST | `trustCertService.uploadDocument` | OK |
| `/api/v1/trustcert/applications/{id}/submit` | POST | `trustCertService.submitApplication` | OK |
| `/api/v1/trustcert/applications/{id}/cancel` | POST | `trustCertService.cancelApplication` | OK |

### TrustCert (continued) (1)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/trustcert/verify/{certId}` | GET | `trustCertService.verifyCert` | OK |

### Ondato KYC (2)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/compliance/kyc/ondato/start` | POST | `ondatoService.startSession` | OK |
| `/api/compliance/kyc/ondato/status/{verificationId}` | GET | `ondatoService.getStatus` | OK |

### Compliance (1)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/compliance/check-address` | GET | `complianceService.checkAddress` | OK |

### Notifications (3)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/notifications` | GET | `notificationService.getNotifications` | OK |
| `/api/v1/notifications/unread-count` | GET | `notificationService.getUnreadCount` | OK |
| `/api/v1/notifications/{id}/read` | POST | `notificationService.markAsRead` | OK |

### User Preferences (2)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/user/preferences` | GET | `preferencesService.getPreferences` | OK |
| `/api/v1/user/preferences` | PATCH | `preferencesService.updatePreferences` | OK |

### Data Export (2)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/user/data-export` | POST | `walletService.requestDataExportAsync` | OK |
| `/api/v1/user/data-export/{exportId}` | GET | `walletService.getDataExportStatus` | OK |

### Avatar (2)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/users/avatar` | POST | `avatarService.uploadAvatar` | OK |
| `/api/v1/users/avatar` | DELETE | `avatarService.deleteAvatar` | OK |

### SSL Pins (1)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/mobile/ssl-pins` | GET | `sslPinsService.getPins` | OK |

### x402 Protocol (10)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/x402/status` | GET | `x402Service.getStatus` | OK |
| `/api/v1/x402/supported` | GET | `x402Service.getSupported` | OK |
| `/api/v1/x402/spending-limits` | GET | `x402Service.getSpendingLimits` | OK |
| `/api/v1/x402/spending-limits` | POST | `x402Service.createOrUpdateSpendingLimit` | OK |
| `/api/v1/x402/spending-limits/{agentId}` | GET | `x402Service.getSpendingLimit` | OK |
| `/api/v1/x402/spending-limits/{agentId}` | PUT | `x402Service.updateSpendingLimit` | OK |
| `/api/v1/x402/spending-limits/{agentId}` | DELETE | `x402Service.deleteSpendingLimit` | OK |
| `/api/v1/x402/payments` | GET | `x402Service.getPayments` | OK |
| `/api/v1/x402/payments/stats` | GET | `x402Service.getPaymentStats` | OK |
| `/api/v1/x402/payments/{id}` | GET | `x402Service.getPayment` | OK |

### x402 (continued) (1)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/x402/endpoints` | GET | `x402Service.getEndpoints` | OK |

### MPP (Machine Payments Protocol) (3)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/mpp/status` | GET | `mppService.getStatus` | OK |
| `/api/v1/mpp/supported-rails` | GET | `mppService.getSupportedRails` | OK |
| `/api/v1/mpp/payments/stats` | GET | `mppService.getPaymentStats` | OK |

### Relayer / Smart Account (10)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/relayer/account` | GET | `relayerService.getSmartAccount` | OK |
| `/api/v1/relayer/account` | POST | `relayerService.createSmartAccount` | OK |
| `/api/v1/relayer/nonce/{address}` | GET | `relayerService.getNonce` | OK |
| `/api/v1/relayer/status` | GET | `relayerService.getRelayerStatus` | OK |
| `/api/v1/relayer/estimate-fee` | POST | `relayerService.estimateGas` | OK |
| `/api/v1/relayer/build-userop` | POST | `relayerService.buildUserOperation` | OK |
| `/api/v1/relayer/submit` | POST | `relayerService.submitUserOperation` | OK |
| `/api/v1/relayer/userop/{hash}` | GET | `relayerService.getUserOperationStatus` | OK |
| `/api/v1/relayer/supported-tokens` | GET | `relayerService.getSupportedTokensForGas` | OK |
| `/api/v1/relayer/networks/{network}/status` | GET | `relayerService.getNetworkStatus` | OK |

### Relayer (continued) (1)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/relayer/sponsor` | POST | `relayerService.sponsorUserOperation` | OK |

### Virtuals Agents (6)

| Endpoint | Method | Mobile Service | Status |
|----------|--------|----------------|--------|
| `/api/v1/virtuals-agents` | GET | `agentService.getAgents` | OK |
| `/api/v1/virtuals-agents/{id}` | GET | `agentService.getAgent` | OK |
| `/api/v1/virtuals-agents/onboard` | POST | `agentService.onboardAgent` | OK |
| `/api/v1/virtuals-agents/{id}/suspend` | PUT | `agentService.suspendAgent` | OK |
| `/api/v1/virtuals-agents/{id}/activate` | PUT | `agentService.activateAgent` | OK |
| `/api/v1/virtuals-agents/{id}/transactions` | GET | `agentService.getSpendingSummary` | OK |

---

## Potentially Breaking (5)

The March audit found five mismatches. All five are resolved at v7.18.0.

| # | Endpoint | Method | Mobile Service | Issue found in March | Status at v7.18.0 |
|---|----------|--------|----------------|----------------------|-------------------|
| 1 | `/api/v1/notifications/mark-all-read` | POST | `notificationService.markAllAsRead` | The mobile app called `mark-all-read`, but the backend only had `POST /api/v1/notifications/read-all`. | Resolved. Both paths are registered. |
| 2 | `/api/v1/notifications/{id}/read` | PATCH | `notificationService.markAsRead` | The mobile app sent `PATCH`, but the backend only registered `POST`. | Resolved. The backend accepts both `POST` and `PATCH`. |
| 3 | `/api/v1/relayer/paymaster-data` | POST | `relayerService.getPaymasterData` | The mobile app sent `POST`, but the backend only registered `GET`. | Resolved. The backend accepts both `GET` and `POST`. |
| 4 | `/api/v1/auth/sign-userop` | POST | `authService.signUserOp` | The mobile app called a `v1` path, but the backend route was `/api/auth/sign-userop`. | No longer relevant. Server-side signing was removed in v7.12.0, and neither path exists. |
| 5 | `/api/v1/rewards/quests/{id}/complete` + `/api/v1/rewards/shop/{id}/redeem` | POST | `rewardsService.completeQuest` / `redeemItem` | Both routes require a UUID id (`whereUuid('id')`), and the mobile mock data used plain string ids. A non-UUID id returns 404. | Quest completion was removed in v7.13.0. The UUID rule still applies to shop redemption, so the app must send the UUID it received from the shop list. |

---

## Missing from Backend (1)

| # | Endpoint | Method | Mobile Service | Notes |
|---|----------|--------|----------------|-------|
| 1 | `/api/v1/devices` | GET | `deviceService.getDevices` | In March the backend only had device management under `/api/mobile/devices`. The backend now also registers `GET /api/v1/devices`, `POST /api/v1/devices`, `DELETE /api/v1/devices/{id}` and `POST /api/v1/devices/bulk-remove`. There is no `DELETE /api/v1/devices/all` alias. A call to that path reaches `DELETE /api/v1/devices/{id}` with the id `all` and does not remove all devices. Use `POST /api/v1/devices/bulk-remove` or `DELETE /api/mobile/devices/all` instead. |

---

## WebSocket Channels (6)

The mobile WebSocket service connects through Pusher or Soketi. It authorises private channels with the bearer token at `/broadcasting/auth`. All six channels it subscribes to are registered in `routes/channels.php`.

The March audit marked every channel as compatible, but it only checked the channel names. A check of the event names at v7.18.0 found that several events the app listens for are not broadcast by the backend under those names. The table shows the event names the app used in March next to the names the backend broadcasts now.

| Channel Pattern | Events the app listened for in March | Events the backend broadcasts (v7.18.0) | Status |
|----------------|--------------------------------------|------------------------------------------|--------|
| `private-user.{userId}` | `transaction.confirmed`, `transaction.failed`, `notification.count_updated` | `notification.count.updated`, `quest.completed`, `bridge.kyc.completed`, `bridge.kyc.rejected`, `bridge.virtual_account.ready`, `card.state_changed`, `card.topup.updated`, `cards.3ds.code`, `cards.onboarding.updated`, `fincard.account.funded`, `fincard.kyc.status_changed` | Mismatch. The count event uses dots (`notification.count.updated`). No `transaction.confirmed` or `transaction.failed` event is broadcast on this channel; `transaction.failed` exists only as a push notification type. |
| `private-privacy.{userId}` | `privacy.operation.completed`, `privacy.balance_updated` | `privacy.operation.completed`, `privacy.balance_updated` | OK |
| `private-privacy.proof.{userId}` | `proof.ready`, `proof.failed` | `proof.requested`, `proof.progress`, `proof.completed`, `proof.failed` | Mismatch. The success event is `proof.completed`, not `proof.ready`. These events belong to the custodial delegated proof flow, which is switched off in production. |
| `private-wallet.{userId}` | `wallet.balance_updated`, `wallet.state_changed` | `wallet.balance_updated`, `wallet.state_changed` | OK |
| `private-commerce.{merchantId}` | `commerce.payment.confirmed` | `commerce.payment.confirmed` | OK |
| `private-trustcert.{userId}` | `trustcert.status.changed` | `trustcert.status.changed` | OK |

---

## Recommendations

The five critical items from March are resolved on the backend, as the table above shows. Three points remain for the mobile team.

1. The app must not call `DELETE /api/v1/devices/all`. It should call `POST /api/v1/devices/bulk-remove` instead.
2. The app must send the UUID that the shop list returned when it redeems a shop item. Mock data with plain string ids will get a 404.
3. The app should listen for `notification.count.updated` and `proof.completed`, which are the names the backend broadcasts. It should not wait for `transaction.confirmed` or `transaction.failed` on `private-user.{userId}`, because the backend does not broadcast them there.

---

## Methodology

In March, every API endpoint URL was taken from the mobile `src/services/api/*.ts` files. Only real implementations were included, and mocks were left out. Each endpoint was compared with the backend route files `routes/api.php`, `routes/api-v2.php` and every `app/Domain/*/Routes/api.php`. The audit checked that the HTTP method matched. It also looked for route constraints such as `whereUuid` and middleware that could cause a 404 without a clear error. The WebSocket channel subscriptions were compared with the Laravel broadcasting configuration.

In November, the same endpoint list was compared again with the output of `php artisan route:list` at v7.18.0. v7.18.1 changes no routes.
