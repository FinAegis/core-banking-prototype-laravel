# x402 Protocol Implementation Plan — FinAegis v5.2.0

> **Version**: 5.2.0 (MINOR — new feature domain)
> **Protocol Version**: x402 v2 (CAIP-2 network identifiers)
> **Date**: 2026-02-22
> **Status**: Implementation Specification
> **Dependencies**: `AgentProtocol`, `Relayer`, `AI/MCP`, `KeyManagement`, `Wallet` domains
>
> **Status at v7.18.1**: This is the v5.2.0 plan, kept as a record. The x402 domain was built in `app/Domain/X402/`, and `config/x402.php` lists eight networks, including Solana. One point in this plan no longer holds. Signing on the server for mobile through `UserOperationSigningService` is not available, because the `/api/auth/sign-userop` endpoint was removed in v7.12.0. The Zelta wallet is non-custodial, and the device signs every payment through Privy.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Architecture Overview](#2-architecture-overview)
3. [Phase 1 — Core Domain & Config](#3-phase-1--core-domain--config)
4. [Phase 2 — Server-Side (Resource Server)](#4-phase-2--server-side-resource-server)
5. [Phase 3 — Facilitator Integration](#5-phase-3--facilitator-integration)
6. [Phase 4 — Client-Side (Paying Agent)](#6-phase-4--client-side-paying-agent)
7. [Phase 5 — AI/MCP Integration](#7-phase-5--aimcp-integration)
8. [Phase 6 — Admin, Observability & GraphQL](#8-phase-6--admin-observability--graphql)
9. [Phase 7 — Testing](#9-phase-7--testing)
10. [Database Migrations](#10-database-migrations)
11. [Configuration Reference](#11-configuration-reference)
12. [File Manifest](#12-file-manifest)
13. [Integration Points](#13-integration-points)
14. [Security Considerations](#14-security-considerations)
15. [Rollout Strategy](#15-rollout-strategy)

---

## 1. Executive Summary

The x402 protocol enables HTTP-native, account-less micropayments using blockchain rails. By integrating x402, FinAegis gains:

- **Resource Server mode**: Monetize premium API endpoints and AI agent tools with per-request USDC payments
- **Client mode**: Our AI agents can autonomously pay for external x402-enabled APIs
- **Facilitator proxy**: Verify and settle EIP-3009/Permit2 signed payments on-chain
- **MCP bridge**: Machine-to-machine commerce through the existing MCP tool infrastructure

This creates a dual-sided payment gateway for the autonomous agent economy, bridging the existing `AgentProtocol` domain with real-world stablecoin value transfer.

### Strategic Fit

| Existing Domain | x402 Integration Point |
|----------------|----------------------|
| `AgentProtocol/AgentPaymentIntegrationService` | Payment recording, fee calculation |
| `AgentProtocol/DigitalSignatureService` | EIP-712 signature creation/verification |
| `AgentProtocol/EscrowService` | Pre-authorized payment holding |
| `Relayer/GasStationService` | On-chain settlement gas sponsoring |
| `Relayer/SmartAccountService` | ERC-4337 smart accounts as payers |
| `Relayer/UserOperationSigningService` | Biometric-gated signing for mobile (no longer exposed: the sign-userop endpoint was removed in v7.12.0) |
| `AI/MCP/ToolRegistry` | Monetized tool tagging |
| `AI/MCP/MCPServer` | 402 response handling for tool calls |
| `AI/AIAgentService` | Auto-retry with payment on 402 |
| `KeyManagement/ShamirService` | Secure key storage for facilitator wallet |

---

## 2. Architecture Overview

```
                         FinAegis Backend
┌─────────────────────────────────────────────────────────┐
│                                                         │
│  ┌──────────────┐    ┌──────────────────┐              │
│  │  x402Payment  │───▶│ X402Facilitator   │──▶ Blockchain│
│  │  GateMiddleware│   │ Service           │   (USDC)    │
│  └───────┬──────┘    └──────────────────┘              │
│          │                                              │
│  ┌───────▼──────────────────────────────┐              │
│  │  X402 Domain (NEW)                    │              │
│  │  ├─ Services/                         │              │
│  │  │  ├─ X402FacilitatorService         │              │
│  │  │  ├─ X402PaymentVerificationService │              │
│  │  │  ├─ X402SettlementService          │              │
│  │  │  ├─ X402ClientService              │              │
│  │  │  └─ X402PricingService             │              │
│  │  ├─ DataObjects/                      │              │
│  │  │  ├─ PaymentRequired                │              │
│  │  │  ├─ PaymentPayload                 │              │
│  │  │  ├─ PaymentRequirements            │              │
│  │  │  └─ SettleResponse                 │              │
│  │  ├─ Enums/                            │              │
│  │  │  ├─ PaymentScheme                  │              │
│  │  │  ├─ SupportedNetwork               │              │
│  │  │  └─ SettlementStatus               │              │
│  │  ├─ Events/                           │              │
│  │  │  ├─ PaymentRequested               │              │
│  │  │  ├─ PaymentVerified                │              │
│  │  │  ├─ PaymentSettled                 │              │
│  │  │  └─ PaymentFailed                  │              │
│  │  └─ Models/                           │              │
│  │     ├─ X402Payment                    │              │
│  │     ├─ X402MonetizedEndpoint          │              │
│  │     └─ X402SpendingLimit              │              │
│  └───────────────────────────────────────┘              │
│                                                         │
│  ┌──────────────────────┐  ┌──────────────────────┐    │
│  │ AgentProtocol Domain  │  │ AI/MCP Domain         │    │
│  │ (payment recording,   │  │ (tool monetization,   │    │
│  │  reputation, escrow)  │  │  auto-pay on 402)     │    │
│  └──────────────────────┘  └──────────────────────┘    │
└─────────────────────────────────────────────────────────┘
```

### HTTP Flow (Resource Server Mode)

```
Client                    FinAegis                  Facilitator (external/self)
  │                          │                              │
  │── GET /api/v1/premium ──▶│                              │
  │                          │ (no PAYMENT-SIGNATURE)       │
  │◀── 402 + PAYMENT-REQUIRED│                              │
  │                          │                              │
  │── GET /api/v1/premium ──▶│                              │
  │   + PAYMENT-SIGNATURE    │── POST /verify ─────────────▶│
  │                          │◀── { isValid: true } ────────│
  │                          │                              │
  │                          │ (serve resource)             │
  │                          │                              │
  │                          │── POST /settle ─────────────▶│
  │                          │◀── { tx: "0x...", ... } ─────│
  │                          │                              │
  │◀── 200 + PAYMENT-RESPONSE│                              │
  │       + response body    │                              │
```

---

## 3. Phase 1 — Core Domain & Config

### 3.1 New Domain: `App\Domain\X402`

Create the bounded context following existing DDD patterns.

```
app/Domain/X402/
├── Contracts/
│   ├── FacilitatorClientInterface.php
│   ├── PaymentSchemeInterface.php
│   └── SignerInterface.php
├── DataObjects/
│   ├── PaymentRequired.php
│   ├── PaymentPayload.php
│   ├── PaymentRequirements.php
│   ├── ResourceInfo.php
│   ├── VerifyRequest.php
│   ├── VerifyResponse.php
│   ├── SettleRequest.php
│   ├── SettleResponse.php
│   └── MonetizedRouteConfig.php
├── Enums/
│   ├── PaymentScheme.php          // exact, upto
│   ├── X402Network.php            // CAIP-2 identifiers
│   ├── SettlementStatus.php       // pending, verified, settled, failed
│   └── AssetTransferMethod.php    // eip3009, permit2
├── Events/
│   ├── X402PaymentRequested.php
│   ├── X402PaymentVerified.php
│   ├── X402PaymentSettled.php
│   ├── X402PaymentFailed.php
│   └── X402SettlementCompleted.php
├── Exceptions/
│   ├── X402VerificationException.php
│   ├── X402SettlementException.php
│   ├── X402InvalidPayloadException.php
│   └── X402InsufficientFundsException.php
├── Models/
│   ├── X402Payment.php
│   ├── X402MonetizedEndpoint.php
│   └── X402SpendingLimit.php
├── Services/
│   ├── X402FacilitatorService.php
│   ├── X402PaymentVerificationService.php
│   ├── X402SettlementService.php
│   ├── X402ClientService.php
│   ├── X402PricingService.php
│   ├── X402HeaderCodecService.php
│   └── X402EIP712SignerService.php
├── Providers/
│   └── X402ServiceProvider.php
└── Routes/
    └── api.php
```

### 3.2 Data Objects

**`PaymentRequired.php`** — Sent in 402 response:
```php
readonly class PaymentRequired
{
    public function __construct(
        public int $x402Version,           // Always 2
        public ResourceInfo $resource,
        public array $accepts,             // PaymentRequirements[]
        public ?string $error = null,
        public ?array $extensions = null,
    ) {}

    public function toBase64(): string;
    public static function fromBase64(string $encoded): self;
}
```

**`PaymentRequirements.php`**:
```php
readonly class PaymentRequirements
{
    public function __construct(
        public string $scheme,             // "exact"
        public string $network,            // "eip155:8453"
        public string $asset,              // USDC contract address
        public string $amount,             // Atomic units (e.g., "1000" = $0.001)
        public string $payTo,              // Recipient wallet
        public int $maxTimeoutSeconds,
        public array $extra = [],          // EIP-712 domain, etc.
    ) {}
}
```

**`PaymentPayload.php`** — Received from client in PAYMENT-SIGNATURE:
```php
readonly class PaymentPayload
{
    public function __construct(
        public int $x402Version,
        public ResourceInfo $resource,
        public PaymentRequirements $accepted,
        public array $payload,             // Scheme-specific (signature + authorization)
        public ?array $extensions = null,
    ) {}

    public static function fromBase64(string $encoded): self;
    public function toBase64(): string;
}
```

### 3.3 Enums

**`X402Network.php`** (backed enum with CAIP-2):
```php
enum X402Network: string
{
    case BASE_MAINNET     = 'eip155:8453';
    case BASE_SEPOLIA     = 'eip155:84532';
    case ETHEREUM_MAINNET = 'eip155:1';
    case SEPOLIA          = 'eip155:11155111';
    case AVALANCHE        = 'eip155:43114';
    case AVALANCHE_FUJI   = 'eip155:43113';

    public function chainId(): int;
    public function isTestnet(): bool;
    public function usdcAddress(): string;
    public function usdcDecimals(): int;   // Always 6 for USDC
}
```

**`PaymentScheme.php`**:
```php
enum PaymentScheme: string
{
    case EXACT = 'exact';
    case UPTO  = 'upto';
}
```

### 3.4 Configuration — `config/x402.php`

```php
return [
    'enabled' => env('X402_ENABLED', false),
    'version' => 2,

    // Resource Server settings (we charge for our APIs)
    'server' => [
        'pay_to' => env('X402_PAY_TO_ADDRESS'),     // Our wallet address
        'default_network' => env('X402_DEFAULT_NETWORK', 'eip155:8453'),
        'default_asset' => env('X402_DEFAULT_ASSET', 'USDC'),
        'max_timeout_seconds' => env('X402_MAX_TIMEOUT', 60),
        'settle_before_response' => env('X402_SETTLE_BEFORE_RESPONSE', true),
    ],

    // Facilitator settings
    'facilitator' => [
        'url' => env('X402_FACILITATOR_URL', 'https://x402.org/facilitator'),
        'timeout_seconds' => env('X402_FACILITATOR_TIMEOUT', 30),
        'self_hosted' => env('X402_SELF_HOSTED_FACILITATOR', false),
    ],

    // Client settings (we pay external x402 APIs)
    'client' => [
        'enabled' => env('X402_CLIENT_ENABLED', false),
        'signer_key_id' => env('X402_CLIENT_SIGNER_KEY_ID'),    // KeyManagement key ID
        'auto_pay' => env('X402_CLIENT_AUTO_PAY', false),
        'max_auto_pay_amount' => env('X402_CLIENT_MAX_AUTO_PAY', '100000'), // $0.10 in atomic
    ],

    // Network-specific USDC contract addresses
    'assets' => [
        'eip155:8453'  => [
            'USDC' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        ],
        'eip155:84532' => [
            'USDC' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
        ],
    ],

    // Smart contract addresses (deterministic across all EVM chains)
    'contracts' => [
        'permit2' => '0x000000000022D473030F116dDEE9F6B43aC78BA3',
        'exact_permit2_proxy' => '0x4020615294c913F045dc10f0a5cdEbd86c280001',
        'upto_permit2_proxy' => '0x4020633461b2895a48930Ff97eE8fCdE8E520002',
    ],

    // Monetized endpoints (route => price config)
    // Managed via database + admin panel, not just config
    'default_endpoints' => [
        // 'GET /api/v1/ai/query' => ['price' => '$0.01', 'description' => 'AI query endpoint'],
    ],

    // Agent spending limits
    'agent_spending' => [
        'default_daily_limit' => env('X402_AGENT_DAILY_LIMIT', '5000000'), // $5.00
        'require_approval_above' => env('X402_REQUIRE_APPROVAL_ABOVE', '1000000'), // $1.00
    ],
];
```

---

## 4. Phase 2 — Server-Side (Resource Server)

### 4.1 Middleware: `X402PaymentGateMiddleware`

**Location**: `app/Http/Middleware/X402PaymentGateMiddleware.php`

This is the core middleware that intercepts requests to monetized endpoints.

```php
class X402PaymentGateMiddleware
{
    public function __construct(
        private readonly X402PaymentVerificationService $verificationService,
        private readonly X402SettlementService $settlementService,
        private readonly X402HeaderCodecService $codec,
        private readonly X402PricingService $pricingService,
    ) {}

    public function handle(Request $request, Closure $next, ?string $priceOverride = null): Response
    {
        // 1. Check if endpoint is monetized
        $routeConfig = $this->pricingService->getRouteConfig($request);
        if (!$routeConfig) {
            return $next($request);
        }

        // 2. Check for PAYMENT-SIGNATURE header
        $paymentSignature = $request->header('PAYMENT-SIGNATURE');
        if (!$paymentSignature) {
            return $this->return402($request, $routeConfig);
        }

        // 3. Decode and verify payment
        $paymentPayload = $this->codec->decodePaymentPayload($paymentSignature);
        $verifyResult = $this->verificationService->verify($paymentPayload, $routeConfig);

        if (!$verifyResult->isValid) {
            return $this->return402($request, $routeConfig, $verifyResult->invalidMessage);
        }

        // 4. Execute the request handler
        $response = $next($request);

        // 5. Settle payment
        $settleResult = $this->settlementService->settle($paymentPayload, $routeConfig);

        // 6. Attach PAYMENT-RESPONSE header
        $response->headers->set(
            'PAYMENT-RESPONSE',
            $this->codec->encodeSettleResponse($settleResult)
        );

        return $response;
    }

    private function return402(Request $request, MonetizedRouteConfig $config, ?string $error = null): Response
    {
        $paymentRequired = $this->pricingService->buildPaymentRequired($request, $config, $error);

        return response()->json(
            ['message' => 'Payment Required', 'error' => $error],
            402,
            ['PAYMENT-REQUIRED' => $this->codec->encodePaymentRequired($paymentRequired)]
        );
    }
}
```

**Middleware Registration** (in service provider or bootstrap):
```php
// Register as route middleware
Route::aliasMiddleware('x402', X402PaymentGateMiddleware::class);

// Usage in routes:
Route::get('/api/v1/premium/weather', [PremiumController::class, 'weather'])
    ->middleware('x402:0.001');  // price in USD
```

### 4.2 Header Codec Service

**`X402HeaderCodecService.php`** — Handles Base64 JSON encoding/decoding:

```php
class X402HeaderCodecService
{
    public function encodePaymentRequired(PaymentRequired $pr): string;   // → base64
    public function decodePaymentPayload(string $header): PaymentPayload; // base64 → DTO
    public function encodeSettleResponse(SettleResponse $sr): string;     // → base64
    public function decodePaymentRequired(string $header): PaymentRequired; // For client mode
}
```

### 4.3 Pricing Service

**`X402PricingService.php`**:

```php
class X402PricingService
{
    public function __construct(
        private readonly X402MonetizedEndpoint $endpointModel,
    ) {}

    // Resolve route config from request (DB-backed + config fallback)
    public function getRouteConfig(Request $request): ?MonetizedRouteConfig;

    // Build the PaymentRequired response object with all accepted methods
    public function buildPaymentRequired(
        Request $request,
        MonetizedRouteConfig $config,
        ?string $error = null,
    ): PaymentRequired;

    // Convert USD price to atomic USDC units
    public function usdToAtomicUnits(string $usdPrice, X402Network $network): string;

    // Dynamic pricing support (e.g., compute-cost-based)
    public function calculateDynamicPrice(Request $request, MonetizedRouteConfig $config): string;
}
```

### 4.4 Route Configuration

In `app/Domain/X402/Routes/api.php`:
```php
// x402 admin endpoints (manage monetized routes)
Route::prefix('x402')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/endpoints', [X402EndpointController::class, 'index']);
    Route::post('/endpoints', [X402EndpointController::class, 'store']);
    Route::put('/endpoints/{id}', [X402EndpointController::class, 'update']);
    Route::delete('/endpoints/{id}', [X402EndpointController::class, 'destroy']);

    // Spending limits management
    Route::get('/spending-limits', [X402SpendingLimitController::class, 'index']);
    Route::post('/spending-limits', [X402SpendingLimitController::class, 'store']);
    Route::put('/spending-limits/{id}', [X402SpendingLimitController::class, 'update']);

    // Payment history
    Route::get('/payments', [X402PaymentController::class, 'index']);
    Route::get('/payments/{id}', [X402PaymentController::class, 'show']);
    Route::get('/payments/stats', [X402PaymentController::class, 'stats']);
});
```

---

## 5. Phase 3 — Facilitator Integration

### 5.1 Facilitator Client Interface

```php
interface FacilitatorClientInterface
{
    public function verify(VerifyRequest $request): VerifyResponse;
    public function settle(SettleRequest $request): SettleResponse;
    public function supported(): SupportedResponse;
}
```

### 5.2 HTTP Facilitator Client

**`HttpFacilitatorClient.php`** — Calls external facilitator (Coinbase or x402.org):

```php
class HttpFacilitatorClient implements FacilitatorClientInterface
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $facilitatorUrl,
        private readonly int $timeout,
    ) {}

    public function verify(VerifyRequest $request): VerifyResponse
    {
        $response = $this->http->post("{$this->facilitatorUrl}/verify", [
            'json' => [
                'paymentPayload' => $request->paymentPayload->toArray(),
                'paymentRequirements' => $request->paymentRequirements->toArray(),
            ],
            'timeout' => $this->timeout,
        ]);
        return VerifyResponse::fromArray($response->json());
    }

    public function settle(SettleRequest $request): SettleResponse { /* similar */ }
    public function supported(): SupportedResponse { /* GET /supported */ }
}
```

### 5.3 Payment Verification Service

**`X402PaymentVerificationService.php`**:

```php
class X402PaymentVerificationService
{
    public function __construct(
        private readonly FacilitatorClientInterface $facilitator,
    ) {}

    public function verify(PaymentPayload $payload, MonetizedRouteConfig $config): VerifyResponse
    {
        // 1. Local pre-validation
        $this->validatePayloadStructure($payload);
        $this->validateX402Version($payload->x402Version);
        $this->validateAmountSufficient($payload, $config);
        $this->validateNetwork($payload->accepted->network);

        // 2. Delegate to facilitator for cryptographic verification
        $verifyRequest = new VerifyRequest(
            paymentPayload: $payload,
            paymentRequirements: $this->buildRequirements($config),
        );

        return $this->facilitator->verify($verifyRequest);
    }
}
```

### 5.4 Settlement Service

**`X402SettlementService.php`**:

```php
class X402SettlementService
{
    public function __construct(
        private readonly FacilitatorClientInterface $facilitator,
        private readonly X402Payment $paymentModel,
    ) {}

    public function settle(PaymentPayload $payload, MonetizedRouteConfig $config): SettleResponse
    {
        // 1. Record payment attempt
        $payment = $this->recordPaymentAttempt($payload, $config);

        // 2. Call facilitator to settle on-chain
        $settleRequest = new SettleRequest(
            paymentPayload: $payload,
            paymentRequirements: $this->buildRequirements($config),
        );

        try {
            $result = $this->facilitator->settle($settleRequest);
            $this->updatePaymentStatus($payment, $result);

            // 3. Fire event
            event(new X402PaymentSettled($payment, $result));

            return $result;
        } catch (\Throwable $e) {
            event(new X402PaymentFailed($payment, $e->getMessage()));
            throw new X402SettlementException($e->getMessage(), previous: $e);
        }
    }
}
```

---

## 6. Phase 4 — Client-Side (Paying Agent)

### 6.1 Client Service

**`X402ClientService.php`** — Used when our agents/system calls external x402 APIs:

```php
class X402ClientService
{
    public function __construct(
        private readonly X402EIP712SignerService $signer,
        private readonly X402HeaderCodecService $codec,
        private readonly X402SpendingLimit $spendingLimitModel,
    ) {}

    /**
     * Handle a 402 response from an external API.
     * Parse requirements, sign payment, return headers for retry.
     */
    public function handlePaymentRequired(
        Response $response,
        string $agentId,
    ): array  // Returns ['PAYMENT-SIGNATURE' => '...']
    {
        // 1. Decode PAYMENT-REQUIRED header
        $paymentRequired = $this->codec->decodePaymentRequired(
            $response->header('PAYMENT-REQUIRED')
        );

        // 2. Select best payment option from accepts[]
        $selected = $this->selectPaymentOption($paymentRequired->accepts);

        // 3. Check spending limit
        $this->enforceSpendingLimit($agentId, $selected->amount, $selected->network);

        // 4. Create EIP-3009 signature
        $signedPayload = $this->signer->createTransferAuthorization(
            network: X402Network::from($selected->network),
            to: $selected->payTo,
            amount: $selected->amount,
            asset: $selected->asset,
            maxTimeoutSeconds: $selected->maxTimeoutSeconds,
            extra: $selected->extra,
        );

        // 5. Build PaymentPayload
        $paymentPayload = new PaymentPayload(
            x402Version: 2,
            resource: $paymentRequired->resource,
            accepted: $selected,
            payload: $signedPayload,
        );

        return ['PAYMENT-SIGNATURE' => $this->codec->encodePaymentPayload($paymentPayload)];
    }
}
```

### 6.2 EIP-712 Signer Service

**`X402EIP712SignerService.php`** — Creates EIP-3009 `transferWithAuthorization` signatures:

```php
class X402EIP712SignerService
{
    public function __construct(
        private readonly DigitalSignatureService $signatureService, // AgentProtocol
        private readonly KeyManagementServiceInterface $keyManagement,
    ) {}

    /**
     * Create an EIP-3009 TransferWithAuthorization signed payload.
     */
    public function createTransferAuthorization(
        X402Network $network,
        string $to,
        string $amount,
        string $asset,
        int $maxTimeoutSeconds,
        array $extra = [],
    ): array
    {
        $from = $this->getSignerAddress();
        $nonce = $this->generateNonce();
        $validAfter = '0';
        $validBefore = (string) (time() + $maxTimeoutSeconds);

        // Build EIP-712 typed data
        $domain = [
            'name' => $extra['name'] ?? 'USD Coin',
            'version' => $extra['version'] ?? '2',
            'chainId' => $network->chainId(),
            'verifyingContract' => $asset,
        ];

        $message = [
            'from' => $from,
            'to' => $to,
            'value' => $amount,
            'validAfter' => $validAfter,
            'validBefore' => $validBefore,
            'nonce' => $nonce,
        ];

        $signature = $this->signEIP712($domain, $message);

        return [
            'signature' => $signature,
            'authorization' => [
                'from' => $from,
                'to' => $to,
                'value' => $amount,
                'validAfter' => $validAfter,
                'validBefore' => $validBefore,
                'nonce' => $nonce,
            ],
        ];
    }
}
```

### 6.3 HTTP Client Wrapper

**`X402HttpClient.php`** — Wraps Guzzle/HTTP client for automatic 402 handling:

```php
class X402HttpClient
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly X402ClientService $clientService,
        private readonly string $agentId,
    ) {}

    public function get(string $url, array $options = []): Response
    {
        $response = $this->http->get($url, $options);

        if ($response->status() === 402) {
            $paymentHeaders = $this->clientService->handlePaymentRequired(
                $response, $this->agentId
            );

            // Retry with payment
            return $this->http->get($url, array_merge($options, [
                'headers' => $paymentHeaders,
            ]));
        }

        return $response;
    }

    // Similar for post(), put(), etc.
}
```

---

## 7. Phase 5 — AI/MCP Integration

### 7.1 ToolRegistry Monetization Extension

Extend the existing `ToolRegistry` to support x402 pricing metadata:

```php
// In ToolRegistry, add support for monetized tools:
public function registerMonetized(
    MCPToolInterface $tool,
    string $category,
    string $price,       // e.g., '$0.01'
    ?string $network = null,
): void
{
    $this->register($tool, $category);
    $this->monetizedTools[$tool->getName()] = [
        'price' => $price,
        'network' => $network ?? config('x402.server.default_network'),
    ];
}

public function isMonetized(string $toolName): bool;
public function getToolPrice(string $toolName): ?array;
```

### 7.2 MCPServer x402 Integration

Update `MCPServer::executeToolWithEventSourcing()` to:
1. Check if tool is monetized
2. If monetized and no payment attached → return 402-equivalent MCP error with payment requirements
3. If payment attached → verify, execute tool, settle

### 7.3 AI Agent Auto-Pay

Update `AIAgentService` and `AIAgentProtocolBridgeService` to:
1. When calling external MCP tools that return 402 → automatically parse requirements
2. Check agent's spending limit (`X402SpendingLimit` model)
3. If within limit → auto-sign and retry
4. If above limit → request human approval via existing `HumanApprovalWorkflow`

### 7.4 New MCP Tool: `x402-payment`

**`app/Domain/AI/MCP/Tools/X402/X402PaymentTool.php`**:

```php
class X402PaymentTool implements MCPToolInterface
{
    public function getName(): string { return 'x402-payment'; }
    public function getDescription(): string
    {
        return 'Handle x402 payment requirements for external API access';
    }

    public function execute(array $params): ToolExecutionResult
    {
        // Parse 402 response, create payment, return signed headers
    }
}
```

---

## 8. Phase 6 — Admin, Observability & GraphQL

### 8.1 Filament Admin Resources

- **X402PaymentResource** — View/search payment history, filter by status/network/amount
- **X402EndpointResource** — CRUD monetized endpoints with price/network configuration
- **X402SpendingLimitResource** — Manage agent and user spending limits

### 8.2 GraphQL Schema

**`graphql/x402.graphql`**:
```graphql
type X402Payment {
    id: ID!
    payer: String!
    amount: String!
    network: String!
    asset: String!
    transactionHash: String
    status: X402SettlementStatus!
    endpoint: String!
    createdAt: DateTime!
    settledAt: DateTime
}

type X402MonetizedEndpoint {
    id: ID!
    method: String!
    path: String!
    price: String!
    network: String!
    description: String
    isActive: Boolean!
}

type X402SpendingLimit {
    id: ID!
    agentId: String!
    dailyLimit: String!
    spent: String!
    resetAt: DateTime!
}

type X402PaymentStats {
    totalPayments: Int!
    totalRevenue: String!
    averagePayment: String!
    activeEndpoints: Int!
}

enum X402SettlementStatus {
    PENDING
    VERIFIED
    SETTLED
    FAILED
}

extend type Query {
    x402Payments(
        status: X402SettlementStatus
        network: String
        first: Int
        after: String
    ): X402PaymentConnection!
    x402Payment(id: ID!): X402Payment
    x402Endpoints: [X402MonetizedEndpoint!]!
    x402SpendingLimits(agentId: String): [X402SpendingLimit!]!
    x402Stats(period: String): X402PaymentStats!
}

extend type Mutation {
    createX402Endpoint(input: CreateX402EndpointInput!): X402MonetizedEndpoint!
    updateX402Endpoint(id: ID!, input: UpdateX402EndpointInput!): X402MonetizedEndpoint!
    deleteX402Endpoint(id: ID!): Boolean!
    setX402SpendingLimit(input: SetX402SpendingLimitInput!): X402SpendingLimit!
}
```

### 8.3 Event Streaming Integration

Connect to existing `EventStreamPublisher` for real-time x402 payment events:

```php
// In X402ServiceProvider::boot()
Event::listen(X402PaymentSettled::class, function ($event) {
    app(EventStreamPublisher::class)->publish('x402.payment.settled', [
        'payment_id' => $event->payment->id,
        'amount' => $event->settleResponse->payer,
        'transaction' => $event->settleResponse->transaction,
        'network' => $event->settleResponse->network,
    ]);
});
```

### 8.4 Live Dashboard Metrics

Add x402 metrics to existing `LiveMetricsService`:

- `x402.payments.total` — Total payments count
- `x402.payments.revenue` — Total revenue in atomic USDC
- `x402.payments.avg_settle_time` — Average settlement time
- `x402.payments.failure_rate` — Payment failure percentage
- `x402.endpoints.active` — Number of active monetized endpoints

---

## 9. Phase 7 — Testing

### 9.1 Unit Tests

```
tests/Unit/Domain/X402/
├── DataObjects/
│   ├── PaymentRequiredTest.php
│   ├── PaymentPayloadTest.php
│   └── PaymentRequirementsTest.php
├── Services/
│   ├── X402HeaderCodecServiceTest.php
│   ├── X402PricingServiceTest.php
│   ├── X402PaymentVerificationServiceTest.php
│   ├── X402SettlementServiceTest.php
│   ├── X402ClientServiceTest.php
│   └── X402EIP712SignerServiceTest.php
└── Enums/
    ├── X402NetworkTest.php
    └── PaymentSchemeTest.php
```

### 9.2 Feature Tests

```
tests/Feature/X402/
├── X402PaymentGateMiddlewareTest.php
├── X402EndpointManagementTest.php
├── X402SpendingLimitTest.php
├── X402PaymentFlowTest.php       // Full end-to-end: request → 402 → pay → 200
├── X402ClientAutoPayTest.php
└── X402MCPIntegrationTest.php
```

### 9.3 Key Test Scenarios

1. **No payment header** → Returns 402 with correctly structured `PAYMENT-REQUIRED`
2. **Valid EIP-3009 payment** → Verifies, settles, returns 200 with `PAYMENT-RESPONSE`
3. **Insufficient funds** → Returns 402 with `insufficient_funds` error
4. **Expired authorization** → Returns 402 with expiration error
5. **Amount mismatch** → Returns 402 with value error
6. **Non-monetized endpoint** → Passes through without 402 logic
7. **Agent auto-pay within limit** → Auto-signs and retries
8. **Agent auto-pay exceeds limit** → Triggers human approval workflow
9. **Facilitator timeout** → Graceful degradation with 500 error
10. **Base64 encoding roundtrip** → Encode → decode produces identical DTOs

---

## 10. Database Migrations

### Migration 1: `create_x402_payments_table`

```php
Schema::create('x402_payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('payer_address', 42);          // 0x-prefixed Ethereum address
    $table->string('pay_to_address', 42);
    $table->string('amount');                       // Atomic units
    $table->string('network');                      // CAIP-2 (e.g., "eip155:8453")
    $table->string('asset', 42);                   // Token contract address
    $table->string('scheme')->default('exact');
    $table->string('status')->default('pending');   // SettlementStatus enum
    $table->string('transaction_hash')->nullable();
    $table->string('endpoint_method', 10);
    $table->string('endpoint_path');
    $table->string('error_reason')->nullable();
    $table->string('error_message')->nullable();
    $table->json('payment_payload')->nullable();    // Full payload for audit
    $table->json('extensions')->nullable();
    $table->foreignUuid('team_id')->nullable()->constrained();  // Multi-tenancy
    $table->timestamp('verified_at')->nullable();
    $table->timestamp('settled_at')->nullable();
    $table->timestamps();

    $table->index(['payer_address', 'created_at']);
    $table->index(['status', 'created_at']);
    $table->index(['endpoint_path', 'created_at']);
    $table->index('transaction_hash');
    $table->index('team_id');
});
```

### Migration 2: `create_x402_monetized_endpoints_table`

```php
Schema::create('x402_monetized_endpoints', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('method', 10);                  // GET, POST, etc.
    $table->string('path');                         // /api/v1/premium/weather
    $table->string('price');                        // USD price (e.g., "0.001")
    $table->string('network');                      // CAIP-2
    $table->string('asset')->default('USDC');
    $table->string('scheme')->default('exact');
    $table->string('description')->nullable();
    $table->string('mime_type')->default('application/json');
    $table->boolean('is_active')->default(true);
    $table->json('extra')->nullable();              // EIP-712 domain overrides, etc.
    $table->foreignUuid('team_id')->nullable()->constrained();
    $table->timestamps();

    $table->unique(['method', 'path', 'team_id']);
    $table->index('is_active');
});
```

### Migration 3: `create_x402_spending_limits_table`

```php
Schema::create('x402_spending_limits', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('agent_id');                     // Agent DID or user ID
    $table->string('agent_type')->default('ai');    // ai, user, service
    $table->string('daily_limit');                  // Atomic units
    $table->string('spent_today')->default('0');
    $table->string('per_transaction_limit')->nullable();
    $table->boolean('auto_pay_enabled')->default(false);
    $table->timestamp('limit_resets_at');
    $table->foreignUuid('team_id')->nullable()->constrained();
    $table->timestamps();

    $table->unique(['agent_id', 'team_id']);
    $table->index('agent_type');
});
```

---

## 11. Configuration Reference

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `X402_ENABLED` | `false` | Enable x402 protocol support |
| `X402_PAY_TO_ADDRESS` | — | Wallet address for receiving payments |
| `X402_DEFAULT_NETWORK` | `eip155:8453` | Default blockchain network (Base mainnet) |
| `X402_DEFAULT_ASSET` | `USDC` | Default payment token |
| `X402_MAX_TIMEOUT` | `60` | Max payment timeout in seconds |
| `X402_SETTLE_BEFORE_RESPONSE` | `true` | Settle on-chain before returning response |
| `X402_FACILITATOR_URL` | `https://x402.org/facilitator` | Facilitator endpoint URL |
| `X402_FACILITATOR_TIMEOUT` | `30` | Facilitator HTTP timeout |
| `X402_SELF_HOSTED_FACILITATOR` | `false` | Run our own facilitator |
| `X402_CLIENT_ENABLED` | `false` | Enable client-side (paying) mode |
| `X402_CLIENT_SIGNER_KEY_ID` | — | KeyManagement key ID for client signing |
| `X402_CLIENT_AUTO_PAY` | `false` | Auto-pay for AI agents |
| `X402_CLIENT_MAX_AUTO_PAY` | `100000` | Max auto-pay per request (atomic USDC) |
| `X402_AGENT_DAILY_LIMIT` | `5000000` | Default daily spending limit (atomic USDC) |
| `X402_REQUIRE_APPROVAL_ABOVE` | `1000000` | Require human approval above this amount |

---

## 12. File Manifest

### New Files

| File | Type | Purpose |
|------|------|---------|
| `config/x402.php` | Config | x402 configuration |
| `app/Domain/X402/Providers/X402ServiceProvider.php` | Provider | Service bindings, event listeners |
| `app/Domain/X402/Contracts/FacilitatorClientInterface.php` | Contract | Facilitator abstraction |
| `app/Domain/X402/Contracts/PaymentSchemeInterface.php` | Contract | Payment scheme abstraction |
| `app/Domain/X402/Contracts/SignerInterface.php` | Contract | Signing abstraction |
| `app/Domain/X402/DataObjects/PaymentRequired.php` | DTO | 402 response payload |
| `app/Domain/X402/DataObjects/PaymentPayload.php` | DTO | Client payment submission |
| `app/Domain/X402/DataObjects/PaymentRequirements.php` | DTO | Single payment option |
| `app/Domain/X402/DataObjects/ResourceInfo.php` | DTO | Protected resource metadata |
| `app/Domain/X402/DataObjects/VerifyRequest.php` | DTO | Facilitator verify request |
| `app/Domain/X402/DataObjects/VerifyResponse.php` | DTO | Facilitator verify response |
| `app/Domain/X402/DataObjects/SettleRequest.php` | DTO | Facilitator settle request |
| `app/Domain/X402/DataObjects/SettleResponse.php` | DTO | Facilitator settle response |
| `app/Domain/X402/DataObjects/MonetizedRouteConfig.php` | DTO | Route pricing config |
| `app/Domain/X402/Enums/PaymentScheme.php` | Enum | exact, upto |
| `app/Domain/X402/Enums/X402Network.php` | Enum | CAIP-2 network IDs |
| `app/Domain/X402/Enums/SettlementStatus.php` | Enum | Payment lifecycle states |
| `app/Domain/X402/Enums/AssetTransferMethod.php` | Enum | eip3009, permit2 |
| `app/Domain/X402/Events/X402PaymentRequested.php` | Event | Payment requested |
| `app/Domain/X402/Events/X402PaymentVerified.php` | Event | Payment verified |
| `app/Domain/X402/Events/X402PaymentSettled.php` | Event | Payment settled on-chain |
| `app/Domain/X402/Events/X402PaymentFailed.php` | Event | Payment failed |
| `app/Domain/X402/Events/X402SettlementCompleted.php` | Event | Settlement finalized |
| `app/Domain/X402/Exceptions/X402VerificationException.php` | Exception | Verification failure |
| `app/Domain/X402/Exceptions/X402SettlementException.php` | Exception | Settlement failure |
| `app/Domain/X402/Exceptions/X402InvalidPayloadException.php` | Exception | Bad payload |
| `app/Domain/X402/Exceptions/X402InsufficientFundsException.php` | Exception | Insufficient balance |
| `app/Domain/X402/Models/X402Payment.php` | Model | Payment record |
| `app/Domain/X402/Models/X402MonetizedEndpoint.php` | Model | Monetized route config |
| `app/Domain/X402/Models/X402SpendingLimit.php` | Model | Agent spending limits |
| `app/Domain/X402/Services/X402FacilitatorService.php` | Service | Orchestrates verify+settle |
| `app/Domain/X402/Services/X402PaymentVerificationService.php` | Service | Payment verification |
| `app/Domain/X402/Services/X402SettlementService.php` | Service | On-chain settlement |
| `app/Domain/X402/Services/X402ClientService.php` | Service | Client-side 402 handling |
| `app/Domain/X402/Services/X402PricingService.php` | Service | Pricing and route config |
| `app/Domain/X402/Services/X402HeaderCodecService.php` | Service | Base64 JSON encoding |
| `app/Domain/X402/Services/X402EIP712SignerService.php` | Service | EIP-712 signing |
| `app/Domain/X402/Services/HttpFacilitatorClient.php` | Service | External facilitator HTTP |
| `app/Domain/X402/Services/X402HttpClient.php` | Service | Auto-pay HTTP wrapper |
| `app/Http/Middleware/X402PaymentGateMiddleware.php` | Middleware | 402 payment gate |
| `app/Http/Controllers/Api/X402EndpointController.php` | Controller | Endpoint CRUD |
| `app/Http/Controllers/Api/X402PaymentController.php` | Controller | Payment history |
| `app/Http/Controllers/Api/X402SpendingLimitController.php` | Controller | Spending limits |
| `app/Domain/X402/Routes/api.php` | Routes | x402 API routes |
| `app/Domain/AI/MCP/Tools/X402/X402PaymentTool.php` | MCP Tool | x402 payment tool |
| `graphql/x402.graphql` | GraphQL | x402 schema |
| `database/migrations/xxxx_create_x402_payments_table.php` | Migration | Payments table |
| `database/migrations/xxxx_create_x402_monetized_endpoints_table.php` | Migration | Endpoints table |
| `database/migrations/xxxx_create_x402_spending_limits_table.php` | Migration | Limits table |

### Modified Files

| File | Change |
|------|--------|
| `config/app.php` | Register `X402ServiceProvider` |
| `app/Http/Kernel.php` or `bootstrap/app.php` | Register `x402` middleware alias |
| `app/Domain/AI/MCP/ToolRegistry.php` | Add `registerMonetized()`, `isMonetized()`, `getToolPrice()` |
| `app/Domain/AI/MCP/MCPServer.php` | x402 payment handling in tool execution |
| `app/Domain/AI/Services/AIAgentService.php` | Auto-retry on 402 with payment |
| `app/Domain/AI/Services/AIAgentProtocolBridgeService.php` | Spending limit checks |
| `.env.example` | Add x402 environment variables |
| `.env.production.example` | Add x402 production variables |
| `graphql/schema.graphql` | Import x402 schema |

---

## 13. Integration Points

### Existing Service → x402 Integration

| Integration | How |
|------------|-----|
| `AgentPaymentIntegrationService` | Record x402 payments in agent payment history |
| `DigitalSignatureService` | Reuse for EIP-712 signature creation/verification |
| `GasStationService` | Sponsor settlement gas for self-hosted facilitator |
| `SmartAccountService` | Allow ERC-4337 smart accounts as x402 payers |
| `UserOperationSigningService` | Biometric-gated signing for mobile x402 payments (no longer exposed: the sign-userop endpoint was removed in v7.12.0) |
| `ShamirService` | Secure storage of facilitator wallet keys |
| `EventStreamPublisher` | Real-time x402 payment event streaming |
| `LiveMetricsService` | x402 dashboard metrics |
| `NotificationService` | Alert on large payments, limit breaches |
| `PluginManager` | x402 as a pluggable module (enable/disable per tenant) |

### x402 → AgentProtocol Bridge

When an x402 payment is received, create corresponding entries in:
- `PaymentRecorded` event (AgentProtocol event store)
- Agent reputation update (successful payment → positive reputation)
- Agent transaction history

---

## 14. Security Considerations

### Critical

1. **Facilitator wallet key isolation**: Use `KeyManagement/ShamirService` for key sharding if self-hosting
2. **Replay prevention**: EIP-3009 nonces are unique per authorization; cache used nonces
3. **Amount validation**: Always verify `payload.amount >= requirements.amount` server-side
4. **Time window validation**: Reject expired authorizations (`validBefore < now`)
5. **Recipient validation**: Ensure `payload.to === config.payTo` to prevent redirect attacks

### Important

6. **Rate limiting**: Apply `ApiRateLimitMiddleware` before `X402PaymentGateMiddleware`
7. **Spending limits**: Enforce per-agent daily limits and per-transaction caps
8. **Audit trail**: Log every x402 payment attempt (success and failure) with full payload
9. **Network validation**: Only accept payments on configured networks
10. **Base64 validation**: Reject malformed Base64 headers with 400, not 500

### Operational

11. **Facilitator failover**: Configure primary + fallback facilitator URLs
12. **Settlement timeout**: Set reasonable timeouts; return 200 with pending settlement if needed
13. **Balance monitoring**: Alert when receiving wallet approaches dust limits
14. **Key rotation**: Support facilitator key rotation without downtime

---

## 15. Rollout Strategy

### Phase A — Foundation (v5.2.0-alpha)
- Domain structure, DTOs, enums, config
- Header codec service
- Database migrations
- Unit tests for all DTOs and enums

### Phase B — Server Mode (v5.2.0-beta)
- Payment gate middleware
- Pricing service + monetized endpoint model
- Integration with external facilitator (x402.org testnet)
- Payment recording and event emission
- Feature tests for full 402 flow

### Phase C — Client Mode (v5.2.0-rc1)
- Client service with EIP-712 signing
- Auto-pay HTTP client wrapper
- Spending limit enforcement
- Integration with KeyManagement for secure signing

### Phase D — AI/MCP Integration (v5.2.0-rc2)
- ToolRegistry monetization extension
- MCPServer x402 handling
- AI agent auto-pay with human-in-the-loop
- X402PaymentTool for MCP

### Phase E — Admin & Production (v5.2.0)
- Filament admin resources
- GraphQL schema + resolvers
- Event streaming integration
- Dashboard metrics
- OpenAPI documentation
- Production facilitator configuration (Coinbase CDP)

---

## Appendix A: x402 Protocol Quick Reference

### HTTP Headers

| Header | Direction | Encoding | Content |
|--------|-----------|----------|---------|
| `PAYMENT-REQUIRED` | Server → Client | Base64 JSON | `PaymentRequired` |
| `PAYMENT-SIGNATURE` | Client → Server | Base64 JSON | `PaymentPayload` |
| `PAYMENT-RESPONSE` | Server → Client | Base64 JSON | `SettleResponse` |

### Facilitator Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/verify` | Verify payment authorization |
| `POST` | `/settle` | Execute on-chain settlement |
| `GET` | `/supported` | List supported schemes/networks |

### USDC Contract Addresses

| Network | Address |
|---------|---------|
| Base Mainnet (`eip155:8453`) | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` |
| Base Sepolia (`eip155:84532`) | `0x036CbD53842c5426634e7929541eC2318f3dCF7e` |

### EIP-3009 Authorization Types (EIP-712)

```json
{
  "TransferWithAuthorization": [
    { "name": "from", "type": "address" },
    { "name": "to", "type": "address" },
    { "name": "value", "type": "uint256" },
    { "name": "validAfter", "type": "uint256" },
    { "name": "validBefore", "type": "uint256" },
    { "name": "nonce", "type": "bytes32" }
  ]
}
```
