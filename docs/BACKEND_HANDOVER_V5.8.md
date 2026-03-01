# Backend Handover v5.8.0 — Go-Live Status

**Date**: 2026-03-01
**Status**: All code blockers resolved. Ready for production deployment.
**PRs**: #670-#676

---

## Original 13 Items — Final Status

| # | Item | Status | PR |
|---|------|--------|-----|
| 1 | Recovery shard backup | **DONE** | #674 |
| 2 | Broadcasting auth / WebSocket channels | **DONE** | #675 |
| 3 | Privacy calldata persistence | **DONE** | #676 |
| 4 | Recipient name field | **DONE** | #672 |
| 5 | Wallet tokens (config-driven) | **DONE** | #672 |
| 6 | Cards list (Marqeta adapter) | **DONE** | #673 |
| 7 | Card transactions (Marqeta) | **DONE** | #675 |
| 8 | Relayer submit (Pimlico bundler) | **DONE** | #672 |
| 9 | UserOp status (Pimlico receipt) | **DONE** | #672 |
| 10 | Commerce merchants (DB-backed) | **DONE** | #673 |
| 11 | SSL pins | Deferred — blocked on production domain |
| 12 | Env vars | See env config section below |
| 13 | Sanctions check (Chainalysis) | **DONE** | #673 |

---

## Resolved Blockers

### Blocker #1: WebSocket Channels (PR #675)
- Added `privacy.{userId}`, `commerce.{merchantId}`, `trustcert.{userId}`, `user.{userId}` channels to `routes/channels.php`
- Laravel auto-prefixes `private-` for PrivateChannel subscriptions

### Blocker #2: Card Transactions (PR #675)
- `CardController::transactions()` now returns structured card transaction data
- Matches mobile `CardTransaction` interface (id, card_id, merchant_name, amount, currency, status, timestamp)

### Blocker #3: Privacy Calldata (PR #676)
- Created `privacy_transactions` table with encrypted calldata storage
- `PrivacyTransaction` model with UUID PK, encrypted `calldata` cast, user scopes
- `RailgunPrivacyService` persists calldata during shield/unshield/transfer operations
- `GET /api/v1/privacy/transaction-calldata/{txHash}` returns persisted data (dual lookup: tx_hash or UUID)
- `PUT /api/v1/privacy/transactions/{id}/tx-hash` for mobile to report on-chain submission
- 21 tests covering retrieval, user isolation, hash updates

### Blocker #4: Production Environment Configuration
**Ops task — no code changes needed.** See env vars table below.

---

## Production Environment Configuration

The following env vars must be set on the **production backend server**:

### Required for go-live

| Env Variable | Current Default | Required Value | Activates |
|-------------|----------------|----------------|-----------|
| `RELAYER_BUNDLER_DRIVER` | `demo` | `pimlico` | Real ERC-4337 bundler submission |
| `PIMLICO_API_KEY` | _(empty)_ | Production API key | Pimlico bundler API access |
| `CARD_ISSUER` | `demo` | `marqeta` | Real Marqeta card provisioning |
| `MARQETA_BASE_URL` | _(empty)_ | `https://sandbox-api.marqeta.com/v3` or production URL | Marqeta API |
| `MARQETA_APPLICATION_TOKEN` | _(empty)_ | From Marqeta dashboard | Marqeta auth |
| `MARQETA_ADMIN_ACCESS_TOKEN` | _(empty)_ | From Marqeta dashboard | Marqeta auth |
| `CHAINALYSIS_ENABLED` | `false` | `true` | Sanctions screening |
| `CHAINALYSIS_API_KEY` | _(empty)_ | From Chainalysis dashboard | Chainalysis API |
| `PUSHER_APP_KEY` | dev key | Production Pusher/Soketi key | WebSocket auth |
| `PUSHER_APP_SECRET` | dev secret | Production secret | WebSocket auth |

### Required for mobile builds (provide to mobile team)

| Mobile Env Variable | Purpose |
|---------------------|---------|
| `EXPO_PUBLIC_API_URL` | Production API base URL (e.g., `https://api.zelta.app`) |
| `EXPO_PUBLIC_PUSHER_APP_KEY` | Must match `PUSHER_APP_KEY` above |
| `EXPO_PUBLIC_PIMLICO_API_KEY` | Mobile-side Pimlico key (for gas estimation) |
| `EXPO_PUBLIC_WALLETCONNECT_PROJECT_ID` | Production WalletConnect Cloud project ID |

---

## v5.8.0 Feature Summary

| Feature | Details |
|---------|---------|
| Rewards GraphQL + Admin | 35th GraphQL domain, Filament admin resources, OpenAPI PHP 8 attributes |
| Mobile v5.7.1 hotfix | Handover items #2, #4, #7 resolved |
| Relayer production | Pimlico v2 bundler submission, receipt query, config-driven tokens |
| Card & Commerce | Marqeta card listing + transactions, DB merchants, Chainalysis sanctions |
| Recovery shard backup | Cloud backup CRUD endpoints for key recovery |
| WebSocket channels | 4 mobile-aligned broadcast channels |
| Privacy calldata | Encrypted calldata persistence, dual-lookup retrieval, tx-hash update |

---

*All code blockers resolved. Production deployment requires only env configuration (Blocker #4).*
