# VertexSMS + Zelta — Multi-Rail Payment Integration

**Version:** 2.0
**Date:** 2026-03-24
**Parties:** Zelta (powered by FinAegis) + VertexSMS

---

## 1. What We're Building

AI agents send SMS via VertexSMS and pay per-message. Agents choose how to pay:

| Payment Rail | How It Works | Settlement Speed |
|-------------|-------------|-----------------|
| **USDC on Base** | On-chain stablecoin transfer (x402 protocol) | ~2 seconds |
| **USDC on Ethereum** | Same, higher gas | ~12 seconds |
| **USDC on Solana** | Same, cheapest gas | ~400ms |
| **Stripe (card/fiat)** | Card or bank payment via Stripe Connect | Instant |
| **Lightning** | Bitcoin Lightning invoice | Instant |

Three protocols work together:

```
┌─────────────────────────────────────────────────────┐
│  AP2 — Authorization Layer (Google)                  │
│  "Agent X can spend up to €50/day on SMS"            │
│  Mandates, budgets, verifiable credentials           │
├─────────────────────────────────────────────────────┤
│  MPP — Payment Orchestration (Stripe)                │
│  Agent picks rail → challenge → pay → settle         │
│  Multi-rail: Stripe, USDC, Lightning                 │
├──────────┬──────────┬───────────┬───────────────────┤
│ x402     │ Stripe   │ Lightning │ Tempo             │
│ USDC     │ Card/SPT │ BOLT11    │ Stablecoin        │
│ (Coinbase)│ (Stripe) │           │ (Chain 42431)     │
└──────────┴──────────┴───────────┴───────────────────┘
```

**x402** = USDC-only, direct between agent and service provider
**MPP** = multi-rail orchestration through Zelta
**AP2** = authorization/delegation layer for enterprise agent spending

---

## 2. Architecture

### 2.1 Main Flow: Agent → Zelta (MPP) → VertexSMS

```
AI Agent                       Zelta API                        VertexSMS
   │                              │                                │
   ├── POST /v1/sms/send ───────►│                                │
   │   {to, from, message}        │                                │
   │                              │                                │
   │◄── 402 + WWW-Authenticate ──┤  MPP challenge:                │
   │    Payment options:          │  "Pay $0.04, pick a rail"      │
   │    • USDC on Base            │                                │
   │    • Stripe card             │                                │
   │    • Lightning               │                                │
   │                              │                                │
   │   [Agent picks rail, pays]   │                                │
   │                              │                                │
   ├── POST /v1/sms/send ───────►│                                │
   │   Authorization: Payment ... │                                │
   │                              │── verify payment ──┐           │
   │                              │◄─ valid ───────────┘           │
   │                              │                                │
   │                              ├── POST /sms ──────────────────►│
   │                              │   {to, from, message}          │
   │                              │   X-VertexSMS-Token: ...       │
   │                              │                                │
   │                              │◄── 200 {messageId} ───────────┤
   │                              │                                │
   │                              │── settle payment ──┐           │
   │                              │◄─ receipt ─────────┘           │
   │                              │                                │
   │◄── 200 + Payment-Receipt ───┤                                │
   │    {messageId, txHash}       │                                │
```

### 2.2 Optional: Direct x402 (USDC-only, no Zelta in path)

VertexSMS can also add x402 to their own endpoint. Agents who only want USDC skip Zelta entirely:

```
AI Agent ──► VertexSMS /x402/sms ──► Coinbase Facilitator
             (x402 middleware)         (verify + settle)
```

Both paths can coexist. The MPP path through Zelta gives multi-rail. The direct x402 path gives zero intermediaries for USDC.

### 2.3 AP2 Mandates (Enterprise)

For enterprise customers who deploy fleets of agents:

```
Enterprise Admin                    Zelta                          Agent
      │                               │                              │
      ├── Create Intent Mandate ──────►│                              │
      │   "Agent can spend €50/day     │                              │
      │    on SMS to LT numbers"       │                              │
      │                               │── Issue VDC ────────────────►│
      │                               │   (verifiable credential)    │
      │                               │                              │
      │                               │    [Agent sends SMS freely   │
      │                               │     within mandate budget]   │
      │                               │                              │
      │◄── Audit trail ──────────────┤◄── Payment Mandates ────────┤
      │    (VDC-signed receipts)       │   (per-message, auto)       │
```

---

## 3. What VertexSMS Provides

### 3.1 Required (for MPP via Zelta)

| Item | Description | Format |
|------|------------|--------|
| **API Token** | Authentication for `POST /sms` | String, via `X-VertexSMS-Token` header |
| **Sender ID** | Default `from` value or list of approved sender IDs | String, e.g. "Zelta" |
| **Rate Card Access** | Pricing per destination | `GET /rates/?format=json` endpoint, or static JSON |
| **DLR Webhook Format** | Delivery report callback spec | URL format + payload structure |
| **IP Whitelist** | If VertexSMS restricts by IP | Zelta server IPs (we provide) |

### 3.2 Required (for direct x402 on VertexSMS side)

| Item | Description |
|------|------------|
| **USDC Wallet** | EVM address (works on Base + ETH) and/or Solana address |
| **SDK Choice** | Node.js (`@x402/express`), Python (`x402`), Go, or PHP manual |
| **Endpoint** | New route, e.g. `POST /x402/sms` |

### 3.3 Optional

| Item | Description |
|------|------------|
| **Stripe Connect** | VertexSMS Stripe account ID (for fiat rail — Zelta uses Stripe Connect) |
| **Lightning Node** | BOLT11 invoice endpoint (for Lightning rail) |

---

## 4. What Zelta Builds

### Phase 1: Core Integration (MVP — USDC on Base)

| # | Task | New/Modify | Complexity |
|---|------|-----------|------------|
| 1 | **VertexSMS HTTP Client** — wraps `POST /sms`, `GET /rates`, DLR parsing | New: `app/Domain/SMS/Services/VertexSmsClient.php` | Low |
| 2 | **SMS Controller** — accepts `{to, from, message}`, forwards to VertexSMS | New: `app/Http/Controllers/Api/SMS/SmsController.php` | Low |
| 3 | **SMS Routes** — `POST /v1/sms/send` (MPP-gated), `GET /v1/sms/rates`, `GET /v1/sms/status/{id}` | New: `app/Domain/SMS/Routes/api.php` | Low |
| 4 | **Monetized Resource Seed** — register SMS endpoint in MPP with pricing | New: migration or seeder | Low |
| 5 | **Dynamic Pricing Service** — fetch VertexSMS rates, convert EUR→USDC (via exchange rate) | New: `app/Domain/SMS/Services/SmsPricingService.php` | Medium |
| 6 | **DLR Webhook Handler** — receive delivery reports, update SMS status | New: `app/Http/Controllers/Api/Webhook/VertexSmsDlrController.php` | Low |
| 7 | **SMS Model + Migration** — track sent messages, link to MPP payment | New: `app/Domain/SMS/Models/SmsMessage.php` + migration | Low |
| 8 | **SMS MCP Tool** — agents discover and send SMS via tool calling | New: `app/Domain/AI/MCP/Tools/SMS/SmsSendTool.php` | Low |
| 9 | **x402 Rail in MPP** — wire USDC settlement through x402 facilitator as an MPP rail | New: `app/Domain/MachinePay/Services/Rails/X402RailAdapter.php` | Medium |
| 10 | **Domain module** — `app/Domain/SMS/module.json` | New | Trivial |

### Phase 2: Multi-Rail (Stripe + Lightning)

| # | Task | New/Modify | Complexity |
|---|------|-----------|------------|
| 11 | **Stripe Connect Setup** — VertexSMS as connected account, platform fee | Modify: `StripeRailAdapter.php` | Medium |
| 12 | **Real Stripe Rail** — PaymentIntent with `transfer_data` to VertexSMS | Modify: `StripeRailAdapter.php` | Medium |
| 13 | **Lightning Rail** — BOLT11 invoice generation + preimage verification | Modify: `LightningRailAdapter.php` | Medium-High |
| 14 | **Settlement Reconciliation** — periodic reports for VertexSMS | New: `app/Domain/SMS/Services/SmsSettlementService.php` | Medium |
| 15 | **Exchange Rate Service** — EUR/USD/USDC conversion with caching | New: `app/Domain/SMS/Services/ExchangeRateService.php` | Low |

### Phase 3: AP2 Enterprise Features

| # | Task | New/Modify | Complexity |
|---|------|-----------|------------|
| 16 | **SMS Intent Mandate Template** — pre-built mandate for SMS campaigns | New: `app/Domain/SMS/DataObjects/SmsIntentMandate.php` | Low |
| 17 | **AP2→MPP Bridge (Real)** — mandate execution triggers MPP payment | Modify: `AP2PaymentBridgeService.php` | Medium |
| 18 | **Mandate Spending Enforcement** — debit mandate budget on each SMS | Modify: `MandateService.php` | Medium |
| 19 | **Enterprise Dashboard API** — mandate usage, spend reports | New: controller + routes | Medium |

### Phase 4: VertexSMS Native x402 (Optional — Their Side)

| # | Task | Owner | Complexity |
|---|------|-------|------------|
| 20 | **x402 Middleware** — wrap `POST /x402/sms` | VertexSMS | Low (50 lines with SDK) |
| 21 | **Dynamic Pricing** — rates API → USDC pricing | VertexSMS | Low |
| 22 | **Multi-Network** — Base + ETH + Solana accepts array | VertexSMS | Low |
| 23 | **Testing on Base Sepolia** | Both | Low |

---

## 5. What Already Exists (No Build Needed)

| Component | Status | Location |
|-----------|--------|----------|
| MPP middleware (402 challenge/response) | Production | `MppPaymentGateMiddleware.php` |
| MPP challenge service (HMAC-SHA256) | Production | `MppChallengeService.php` |
| MPP verification service | Production | `MppVerificationService.php` |
| MPP settlement service (idempotent, transactional) | Production | `MppSettlementService.php` |
| MPP header codec (base64url + JCS) | Production | `MppHeaderCodecService.php` |
| MPP monetized resource CRUD | Production | `MppResourceController.php` |
| MPP spending limits (per-agent, daily/per-tx) | Production | `MppSpendingLimit` model + API |
| MPP status + discovery endpoints | Production | `MppStatusController.php` |
| MPP rail adapter registry | Production | `MppRailResolverService.php` |
| Demo rail adapter | Production (non-prod) | `DemoRailAdapter.php` |
| x402 full stack (middleware, facilitator, settlement) | Production | `app/Domain/X402/` |
| x402 MCP tool | Production | `X402PaymentTool.php` |
| AP2 mandate lifecycle (create/accept/execute/complete/revoke) | Production | `MandateService.php` |
| AP2 VDC issuance + verification | Production | `VdcService.php` |
| AP2 mandate MCP tool | Production | `AgentMandateTool.php` |
| Agent spending limits UI (mobile) | Production | `finaegis-mobile` |
| x402 signing + interceptor (mobile, EVM) | Production | `finaegis-mobile` |

---

## 6. Money Flow

### USDC Rail (via x402)

```
Agent's USDC wallet ──► VertexSMS wallet (direct, on-chain)
                         │
                    Zelta takes 0% for beta
                    (future: optional platform fee via facilitator)
```

### Stripe Rail

```
Agent's card ──► Stripe ──► VertexSMS Stripe account (via Stripe Connect)
                    │
               Zelta platform fee (e.g., 2%) via Stripe Connect
```

### Lightning Rail

```
Agent ──► BOLT11 invoice ──► VertexSMS Lightning node
                               │
                          Direct payment, Zelta verifies preimage
```

**Key: Zelta does not hold funds.** On USDC: facilitator transfers directly. On Stripe: Connect transfers directly. On Lightning: invoice paid directly. Zelta is the orchestrator, not a custodian.

---

## 7. Pricing Model

### SMS Pricing

```
Base Price = VertexSMS rate (EUR) × EUR/USD × (1 + margin)
```

Example: Lithuania SMS
- VertexSMS rate: €0.039
- EUR/USD: 1.08
- Margin: 15% (configurable)
- USDC price: $0.048 = `48000` atomic USDC

### Multi-Part SMS

Long messages split into multiple parts. Two approaches:

**Flat rate (beta):** Charge single-part price. Accept margin risk on long messages.

**`upto` scheme (production):** Agent authorizes maximum (e.g., 5 parts). Zelta settles actual cost after VertexSMS reports parts used via `X-VertexSMS-Amount-Sent`.

### Platform Fees

| Rail | Fee | Mechanism |
|------|-----|-----------|
| USDC (x402) | 0% beta / negotiable | On-chain fee or facilitator fee |
| Stripe | ~2-3% | Stripe Connect application fee |
| Lightning | 0% | Routing fee only |

---

## 8. API Specification (Zelta endpoints)

### 8.1 Send SMS (MPP-gated)

```
POST /api/v1/sms/send
```

**Without payment** → returns 402 with `WWW-Authenticate: Payment` header containing available rails and pricing.

**With payment** → verifies, sends SMS, settles, returns:

```json
{
  "data": {
    "message_id": "1281532560",
    "status": "sent",
    "parts": 1,
    "destination": "+37069912345",
    "payment": {
      "rail": "stripe_spt",
      "amount": "48000",
      "currency": "USDC",
      "receipt_id": "rcpt_abc123"
    }
  }
}
```

### 8.2 Check Rates

```
GET /api/v1/sms/rates?country=LT
```

```json
{
  "data": {
    "country": "LT",
    "country_name": "Lithuania",
    "rate_eur": "0.039",
    "rate_usdc": "48000",
    "networks": ["eip155:8453", "eip155:1"]
  }
}
```

### 8.3 Check Delivery Status

```
GET /api/v1/sms/status/{message_id}
```

```json
{
  "data": {
    "message_id": "1281532560",
    "status": "delivered",
    "delivered_at": "2026-03-24T12:01:05Z",
    "payment_status": "settled"
  }
}
```

### 8.4 SMS MCP Tool

Agents discover this automatically:

```json
{
  "tool": "sms.send",
  "description": "Send SMS via VertexSMS. Pays per-message via USDC or card.",
  "input": {
    "to": "+37069912345",
    "from": "MyApp",
    "message": "Hello from AI"
  }
}
```

---

## 9. VertexSMS Native x402 (Their Endpoint)

If VertexSMS also wants direct x402 on their own API (USDC-only, no Zelta in path):

### Node.js (50 lines)

```typescript
import express from "express";
import { paymentMiddleware } from "@x402/express";

const app = express();
app.use(express.json());

const PAY_TO = "0xYOUR_BASE_USDC_WALLET";
const USDC_BASE = "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913";

app.post(
  "/x402/sms",
  paymentMiddleware(
    PAY_TO,
    [
      { amount: "48000", network: "eip155:8453", asset: USDC_BASE },  // Base
    ],
    { facilitatorUrl: "https://x402.org/facilitator" }
  ),
  async (req, res) => {
    // Only runs after payment verified + settled
    const result = await yourExistingSmsLogic(req.body);
    res.json({ messageId: result.id, status: "sent" });
  }
);
```

### PHP/Laravel (~150 lines)

See Appendix A for full PHP middleware implementation.

### Python/Go

x402 SDKs available: `pip install x402`, `go get github.com/coinbase/x402/go`

---

## 10. Testing Plan

### Stage 1: Demo Mode (no real payments, no real SMS)

- Zelta: MPP with `DemoRailAdapter` + VertexSMS `testMode: "1"`
- Validates full flow: 402 → pay → SMS → receipt
- Zero cost

### Stage 2: Testnet (real payments, no real SMS)

- Network: Base Sepolia
- USDC: test tokens from [Circle Faucet](https://faucet.circle.com/)
- VertexSMS: `testMode: "1"` (no real SMS delivery)
- Validates payment verification + settlement on-chain

### Stage 3: Production Beta (real payments, real SMS)

- Network: Base mainnet
- Start with known test numbers
- Monitor: payment success rate, settlement time, DLR delivery rate

---

## 11. Timeline Estimate

| Phase | What | Duration |
|-------|------|----------|
| **Phase 1** | USDC on Base via Zelta MPP (tasks 1-10) | 1 week |
| **Phase 2** | Stripe + Lightning rails (tasks 11-15) | 1 week |
| **Phase 3** | AP2 enterprise mandates (tasks 16-19) | 1 week |
| **Phase 4** | VertexSMS native x402 (tasks 20-23) | VertexSMS: 1-2 days |
| **E2E testing** | Demo → testnet → production | 1 week parallel |

Phase 1 is enough for beta launch. Phases 2-4 in parallel.

---

## 12. FAQ

**Why both MPP and x402?**
x402 is USDC-only (crypto). MPP adds Stripe cards, Lightning, and other rails. Together they cover both crypto-native agents and fiat-based agents.

**Why does the agent call Zelta, not VertexSMS directly?**
For multi-rail (Stripe, Lightning). If an agent only wants to pay in USDC, the direct x402 path to VertexSMS works too — both can coexist.

**Does Zelta hold funds?**
No. USDC goes directly to VertexSMS wallet via facilitator. Stripe goes directly via Connect. Zelta orchestrates the payment, doesn't custody it.

**What does AP2 add?**
Enterprise authorization. A company creates a mandate: "Agent X can spend €50/day on SMS to EU numbers." The agent operates within that budget autonomously. Every transaction is signed with a verifiable credential for audit.

**What about Solana?**
x402 on Solana requires different signing (Ed25519 vs EIP-712). Base is ready now. Solana is Phase 2 for both Zelta mobile and the facilitator.

**What does VertexSMS need to change in their existing API?**
Nothing. For the MPP path, Zelta calls their existing `POST /sms` with their existing token auth. For the optional native x402 path, they add a new endpoint alongside the existing one.

---

## Appendix A: PHP x402 Middleware (for VertexSMS native path)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class X402PaymentGate
{
    private const FACILITATOR = 'https://x402.org/facilitator';
    private const PAY_TO      = '0xYOUR_USDC_WALLET_ADDRESS';
    private const NETWORK     = 'eip155:8453';
    private const ASSET       = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';

    public function handle(Request $request, Closure $next)
    {
        $signature = $request->header('PAYMENT-SIGNATURE');

        if (! $signature) {
            return $this->paymentRequired($request);
        }

        $payload = json_decode(base64_decode($signature), true);
        if (! $payload) {
            return response()->json(['error' => 'Invalid payment'], 400);
        }

        $requirements = $this->requirements($request);

        // Verify via facilitator
        $verify = Http::timeout(30)->post(self::FACILITATOR . '/verify', [
            'x402Version'         => 2,
            'paymentPayload'      => $payload,
            'paymentRequirements' => $requirements,
        ]);

        if (! $verify->ok() || ! $verify->json('isValid')) {
            return response()->json([
                'error'  => 'Payment verification failed',
                'reason' => $verify->json('invalidReason', 'unknown'),
            ], 402);
        }

        // Process SMS
        $response = $next($request);

        // Settle on-chain
        $settle = Http::timeout(60)->post(self::FACILITATOR . '/settle', [
            'x402Version'         => 2,
            'paymentPayload'      => $payload,
            'paymentRequirements' => $requirements,
        ]);

        if ($settle->ok() && $settle->json('success')) {
            $response->headers->set('PAYMENT-RESPONSE', base64_encode(json_encode([
                'success'     => true,
                'transaction' => $settle->json('transaction'),
                'network'     => $settle->json('network'),
            ])));
        }

        return $response;
    }

    private function paymentRequired(Request $request)
    {
        $header = base64_encode(json_encode([
            'x402Version' => 2,
            'error'       => 'Payment required',
            'resource'    => [
                'url'         => $request->fullUrl(),
                'description' => 'Send SMS via VertexSMS',
                'mimeType'    => 'application/json',
            ],
            'accepts' => [$this->requirements($request)],
        ]));

        return response('', 402)->header('PAYMENT-REQUIRED', $header);
    }

    private function requirements(Request $request): array
    {
        return [
            'scheme'            => 'exact',
            'network'           => self::NETWORK,
            'amount'            => $this->price($request),
            'asset'             => self::ASSET,
            'payTo'             => self::PAY_TO,
            'maxTimeoutSeconds' => 300,
            'extra'             => ['name' => 'USDC', 'version' => '2'],
        ];
    }

    private function price(Request $request): string
    {
        // Replace with dynamic lookup from your rates table
        return '48000'; // $0.048
    }
}
```

Route: `Route::post('/x402/sms', [SmsController::class, 'send'])->middleware(X402PaymentGate::class);`

---

## Appendix B: Network Reference

| Network | CAIP-2 | USDC Address | Gas | Status |
|---------|--------|-------------|-----|--------|
| Base | `eip155:8453` | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` | ~$0.001 | Ready |
| Ethereum | `eip155:1` | `0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48` | ~$0.50-5 | Ready |
| Solana | `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` | `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v` | ~$0.001 | Phase 2 |
| Base Sepolia | `eip155:84532` | `0x036CbD53842c5426634e7929541eC2318f3dCF7e` | Free | Testing |

## Appendix C: Protocol References

- **x402**: https://x402.org — https://github.com/coinbase/x402
- **MPP**: https://stripe.com/blog/machine-payments-protocol
- **AP2**: https://github.com/google-agentic-commerce/AP2
- **Zelta**: https://zelta.app
