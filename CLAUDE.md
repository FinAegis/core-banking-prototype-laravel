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
| v2.7.0 | 📋 Planned | AI Query Endpoints, RegTech Adapters, SDK Generation, BaaS Implementation |
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
