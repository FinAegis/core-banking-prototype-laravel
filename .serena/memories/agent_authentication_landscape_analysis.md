# FinAegis Agent Authentication Landscape Analysis

## Executive Summary

The FinAegis platform has a sophisticated authentication system with multiple mechanisms:
1. **Sanctum-based API token authentication** for user/agent API access (OAuth2-like)
2. **Passport (Laravel Passport)** configured as the API guard for OAuth2 flows
3. **DID-based agent identity system** with cryptographic validation
4. **API Key authentication** for service-to-service communication
5. **Scope-based access control** for OAuth2 scopes on Sanctum tokens

Agent Protocol implementation currently uses **Sanctum authentication** for protected endpoints but lacks agent-specific OAuth2 scope definitions and DID-based authentication mechanisms.

---

## 1. Current Authentication Mechanisms

### 1.1 Default Laravel Auth Configuration (config/auth.php)

**API Guard: Passport**
```php
'api' => [
    'driver' => 'passport',
    'provider' => 'users',
],
```

- Default API authentication driver is **Laravel Passport** (OAuth2)
- Provider is 'users' (User model)
- Passport handles OAuth2 token generation and validation
- Primary guard for API requests

### 1.2 Sanctum Configuration (config/sanctum.php)

**Token-based Authentication:**
- Token expiration: 1440 minutes (24 hours by default)
- First-party SPA support with stateful sessions
- Guard: `['web']` (configured to check web guard for sessions)
- Token prefix: Empty (can be configured)
- Middleware for authentication: `AuthenticateSession`, `EncryptCookies`, `ValidateCsrfToken`

**Current Routes Using Sanctum:**
```
- /api/auth/* (all auth endpoints use Sanctum)
- /api/user (profile endpoint)
- /api/accounts/* (most account operations)
- /api/agent-protocol/* (Agent Protocol endpoints)
- Protected routes marked with middleware: auth:sanctum, check.token.expiration
```

---

## 2. Agent Protocol Authentication

### 2.1 DID (Decentralized Identifier) System

**Location:** `app/Domain/AgentProtocol/Services/DIDService.php`

**DID Format:**
```
did:finaegis:{method}:{identifier}
```

Valid methods:
- `key` - Key-based identifiers
- `web` - Web-based identifiers  
- `agent` - Agent-specific identifiers

Example:
- `did:finaegis:agent:abc123def456`

**DID Generation:**
- Uses SHA256 hashing of UUID + timestamp + random bytes
- 32-character hex identifier
- Cached for 1 hour (configurable in `config/agent_protocol.php`)

**DID Validation:**
- Checks prefix starts with `did:finaegis:`
- Validates method is in allowed list
- Validates identifier is 32-character hex string

### 2.2 Agent Identity Model (AgentIdentity)

**Location:** `app/Domain/AgentProtocol/Models/AgentIdentity.php`

**Key Fields:**
- `agent_id` - UUID for agent
- `did` - Decentralized Identifier
- `name` - Agent name
- `type` - Service, user, or system
- `status` - active/inactive/suspended
- `capabilities` - JSON array of capabilities
- `reputation_score` - Float score
- `wallet_id` - Linked wallet
- `metadata` - JSON metadata

**Relationships:**
- `wallet()` - HasOne relationship
- `outgoingTransactions()` - HasMany
- `incomingTransactions()` - HasMany
- `sentEscrows()`, `receivedEscrows()` - Escrow relationships
- `disputes()` - Dispute relationships

### 2.3 Agent Model (Agent.php)

**Location:** `app/Models/Agent.php`

**Key Fields for Authentication:**
- `agent_id` - UUID
- `did` - DID identifier
- `name` - Agent name
- `type` - Agent type
- `status` - Operational status
- `kyc_verified` - Boolean
- `kyc_status` - Verification status
- `kyc_verification_level` - Basic/Enhanced/Full
- `reputation_score` - Integer score
- `endpoints` - JSON array (API endpoints, webhook URLs)
- `capabilities` - JSON array (what agent can do)
- `metadata` - JSON (authType, authRequired, authMechanisms, tokenEndpoint, etc.)

**Authentication-Related Metadata Fields:**
```php
$agent->metadata = [
    'authType' => 'bearer', // or 'api-key', 'did-auth'
    'authRequired' => true,
    'authMechanisms' => ['oauth2', 'api-key'], // Per RouteMessageActivity
    'tokenEndpoint' => 'https://agent-endpoint/token',
    'rateLimit' => 60, // requests per minute
];
```

---

## 3. Current OAuth2 Scopes

### 3.1 Existing Scopes in Routes

**Standard Scopes Used:**
```php
// In routes/api.php

// Basic CRUD scopes
'scope:read'    // GET operations
'scope:write'   // POST, PUT, PATCH operations
'scope:delete'  // DELETE operations

// Domain-specific scopes
'scope:treasury' // Treasury management endpoints
```

**Route Examples:**
```php
Route::post('/accounts', [...])
    ->middleware('scope:write');
    
Route::get('/accounts/{uuid}', [...])
    ->middleware('scope:read');

Route::delete('/accounts/{uuid}', [...])
    ->middleware('scope:delete');

// Treasury-specific
Route::middleware('scope:treasury')->group(function () {
    // Portfolio management
    Route::get('/portfolios', [PortfolioController::class, 'index']);
    Route::post('/portfolios', [PortfolioController::class, 'store']);
});
```

### 3.2 Scope Middleware Implementation

**Location:** `app/Http/Middleware/CheckApiScope.php`

**Functionality:**
- Checks if authenticated user's token has required scope(s)
- Multiple scopes can be required (OR logic)
- Test environment: Backward compatible with tokens without explicit abilities
- Production: Strict scope checking

**How It Works:**
```php
public function handle(Request $request, Closure $next, string ...$scopes): Response
{
    // If no user authenticated, pass through
    if (!$request->user()) {
        return $next($request);
    }

    // In testing, allow backward compatibility
    if (app()->environment('testing')) {
        // Check if token has any of the required scopes
        foreach ($scopes as $scope) {
            if ($request->user()->tokenCan($scope)) {
                return $next($request);
            }
        }
    } else {
        // Production: Standard scope checking
        foreach ($scopes as $scope) {
            if ($request->user()->tokenCan($scope)) {
                return $next($request);
            }
        }
    }

    // Deny if no matching scopes
    return response()->json([...], 403);
}
```

### 3.3 Sanctum Abilities

**Not Explicitly Defined in Code:**
- Scopes are passed to `Sanctum::actingAs()` in tests
- Scopes are checked via `tokenCan()` method on User model
- Passport handles scope registration and validation in OAuth2 flow

---

## 4. Existing API Key Authentication

### 4.1 API Key Middleware

**Location:** `app/Http/Middleware/AuthenticateApiKey.php`

**How It Works:**
```php
// Extract from Authorization header: "Bearer {apikey}"
$apiKeyString = substr($authHeader, 7);

// Verify API key
$apiKey = ApiKey::verify($apiKeyString);

// Check IP restrictions
if (!$apiKey->isIpAllowed($request->ip())) { ... }

// Check permissions
if (!$apiKey->hasPermission($permission)) { ... }

// Record usage
$apiKey->recordUsage($request->ip());

// Set as authenticated user
$request->setUserResolver(function () use ($apiKey) {
    return $apiKey->user;
});
```

**Features:**
- Bearer token format
- IP restriction validation
- Permission-based access control
- Usage logging and tracking
- Associates API key with user

---

## 5. Agent Protocol Authentication Gaps

### 5.1 Current Implementation Limitations

**1. No Agent-Specific OAuth2 Scopes**
- Agent endpoints use generic `auth:sanctum` middleware
- Only standard read/write/delete scopes available
- No agent-specific scope like `agent:payment`, `agent:escrow`, `agent:messaging`

**2. DID Authentication Not Integrated**
- DIDs are generated and stored but not used for authentication
- Payment/messaging endpoints require Sanctum token from linked user
- No DID-to-OAuth2 token binding mechanism

**3. Limited Authorization Granularity**
- Agents inherit user permissions (if linked)
- No agent-specific permissions or capabilities enforcing
- No scope-based capability validation

**4. Agent-to-Agent Communication**
- Currently planned for OAuth2 and API-key mechanisms
- No implemented DID-based authentication for agent-to-agent
- RouteMessageActivity shows planned support but not implemented

### 5.2 Authorization Methods Per Discovery Configuration

**From DiscoveryService.php:**
```php
'authentication_methods' => [
    'oauth2',   // OAuth2 Bearer tokens
    'api_key',  // API Key authentication
    'did_auth', // DID-based auth (not implemented)
],
```

**From RouteMessageActivity.php:**
```php
'authentication' => [
    'type' => 'bearer',
    'required' => true,
    'mechanisms' => ['oauth2', 'api-key'],
    'tokenEndpoint' => $agentInfo['tokenEndpoint'] ?? null,
]
```

---

## 6. Key Authentication Configuration Files

### 6.1 config/agent_protocol.php

**Relevant Security Settings:**
```php
'security' => [
    'rate_limiting' => [
        'enabled' => env('AGENT_RATE_LIMIT_ENABLED', true),
        'max_requests_per_minute' => env('AGENT_MAX_REQUESTS', 60),
        'max_payments_per_hour' => env('AGENT_MAX_PAYMENTS_HOUR', 100),
    ],
    'transaction_limits' => [
        'daily_limit' => env('AGENT_DAILY_LIMIT', 10000.00),
        'single_transaction_limit' => env('AGENT_SINGLE_LIMIT', 5000.00),
    ],
],

'kyc' => [
    'enabled' => env('AGENT_KYC_ENABLED', true),
    'inherit_from_linked_user' => env('AGENT_KYC_INHERIT', true),
    'levels' => [
        'basic' => ['daily_limit' => 1000, ...],
        'enhanced' => ['daily_limit' => 10000, ...],
        'full' => ['daily_limit' => null, ...], // Unlimited
    ],
],

'system_agents' => [
    'admin_dids' => explode(',', env('SYSTEM_ADMIN_DIDS', '...')),
    'system_did' => env('SYSTEM_AGENT_DID', 'did:agent:finaegis:system'),
    'treasury_did' => env('TREASURY_AGENT_DID', 'did:agent:finaegis:treasury'),
    'reserve_did' => env('RESERVE_AGENT_DID', 'did:agent:finaegis:reserve'),
],
```

### 6.2 config/auth.php

**Guards Configuration:**
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],
```

---

## 7. Agent Protocol Routes & Middleware

### 7.1 Agent Protocol Endpoints

**Location:** `routes/api.php` (Lines 785-863)

**Public Endpoints (No Auth):**
```php
Route::get('/.well-known/ap2-configuration', [AgentIdentityController::class, 'wellKnownConfiguration']);
Route::get('/agent-protocol/agents/discover', [AgentIdentityController::class, 'discover']);
Route::get('/agent-protocol/agents/{did}', [AgentIdentityController::class, 'show']);
Route::get('/agent-protocol/agents/{did}/reputation', [AgentReputationController::class, 'show']);
Route::get('/reputation/leaderboard', [AgentReputationController::class, 'leaderboard']);
```

**Protected Endpoints (Sanctum + Rate Limit):**
```php
Route::middleware(['auth:sanctum', 'check.token.expiration', 'api.rate_limit:private'])->group(function () {
    // Agent registration
    Route::post('/agents/register', [AgentIdentityController::class, 'register']);
    Route::put('/agents/{did}/capabilities', [AgentIdentityController::class, 'updateCapabilities']);
    
    // Payment endpoints
    Route::post('/agents/{did}/payments', [AgentPaymentController::class, 'initiatePayment'])
        ->middleware('transaction.rate_limit:agent_payment');
    Route::post('/agents/{did}/payments/{transactionId}/confirm', [...])
        ->middleware('transaction.rate_limit:agent_payment');
    
    // Escrow endpoints
    Route::post('/escrow', [AgentEscrowController::class, 'create'])
        ->middleware('transaction.rate_limit:agent_escrow');
    
    // Messaging endpoints
    Route::post('/agents/{did}/messages', [AgentMessageController::class, 'send'])
        ->middleware('transaction.rate_limit:agent_message');
    
    // Reputation endpoints
    Route::post('/agents/{did}/reputation/feedback', [...]);
});
```

**Middleware Stack:**
1. `auth:sanctum` - Require Sanctum token
2. `check.token.expiration` - Check token not expired
3. `api.rate_limit:private` - Apply private API rate limiting
4. `transaction.rate_limit:*` - Apply transaction-specific rate limiting

---

## 8. Agent Services Related to Authentication

### 8.1 AgentRegistryService

**Location:** `app/Domain/AgentProtocol/Services/AgentRegistryService.php`

**Key Methods:**
- `registerAgent()` - Create new agent, create aggregate, store model
- `agentExists()` - Check if agent is registered and active
- `getAgent()` - Retrieve agent by ID with caching
- `updateAgentStatus()` - Change agent status
- `updateAgentEndpoints()` - Update agent endpoints

### 8.2 DigitalSignatureService

**Location:** `app/Domain/AgentProtocol/Services/DigitalSignatureService.php`

**Key Methods:**
```php
// Sign transactions for non-repudiation
public function signAgentTransaction(
    string $transactionId,
    string $agentId,
    array $transactionData,
    array $options = []
): array

// Verify transaction signatures
public function verifyAgentSignature(
    string $transactionId,
    string $agentId,
    array $transactionData,
    string $signature,
    array $metadata
): array
```

**Features:**
- RS256 or Ed25519 signature algorithms
- Nonce generation for replay protection
- Signature expiration
- Multi-factor authentication support

### 8.3 TransactionVerificationService

**Location:** `app/Domain/AgentProtocol/Services/TransactionVerificationService.php`

**Verification Includes:**
- Multi-factor authentication verification
- Fraud detection checks
- Transaction amount validation
- Security checks

---

## 9. Summary: Authentication Landscape

| Component | Current Implementation | Status | Notes |
|-----------|----------------------|--------|-------|
| **User Auth** | Sanctum + Passport | ✅ Full | Works for web users and API access |
| **API Key Auth** | Custom middleware | ✅ Full | Service-to-service authentication |
| **OAuth2 Scopes** | Basic (read/write/delete) | ⚠️ Limited | No agent-specific scopes defined |
| **DID Generation** | DIDService | ✅ Full | DIDs generated and validated |
| **DID Authentication** | Not implemented | ❌ Missing | DIDs not used for auth yet |
| **Agent Registration** | Via Sanctum auth | ✅ Full | Requires authenticated user |
| **Agent Payments** | Via Sanctum auth | ✅ Full | Protected by Sanctum token |
| **Digital Signatures** | SignatureService | ✅ Full | For non-repudiation and audit |
| **Agent-to-Agent Auth** | Planned (OAuth2/API-Key) | 🔶 Partial | Infrastructure ready, not implemented |
| **Capability Verification** | Service discovery | ✅ Full | Via AgentDiscoveryService |
| **Transaction Limits** | KYC-based limits | ✅ Full | Via config and KYC verification |

---

## 10. Recommended Next Steps for Agent Authentication Implementation

1. **Define Agent-Specific OAuth2 Scopes**
   - `agent:payment` - Make payments
   - `agent:escrow` - Manage escrow
   - `agent:messaging` - Send A2A messages
   - `agent:reputation` - Modify reputation
   - `agent:capability` - Register capabilities

2. **Implement DID-Based Authentication**
   - Create DID provider for Laravel auth
   - Implement DID signature verification
   - Create middleware for DID-based protection

3. **Link DIDs to OAuth2 Tokens**
   - Store agent DID in token metadata
   - Validate agent identity in scope middleware
   - Enforce capability-based authorization

4. **Implement Agent-to-Agent Communication Auth**
   - OAuth2 client credentials flow for agents
   - DID-based authentication for peer agents
   - Rate limiting per agent pair

5. **Add Agent Credentials Management**
   - API keys for agents
   - OAuth2 client credentials
   - DID registration and renewal
