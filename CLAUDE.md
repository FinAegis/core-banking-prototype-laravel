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
├── Domain/           # DDD bounded contexts (30+ domains)
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
│   ├── CrossChain/   # Bridge protocols, cross-chain swaps, multi-chain portfolio (v3.0.0)
│   ├── DeFi/         # DEX aggregation, lending, staking, yield optimization (v3.0.0)
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

### Key Services (DON'T RECREATE)
| Need | Existing Service |
|------|------------------|
| Hardware Wallets | `HardwareWalletManager` (Wallet) |
| Ledger Signing | `LedgerSignerService` (Wallet) |
| Trezor Signing | `TrezorSignerService` (Wallet) |
| Webhook Processing | `WebhookProcessorService` (Custodian) |
| Agent Payments | `AgentPaymentIntegrationService` (AgentProtocol) |
| Yield Optimization | `YieldOptimizationService` (Treasury) |
| Mobile Devices | `MobileDeviceService` (Mobile) |
| Biometric Auth | `BiometricAuthenticationService` (Mobile) |
| Push Notifications | `PushNotificationService` (Mobile) |
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
| Config | `.env.example`, `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.php` |
| CI/CD | `.github/workflows/ci-pipeline.yml`, `.github/workflows/security.yml` |
| Docs | `docs/`, `README.md` |

---

## Notes

- Always work in feature branches
- Ensure GitHub Actions pass before merging
- Use Serena memories for detailed context
- Never create docs files unless explicitly requested
- Prefer editing existing files over creating new ones
