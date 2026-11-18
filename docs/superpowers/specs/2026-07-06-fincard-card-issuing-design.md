# FinCard Card Issuing — Integration Design

- **Status:** Approved (design shape) 2026-07-06 — implementation in phases.
- **Partner:** FinCard Virtual Card BFF, operated by FinHub (`finhub.cloud`, a SEPA Cyber Technologies group product).
- **Source artifacts:** `docs/partners/FinCard_BFF_Virtual_Card_API_Test_Tenant_Mock_postman_collection.json` (mock test-tenant collection); public docs at `https://docs.finhub.cloud/paas/fincard-virtual/`.
- **Related:** `config/cardissuance.php` (issuer registry), `app/Domain/CardIssuance/` (existing domain), ADR-0005/0006 (Bridge), `docs/operations/bridge-ramp.md` (partner-ops precedent).

---

## 1. Summary & decisions

FinCard is the card-issuing partner Zelta will use to launch its virtual card product. It is a **prefunded, stored-value virtual card program** — architecturally different from the JIT-funded issuers (Marqeta/Lithic/Stripe Issuing) the existing `CardIssuerInterface` was designed around. FinCard holds a per-tenant fiat "account" ledger that we fund (with crypto or fiat), and cards spend from a balance moved onto them; authorization happens inside FinCard and is reported to us by webhook.

Product decisions (locked 2026-07-06):

| Decision | Choice |
|---|---|
| **Funding** | Both crypto (USDT from the user's Zelta wallet) **and** fiat (via the existing Bridge ramp). Crypto ships first (unblocked); fiat second. |
| **KYC** | Dedicated FinCard KYC, prefilled from the user's Zelta profile. No reliance on third-party KYC passthrough (FinCard performs its own verification). |
| **v1 scope** | Virtual cards only, viewed in-app. **No** Apple/Google Pay tokenization, **no** physical cards. |
| **Custody** | One FinCard account per Zelta user. |

The integration extends the existing `CardIssuance` domain rather than creating a new one: FinCard becomes `CARD_ISSUER=fincard`, a new adapter + a companion funding contract + local persistence + a webhook handler.

---

## 2. What FinCard is (corrected from public docs)

The mock collection is a stale, simplified subset of FinCard's real API. The authoritative facts, from `docs.finhub.cloud/paas/fincard-virtual/`:

- **BFF/aggregator model.** "FinHub BFF" (`/api/v2.1/fincard/virtual/...`) wraps an upstream "FinCard Virtual" card platform. FinHub is an EU BaaS marketplace (SEPA Cyber Technologies, Bulgaria/UK); it is **not** the license holder — the upstream issuer / BIN sponsor is not publicly disclosed (open item).
- **RPC-over-POST.** Every call is a `POST` with a JSON body, including reads and lists (`pageNum`/`pageSize`). Client idempotency key is `merchantOrderNo` on mutating calls.
- **Response envelope:** `{ "success": bool, "code": int, "msg": string, "data": ... }` (note `msg`, not `message`).
- **Base URLs:** sandbox `https://sandbox.finhub.cloud/api/v2.1/fincard/virtual`; integration/beta `https://api-beta.finhub.cloud/...`; production `https://api.finhub.cloud/...`.
- **Four resource layers:** Cardholder (KYC identity) → Account (fiat USD ledger) → Card (spendable balance, opened against an account + cardholder). Wallet-V2 (crypto deposit) credits an Account.
- **Card networks:** Visa, Mastercard, Discover. v1 uses whatever virtual BIN(s) the tenant is provisioned for (`card/v2/cardTypes`).

### 2.1 Authentication (outbound: us → FinHub BFF)

- **We do NOT sign outbound requests.** The `X-WSB-SIGNATURE` header in the mock is a **Playground placeholder** (`valid_signature_12345`). The RSA signing that reaches the upstream FinCard production API is performed **inside FinHub's BFF**, not by us. Playground/sandbox tenants accept the placeholder; Integration tenants have FinHub sign on our behalf.
- **Auth = JWT bearer.** Obtained via `POST /api/v2.1/admin/organization/{orgId}/users/{userId}/sessions` with `{username, password}` → `data.accessToken`. Token `expiresIn: 3600` (1 hour), **no refresh token** → cache and re-login before expiry.
- **Required context headers on every call:** `Authorization: Bearer <jwt>`, `Content-Type: application/json`, `X-Tenant-ID`, `X-Forwarded-For` (end-user IP), `X-Forwarded-From` (originating service name), `platform` (`web`|`ios`|`android`), `deviceId`.

### 2.2 Webhooks (inbound: FinCard → us)

- **Signature is RSA, inbound only.** Each webhook carries `SHA256withRSA(rawBody)` base64-encoded, verified against FinCard's **platform RSA public key**. This is the same shape as the existing `BridgeWebhookVerifier` (asymmetric RSA over the raw body) and is copy-and-adapt.
- **Header name is ambiguous** in FinCard's own materials: `X-FC-SIGNATURE` (narrative webhooks page) vs `X-WSB-SIGNATURE` (API reference + mock). Our verifier will **accept either header** and this must be confirmed with FinCard (open item), along with obtaining the public key.
- **Retries + idempotency.** The platform retries failed deliveries; we must process idempotently keyed on `orderNo`/`tradeNo` and reply `{"success": true}`.
- **Event catalog (8 categories).** The real `type` values differ from the mock's `card_create`:
  - *Card operation:* `create`, `deposit`, `withdraw`, `Freeze`, `UnFreeze`, `cancel`, `blocked`, `overdraft_statement`
  - *Card authorization (spend):* `auth`, `refund`, `verification`, `Void`
  - *Card auth fee:* `maintain_fee`, `card_patch_fee`, `card_patch_cross_border`
  - *Card 3DS:* `third_3ds_otp`, `auth_url`, `activation_code`
  - *Activate (physical):* `card_activated` *(out of v1 scope)*
  - *Cardholder:* `wait_audit`, `under_review`, `pass_audit`, `reject`
  - *Work order:* `processing`, `success`, `fail`
  - *Wallet V2 (crypto):* `DEPOSIT`, `WITHDRAW`

### 2.3 KYC (heavier than the mock)

Cardholder-V2 create requires far more than the mock body:
`firstName`, `lastName`, `gender`, `birthday`, `nationality` (ISO-2), `occupation` (from `card/holder/occupations`), `annualSalary`, `expectedMonthlyVolume`, `accountPurpose`, `phone` + `phoneCountryCode`, `email`, full address, `idType` (`PASSPORT` | `DLN` | `GOVERNMENT_ISSUED_ID_CARD` — **not** the mock's `NATIONAL_ID`), `idNumber`, `issueDate`, `idNoExpiryDate`, **three uploaded photos** (`idFrontId`, `idBackId`, and a selfie/hold `idHoldId`), and `ipAddress`.

Approval is **two-stage**: `admin` (FinHub platform review) → `channel` (bank review), surfaced as cardholder webhooks `wait_audit → pass_audit | under_review | reject`. **Cards can only be created after `pass_audit`.** FinCard maintains a restricted-country list (enforced by them; we mirror a check to fail fast).

### 2.4 Crypto funding

`wallet/v2/create` with a `coinKey` returns a per-coin deposit **address**; the user deposits stablecoin; FinCard **converts crypto → USD** on receipt (fees `feeRate%` + `fixedFee`; ~38 confirmations for TRC20) and credits the merchant account, firing a wallet `DEPOSIT` webhook. Documented coins are `USDT_TRC20` and `USDT_BEP20`; **USDC is not confirmed** — the live set comes from `wallet/v2/coins` (fields: `coinKey, chain, enableDeposit, confirmations, depositFee, minDepositAmount, maxDepositAmount, …`), which we treat as the runtime source of truth.

---

## 3. Architecture

FinCard extends the existing `app/Domain/CardIssuance/` domain. Three seams are added.

### 3.1 Infrastructure client — `app/Infrastructure/FinCard/`

Placed in `Infrastructure` (like `app/Infrastructure/Bridge/`) because both the domain adapter and the webhook controller consume it.

- **`FinCardClient`** — the outbound HTTP layer.
  - `fromConfig()` factory reading `config('cardissuance.issuers.fincard.*')`.
  - Cached JWT session auth mirroring `OndatoService::getAccessToken()`: `Cache::get('fincard:jwt')` → on miss, `POST .../sessions`, cache for ~55 min (`expiresIn - 300s`), re-login on a `401`/expiry.
  - A single private `rpc(string $path, array $params, array $context): array` that POSTs to `{base_url}{path}`, injects the bearer + context headers, `->timeout(30)->retry(2, 500, throw: false)`, and decodes the `{success, code, msg, data}` envelope — throwing `FinCardApiException` (carrying `code` + `msg`) on `success=false` or transport failure, logging `path/code/msg` first (never the body — it holds PII/PAN).
  - Typed public methods per endpoint group (auth, common/reference, account, wallet, cardholder, card).
  - `X-Forwarded-For`/`deviceId`/`platform` are passed **per call** from the originating mobile request context (not static), so FinCard's fraud/geo signals reflect the real end user.
- **`FinCardWebhookVerifier`** — RSA verification, modeled on `BridgeWebhookVerifier`: `openssl_verify($rawBody, base64_decode($sig), $publicKey, OPENSSL_ALGO_SHA256) === 1`, accepting `X-FC-SIGNATURE` or `X-WSB-SIGNATURE`, with the "accept in non-prod, fail closed in production" idiom when no key is configured.
- **`FinCardApiException`** — carries FinCard's business `code` + `msg` for mapping to our `ERR_CARDS_*` responses.

### 3.2 Domain contracts & adapter — `app/Domain/CardIssuance/`

- **`FinCardCardIssuerAdapter implements CardIssuerInterface`** — satisfies the existing 9-method lifecycle contract (`createCard`, `freezeCard`, `unfreezeCard`, `cancelCard`, `getCard`, `listUserCards`, `getTransactions`, `getName`). `getProvisioningData()` throws `UnsupportedCardOperationException` (no Apple/Google Pay in v1). This keeps `CardProvisioningService`, the GraphQL mutations, and the existing `CardController` lifecycle routes working through the standard seam.
- **`FinCardFundingInterface`** (new companion contract) — the prefunded operations the base interface cannot express:
  `createCardholder`, `uploadKycDocument`, `getCardholder`, `createAccount`, `getAccountBalance`, `listAccountTransactions`, `createDepositAddress`, `listCoins`, `openCard`, `topUpCard`, `withdrawFromCard`, `getCardBalance`, `getSensitiveCardDetails`, `getPurchaseTransactions`, `get3dsTransactions`. `FinCardCardIssuerAdapter` implements this too; other issuers do not.
- **`FinCardCardService`** — orchestration + **local persistence** (the crux). The current adapter/`VirtualCard` path never writes `cards`/`cardholders`; FinCard entities are long-lived and must be mapped to Zelta users. This service wraps the adapter, persists to the tables in §4, enforces our own limits, dispatches domain events, and is what the new mobile controllers depend on.

### 3.3 Selection & wiring

- `config/cardissuance.php` gains a `fincard` issuer stanza; `CardIssuanceServiceProvider::register()` gains a `'fincard' => new FinCardCardIssuerAdapter(FinCardClient::fromConfig(), ...)` match arm and binds `FinCardFundingInterface`.
- `ValidateWebhookSignature` gains a `fincard` arm **or** (preferred) FinCard gets its own webhook route with a dedicated controller that calls `FinCardWebhookVerifier` directly (cleaner, since FinCard's RSA scheme differs from the Marqeta basic-auth arm).

---

## 4. Data model

Reuse existing partner hooks and add FinCard-specific persistence. All sensitive material uses Laravel's `encrypted`/`encrypted:array` cast over `text` columns.

### New tables

- **`fincard_accounts`** — one per user.
  `id` (uuid), `user_id` (FK), `fincard_account_id` (unique, indexed), `currency` (default `USD`), `balance_cents` (bigint), `status`, `created_at`/`updated_at`. `UsesTenantConnection`.
- **`fincard_deposit_addresses`** — crypto deposit addresses.
  `id` (uuid), `user_id` (FK), `fincard_account_id`, `coin_key` (e.g. `USDT_TRC20`), `chain`, `address` (indexed), `min_deposit_cents` nullable, `created_at`/`updated_at`. Unique `(user_id, coin_key)`.

### Extend existing tables (new migrations)

- **`cardholders`** — add `kyc_stage` (`admin`|`channel`|null), `kyc_rejection_reason` (nullable), and store the extended KYC attributes inside the existing encrypted `verification_data` blob (no new plaintext PII columns). `issuer_cardholder_id` already exists → FinCard `holderId`.
- **`cards`** — add `balance_cents` (bigint, nullable), `fincard_account_id` (nullable, indexed), `merchant_order_no` (nullable, for idempotent open/reconcile). `issuer_card_token` already exists → FinCard `cardId`; `issuer` = `fincard`.

### Reuse

- **`card_transactions`** — purchase/auth/3DS history, upserted by `CardTransactionSyncService` keyed on `external_id` (FinCard `orderNo`/`tradeNo`). Add `type` (`purchase`|`auth`|`fee`|`3ds`|`refund`) to distinguish streams.
- **`processed_webhook_events`** — dedupe with `provider='fincard'`, `event_id = orderNo` (or `tradeNo`). No new table.

---

## 5. Auth & session management

`FinCardClient` holds no long-lived secret beyond the service-account `username`/`password` and `org`/`user`/`tenant` IDs (all from env/config). The JWT is cached in Redis under `fincard:jwt` with a TTL of `min(expiresIn, 3600) - 300` seconds. A `401` from any RPC triggers a single transparent re-login + retry (guarded against infinite loops). Concurrent cache-miss logins are de-duplicated with a short lock (`Cache::lock('fincard:jwt:login', 10)`) so a burst of requests doesn't spawn many logins.

---

## 6. Funding flows

### 6.1 Crypto (ships first, non-custodial upstream)

1. User has a `pass_audit` cardholder and a FinCard account (§7, §8).
2. Mobile requests a deposit address → `FinCardCardService::getOrCreateDepositAddress(user, coinKey)` → `wallet/v2/create` → persisted in `fincard_deposit_addresses`, returned to mobile with the chain, min amount, and confirmations.
3. User sends USDT from their **existing Zelta TRON/BSC wallet** (self-custody) to that address using the wallet they already have.
4. FinCard converts crypto→USD, credits the account, fires a wallet `DEPOSIT` webhook → we update `fincard_accounts.balance_cents`, dispatch `FinCardAccountFunded`, broadcast + push.
5. User opens a card (moves USD from account→card) or tops up an existing card.

### 6.2 Fiat (ships second — one open dependency)

The user's fiat enters through the **existing Bridge ramp**. FinCard's documented account-funding rails are **crypto-in and inter-account transfer only** — there is no documented direct fiat-into-account endpoint. So the default design routes fiat as a **treasury operation**: Bridge fiat → USDC/USDT → deposited to the FinCard account via the same crypto rail. **Open item:** confirm with FinCard whether a merchant account can be funded with fiat directly (bank wire / settlement rail); if so, the fiat path simplifies and skips the stablecoin hop. Because of this dependency, fiat funding is sequenced after crypto.

### 6.3 Money handling

All amounts are integer minor units (`*_cents`) end to end; conversions use `bcmath` (`bcmul`/`bcdiv`), never float. FinCard returns decimal strings — normalize with `bcadd($v, '0', 2)` before converting to cents. Deposit crediting follows the HyperSwitch webhook discipline: dedupe/claim on the default connection, then credit balances on the tenant connection in a **separate** transaction (cross-connection transactions self-deadlock); the credited amount always comes from the webhook's settled figure reconciled against our record, never blind trust.

---

## 7. KYC / cardholder flow

1. **Prefill.** Mobile opens the card-onboarding flow; backend returns a prefilled cardholder draft from the Zelta profile (name, DOB, nationality, address) plus the FinCard-required-field schema (occupation list from `card/holder/occupations`, ID types, financial fields).
2. **Document upload.** Each of the three photos is uploaded via FinCard `common/file/upload` → returns a `fileId`; we hold the ids transiently (not the images).
3. **Create cardholder.** `card/holder/v2/create` with the full field set → `holderId`; persist to `cardholders` (`issuer_cardholder_id`, `kyc_status='in_review'`, `kyc_stage='admin'`).
4. **Approval tracking.** Cardholder webhooks drive `kyc_status`/`kyc_stage`: `under_review` → still pending; `pass_audit` → `verified` (card creation unlocked); `reject` → `rejected` + `kyc_rejection_reason`. Each transition broadcasts on `private-user.{userId}` (`fincard.kyc.*`) and sends a push on the terminal states.
5. **Restricted country** is checked before submission to fail fast with a clear error.

Prefill reduces friction but the user still supplies FinCard-specific fields and a live selfie; we do not claim third-party KYC passthrough.

---

## 8. Card lifecycle

Gated on a `pass_audit` cardholder + a funded account.

- **Open card** — `card/v2/openCard` (`cardTypeId`, `amount`, `accountId`, `holderId`, `merchantOrderNo`) → persist `cards` row (`issuer_card_token`, `balance_cents`, `fincard_account_id`, `status='active'`). `create` webhook confirms.
- **Read** — card info, **sensitive details** (PAN/CVV via `card/info/sensitive`, returned ephemerally to mobile over TLS, never persisted plaintext), balance, list.
- **Control** — freeze / unfreeze / cancel (map FinCard status ↔ our `CardStatus`).
- **Fund movement** — top-up (`card/deposit`) and withdraw (`card/withdraw`) between account and card.
- **Transactions** — purchase, operation, authorization, auth-fee, and 3DS streams synced via webhooks (primary) + `CardTransactionSyncService::pollAndSync()` (reconciliation backstop).

### Status mapping (FinCard → `CardStatus`)

`Normal → active`, frozen states → `frozen`, `cancel`/closed → `cancelled`, pending open → `pending`, `blocked` → `frozen` (with a distinct reason surfaced). Networks fold into the existing `CardNetwork` enum (extend with `discover` if a Discover BIN is provisioned).

---

## 9. Webhooks

- **Route:** `POST /api/v1/webhooks/fincard` (public, `api.rate_limit:webhook`, no Sanctum — authenticated by signature), matching the Bridge/HyperSwitch route shape.
- **Controller flow** (Bridge/HyperSwitch discipline): read raw body → `FinCardWebhookVerifier::verify()` (401 on fail) → decode → extract event `type` + `orderNo`/`tradeNo` → `ProcessedWebhookEvent::firstOrCreate(['provider'=>'fincard','event_id'=>$orderNo])`; if not `wasRecentlyCreated`, reply `{"success":true}` (duplicate) → dispatch by category to a handler → reply `{"success":true}`. Handlers run in `try/catch`, log-and-swallow (the dedupe row + FinCard's own retry guard against loss), and apply state inside `DB::transaction` + `lockForUpdate` on the target row, with slow side-effects (push/broadcast) **outside** the transaction.
- **Handlers by category:** cardholder → KYC state; wallet `DEPOSIT`/`WITHDRAW` → account balance; card-op `create/deposit/withdraw/Freeze/UnFreeze/cancel/blocked` → card state + balance; auth/refund/verification/Void + auth-fee + 3DS → `card_transactions` via `CardTransactionSyncService`.

---

## 10. Mobile API surface

New/extended `/api/v1/cards/*` and `/api/v1/cardholders/*` endpoints. Conventions (from the existing mobile surface): `{success, data}` success envelope; `ErrorResponse::make('ERR_CARDS_*', …)` errors (register new codes in `config/error_codes.php`; `ERR_CARDS_001..006` already exist); **snake_case** bodies (match existing `CardController`); `Idempotency-Key` header + `idempotency.required` on writes; `require.kyc` (Zelta-level) on the group, plus a FinCard-KYC gate in-service; list responses add `pagination:{next_cursor,has_more,total}`; `#[OA\*]` annotations regenerated via `l5-swagger:generate`.

| Method & path | Purpose |
|---|---|
| `GET /v1/cards/onboarding` | Prefilled cardholder draft + FinCard field schema (occupations, id types) |
| `POST /v1/cards/kyc/documents` | Upload an ID photo → `fileId` (front/back/selfie) |
| `POST /v1/cardholders` | Create FinCard cardholder (extends existing) |
| `GET /v1/cardholders/{id}` | Cardholder + KYC stage/status |
| `GET /v1/cards/reference/card-types` | Supported card types (BINs) |
| `GET /v1/cards/account` | FinCard account + balance |
| `POST /v1/cards/account/deposit-address` | Get/create a crypto deposit address (`coin_key`) |
| `GET /v1/cards/account/coins` | Supported deposit coins (live from FinCard) |
| `GET /v1/cards` | List the user's cards *(exists)* |
| `POST /v1/cards` | Open a card *(exists — routed to FinCard)* |
| `GET /v1/cards/{cardId}` | Card info *(exists)* |
| `GET /v1/cards/{cardId}/sensitive` | Ephemeral PAN/CVV |
| `GET /v1/cards/{cardId}/balance` | Card balance |
| `POST /v1/cards/{cardId}/topup` | Move funds account→card |
| `POST /v1/cards/{cardId}/withdraw` | Move funds card→account |
| `POST /v1/cards/{cardId}/freeze` · `DELETE .../freeze` | Freeze / unfreeze *(exists)* |
| `DELETE /v1/cards/{cardId}` | Cancel *(exists — biometric-gated)* |
| `GET /v1/cards/{cardId}/transactions` | Purchase history *(exists — extend with type filter)* |

Realtime: card/KYC/funding state changes raise domain events → queued listener dispatches a `CardStateChanged`/`FinCardKycStatusChanged`/`FinCardAccountFunded` broadcast on `private-user.{userId}` and calls `PushNotificationService::sendToUser(...)` (respecting notification preferences), matching the wallet/payments pattern.

A separate **mobile-developer spec** (`docs/mobile/FINCARD_CARD_INTEGRATION.md`) documents request/response bodies, error codes, socket events, and the end-to-end flow for the app team.

---

## 11. Config, env & ops

`config/cardissuance.php` `issuers.fincard`:

```php
'fincard' => [
    'driver'              => 'fincard',
    'base_url'            => env('FINCARD_BASE_URL', 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual'),
    'tenant_id'           => env('FINCARD_TENANT_ID'),
    'org_id'              => env('FINCARD_ORG_ID'),
    'user_id'             => env('FINCARD_USER_ID'),
    'username'            => env('FINCARD_USERNAME'),
    'password'            => env('FINCARD_PASSWORD'),
    'forwarded_from'      => env('FINCARD_FORWARDED_FROM', 'zelta'),
    'webhook_public_key'  => env('FINCARD_WEBHOOK_PUBLIC_KEY', ''),
    'default_card_type_id'=> env('FINCARD_DEFAULT_CARD_TYPE_ID'),
    'default_coin_key'    => env('FINCARD_DEFAULT_COIN_KEY', 'USDT_TRC20'),
],
```

Env keys mirrored into `.env.production.example` and `.env.zelta.example` (a `# FinCard` block). `OpsVerifyEnvCommand::checkConditional()` gains a FinCard block: when `CARD_ISSUER=fincard`, FAIL if `tenant_id`/`username`/`password`/`webhook_public_key` are empty (matching the HyperSwitch conditional). `CardIssuanceServiceProvider::boot()` logs a production warning when the webhook public key is missing.

---

## 12. Security & compliance

- **PCI:** PAN/CVV from `card/info/sensitive` are relayed to mobile ephemerally over TLS and **never persisted** (not even encrypted-at-rest); no logging of card bodies. If FinCard offers a hosted/tokenized sensitive-data channel, prefer it (open item).
- **PII:** KYC attributes live only in the encrypted `verification_data` blob; ID images are never stored (only transient `fileId`s). Webhook/RPC logging is restricted to `path/code/msg`, never bodies.
- **Custody note:** funding a card moves value from the user's self-custody wallet into FinCard's custody — inherent to cards and made explicit in-product. Until deposit, funds remain non-custodial.
- **Idempotency & races:** `merchantOrderNo` on every mutating FinCard call; `processed_webhook_events` on inbound; `lockForUpdate` on balance mutations; cross-connection credit kept out of the claim transaction.
- **Restricted countries** checked pre-submission.

---

## 13. Delivery phases (one PR each)

1. **Foundations** — `FinCardClient` (auth/session/RPC/context headers), `FinCardApiException`, `FinCardWebhookVerifier`, config stanza + env + `ops:verify-env` conditional + provider wiring, webhook route/controller skeleton, reference-data passthrough (regions/cities/occupations/card-types/coins). Unblocked against sandbox Playground. *(This session.)*
2. **KYC / cardholder** — file upload, Cardholder-V2 create, approval webhooks + state machine, mobile onboarding/KYC endpoints, prefill, restricted-country check.
3. **Account + crypto funding** — per-user account provisioning, deposit-address create, wallet `DEPOSIT`/`WITHDRAW` webhook credit, balance endpoints.
4. **Card lifecycle** — open/get/sensitive/balance/freeze/unfreeze/cancel/top-up/withdraw + transaction sync (purchase/auth/fee/3DS) + realtime/push.
5. **Fiat funding + polish** — Bridge→FinCard treasury path (pending the fiat-rail confirmation), Filament admin visibility, OpenAPI regen, reconciliation cron, docs.

---

## 14. Open dependencies (to confirm with FinCard — none block phases 1–2)

1. Production webhook **header name** (`X-FC-SIGNATURE` vs `X-WSB-SIGNATURE`) and the **RSA public key** for verification.
2. FinCard-PaaS **business error `code` catalog** (the `code` integer values and meanings).
3. Authoritative **supported-coin list**, min/max deposit, and **FX rate methodology** (pull live from `wallet/v2/coins`; confirm FX).
4. Whether an account can be **funded with fiat directly** (determines whether the fiat path needs the stablecoin hop).
5. **Upstream issuer / BIN sponsor identity** and the regulatory license under which cards are issued.
6. Formal confirmation that FinCard **performs its own KYC** (no third-party passthrough) and the review SLA.
7. Production **credentials** (org/user/tenant IDs, service-account username/password) and Integration-tenant onboarding.

---

## 15. Testing strategy

- **Unit:** `FinCardClient` envelope decode + error mapping + token caching/re-login (HTTP faked); `FinCardWebhookVerifier` RSA verify (fixture keypair) incl. both header names and the fail-closed-in-prod path; status/network mapping.
- **Feature:** mobile endpoints (auth, validation, envelopes, idempotency, KYC gating) with the FinCard client faked.
- **Webhook:** signed-payload dedupe + each event category's state transition; replay returns duplicate; malformed/wrong-signature → 401.
- **MultiConnection:** account-credit + card-balance writes against real MySQL (tenant connection), asserting no cross-connection deadlock and the `account_balances.asset_code → assets.code` FK is seeded.
- **Money:** bcmath/cents assertions; no float paths.
```
