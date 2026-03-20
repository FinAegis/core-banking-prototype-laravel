# Backend Response to Mobile Handover v5.7.0

**Date**: 2026-02-28
**From**: Backend team
**To**: Mobile team
**Re**: Response to `BACKEND_HANDOVER_V5.7.md` — all 13 items reviewed

---

## TL;DR

All 13 items are **confirmed accurate**. No rejections. Here's the implementation plan:

| Priority | Items | Target |
|----------|-------|--------|
| **P0** | #2 broadcasting auth, #1 recovery shard backup, #3 privacy calldata | **#2 shipped in v5.7.1 (PR #671)**, #1/#3 → v5.8.0 |
| **P1** | #4 recipient name field | **Shipped in v5.7.1 (PR #671)** |
| **P2** | #5 tokens, #6 cards, #7 card txns, #8 relayer submit, #9 userop status, #10 merchants | **#7 interim fix shipped in v5.7.1**, rest → v5.8.0 |
| **P3** | #11 SSL pins, #12 env vars, #13 sanctions | Pre-launch checklist |

---

## Detailed Item Analysis

### P0 — Missing Endpoints

#### #1 `POST /api/v1/wallet/recovery-shard-backup` — CONFIRMED MISSING

**Verdict**: Accurate. No route or controller action exists.

**Backend readiness**: ~90% of infrastructure is already in place:
- `ShamirService` (`app/Domain/KeyManagement/Services/ShamirService.php`) — handles shard splitting/reconstruction
- `ShardDistributionService` (`app/Domain/KeyManagement/Services/ShardDistributionService.php`) — handles shard distribution logic
- What's missing: A controller endpoint to accept the encrypted shard blob and persist it

**Implementation plan**:
- Add `RecoveryShardController` with `store`, `show`, `destroy` actions
- Store encrypted shard as opaque blob in a `recovery_shard_backups` table
- Index on `(user_id, device_id, backup_provider)` for lookup
- Endpoints: `POST` (create), `GET` (restore), `DELETE` (revoke) per mobile's suggestion
- Leverage existing `ShardDistributionService` for distribution tracking

**Effort**: ~4 hours (migration, model, controller, routes, tests)

**Mobile workaround until ready**: Continue with `setTimeout` mock. Once endpoint ships, swap `await fakeBackup()` → `await api.post('/wallet/recovery-shard-backup', payload)`.

---

#### #2 `POST /broadcasting/auth` — CONFIRMED MISSING

**Verdict**: Accurate. `bootstrap/app.php` was missing the `withBroadcasting()` call. `routes/channels.php` already exists and defines channels including:
- `App.Models.User.{id}` — user-specific
- `tenant.{tenantId}` — tenant notifications
- `tenant.{tenantId}.transactions` — transaction feed
- `privacy.merkle.{network}` — privacy pool updates
- `payments.{userId}` — payment status updates

**Fix**: One-line addition to `bootstrap/app.php`:
```php
->withBroadcasting(base_path('routes/channels.php'))
```

**Effort**: 15 minutes (code change + test verification)

**Status**: **SHIPPED** in v5.7.1 (PR #671). Also installed `pusher/pusher-php-server` package.

---

#### #3 `GET /api/v1/privacy/transaction-calldata/{txHash}` — CONFIRMED MISSING

**Verdict**: Accurate. No route exists. The RAILGUN infrastructure is in place:
- `RailgunBridgeClient` (`app/Domain/Privacy/Services/RailgunBridgeClient.php`) — HTTP client to the Node.js RAILGUN bridge service
- `RailgunPrivacyService` (`app/Domain/Privacy/Services/RailgunPrivacyService.php`) — orchestrator for shield/unshield/transfer
- Existing privacy routes: shield, unshield, transfer — but no calldata retrieval

**Implementation plan**:
- Add `getTransactionCalldata(string $txHash)` method to `RailgunBridgeClient`
- This requires the Node.js bridge service to expose a corresponding endpoint
- Add `PrivacyController::getTransactionCalldata()` endpoint
- Returns: `tx_hash`, `calldata`, `block_number`, `timestamp`

**Effort**: ~6 hours (bridge service endpoint + Laravel endpoint + tests)

**Dependency**: Requires Node.js RAILGUN bridge service update (the Laravel side alone can't fetch calldata — it's retrieved via the bridge).

**Mobile workaround**: As mobile noted, privacy proof verification can be deferred. Continue returning placeholder data. Not a launch blocker.

---

### P1 — Missing Response Field

#### #4 `GET /api/v1/wallet/recent-recipients` — `name` field missing — CONFIRMED

**Verdict**: Accurate. `MobileWalletController::recentRecipients()` returns `address`, `network`, `token`, `last_sent_at` — no `name` field.

**Implementation plan**:
- Cross-reference recipient `address` against `blockchain_addresses` table (which links to `users`)
- If match found → return `user.name`
- If no match → return `null` (mobile already handles "Unknown" fallback)
- Consider also checking a future contacts/address book table

**Effort**: ~1 hour

**Status**: **SHIPPED** in v5.7.1 (PR #671). Cross-references `blockchain_addresses` → `users` table. Returns `null` for unknown addresses.

**Mobile impact if skipped**: Users see "Unknown" for all recipients. Functional but poor UX — agreed.

---

### P2 — Stub Implementations

#### #5 `GET /api/v1/wallet/tokens` — hardcoded — CONFIRMED

**Verdict**: Accurate. `MobileWalletController::tokens()` returns a static array of `[USDC, USDT, WETH, WBTC]` regardless of chain.

**Implementation plan**:
- Create `supported_tokens` config or DB table per chain
- Respect `chain_id` query parameter
- Include token metadata: symbol, decimals, contract address, icon URL

**Effort**: ~3 hours

---

#### #6 `GET /api/v1/cards` — returns `[]` — CONFIRMED

**Verdict**: Accurate. `CardController::index()` contains a comment "Demo implementation - return empty list."

**Implementation plan**:
- Query `cards` table for user's cards (the `Card` model and `CardProvisioningService::createCard()` already write to DB)
- Return card summary: last4, network, status, expiry, spending limits

**Effort**: ~2 hours

---

#### #7 `GET /api/v1/cards/{id}/transactions` — deterministic demo data — CONFIRMED

**Verdict**: Accurate. `CardController::transactions()` generates fake transactions seeded by card ID.

**Implementation plan**:
- Depends on card provider integration (Marqeta webhook ingestion)
- Short-term: Return `[]` instead of fake data (mobile can show "No transactions yet")
- Long-term: Query real transaction history from Marqeta webhook events stored in DB

**Effort**: Short-term ~30 min; Long-term ~8 hours (Marqeta webhook transaction persistence)

**Status**: **Short-term fix SHIPPED** in v5.7.1 (PR #671) — returns `[]`. Full Marqeta integration in v5.9.0.

---

#### #8 `POST /api/v1/relayer/submit` — random hash, always pending — CONFIRMED

**Verdict**: Accurate. `MobileRelayerController::submitUserOp()` generates `random_bytes(32)` as the hash and returns `status: "pending"` without touching the bundler.

**Implementation plan**:
- Follow the pattern from `POST /api/v1/relayer/sponsor` which correctly uses the Pimlico bundler
- Submit the UserOperation to the configured ERC-4337 bundler
- Return the real operation hash from the bundler response

**Effort**: ~4 hours (needs Pimlico bundler integration for submission, not just sponsorship)

---

#### #9 `GET /api/v1/relayer/userop/{hash}` — always returns pending — CONFIRMED

**Verdict**: Accurate. `MobileRelayerController::getUserOp()` returns hardcoded `status: 'pending'` with `null` tx_hash.

**Implementation plan**:
- Query the Pimlico bundler for UserOperation receipt
- Return actual status: `pending`, `confirmed`, `failed`
- Include real `tx_hash` and `block_number` once confirmed

**Effort**: ~3 hours (pairs with #8, shared bundler integration)

---

#### #10 `GET /api/v1/commerce/merchants` — demo merchants — CONFIRMED

**Verdict**: Accurate. `MobileCommerceController::merchants()` calls `getDemoMerchants()` returning hardcoded Coffee Shop, Book Store, etc.

**Implementation plan**:
- Query from `merchants` table (the `MerchantOnboardingService` and `Merchant` model already exist)
- Add pagination, search, and location-based filtering

**Effort**: ~2 hours for basic DB query; ~6 hours with search/location

**Mobile's suggestion accepted**: Keep demo data for initial launch with a "coming soon" note in merchant discovery. Merchant payments via QR still work.

---

### P3 — Infrastructure / Config

#### #11 SSL Certificate Pin Hashes — CONFIRMED NEEDED

**Verdict**: Accurate. Placeholder pins in `plugins/withNetworkSecurity.js`.

**Action**: DevOps/infrastructure team to provide SPKI SHA-256 hashes for production API certificate once the production domain is provisioned.

**Command to generate** (as mobile noted):
```bash
openssl s_client -connect api.zelta.app:443 | \
  openssl x509 -pubkey -noout | \
  openssl pkey -pubin -outform DER | \
  openssl dgst -sha256 -binary | base64
```

**Status**: Blocked on production domain provisioning. Will provide once infrastructure is ready.

---

#### #12 Production Environment Variables — PARTIALLY READY

**Verdict**: Accurate. Mobile needs production values.

**Current status**:
| Variable | Status |
|----------|--------|
| `EXPO_PUBLIC_API_URL` | Depends on production domain |
| `EXPO_PUBLIC_PUSHER_APP_KEY` | Current dev key works with Soketi; production key TBD |
| `EXPO_PUBLIC_PIMLICO_API_KEY` | Current key is testnet; production key from Pimlico dashboard |
| `EXPO_PUBLIC_WALLETCONNECT_PROJECT_ID` | Current project ID may work for production; verify in WalletConnect Cloud |

**Action**: Will provide a `mobile-env.production` file when production infrastructure is provisioned.

---

#### #13 Sanctions/Compliance Endpoint — CONFIRMED: ADAPTER EXISTS, NO ENDPOINT

**Verdict**: Accurate. `ChainalysisAdapter` (`app/Domain/Compliance/Adapters/ChainalysisAdapter.php`) is fully implemented with `screenIndividual()` and `screenAddress()` methods. However, no REST endpoint exposes it for mobile consumption.

**Recommendation**: Agree with mobile — **Option A** (backend endpoint) is correct:
- Add `GET /api/v1/compliance/check-address?address=0x...`
- Delegates to `ChainalysisAdapter::screenAddress()`
- Returns `{ sanctioned: bool, risk_score: string, details: {...} }`
- Rate-limit to prevent abuse

**Effort**: ~2 hours (controller + route + test; adapter already handles the logic)

**Target**: Pre-launch for regulated markets. Not a launch blocker if geo-restricted initially — agreed.

---

## Implementation Roadmap

### v5.7.1 Hotfix — SHIPPED (PR #671)

| Item | Work | Status |
|------|------|--------|
| #2 Broadcasting auth | Added `withBroadcasting()` + installed `pusher/pusher-php-server` | Done |
| #4 Recipient name | Added name lookup via `blockchain_addresses` → `users` | Done |
| #7 Card transactions | Returns `[]` instead of fake data (interim fix) | Done |

### v5.8.0 (next sprint)

| Item | Work | Effort |
|------|------|--------|
| #1 Recovery shard backup | New controller, migration, model, 3 endpoints | 4 hr |
| #5 Wallet tokens | Config/DB-driven token list per chain | 3 hr |
| #6 Cards list | Query from DB instead of returning `[]` | 2 hr |
| #8 Relayer submit | Pimlico bundler integration for UserOp submission | 4 hr |
| #9 UserOp status | Pimlico bundler receipt query | 3 hr |
| #10 Commerce merchants | DB query with pagination | 2 hr |
| #13 Sanctions endpoint | Expose ChainalysisAdapter via REST | 2 hr |

**Total**: ~20 hours

### v5.9.0 / Pre-Launch

| Item | Work | Effort |
|------|------|--------|
| #3 Privacy calldata | Bridge service + Laravel endpoint | 6 hr |
| #7 Card transactions (full) | Marqeta webhook transaction persistence | 8 hr |
| #11 SSL pins | Generate from production certificate | DevOps |
| #12 Env vars | Production config package | DevOps |

**Total**: ~14 hours + DevOps

---

## What Mobile Can Do Now

1. **Broadcasting (#2)**: **Already shipped.** Test WebSocket channel subscriptions now. `POST /broadcasting/auth` returns 200 with Pusher auth signature for authenticated users.

2. **Recent recipients (#4)**: **Already shipped.** `name` field is now present (nullable). No mobile code changes needed if you already handle `null` → "Unknown".

3. **Card transactions (#7)**: **Already shipped.** Now returns `[]` instead of fake data. Mobile should show "No transactions yet" empty state.

4. **Recovery shard (#1)**: Targeted for v5.8.0. Mobile can keep the `setTimeout` mock until then. API contract matches your expected request/response format exactly.

5. **Relayer (#8, #9)**: Targeted for v5.8.0. Mobile can keep current flow; the API contract won't change — just the responses will contain real data.

6. **Sanctions (#13)**: Targeted for v5.8.0. Mobile can keep `isSanctionedAddress() => false` stub until endpoint is ready. API: `GET /api/v1/compliance/check-address?address=0x...`

7. **SSL pins (#11) / Env vars (#12)**: Will provide when production infrastructure is provisioned. Mobile can keep placeholders for development builds.

---

## Questions for Mobile

1. **Recovery shard restore flow**: Should `GET /api/v1/wallet/recovery-shard-backup?device_id=xxx&provider=icloud` return all shards for a user, or just the most recent? Does mobile handle multi-device shard merging?

2. **Token list (#5)**: Do you need just the token metadata (symbol, decimals, address), or also the user's balance per token? Balance would require a separate call to the node or indexer.

3. **Card transactions (#7)**: When we ship real Marqeta data, do you need the full Marqeta transaction schema, or a simplified version? Please share your `CardTransaction` type/interface so we match it.

4. **Merchant search (#10)**: Do you plan to implement location-based merchant discovery (nearby merchants), or is a simple paginated list sufficient?

---

*v5.7.1 shipped (PR #671). Backend will create a tracking issue for v5.8.0 items.*
