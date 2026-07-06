# FinCard Cards — Mobile Integration Spec

Audience: Zelta mobile developers (iOS / Android). This is the contract for the
crypto- and fiat-funded virtual card product backed by FinCard. It describes the
end-to-end flows, the `/api/v1/cards/*` HTTP surface, realtime events, and state
machines so the app can be built in parallel with the backend.

- **Backend design:** `docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md`
- **Status legend per endpoint:** 🟢 available now · 🟡 in progress · ⚪ planned (phase noted). Phase 1 (backend foundations) ships no new mobile endpoints; the mobile surface lands across phases 2–4. Build against this contract; treat ⚪ fields marked *(pending FinCard)* as subject to final confirmation of FinCard's response schemas.

---

## 1. Product model (what to build)

A **virtual card** the user funds from a prefunded balance and spends online. Two custody hops the UI must make legible:

- The user's **crypto stays self-custodial** in their Zelta wallet until they choose to fund a card.
- **Funding a card moves value into FinCard's custody** (a per-user "card account"). This is inherent to cards — surface it plainly at the funding step.

The user journey is four stages, each gated on the previous:

```
KYC (cardholder)  →  Fund account  →  Open card  →  Use & manage
   pass_audit         balance > 0      active         spend / freeze / topup
```

v1 is **virtual cards only**, viewed in-app (no Apple/Google Pay, no physical cards).

---

## 2. Conventions

Identical to the rest of the Zelta mobile API:

- **Auth:** `Authorization: Bearer <sanctum_token>` (the token from `/api/v1/auth/privy-login`). Every card endpoint requires it.
- **Success:** `{ "success": true, "data": { ... } }` (or `data: [ ... ]` for lists).
- **Error:** `{ "success": false, "error": { "code": "ERR_CARDS_00x", "message": "…" } }`. HTTP status carries the class (401/403/404/409/422/429). Branch on `error.code`, not the message.
- **Bodies are snake_case** (e.g. `card_id`, `coin_key`, `amount_cents`).
- **Idempotency:** POST/PATCH/DELETE require an `Idempotency-Key` HTTP header (UUID or 16–255 `[A-Za-z0-9_-]`). Reusing a key replays the first response; same key + different body → `409`.
- **Lists** return `data: [...]` plus `pagination: { next_cursor, has_more, total }`. Query with `?cursor=…&limit=…` (limit ≤ 100, default 20).
- **KYC gate:** issuance/spend endpoints require completed Zelta KYC (`require.kyc`) *and* a `pass_audit` FinCard cardholder; a missing verified cardholder returns `ERR_CARDS_007` (see §7).

---

## 3. Money & amounts

All amounts are **integer minor units** in the field's currency: `amount_cents` (USD cents for card/account balances). Never send floats. Card balances are USD (FinCard converts crypto deposits to USD on receipt). When you show a crypto deposit amount, use the wallet's native decimals; when you show a card/account balance, it's USD cents.

---

## 4. End-to-end flows

### 4.1 KYC / cardholder onboarding ⚪ (phase 2)

FinCard requires its own KYC — richer than the app's existing profile. Prefill what you can; collect the rest.

1. `GET /v1/cards/onboarding` → returns a prefilled draft (name, DOB, nationality, address from the Zelta profile) **plus the field schema** the user must complete: `occupations` list, allowed `id_types` (`PASSPORT`, `DLN`, `GOVERNMENT_ISSUED_ID_CARD`), and the required financial fields (`annual_salary`, `expected_monthly_volume`, `account_purpose`, `gender`).
2. Capture **three photos** and upload each: `POST /v1/cards/kyc/documents` (multipart, `type` = `id_front` | `id_back` | `selfie`) → returns `{ file_id }`. A selfie is mandatory.
3. `POST /v1/cardholders` with the full field set + the three `file_id`s → creates the cardholder; response `kyc_status: "in_review"`, `kyc_stage: "admin"`.
4. Poll `GET /v1/cardholders/{id}` **or** listen for the WebSocket events in §6. Approval is two-stage (`admin` → `channel`). Only `kyc_status: "verified"` (`pass_audit`) unlocks card creation. `rejected` carries `kyc_rejection_reason`.

> Restricted countries are rejected up front with `ERR_CARDS_010`. The full FinCard field list and photo requirements are *(pending FinCard)* final confirmation; `GET /v1/cards/onboarding` is the source of truth at runtime — render from it, don't hard-code the field set.

### 4.2 Funding ⚪ (phase 3 crypto, phase 5 fiat)

**Crypto (recommended, reuses the wallet):**
1. `GET /v1/cards/account/coins` → supported deposit coins (e.g. `USDT_TRC20`, `USDT_BEP20`) with `chain`, `min_deposit`, `confirmations`, `deposit_fee`. Render from this — the set is dynamic; do not assume USDC.
2. `POST /v1/cards/account/deposit-address` `{ coin_key }` → `{ address, chain, coin_key, min_deposit, confirmations }`.
3. User sends that stablecoin from their **existing Zelta wallet** to `address` (normal wallet send). Show the min amount and confirmation count.
4. On credit, the backend emits `fincard.account.funded` (§6) and the account balance updates. FinCard converts to USD (a fee applies); show that the credited USD may be less than the crypto sent.

**Fiat (phase 5):** routes through the existing Bridge ramp; exact UX TBD pending the fiat-funding rail (*open item*). Treat as a later addition.

### 4.3 Open & use a card ⚪ (phase 4)

1. `GET /v1/cards/reference/card-types` → available products (`card_type_id`, network, currency).
2. `POST /v1/cards` `{ card_type_id, amount_cents, cardholder_id }` (+ `Idempotency-Key`) → opens a card, moving `amount_cents` from the account onto the card. Response is the card object (§5.2), `status: "active"`.
3. `GET /v1/cards/{card_id}/sensitive` → **ephemeral** PAN/CVV/expiry for display or manual entry. Never cache or persist these; fetch on demand behind a biometric re-auth and clear from memory after display.
4. Manage: `POST /v1/cards/{card_id}/topup`, `/withdraw`, `/freeze`, `DELETE /{card_id}/freeze` (unfreeze), `DELETE /{card_id}` (cancel, biometric-gated).
5. `GET /v1/cards/{card_id}/transactions?type=purchase` for history (types: `purchase`, `auth`, `fee`, `3ds`, `refund`).

---

## 5. Data shapes

### 5.1 Account
```json
{ "account_id": "…", "currency": "USD", "balance_cents": 12345, "status": "active" }
```

### 5.2 Card
```json
{
  "card_id": "…",
  "last4": "4242",
  "network": "visa",
  "status": "active",
  "currency": "USD",
  "balance_cents": 5000,
  "label": "Travel",
  "expires_at": "2029-05-31",
  "created_at": "2026-07-06T12:00:00Z"
}
```
`status` ∈ `pending | active | frozen | cancelled | expired` (see §8). `network` ∈ `visa | mastercard | discover`.

### 5.3 Sensitive details (ephemeral — never persist)
```json
{ "pan": "4242424242424242", "cvv": "123", "expiry": "05/29", "cardholder_name": "JANE SMITH" }
```

### 5.4 Transaction
```json
{
  "transaction_id": "…", "card_id": "…", "type": "purchase",
  "merchant_name": "ACME", "merchant_category": "5411",
  "amount_cents": 1999, "currency": "USD", "status": "settled",
  "transacted_at": "2026-07-06T12:34:56Z"
}
```
`status` ∈ `pending | settled | declined | reversed`. Field names for FinCard-specific transaction streams (auth/fee/3ds) are *(pending FinCard)* final schema confirmation.

---

## 6. Realtime events (WebSocket)

Subscribe to the private channel `private-user.{userId}` (the same channel the app already authorizes). Card events:

| Event name | When | Payload |
|---|---|---|
| `fincard.kyc.status_changed` | KYC stage/decision changes | `{ cardholder_id, kyc_status, kyc_stage, rejection_reason? }` |
| `fincard.account.funded` | A deposit credits the account | `{ account_id, balance_cents, credited_cents, coin_key? }` |
| `card.state_changed` | Card created / frozen / cancelled / balance change | `{ card_id, status, balance_cents }` |

Push notifications (FCM/APNs) fire on the **terminal KYC states** (`verified`, `rejected`) and on **inbound funding**, mirroring the wallet's transaction-received pattern. Use the WebSocket for live in-app updates and push for background. All ⚪ phase 2–4.

---

## 7. Error codes

Card endpoints use the `ERR_CARDS_*` family (registered in `config/error_codes.php`). Codes match `^ERR_[A-Z]+_\d{3}$` (numbered, not semantic) — branch on the exact code. Cardholder/KYC codes (Phase 2) shipped are:

| Code | HTTP | Meaning / UX |
|---|---|---|
| `ERR_CARDS_007` | 403 | No verified cardholder yet → route to onboarding |
| `ERR_CARDS_008` | 409 | Identity verification still under review → show pending state |
| `ERR_CARDS_009` | 409 | A cardholder already exists for the user |
| `ERR_CARDS_010` | 422 | User's country is restricted for issuance |
| `ERR_CARDS_011` | 422 | KYC document upload failed / unsupported type |
| `ERR_CARDS_012` | 502 | Card issuer rejected the cardholder request → retry later |
| `ERR_VALIDATION_001/003` | 422 | Missing/invalid `Idempotency-Key` or body |
| `ERR_IDEMPOTENCY_409` | 409 | Same key, different body |
| (rate limit) | 429 | Too many card operations → back off |

Funding/card-lifecycle codes (`ERR_CARDS_013+`) are added as phases 3–4 land. The envelope shape (`{success:false, error:{code,message}}`) is stable; the app branches on `code` and may override the message copy.

---

## 8. Card status state machine

```
pending ──▶ active ──▶ frozen ──▶ active
                │          │
                ▼          ▼
            cancelled   cancelled     (also: active/frozen ──▶ expired)
```
`frozen` is reversible (unfreeze → `active`); `cancelled` and `expired` are terminal. FinCard's `blocked` state maps to `frozen` with a distinct reason surfaced in `card.state_changed`.

KYC: `in_review (admin)` → `in_review (channel)` → `verified` | `rejected`. Only `verified` allows opening cards.

---

## 9. What mobile needs from backend before building each screen

| Screen | Blocked on backend phase |
|---|---|
| Card onboarding / KYC | Phase 2 (`/onboarding`, `/kyc/documents`, `/cardholders`) |
| Fund with crypto | Phase 3 (`/account`, `/account/coins`, `/account/deposit-address`) |
| Card list / open / manage / sensitive | Phase 4 (`/cards*`, `/sensitive`, `/topup`, `/withdraw`) |
| Fund with fiat | Phase 5 (Bridge path) |

Each phase ships with its OpenAPI (`/api/documentation`) regenerated, so request/response schemas are always live there. When a phase merges, diff this doc against the generated spec for the authoritative field list.
