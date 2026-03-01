# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

---

## Quick Start (READ FIRST)

```bash
# 1. Check current state
git status && git branch --show-current

# 2. Read Serena memory for session context
# mcp__serena__read_memory("development_continuation_guide")

# 3. Quick health check
./vendor/bin/pest --parallel --stop-on-failure
```

### Version Status
| Version | Status | Key Changes |
|---------|--------|-------------|
| v5.8.0 | ✅ Released | Mobile Go-Live: Rewards GraphQL (35th domain) + Filament admin, Pimlico bundler production submission, Marqeta card transactions, DB merchants, Chainalysis sanctions, recovery shard backup CRUD, WebSocket mobile channels (privacy/commerce/trustcert/user), privacy calldata persistence with encrypted storage, OpenAPI PHP 8 attributes migration, 7 PRs (#670-#676) |
| v5.7.0 | ✅ Released | Mobile Rewards & Security Hardening: Rewards/gamification domain (quests, XP/levels, points shop, streaks with race-safe locking), WebAuthn FIDO2 hardening (rpIdHash, UV/UP flags, COSE validation, origin check), recent recipients, notification unread count, route aliases, 44 feature tests |
| v5.6.0 | ✅ Released | RAILGUN Privacy Protocol: Node.js bridge service (@railgun-community/wallet SDK), RailgunBridgeClient HTTP client, RailgunMerkleTreeService/ZkProverService, RailgunPrivacyService orchestrator (shield/unshield/transfer), RailgunWallet/ShieldedBalance models, PrivacyController integration, 57 tests, chains: Ethereum/Polygon/Arbitrum/BSC |
| v5.5.0 | ✅ Released | Production Relayer & Card Webhooks: ERC-4337 Pimlico v2 integration (bundler, paymaster, smart account factory), Marqeta webhook Basic Auth + HMAC verification, .env.zelta.example sync, platform hardening (IdempotencyMiddleware, E2E tests, Dependabot triage) |
| v5.4.1 | ✅ Released | Platform Hardening: Dependabot triage (PRs #642-#659), IdempotencyMiddleware, E2E tests, multi-tenancy isolation tests, CI reliability, docs refresh |
| v5.4.0 | ✅ Released | Ondato KYC, Sanctions Screening & Card Issuing: Ondato identity verification with TrustCert linkage, Chainalysis sanctions adapter, Marqeta card issuing adapter, Firebase FCM v1 migration, X402/mobile test hardening, CVE patches |
| v5.2.0 | ✅ Released | X402 Protocol: HTTP-native micropayments (USDC on Base), payment gate middleware, facilitator integration, AI agent payments, spending limits, GraphQL/REST APIs, MCP tool |
| v5.1.6 | ✅ Released | Security Hardening: copyright year, accessibility improvements, CSP headers, email config defaults |
| v5.1.5 | ✅ Released | Dependency Cleanup: l5-swagger 9→10 (swagger-php 6), PSR-4 plugin fix, `.env.production.example`, passkey test fix |
| v5.1.4 | ✅ Released | Refresh Token Mechanism: proper access/refresh token pairs with rotation, PHPStan fix, OpenAPI docs update |
| v5.1.3 | ✅ Released | Mobile API Compat: optional `owner_address` for smart account onboarding, auth response standardization, token refresh/logout-all endpoints, rate limiter 500 fix |
| v5.1.2 | ✅ Released | Production Landing Page Fix: standalone pre-compiled CSS for `/app` (CSP-compliant, Vite-independent) |
| v5.1.1 | ✅ Released | Mobile App Landing Page: `/app` teaser with email signup, feature showcase, flaky Azure HSM test fix |
| v5.1.0 | ✅ Released | Mobile API Completeness: 21 mobile endpoints (Privacy, Commerce, Card, Wallet, Mobile), GraphQL 33-domain full coverage, blockchain address models, CI hardening, axios CVE fix |
| v5.0.1 | ✅ Released | Platform Hardening: GraphQL CQRS alignment (21 mutations), OpenAPI 100% coverage (52 controllers), Plugin Marketplace UI, PHP 8.4 CI, 97 test conversions, documentation refresh |
| v5.0.0 | ✅ Released | Streaming Architecture (MAJOR): Event streaming (Redis Streams publisher/consumer), live dashboard (5 metrics endpoints), notification system (5 channels), API gateway middleware |
| v4.3.0 | ✅ Released | Developer Experience: 4 new GraphQL domains (Fraud, Mobile, MobilePayment, TrustCert), dashboard widget plugin, CLI commands (schema-check, plugin-verify, domain-status), GraphQL rate limiting + query cost analysis |
| v4.2.0 | ✅ Released | Real-time Platform: GraphQL subscriptions (4 new), plugin hook system (17 hooks), webhook-notifier + audit-exporter plugins, core domain mutations (8 new) |
| v4.1.0 | ✅ Released | GraphQL Expansion: 6 new domains (Treasury, Payment, Lending, Stablecoin, CrossChain, DeFi), event replay filtering, projector health monitoring |
| v4.0.0 | ✅ Released | Architecture Evolution: Event Store v2 (domain routing, migration tooling, upcasting), GraphQL API (lighthouse-php, 4 domains, subscriptions, DataLoaders), Plugin Marketplace (manager, sandbox, security scanner) |
| v3.5.0 | ✅ Released | Compliance Certification: SOC 2 Type II, PCI DSS readiness, multi-region deployment, GDPR enhanced (ROPA, DPIA, breach notification, consent v2, retention) |
| v3.4.0 | ✅ Released | API Maturity & DX: API versioning, tier-aware rate limiting, SDK generation, OpenAPI annotations (143+ endpoints) |
| v3.3.0 | ✅ Released | Event Store Optimization & Observability: Event replay/rebuild/stats/cleanup commands, observability dashboards, structured logging, deep health checks, event archival/compaction |
| v3.2.0 | ✅ Released | Production Readiness & Plugin Architecture: Module manifests, enable/disable, modular routes, module admin API/UI, k6 load tests, query performance middleware, open-source templates |
| v3.1.0 | ✅ Released | Consolidation, Documentation & UI Completeness: Swagger annotations, 7 feature pages, 15 Filament admin resources, 4 user-facing views, developer portal update |
| v3.0.0 | ✅ Released | Cross-Chain & DeFi: Bridge protocols (Wormhole/LayerZero/Axelar), DeFi connectors (Uniswap/Aave/Curve/Lido), cross-chain swaps, multi-chain portfolio |
| v2.10.0 | ✅ Released | Mobile API Compatibility: Wallet, TrustCert, Commerce, Relayer mobile endpoints |
| v2.9.0 | ✅ Released | BaaS Implementation, SDK Generation, Production Hardening |
| v2.8.0 | ✅ Released | AI Query Endpoints, RegTech Adapters, MiFID II/MiCA/Travel Rule Services |
| v2.7.0 | ✅ Released | Mobile Payment API, Passkey Auth, P2P Transfer Helpers, TrustCert Export, Security Hardening |
| v2.6.0 | ✅ Released | Privacy Layer & ERC-4337: Merkle Trees, Smart Accounts, Delegated Proofs, UserOp Signing with Biometric JWT, Production-Ready Gas Station |
| v2.5.0 | ✅ Released | Mobile App Launch (Expo/React Native, separate repo) |
| v2.4.0 | ✅ Released | Privacy & Identity: Key Management, Privacy Layer, Commerce, TrustCert |
| v2.3.0 | ✅ Released | AI Framework, RegTech Foundation, BaaS Configuration |
| v2.2.0 | ✅ Released | Mobile backend: device mgmt, biometrics, push notifications |
| v2.1.0 | ✅ Released | Security hardening, Hardware wallets, WebSocket, Kubernetes |
| v2.0.0 | ✅ Released | Multi-Tenancy (stancl/tenancy v3.9) |

### Key Serena Memories
| Memory | Purpose |
|--------|---------|
| `development_continuation_guide` | Master handoff document |
| `v2.2.0_mobile_backend_implementation` | v2.2.0 Mobile implementation details |
| `coding_standards_and_conventions` | Code style reference |
| `project_architecture_overview` | Architecture details |

---

## Essential Commands

### Pre-Commit (ALWAYS RUN)
```bash
./bin/pre-commit-check.sh --fix    # Auto-fix and check
./bin/pre-commit-check.sh --all    # Full codebase check
```

### Testing
```bash
./vendor/bin/pest --parallel                                    # All tests
./vendor/bin/pest --parallel --coverage --min=50                # With coverage
./vendor/bin/pest tests/Domain/                                  # Domain tests only
```

### Code Quality
```bash
./vendor/bin/php-cs-fixer fix                                    # Code style
./vendor/bin/phpcbf --standard=PSR12 app/                        # PSR-12 fix
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G     # Static analysis
```

### Development
```bash
php artisan serve                   # Start server
npm run dev                         # Vite dev server
php artisan migrate:fresh --seed    # Reset database
php artisan horizon                 # Queue monitoring
php artisan l5-swagger:generate     # API docs
```

### Multi-Tenancy (v2.0.0)
```bash
php artisan tenants:migrate                              # Tenant migrations
php artisan tenants:migrate-data --tenant=<uuid>         # Data migration
php artisan tenants:export-data <id> --format=json       # Export data
```

---

## Architecture Overview

```
app/
├── Domain/           # DDD bounded contexts (42 domains)
│   ├── Account/      # Account management
│   ├── Exchange/     # Trading engine
│   ├── Lending/      # P2P lending
│   ├── Mobile/       # Mobile wallet backend (v2.2.0)
│   ├── Treasury/     # Portfolio management
│   ├── Wallet/       # Blockchain wallets
│   ├── Compliance/   # KYC/AML
│   ├── KeyManagement/# Shamir's Secret Sharing, HSM (v2.4.0)
│   ├── Privacy/      # ZK-KYC, Proof of Innocence, Merkle Trees (v2.4.0+v2.6.0)
│   ├── Commerce/     # SBT, Merchants, Attestations (v2.4.0)
│   ├── TrustCert/    # W3C VCs, Certificate Authority (v2.4.0)
│   ├── Relayer/      # ERC-4337 Gas Abstraction, Smart Accounts (v2.6.0)
│   ├── MobilePayment/# Payment Intents, Receipts, Activity Feed (v2.7.0)
│   ├── RegTech/      # MiFID II, MiCA, Travel Rule, Jurisdiction Adapters (v2.8.0)
│   ├── X402/         # HTTP 402 Protocol, Payment Gate, AI Agent Payments (v5.2.0)
│   ├── CrossChain/   # Bridge protocols, cross-chain swaps, multi-chain portfolio (v3.0.0)
│   ├── DeFi/         # DEX aggregation, lending, staking, yield optimization (v3.0.0)
│   ├── Rewards/      # Gamification: quests, XP/levels, points shop, streaks (v5.7.0)
│   └── Shared/       # CQRS interfaces, events
├── Infrastructure/   # CQRS bus implementations
├── Http/Controllers/ # REST API
├── Models/           # Eloquent models
└── Filament/         # Admin panel
```

### Key Patterns
- **Event Sourcing**: Spatie Event Sourcing with domain-specific event tables
- **CQRS**: Command/Query Bus with read/write separation
- **Sagas**: Laravel Workflow with compensation
- **Multi-Tenancy**: Team-based isolation with `UsesTenantConnection` trait
- **GraphQL API**: Lighthouse PHP with schema-first design, 34 domain schemas
- **Event Streaming**: Redis Streams publisher/consumer with domain routing

### Key Services (DON'T RECREATE)
| Need | Existing Service |
|------|------------------|
| X402 Payment Gate | `X402PaymentGateMiddleware` (X402) |
| X402 Settlement | `X402SettlementService` (X402) |
| X402 Client | `X402ClientService` (X402) |
| X402 Verification | `X402PaymentVerificationService` (X402) |
| X402 Pricing | `X402PricingService` (X402) |
| X402 MCP Tool | `X402PaymentTool` (AI/MCP) |
| Hardware Wallets | `HardwareWalletManager` (Wallet) |
| Ledger Signing | `LedgerSignerService` (Wallet) |
| Trezor Signing | `TrezorSignerService` (Wallet) |
| Webhook Processing | `WebhookProcessorService` (Custodian) |
| Agent Payments | `AgentPaymentIntegrationService` (AgentProtocol) |
| Yield Optimization | `YieldOptimizationService` (Treasury) |
| Mobile Devices | `MobileDeviceService` (Mobile) |
| Biometric Auth | `BiometricAuthenticationService` (Mobile) |
| Push Notifications | `PushNotificationService` (Mobile) |
| Firebase Messaging | `kreait/laravel-firebase` → `Kreait\Firebase\Contract\Messaging` |
| Mobile Sessions | `MobileSessionService` (Mobile) |
| Key Sharding | `ShamirService` (KeyManagement) |
| ZK-KYC | `ZkKycService` (Privacy) |
| Proof of Innocence | `ProofOfInnocenceService` (Privacy) |
| Soulbound Tokens | `SoulboundTokenService` (Commerce) |
| Merchant Onboarding | `MerchantOnboardingService` (Commerce) |
| Verifiable Credentials | `VerifiableCredentialService` (TrustCert) |
| Certificate Authority | `CertificateAuthorityService` (TrustCert) |
| Trust Framework | `TrustFrameworkService` (TrustCert) |
| Smart Accounts | `SmartAccountService` (Relayer) |
| Gas Station | `GasStationService` (Relayer) |
| UserOp Signing | `UserOperationSigningService` (Relayer) |
| Wallet Balance | `WalletBalanceService` (Relayer) |
| Biometric JWT | `BiometricJWTService` (Mobile) |
| Merkle Trees | `MerkleTreeService` (Privacy) |
| Delegated Proofs | `DelegatedProofService` (Privacy) |
| SRS Manifest | `SrsManifestService` (Privacy) |
| Payment Intents | `PaymentIntentService` (MobilePayment) |
| Receipts | `ReceiptService` (MobilePayment) |
| Activity Feed | `ActivityFeedService` (MobilePayment) |
| Receive Address | `ReceiveAddressService` (MobilePayment) |
| Network Status | `NetworkAvailabilityService` (MobilePayment) |
| Passkey Auth | `PasskeyAuthenticationService` (Mobile) |
| Wallet Transfer | `WalletTransferService` (Wallet) |
| Certificate Export | `CertificateExportService` (TrustCert) |
| MiFID II Reporting | `MifidReportingService` (RegTech) |
| MiCA Compliance | `MicaComplianceService` (RegTech) |
| Travel Rule | `TravelRuleService` (RegTech) |
| RegTech Orchestration | `RegTechOrchestrationService` (RegTech) |
| AI Transaction Query | `TransactionQueryTool` (AI) |
| Bridge Orchestration | `BridgeOrchestratorService` (CrossChain) |
| Bridge Fee Comparison | `BridgeFeeComparisonService` (CrossChain) |
| Cross-Chain Swap | `CrossChainSwapService` (CrossChain) |
| Multi-Chain Portfolio | `MultiChainPortfolioService` (CrossChain) |
| Cross-Chain Yield | `CrossChainYieldService` (CrossChain) |
| Swap Aggregation | `SwapAggregatorService` (DeFi) |
| Swap Routing | `SwapRouterService` (DeFi) |
| DeFi Portfolio | `DeFiPortfolioService` (DeFi) |
| DeFi Positions | `DeFiPositionTrackerService` (DeFi) |
| Flash Loans | `FlashLoanService` (DeFi) |
| Event Stream Publishing | `EventStreamPublisher` (Shared/EventSourcing) |
| Event Stream Consuming | `EventStreamConsumer` (Shared/EventSourcing) |
| Live Metrics | `LiveMetricsService` (Monitoring) |
| Notifications | `NotificationService` (Shared/Notifications) |
| Plugin Management | `PluginManager` (Infrastructure/Plugins) |
| Plugin Sandbox | `PluginSandbox` (Infrastructure/Plugins) |
| Plugin Security | `PluginSecurityScanner` (Infrastructure/Plugins) |
| Projector Health | `ProjectorHealthService` (Monitoring) |
| Rewards | `RewardsService` (Rewards) |
| RAILGUN Bridge | `RailgunBridgeClient` (Privacy) |
| RAILGUN Privacy | `RailgunPrivacyService` (Privacy) |

---

## Code Conventions

### PHP Standards
```php
<?php
declare(strict_types=1);

namespace App\Domain\Exchange\Services;

class OrderMatchingService
{
    public function __construct(
        private readonly OrderRepository $repository
    ) {}
}
```

### Import Order
1. `App\Domain\...` → 2. `App\Http\...` → 3. `App\Models\...` → 4. `Illuminate\...` → 5. Third-party

### Commit Messages
```
feat: Add liquidity pool management
fix: Resolve order matching race condition
test: Add coverage for wallet workflows

Co-Authored-By: Claude <noreply@anthropic.com>
```

---

## CI/CD Quick Reference

### Common Fixes
| Issue | Fix |
|-------|-----|
| PHPStan type errors | Cast return types: `(int)`, `(string)`, `(float)` |
| Test isolation failures | Add `Cache::flush()` in `setUp()` |
| Code style violations | Run `./vendor/bin/php-cs-fixer fix` |

### GitHub Actions
```bash
gh pr checks <PR_NUMBER>              # Check PR status
gh run view <RUN_ID> --log-failed     # View failed logs
```

---

## Task Completion Checklist

Before marking any task complete:
1. Run `./bin/pre-commit-check.sh --fix`
2. Verify tests pass: `./vendor/bin/pest --parallel`
3. Update API docs if endpoints changed: `php artisan l5-swagger:generate`
4. Commit with conventional commit message

---

## Important Files

| Category | Files |
|----------|-------|
| Config | `.env.example`, `.env.production.example`, `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.php` |
| CI/CD | `.github/workflows/ci-pipeline.yml`, `.github/workflows/security.yml` |
| Docs | `docs/`, `README.md` |

---

## Notes

- Always work in feature branches
- Ensure GitHub Actions pass before merging
- Use Serena memories for detailed context
- Never create docs files unless explicitly requested
- Prefer editing existing files over creating new ones
