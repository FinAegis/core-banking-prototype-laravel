# Threat Model — FinAegis/Zelta v7.15.0

**Methodology**: STRIDE (Spoofing, Tampering, Repudiation, Information Disclosure, Denial of Service, Elevation of Privilege)
**Prepared**: 2026-03-28
**Updated**: 2026-06-11 (v7.15.0 — added flows 6–9: public MCP server, non-custodial wallet send, IAP verify, Bridge.xyz ramp)
**Scope**: Top 9 critical data flows (flows 1–5 original; 6–9 added for the post-v7.10 surfaces)

---

## 1. Payment Authorization (JIT Funding)

**Flow**: Card network sends authorization request -> `JitFundingService` -> balance check -> spend limit check -> hold creation -> approve/decline response. Latency budget: 2000ms.

**Entry points**: Card issuer webhook endpoint (Marqeta/Rain adapter), internal API calls.

### Spoofing
- **Risk**: Attacker impersonates card issuer by sending fake authorization requests.
- **Mitigation**: `ValidateWebhookSignature` middleware validates Marqeta webhooks via Basic Auth + optional HMAC-SHA256. Stripe-style timestamp tolerance (5 minutes) prevents delayed replays. All validation uses `hash_equals()` for constant-time comparison.
- **Gap**: Marqeta HMAC signature is optional (`if ($secret !== null && $secret !== '' && $signature !== null)`). If HMAC secret is not configured, Basic Auth alone protects the endpoint. Recommend making HMAC mandatory in production.

### Tampering
- **Risk**: Modification of authorization amount between balance check and hold creation. Amount field manipulation in transit.
- **Mitigation**: `AuthorizationRequest` is a value object with immutable properties. Amount is passed through as `getAmountDecimal()`. Card token is validated against issuer records.
- **Gap**: Balance check and hold creation are not wrapped in a database transaction with `lockForUpdate()`. Under concurrent requests for the same card/user, a TOCTOU race condition could approve two authorizations against the same balance. This is the highest-severity finding in this flow.

### Repudiation
- **Risk**: Disputed transactions without audit trail.
- **Mitigation**: All authorization decisions (approved/declined) dispatch domain events (`AuthorizationApproved`, `AuthorizationDeclined`). Structured logging captures authorization_id, amount, merchant, latency. Event sourcing provides immutable audit trail.
- **Gap**: None identified. Event sourcing and structured logging provide comprehensive non-repudiation.

### Information Disclosure
- **Risk**: Leakage of card tokens, user balances, or hold IDs in logs or error responses.
- **Mitigation**: Log entries include authorization_id and hold_id but not raw card numbers. Error responses return generic decline reasons without internal state. `JitFundingService` catches exceptions and returns 0.0 balance on failure (fail-closed).
- **Gap**: `card.metadata['user_id']` is accessed without null-safe operator — if metadata is malformed, the empty string fallback propagates silently. Not a direct disclosure risk but could mask issues.

### Denial of Service
- **Risk**: Flood of authorization requests exhausting balance lookup or hold creation capacity, causing legitimate transactions to time out beyond 2000ms budget.
- **Mitigation**: `ApiRateLimitMiddleware` on webhook routes. Card issuer IP allowlisting can be configured via `IpBlocking` middleware. Metrics tracking via `MetricsService` enables alerting on latency spikes.
- **Gap**: No per-card-token rate limiting. A compromised card could generate unlimited authorization requests. Consider implementing card-level throttling.

### Elevation of Privilege
- **Risk**: Bypassing spend limits or using another user's balance for authorization.
- **Mitigation**: `SpendLimitEnforcementService.checkLimit()` validates per-card-token limits before approval. Balance is looked up by `user_uuid` from the card's metadata, not from the request payload.
- **Gap**: The `user_id` used for balance lookup comes from `$card->metadata['user_id']`. If card metadata can be tampered at the issuer level, an attacker could reference another user's balance. This trust boundary should be documented.

---

## 2. Cross-Tenant Isolation

**Flow**: Authenticated user -> `InitializeTenancyByTeam` middleware -> team membership verification -> tenant context initialization -> tenant-scoped data access throughout request lifecycle. Terminated after response.

**Entry points**: All API routes behind `auth:sanctum` + `tenant` middleware, GraphQL endpoint, WebSocket channels.

### Spoofing
- **Risk**: User spoofs team membership to access another tenant's data.
- **Mitigation**: `InitializeTenancyByTeam` explicitly verifies team ownership (`ownsTeam()`) and membership (`belongsToTeam()`) before initializing tenant context. Uses Laravel Jetstream's relationship checks backed by `team_user` pivot table.
- **Gap**: The middleware allows pass-through for unauthenticated users (`if (!$user) return $next($request)`). Routes behind this middleware must also require `auth:sanctum` — if middleware ordering is incorrect, unauthenticated requests could bypass tenant checks.

### Tampering
- **Risk**: Manipulating the `currentTeam` relationship to switch tenant context mid-session.
- **Mitigation**: `currentTeam` is resolved from the user's `current_team_id` column, which requires an API call to switch. The middleware runs on every request, re-verifying membership. Tenant context is terminated in `terminate()` after each response.
- **Gap**: If `current_team_id` can be mass-assigned on the User model, an attacker could update it via profile update endpoints. Verify that `current_team_id` is not in `$fillable` or is protected by Jetstream's team switching logic.

### Repudiation
- **Risk**: Cross-tenant actions performed without attribution.
- **Mitigation**: `logTenancyEvent()` logs all tenancy events with user_id, team_id, IP, user_agent, and full URL. Unauthorized access attempts are logged at WARNING level. Rate-limited events are also logged.
- **Gap**: None identified. Comprehensive audit logging is in place.

### Information Disclosure
- **Risk**: Data from one tenant leaking into another tenant's queries due to missing tenant scope.
- **Mitigation**: Stancl/Tenancy provides database-level isolation (separate databases or scoped queries). `UsesTenantConnection` trait forces models to use tenant-scoped database connections.
- **Gap**: All 56 domain modules must correctly use tenant-scoped connections. Any model that accidentally uses the central database connection could leak cross-tenant data. This should be verified systematically during the audit — query all domain models for `UsesTenantConnection` trait usage versus direct `Model` extension.

### Denial of Service
- **Risk**: Excessive tenant lookup requests causing database pressure or cache exhaustion.
- **Mitigation**: Per-user rate limiting: 60 attempts per minute on tenant lookups with `RateLimiter::tooManyAttempts()`. Returns 429 when exceeded.
- **Gap**: The rate limit key is `tenant_lookup:{user_id}`. This is per-user, not per-IP, so it requires authentication first. Unauthenticated flood attempts would not be rate-limited by this middleware (but would be caught by `IpBlocking` in the API middleware group).

### Elevation of Privilege
- **Risk**: Regular user accessing admin-only features within a tenant, or team member accessing data beyond their team role.
- **Mitigation**: Admin endpoints use `require.2fa.admin` middleware. Team roles are enforced by Jetstream. The tenant middleware only checks membership, not role — role enforcement happens at the controller/policy level.
- **Gap**: The `$allowWithoutTenant` static flag defaults to `false` but is publicly writable. If any code sets this to `true`, the security check is globally bypassed for all subsequent requests in that process. This should be made private or enforced via config.

---

## 3. Cross-Chain Bridge Initiation

**Flow**: User requests bridge quote -> `BridgeOrchestratorService` queries adapters (Wormhole, Circle CCTP) -> user selects quote -> `initiateBridge()` validates quote expiry -> adapter executes bridge -> `BridgeTransactionInitiated` event -> `BridgeTransactionTracker` monitors completion.

**Entry points**: REST API cross-chain endpoints, GraphQL crosschain schema.

### Spoofing
- **Risk**: Attacker submits bridge request with spoofed sender/recipient addresses.
- **Mitigation**: `auth:sanctum` required on all cross-chain endpoints. Sender address should be verified against user's registered wallet addresses.
- **Gap**: `initiateBridge()` accepts `senderAddress` and `recipientAddress` as plain strings. There is no on-chain ownership verification that the `senderAddress` belongs to the authenticated user. An attacker could initiate a bridge from someone else's address if they know the address (which are public on-chain). The adapter layer must enforce ownership.

### Tampering
- **Risk**: Quote manipulation between quote retrieval and bridge initiation — changing amount, fee, or destination chain.
- **Mitigation**: `BridgeQuote` is a value object. Quote expiry is checked via `$quote->isExpired()` before initiation. Quote ID ties the execution to the original quote parameters.
- **Gap**: The quote object is reconstructed from the client's request, not fetched from a server-side cache by quote ID. If the client sends a modified quote object, the adapter might accept tampered parameters. Quotes should be stored server-side and referenced by ID only.

### Repudiation
- **Risk**: Disputed bridge transactions (funds stuck in limbo between chains).
- **Mitigation**: `BridgeTransactionInitiated`, `BridgeTransactionCompleted`, and `BridgeTransactionFailed` events are dispatched. Transaction ID is logged with full details (provider, chains, token, amount, addresses). `BridgeTransactionTracker` monitors status.
- **Gap**: None identified for logging. However, the recovery process for failed bridges (funds locked on source chain but not released on destination) should be documented as a manual procedure for the security team.

### Information Disclosure
- **Risk**: Leakage of private keys, bridge adapter credentials, or internal RPC endpoints.
- **Mitigation**: Adapter credentials stored in config (not in code). Logs include transaction details but not private keys. `EthRpcClient` uses circuit breaker pattern, which does not leak RPC URLs in error responses.
- **Gap**: Bridge quote responses may include internal adapter details (provider names, fee structures) that could aid an attacker in selecting more favorable manipulation vectors.

### Denial of Service
- **Risk**: Flooding the bridge quote endpoint to exhaust adapter rate limits, causing legitimate bridge requests to fail.
- **Mitigation**: `ApiRateLimitMiddleware` on API routes. Each adapter is queried in sequence with try-catch (failed adapters are skipped).
- **Gap**: No per-user bridge operation throttling. An attacker could exhaust Wormhole/CCTP API rate limits for all users by requesting many quotes. Consider per-user bridge operation rate limiting.

### Elevation of Privilege
- **Risk**: Unauthorized bridge initiation or manipulation of bridge parameters to redirect funds.
- **Mitigation**: Authentication required. Bridge amount comes from the quote, which was computed by the adapter.
- **Gap**: No separate authorization check verifying the user has permission to bridge the requested amount. Spend limits apply to cards but not to bridge operations. Consider implementing bridge-specific value limits.

---

## 4. ZK Proof Generation and Verification

**Flow**: Client submits private/public inputs -> `SnarkjsProverService` resolves circuit -> writes inputs to temp file -> runs snarkjs CLI as subprocess -> reads proof/public signals -> verifies proof -> returns `ZkProof` value object. Also: `OnChainVerifierService` for Solidity verification, `ZkKycService` for privacy-preserving KYC.

**Entry points**: Privacy API endpoints, GraphQL privacy schema, internal service calls.

### Spoofing
- **Risk**: Attacker submits proof generation request impersonating another user to generate proofs for their data.
- **Mitigation**: `auth:sanctum` on privacy endpoints. Private inputs should be derived from authenticated user's data, not from request payload.
- **Gap**: The `generateProof()` method accepts `$privateInputs` and `$publicInputs` as generic arrays. If private inputs are passed directly from the HTTP request, an attacker could forge proofs for arbitrary data. Verify that private inputs are server-derived, not client-supplied.

### Tampering
- **Risk**: Manipulation of circuit files or trusted setup parameters (toxic waste) to create a backdoor allowing proof forgery.
- **Mitigation**: `TrustedSetupService` manages ceremony. Circuit files stored in `storage/app/circuits/`. `validateCircuitFiles()` checks file existence. `SrsManifestService` tracks circuit manifest integrity.
- **Gap**: Circuit files on the filesystem could be tampered if storage is compromised. Consider adding hash verification of circuit files (zkey, wasm, vkey) against a known-good manifest before each proof generation. The current `validateCircuitFiles()` checks existence only, not integrity.

### Repudiation
- **Risk**: Denial of having generated a specific proof, or claiming a valid proof was not accepted.
- **Mitigation**: Proof generation is logged with circuit name, constraint count, and timing. `ZkProof` value objects include proof ID, type, and metadata.
- **Gap**: Proofs should be stored with cryptographic binding to the generating user. Currently, the proof object does not include a user identifier or timestamp signature.

### Information Disclosure
- **Risk**: Leakage of private inputs (the entire purpose of ZK proofs is to keep these secret).
- **Mitigation**: Private inputs are written to temporary JSON files, used for proof generation, then presumably cleaned up. Proof of Innocence service uses `random_bytes()` for nonces.
- **Gap**: Temporary files containing private inputs (`input.json`) must be securely deleted after proof generation. Verify that `SnarkjsProverService` cleans up temp files in a `finally` block. If the snarkjs process crashes, temp files with private inputs may persist on disk. Use `tmpfile()` or secure deletion.

### Denial of Service
- **Risk**: Proof generation is CPU-intensive (snarkjs runs as subprocess with 120-second timeout). An attacker could exhaust server resources by requesting many proofs simultaneously.
- **Mitigation**: Configurable timeout via `privacy.zk.snarkjs_timeout_seconds` (default 120s). Symfony Process is used for subprocess management.
- **Gap**: No concurrency limit on proof generation. Each proof spawns a Node.js subprocess. Without a semaphore or queue-based throttling, parallel requests could exhaust CPU/memory. Consider routing proof generation through a dedicated queue with bounded workers.

### Elevation of Privilege
- **Risk**: Using proof generation to execute arbitrary commands via the snarkjs CLI.
- **Mitigation**: Circuit names are resolved through `$circuitMapping` config, not from user input. The snarkjs binary path is config-driven.
- **Gap**: If `$circuitMapping` or `$circuitDirectory` contain user-influenced values, path traversal could lead to arbitrary file read/write via the snarkjs process. Verify that circuit resolution is strictly config-based with no user input in file paths.

---

## 5. Webhook Processing (Outbound and Inbound)

**Flow (Outbound)**: Domain event -> `WebhookService::dispatch()` -> find active webhooks -> create `WebhookDelivery` record -> queue `ProcessWebhookDelivery` job -> HTTP POST with HMAC signature -> retry on failure (exponential backoff: 1m, 5m, 15m) -> mark delivered/failed.

**Flow (Inbound)**: External service sends webhook -> `ValidateWebhookSignature` middleware -> provider-specific validation (Stripe, Coinbase, Paysera, Santander, Open Banking, Marqeta) -> controller processes payload.

**Entry points**: Inbound webhook routes (6+ providers), outbound delivery to customer-configured URLs.

### Spoofing
- **Risk (Inbound)**: Attacker sends fake webhooks impersonating Stripe/Coinbase/etc. to trigger unauthorized actions (fake payment confirmations, fake card events).
- **Mitigation**: Provider-specific signature validation. Stripe: timestamp + HMAC-SHA256 with `v1` signature. Coinbase: HMAC-SHA256 on payload. Santander: timestamp + HMAC-SHA512. Marqeta: Basic Auth + optional HMAC. All use `hash_equals()`.
- **Gap**: Open Banking webhook validation uses session-based `state` parameter comparison (`session('openbanking_state')`). This is fragile — sessions may expire between redirect and callback, and session fixation attacks could bypass this. Recommend switching to a database-persisted state with expiry.

- **Risk (Outbound)**: Customer configures webhook URL pointing to internal services (SSRF via webhook delivery).
- **Mitigation**: `Http::withHeaders()->timeout($webhook->timeout_seconds)->post($webhook->url, ...)` — standard Laravel HTTP client.
- **Gap**: No URL validation against internal/private IP ranges. A customer could set `webhook.url` to `http://169.254.169.254/` (AWS metadata), `http://localhost:6379/` (Redis), or internal service endpoints. This is a critical SSRF vector. Implement URL allowlisting or block private IP ranges.

### Tampering
- **Risk (Inbound)**: Modification of webhook payload in transit to change payment amounts or transaction status.
- **Mitigation**: HMAC signatures cover the entire payload body. Timestamp tolerance (5 minutes) prevents replay of old payloads. Signatures are validated before any business logic processes the payload.
- **Gap**: Coinbase and Paysera signatures do not include a timestamp component — they sign only the payload. A captured valid signature+payload pair can be replayed indefinitely. Implement per-provider replay protection using delivery ID tracking.

- **Risk (Outbound)**: Man-in-the-middle modification of outbound webhook delivery.
- **Mitigation**: `X-Webhook-Signature` header generated via `WebhookService::generateSignature()` using the webhook's secret. Customers can verify signatures on their end.
- **Gap**: No enforcement that webhook URLs use HTTPS. Outbound webhooks over HTTP would expose payload and signature in transit. Enforce HTTPS-only webhook URLs.

### Repudiation
- **Risk**: Disputes over whether a webhook was delivered or what it contained.
- **Mitigation**: `WebhookDelivery` model records event_type, payload, status, status_code, response_body, response_headers, duration_ms, and attempt_number. Delivery lifecycle: pending -> delivered/failed. Structured logging at INFO/ERROR level.
- **Gap**: None identified. The delivery record system provides comprehensive non-repudiation.

### Information Disclosure
- **Risk (Outbound)**: Webhook payloads may contain sensitive financial data sent to customer-controlled URLs over insecure channels.
- **Mitigation**: Webhook secret enables customers to verify authenticity. `User-Agent` header identifies the service.
- **Gap**: Payload construction in `WebhookService::dispatch()` uses `array_merge($payload, ...)` without filtering sensitive fields. If upstream services include PII, card numbers, or internal IDs in the payload, these are forwarded to the webhook URL. Implement a payload sanitization layer.

- **Risk (Inbound)**: Webhook error logs may contain sensitive payload data.
- **Mitigation**: Logs include webhook_id and delivery_id, not full payloads.
- **Gap**: The `$request->getContent()` used in signature validation contains the full webhook body. If logging level is DEBUG, framework middleware could log the raw request body. Ensure log levels in production do not include raw webhook payloads.

### Denial of Service
- **Risk (Inbound)**: Flood of webhook requests exhausting processing capacity.
- **Mitigation**: `api.rate_limit:webhook` middleware on all inbound webhook routes.
- **Gap**: Webhook processing may trigger expensive downstream operations (database writes, external API calls). Rate limiting at the HTTP layer may not prevent queue exhaustion if many valid webhooks arrive in a burst. Consider per-provider queue isolation.

- **Risk (Outbound)**: Customer webhook endpoint is slow or unresponsive, causing queue backlog.
- **Mitigation**: Configurable timeout per webhook (`$webhook->timeout_seconds`). Exponential backoff (1m, 5m, 15m). Job retries allowed for 24 hours then marked as permanently failed.
- **Gap**: No circuit breaker for persistently failing webhook endpoints. A misconfigured webhook could generate retries indefinitely for 24 hours, consuming queue capacity. Implement automatic webhook deactivation after N consecutive failures.

### Elevation of Privilege
- **Risk (Inbound)**: Crafted webhook payload triggers unintended business logic (e.g., fake payment confirmation approving a card issuance).
- **Mitigation**: Signature validation ensures payload authenticity. Controllers should verify the event type and cross-reference with internal state (e.g., confirm a pending payment exists before marking it complete).
- **Gap**: Verify that all webhook controllers perform state validation (checking that the referenced entity exists and is in the expected state) rather than blindly trusting the webhook payload content. A valid-signature webhook with a crafted payment ID could reference a different user's payment if entity-level authorization is missing.

- **Risk (Outbound)**: Webhook subscriber escalates privileges by manipulating the webhook registration to receive events from other tenants.
- **Mitigation**: Webhook model should be tenant-scoped, ensuring subscribers only receive events for their tenant.
- **Gap**: Verify that `Webhook::active()->forEvent()` query is tenant-scoped. If the Webhook model does not use `UsesTenantConnection` or a global scope, a webhook registered by Tenant A could receive events from Tenant B.

---

## 6. Public MCP Server (v7.11.0+)

**Flow**: AI agent discovers `https://mcp.zelta.app/mcp` -> Dynamic Client Registration at `/oauth/register` -> OAuth authorization (Laravel Passport) -> bearer token -> JSON-RPC `tools/call` -> `McpOAuthGuard` resolves token + `Auth::shouldUse('api')` -> scope check -> tool execution (some tools move money: `payments:write`, `ramp:write`, `sms:send`).

**Entry points**: `mcp.zelta.app` subdomain routes (`app/Domain/MCP/Routes/api.php`, minimal middleware — no CSRF, no Sanctum, no web session), `/oauth/register` DCR endpoint.

### Spoofing
- **Risk**: Rogue MCP client impersonates a legitimate one; anonymous DCR is by design, so client identity is weak.
- **Mitigation**: User consent happens at OAuth authorization time; tokens are user-bound; redirect-URI validation is strict (literal `127.0.0.1` per RFC 8252 §7.3, `http://localhost` rejected).
- **Gap**: Client metadata (name) is self-asserted at DCR — a client named "Claude" can be anyone. Consent screens must not imply verified client identity.

### Tampering
- **Risk**: Tool-call parameter manipulation by a prompt-injected agent (the *authorized* client sends attacker-chosen amounts/recipients).
- **Mitigation**: Per-user daily spending limit (`MCP_DEFAULT_DAILY_LIMIT_MINOR`, default $500) on payment-class tools; amounts converted major→minor via bcmath; `is_write` tools annotated for client-side confirmation hints.
- **Gap**: `exchange.trade` is deliberately not counted against the spending limit (documented in `config/mcp.php`) — a prompt-injected agent can churn trades without tripping a cap. Re-evaluate when exchange goes beyond idempotent quotes.

### Repudiation
- **Risk**: Disputed agent-initiated payments ("I never told my agent to do that").
- **Mitigation**: Passport token + user binding on every call; payment tools route through the same domain sagas as first-party clients (event-sourced audit trail).
- **Gap**: The JSON-RPC layer should retain request-level logs (tool name, params hash, client id) long enough for dispute windows; verify retention.

### Information Disclosure
- **Risk**: Read-scope tools (`accounts:read`, `transactions:read`) exfiltrate financial history to a hostile agent runtime once the user consents.
- **Mitigation**: Scope-gated catalog; per-tool kill-switches (`MCP_TOOL_*`); `WWW-Authenticate: Bearer resource_metadata=...` on 401 keeps discovery within spec.
- **Gap**: Consent is all-or-nothing per scope; no per-tool consent granularity. Document this trust boundary for users — once an agent holds `accounts:read`, every read tool in that scope is reachable.

### Denial of Service
- **Risk**: Anonymous DCR + `tools/list` are reachable pre-auth; a flood exhausts Passport/registration or hot tools exhaust downstream domains.
- **Mitigation**: `config('mcp.php')` `rate_limits` block; per-tool kill-switches disable a hot tool without a deploy; the per-user spending limit caps payment-tool value.
- **Gap**: Verify DCR and the token endpoint are rate-limited independently of authenticated tool calls. The spending limit bounds value, not raw call volume on read tools.

### Elevation of Privilege
- **Risk**: Scope escalation — a token granted `transactions:read` invokes `payments:write`; or the spending limit is evaded per-client.
- **Mitigation**: Scopes are snake_case with a `:` separator; `Scope::can()` is case-sensitive and checked per tool. The spending limit is keyed per user.
- **Gap**: Verify scope strings match exactly (no prefix/substring match) and that the daily spending limit binds per-user, not per-OAuth-client — one user with many DCR clients must not get N× the limit. `exchange.trade` being uncounted (above) is the known carve-out.

---

## 7. Non-Custodial Wallet Send (Privy Bridge + Prepare/Submit) (v7.12.0+)

**Flow**: Privy issues a session JWT -> `POST /api/v1/auth/privy-login` -> `PrivyJwtVerifier` validates the ES256 signature against cached JWKS + `iss`/`aud`/expiry -> `firstOrCreate(privy_user_id)` -> Sanctum token. Send: `POST /api/v1/wallet/transactions/prepare` builds the unsigned Solana message / ERC-4337 UserOp hash (Pimlico-sponsored) and writes a `pending` `wallet_send_record` -> device signs -> `POST /api/v1/wallet/transactions/submit` -> backend co-signs (Solana fee payer at account index 0) and broadcasts.

**Entry points**: `/api/v1/auth/privy-login`, `/api/v1/wallet/addresses`, `/api/v1/wallet/transactions/{prepare,submit}`.

### Spoofing
- **Risk**: Forged Privy JWT, alg-confusion, or a token minted for a different Privy app exchanged for a Sanctum session.
- **Mitigation**: `PrivyJwtVerifier` verifies the ES256 signature against Privy's JWKS (cached `privy:jwks`) and enforces `iss === config('privy.issuer')`, `aud === config('privy.app_id')`, expiry. `GuzzleHttp\ClientInterface` must be bound or the verifier 500s.
- **Gap**: JWKS is cached — verify a stale-key window cannot accept a key Privy has rotated out, and that `alg` is pinned to ES256 (no `none`/HS256-against-public-key confusion).

### Tampering
- **Risk**: Changing recipient/amount between prepare and submit, or submitting another user's `pending` record.
- **Mitigation**: The device signs the exact opaque bytes the backend built; Solana two-signer message is `[sponsor, sender]`, sponsor co-signs the SAME bytes. `submit` requires status `pending` and rejects `submitted`/`confirmed`/`failed`.
- **Gap**: Verify `submit` authorizes the `wallet_send_record` against the authenticated user (record from user A cannot be submitted by user B) and that the device signature is verified against the sender key before broadcast.

### Repudiation
- **Risk**: User disputes a send they signed.
- **Mitigation**: `wallet_send_record` tracks status + `tx_hash`; confirmation reconciled by `PollEvmWalletSendConfirmations` / `HeliusTransactionProcessor`. The on-chain signature is non-repudiable.
- **Gap**: None material — the device-held key signs. Account-recovery (Privy passkey) is the dispute boundary.

### Information Disclosure
- **Risk**: Backend exposure of key material.
- **Mitigation**: Backend never holds the user's private key (Privy + device). Only the platform Solana **sponsor** secret (`WALLET_SOLANA_SPONSOR_SECRET_KEY`) is server-side, used solely as fee payer.
- **Gap**: Verify the sponsor secret loads only from the secret manager (never logged) and that `wallet:inspect-user`/logs never print signatures or the sponsor key.

### Denial of Service
- **Risk (financial DoS)**: Sponsored sends cost real gas/SOL — a scripted account or spike drains the Pimlico paymaster / sponsor account.
- **Mitigation**: `enforceSendRateLimit` caps per-user (30/day) + global (5000/day) at prepare time via UTC-day cache counters (`Cache::add`+`increment`), returning 429; `solana:check-sponsor-balance` alerts hourly; L1 disabled by default.
- **Gap**: Counters live in cache — a `cache:clear`/Redis flush resets the day's counts and reopens the window. Only a count ceiling, no hard global value ceiling.

### Elevation of Privilege
- **Risk**: Registering another user's address, or steering `firstOrCreate(privy_user_id)` into a victim account.
- **Mitigation**: `POST /api/v1/wallet/addresses` writes against the authenticated user's `uuid` (EVM lowercased, Solana case-sensitive).
- **Gap**: Verify `privy_user_id` is an immutable identity key (no path lets a second Privy identity claim an existing row) and that address registration cannot overwrite another user's active row.

---

## 8. IAP Receipt Verification (Apple / Google) (v7.13.0+)

**Flow**: Mobile submits a store receipt -> `POST /api/v1/subscription/iap/verify` -> currency/plan gates -> `AppleReceiptVerifier` (JWS x5c chain pinned to Apple Root CA G3) / `GooglePlayReceiptVerifier` (Play Developer API) -> Family Sharing + account-binding + multi-store conflict gates -> persist `iap_subscriptions` + `iap_receipts` + revenue outbox. Store webhooks (Apple V2 JWS, Google RTDN OIDC) dedupe via `processed_webhook_events`.

**Entry points**: `/api/v1/subscription/iap/verify` (Sanctum, `throttle:10,1`), `/api/webhooks/apple/notifications`, `/api/webhooks/google/play`.

### Spoofing
- **Risk**: Self-crafted receipt forges a Pro entitlement; fake store webhook.
- **Mitigation**: Apple JWS x5c chain validated to the pinned Apple Root CA G3 fingerprint; Google tokens verified against the Play Developer API; Google RTDN requires an `accounts.google.com` OIDC bearer (`aud` checked); Apple webhooks re-validate the chain.
- **Gap**: `APPLE_JWS_VERIFICATION_BYPASS=true` is a full auth-bypass for the entire Apple IAP surface — ignored + logged ERROR in production and FAILed by `ops:verify-env`, but verify the `app()->environment('production')` gate cannot be sidestepped via env/config-cache. Highest-severity finding in this flow.

### Tampering
- **Risk**: Altering receipt fields (product id, amount, expiry) for a higher tier / longer period.
- **Mitigation**: Verified facts come from the JWS payload / Play API response, not the request body; plan resolved from the verified `productId`; amounts in minor units.
- **Gap**: Verify the persisted period/expiry derives strictly from the verified transaction, never a client-supplied envelope field.

### Repudiation
- **Risk**: Disputes over what was purchased / refunded.
- **Mitigation**: `iap_subscription_events` append-only log; `iap_receipts` stores the verified receipt; revenue outbox idempotent on `(source_type, source_event_id, event_type)`; refunds emit negative-amount rows (ADR-0004).
- **Gap**: None material.

### Information Disclosure
- **Risk**: Leakage of raw receipts / original transaction ids in logs or after GDPR erasure.
- **Mitigation**: `IapReceiptPseudonymiser` HMAC-pseudonymises (`IAP_RECEIPT_PEPPER`); GDPR erasure nulls raw ids, keeping only the HMAC hash for post-erasure webhook matching.
- **Gap**: The pepper is one-way and **must never be rotated** — rotation (or an empty pepper) permanently breaks post-erasure webhook lookups. `pseudonymise()` hard-throws on an empty pepper in prod/staging; verify it lives in the secret manager and is never logged.

### Denial of Service
- **Risk**: Receipt-verification flood (each call fans out to Apple/Google + writes revenue rows).
- **Mitigation**: `throttle:10,1` per user on `/iap/verify`; store webhooks return 200 even on error (controllers log + acknowledge) so transient failures don't trigger infinite store retries against expensive logic.
- **Gap**: Verify the outbox sweep (`ProjectRevenueOutbox`, every 5 min, `attempts < max`) cannot wedge on a poison row.

### Elevation of Privilege
- **Risk**: Replaying a valid receipt against a different Zelta account to grant it Pro.
- **Mitigation**: Account binding — Apple `appAccountToken` must equal `users.uuid`; Google `obfuscatedExternalAccountId` must equal `sha256(users.uuid)`; an id owned by another user yields ERR_SUB_002 `different_zelta_user`.
- **Gap**: When the account token is **absent**, the binding is accepted (Sanctum bearer is the fallback). Verify a token-less receipt cannot be replayed across accounts before the per-original-transaction-id ownership check catches it.

---

## 9. Bridge.xyz Ramp (Webhook + VA Provisioning + Dev Fee) (v7.15.0+)

**Flow**: User completes Bridge KYC -> `customer.kyc_link_completed` webhook -> auto-provision a virtual account if a Polygon address exists, else `BlockchainAddressBridgeObserver` retries on address registration. Fiat deposit -> `virtual_account.activity` / `transfer.*` webhook -> credited. `SubscriptionTierChanged` -> PATCH `developer_fee_bps` (Free=75 / Pro=0, the 0.75% Zelta markup). Single webhook endpoint, asymmetric-signature verified.

**Entry points**: `POST /api/v1/webhooks/bridge`, `GET /api/v1/user/bridge-setup-status`, `POST /api/v1/user/bridge-kyc-link`.

### Spoofing
- **Risk**: Forged webhook (fake KYC approval / fake deposit) impersonating Bridge.
- **Mitigation**: `BridgeWebhookVerifier` verifies `X-Webhook-Signature: t=<unix_ms>,v0=<base64>`, RSA-SHA256 over `<t>.<body>` against `BRIDGE_WEBHOOK_PUBLIC_KEY`; legacy HMAC `v1` auto-detected as fallback. The empty-credentials dev passthrough fires only when both creds are empty AND non-production; production with no credential 401s every webhook.
- **Gap**: The asymmetric scheme (timestamp units, RSA padding) is **unverified against real live bytes** — confirm against a real sandbox event before go-live. `ops:verify-env` FAILs when Bridge is active with no webhook credential.

### Tampering
- **Risk**: Altering a `virtual_account.activity` amount, or replaying a captured webhook.
- **Mitigation**: Signature covers `<t>.<body>`; event-level dedupe via `processed_webhook_events (provider='bridge', event_id)`; credited amount derives from the verified payload tied to the user's `virtual_account_id`.
- **Gap**: Replay-window/timestamp tolerance is not yet confirmed against live payloads (units unverified). Verify a captured valid webhook cannot be replayed outside a tight window and that Bridge `event_id` reuse is handled (logged "duplicate event ignored").

### Repudiation
- **Risk**: Disputes over deposits / VA state.
- **Mitigation**: `ramp_sessions` (incl. retroactive `bridge_initiated` rows), `bridge_customers` state, structured per-handler logs; `bridge:inspect-user` dumps the full read-only state.
- **Gap**: None material.

### Information Disclosure
- **Risk**: Leakage of bank deposit instructions or KYC PII.
- **Mitigation**: `ramp_sessions.deposit_instructions` is encrypted at rest; Bridge KYC state is partitioned in `bridge_customers.kyc_status`, separate from Ondato `users.kyc_status`.
- **Gap**: Verify the encrypted cast covers all deposit-instruction fields and that webhook metadata is whitelisted (`array_intersect_key`, no raw payload persistence).

### Denial of Service
- **Risk**: Webhook flood, or a VA-provisioning storm against the Bridge API.
- **Mitigation**: `api.rate_limit:webhook` on the endpoint; provisioning is event-driven (KYC completion / address registration), not user-pollable.
- **Gap**: Verify `BlockchainAddressBridgeObserver` retries cannot loop on address churn and exhaust Bridge API quota.

### Elevation of Privilege
- **Risk**: Zeroing the 0.75% markup (`developer_fee_bps`) without paying for Pro.
- **Mitigation**: `developer_fee_bps` is PATCHed only by the `SubscriptionTierChanged` listener (queued) and the operator command `bridge:sync-dev-fee`.
- **Gap**: Verify nothing outside the legitimate subscription path can emit `SubscriptionTierChanged` or drive a Free user's fee to 0, and that a queued-listener failure (no worker) leaves the fee at the safe Free default, not unset.

---

## Summary of Critical Findings

| # | Finding | Severity | Flow |
|---|---------|----------|------|
| 1 | JIT funding balance check + hold lacks `lockForUpdate()` — TOCTOU race condition | **Critical** | Payment Auth |
| 2 | Outbound webhook SSRF — no URL validation against private IP ranges | **Critical** | Webhook |
| 3 | Bridge quote not stored server-side — client can submit tampered quote object | **High** | Cross-Chain |
| 4 | ZK private inputs in temp files not guaranteed to be securely deleted | **High** | ZK Proofs |
| 5 | Open Banking webhook uses session-based state (fragile, session fixation risk) | **High** | Webhook |
| 6 | No concurrency limit on ZK proof generation (CPU exhaustion via subprocess spawning) | **High** | ZK Proofs |
| 7 | `$allowWithoutTenant` is publicly writable static, could be globally toggled | **Medium** | Tenant Isolation |
| 8 | Coinbase/Paysera webhooks lack timestamp — replay attacks possible | **Medium** | Webhook |
| 9 | No per-card-token rate limiting on JIT authorization requests | **Medium** | Payment Auth |
| 10 | Bridge sender address not verified against user's registered wallets | **Medium** | Cross-Chain |
| 11 | No HTTPS enforcement on outbound webhook URLs | **Medium** | Webhook |
| 12 | Outbound webhook payloads not sanitized for sensitive fields | **Medium** | Webhook |
| 13 | Circuit file integrity not cryptographically verified before proof generation | **Medium** | ZK Proofs |
| 14 | No bridge-specific value/frequency limits (card spend limits don't apply) | **Low** | Cross-Chain |
| 15 | Marqeta HMAC signature is optional when secret not configured | **Low** | Payment Auth |
| 16 | `APPLE_JWS_VERIFICATION_BYPASS` is a full IAP auth-bypass — verify the production gate cannot be sidestepped | **Critical** | IAP Verify |
| 17 | Bridge asymmetric webhook signature (timestamp units / RSA padding) unverified against live bytes | **High** | Bridge Ramp |
| 18 | Sponsorship caps are cache-counter based — a cache flush resets the day's per-user/global limits | **High** | Wallet Send |
| 19 | MCP `exchange.trade` is not counted against the daily spending limit (deliberate carve-out) | **Medium** | MCP Server |
| 20 | MCP daily spending limit must bind per-user, not per-OAuth-client (anonymous DCR allows many clients) | **Medium** | MCP Server |
| 21 | IAP account binding is accepted when the store account token is absent — replay-across-accounts risk | **Medium** | IAP Verify |
| 22 | Privy JWKS is cached — verify a rotated-out key cannot be accepted within the stale window | **Medium** | Wallet Send |
| 23 | Bridge `developer_fee_bps` markup zeroed by any path that can emit `SubscriptionTierChanged` | **Medium** | Bridge Ramp |

---

*Document Version: 7.15.0*
*Updated: June 11, 2026 (v7.15.0 — flows 6–9 added: public MCP server, non-custodial wallet send, IAP verify, Bridge.xyz ramp)*
