# Release Plan v1.1.0 - Quality & Completeness Release

**Target Release Date**: Q1 2025 (Minor Release)
**Codename**: "Foundation Hardening"
**Focus**: Code Quality, Test Coverage, and Feature Completion

---

## Executive Summary

This minor release focuses on **hardening the v1.0.0 open-source foundation** by:
1. Reducing technical debt from PHPStan baselines
2. Expanding test coverage for untested domains
3. Completing TODO items and incomplete features
4. Hardening CI/CD pipeline with fail-on-security-issues

**Key Deliverables**:
- PHPStan baseline reduction by 50%+
- Test coverage for Banking and Governance domains
- Complete Phase 6 integration tasks
- Enhanced CI/CD with security audit enforcement

---

## Current State Analysis (v1.0.0)

### What Was Released

| Component | Status | Files | Tests |
|-----------|--------|-------|-------|
| **Core Banking** (Account, Banking, Compliance) | Production Ready | 277 | ~60 |
| **Trading & Digital Assets** (Exchange, Basket, Stablecoin, Wallet) | Production Ready | 311 | ~80 |
| **Agent Protocol** (AP2/A2A) | Production Ready | 180 | ~50 |
| **Treasury Management** | Mature | 62 | ~9 |
| **Lending Platform** | Mature | 48 | ~9 |
| **Governance** | Production Ready | 29 | **0** |
| **AI Framework** (MCP Tools) | Production Ready | 13 dirs | ~19 |
| **Infrastructure** (CQRS, Event Sourcing) | Complete | 50+ | ~30 |

### Technical Debt Inventory

| Category | Current State | Target |
|----------|---------------|--------|
| PHPStan baseline-level6.neon | 45,625 lines | <15,000 lines |
| PHPStan baseline.neon | 9,007 lines | <3,000 lines |
| TODO/FIXME comments | 12 items | 0 items |
| Static methods | 394 methods | <200 methods |
| Untested domains | 5 domains | 0 domains |
| E2E test scenarios | 1 Behat feature | 10+ features |

### Open TODO Items (from TODO.md)

**Phase 6 - Integration & Testing (Incomplete)**:
- [ ] Connect agent wallets to main payment system
- [ ] Integrate with existing KYC/AML workflows
- [ ] Link to AI Agent framework
- [ ] Connect to multi-agent coordination service
- [ ] Protocol compliance testing

**Medium Priority - Feature Enhancement**:
- [ ] Enhanced Due Diligence (EDD) workflows
- [ ] Hardware wallet integration (Ledger, Trezor)
- [ ] Multi-signature support

**Infrastructure**:
- [ ] ELK Stack log aggregation
- [ ] Advanced Grafana dashboards per domain
- [ ] E2E test suite expansion

---

## v1.1.0 Release Scope

### Theme 1: Code Quality (50% of effort)

#### 1.1 PHPStan Baseline Reduction
**Goal**: Reduce total baseline lines by 50%

**Priority Fixes**:

1. **Event Sourcing Return Types** (~40 errors)
   ```php
   // BEFORE
   /** @return App\Domain\Account\Aggregates\TransactionAggregate */
   public function credit(Money $amount): self

   // AFTER
   /** @return static */
   public function credit(Money $amount): static
   ```

2. **Null Safety Issues** (~15 errors)
   - Add null-safe operators (?->) in AI/MCP services
   - Fix Carbon|null method calls
   - Add proper null checks in reflection methods

3. **Factory Return Types** (~10 errors)
   - Use specific model types instead of generic Model|Collection
   - Add generics annotations where appropriate

4. **Value Object Dynamic Properties** (~20 errors)
   - Convert magic properties to explicit declarations
   - Use Spatie Laravel Data for immutable objects

**Deliverables**:
- [ ] Reduce phpstan-baseline-level6.neon from 45,625 to <20,000 lines
- [ ] Reduce phpstan-baseline.neon from 9,007 to <4,000 lines
- [ ] Zero new baseline additions

#### 1.2 TODO/FIXME Resolution

| File | Issue | Resolution |
|------|-------|------------|
| `DomainServiceProvider.php` | LoanDisbursementSaga missing | Implement saga |
| `PayseraDepositController.php` | Integration incomplete | Complete or remove |
| `YieldOptimizationController.php` | Portfolio optimization TODO | Implement fully |
| `BatchProcessingController.php` | Scheduling/cancellation incomplete | Complete features |
| `AgentProtocol NotifyReputationChangeActivity.php` | Notification not implemented | Add Laravel notification |
| `BasketService.php` | Query service refactor | Extract to dedicated service |
| `Exchange LiquidityRetryPolicy.php` | RetryOptions blocked | Document limitation |
| `StablecoinAggregateRepository.php` | Reserves implementation | Implement when model ready |
| `ProcessCustodianWebhook.php` | Webhook processing incomplete | Complete integration |

**Deliverables**:
- [ ] 0 TODO/FIXME comments in production code
- [ ] All incomplete features either completed or documented as "planned for v1.2"

#### 1.3 Static Method Consolidation

**Target**: Convert high-value static helpers to injectable services

**Priority Conversions**:
1. Helper classes with side effects
2. Static methods that access configuration
3. Static methods that could benefit from mocking in tests

**Deliverables**:
- [ ] Reduce static methods from 394 to <250
- [ ] Document remaining static methods (pure utility functions)

---

### Theme 2: Test Coverage Expansion (30% of effort)

#### 2.1 Domain Test Coverage

**Critical Gaps to Fill**:

| Domain | Current | Target | Priority |
|--------|---------|--------|----------|
| Banking | 0 tests | 30+ tests | HIGH |
| Governance | 0 tests | 25+ tests | HIGH |
| Regulatory | 0 tests | 15+ tests | MEDIUM |
| Product | 0 tests | 10+ tests | MEDIUM |
| User | 1 test | 15+ tests | MEDIUM |

**Banking Domain Test Coverage**:
```
tests/Domain/Banking/
├── Aggregates/
│   ├── BankTransferAggregateTest.php
│   └── ReconciliationAggregateTest.php
├── Services/
│   ├── SEPATransferServiceTest.php
│   ├── SWIFTTransferServiceTest.php
│   └── BankRoutingServiceTest.php
├── Workflows/
│   ├── InternationalTransferWorkflowTest.php
│   └── ReconciliationWorkflowTest.php
└── Connectors/
    ├── PayseraConnectorTest.php
    ├── DeutscheBankConnectorTest.php
    └── SantanderConnectorTest.php
```

**Governance Domain Test Coverage**:
```
tests/Domain/Governance/
├── Aggregates/
│   ├── ProposalAggregateTest.php
│   └── VotingAggregateTest.php
├── Services/
│   ├── VotingServiceTest.php
│   └── ProposalLifecycleServiceTest.php
├── Strategies/
│   ├── AssetWeightedVotingTest.php
│   └── DemocraticVotingTest.php
└── Workflows/
    └── ProposalExecutionWorkflowTest.php
```

**Deliverables**:
- [ ] Banking domain: 30+ tests with 80%+ coverage
- [ ] Governance domain: 25+ tests with 80%+ coverage
- [ ] All domains with >0 test coverage

#### 2.2 E2E/Behavioral Test Expansion

**Current State**: 1 Behat feature (account_creation.feature)

**Target State**: 10+ critical user journeys

**New Feature Files**:
```
features/
├── account/
│   ├── account_creation.feature (existing)
│   ├── account_deposit.feature
│   └── account_withdrawal.feature
├── transfer/
│   ├── domestic_transfer.feature
│   └── international_transfer.feature
├── exchange/
│   ├── market_order.feature
│   └── limit_order.feature
├── agent_protocol/
│   ├── agent_registration.feature
│   ├── agent_payment.feature
│   └── escrow_transaction.feature
└── governance/
    └── proposal_voting.feature
```

**Deliverables**:
- [ ] 10+ Behat feature files
- [ ] Critical path coverage for all domains
- [ ] CI integration for behavioral tests

#### 2.3 Coverage Reporting Enablement

**Current State**: Coverage disabled due to memory issues

**Resolution**:
1. Switch from Xdebug to PCOV for coverage
2. Add strategic exclusions for generated code
3. Set incremental coverage targets

**phpunit.xml Changes**:
```xml
<source>
    <include>
        <directory>app</directory>
    </include>
    <exclude>
        <directory>app/Console/Commands</directory>
        <file>app/Http/Kernel.php</file>
        <directory>app/Providers</directory>
        <directory suffix="Seeder.php">database/seeders</directory>
    </exclude>
</source>
```

**Deliverables**:
- [ ] PCOV-based coverage in CI
- [ ] Coverage badge in README
- [ ] Target: 60% coverage (up from ~50%)

---

### Theme 3: Feature Completion (15% of effort)

#### 3.1 Phase 6 Integration Completion

**Agent Protocol Integration Tasks**:

| Task | Status | v1.1.0 Target |
|------|--------|---------------|
| Connect agent wallets to main payment system | Pending | Complete |
| Integrate with existing KYC/AML workflows | Pending | Complete |
| Link to AI Agent framework | Pending | Complete |
| Connect to multi-agent coordination service | Pending | Defer to v1.2 |
| Protocol compliance testing | Pending | Complete |

**Implementation**:
1. **Agent Wallet ↔ Payment System Bridge**
   - Create `AgentPaymentBridgeService`
   - Map agent wallets to internal account IDs
   - Implement bidirectional sync

2. **Agent KYC ↔ Compliance Domain Bridge**
   - Create shared compliance interface
   - Map agent KYC levels to existing tiers
   - Implement KYC status sync

3. **Agent ↔ AI Framework Bridge**
   - Create `AgentMCPBridgeService`
   - Add agent context to MCP tools
   - Enable tool execution on behalf of agents

**Deliverables**:
- [ ] AgentPaymentBridgeService with tests
- [ ] AgentComplianceBridgeService with tests
- [ ] AgentMCPBridgeService with tests
- [ ] Integration tests for all bridges

#### 3.2 Yield Optimization Completion

**Current State**: TODO in YieldOptimizationController

**Implementation**:
```php
// app/Domain/Treasury/Services/YieldOptimizationService.php
class YieldOptimizationService
{
    public function optimizePortfolio(Portfolio $portfolio): OptimizationResult;
    public function calculateExpectedYield(Portfolio $portfolio): YieldProjection;
    public function suggestRebalancing(Portfolio $portfolio): RebalancingPlan;
    public function applyStrategy(Portfolio $portfolio, Strategy $strategy): void;
}
```

**Deliverables**:
- [ ] Complete YieldOptimizationService
- [ ] 15+ unit tests
- [ ] API endpoints functional

---

### Theme 4: CI/CD Hardening (5% of effort)

#### 4.1 Security Audit Enforcement

**Current Issue**: Security audits silently fail

**Resolution**:
```yaml
# .github/workflows/02-security-scanning.yml
- name: Security Audit
  run: |
    # Fail on critical and high vulnerabilities only
    composer audit --format=json > audit.json
    if jq -e '.advisories | map(select(.severity == "critical" or .severity == "high")) | length > 0' audit.json; then
      echo "Critical or high severity vulnerabilities found!"
      cat audit.json
      exit 1
    fi
```

**Deliverables**:
- [ ] CI fails on critical/high security vulnerabilities
- [ ] Security audit results visible in PR checks
- [ ] Weekly security scan scheduled action

#### 4.2 Query Performance Monitoring

**Add N+1 Detection**:
```php
// tests/TestCase.php
protected function setUp(): void
{
    parent::setUp();

    if (config('app.env') === 'testing') {
        DB::listen(function ($query) {
            // Log queries for N+1 detection
        });
    }
}
```

**Deliverables**:
- [ ] Query count assertions in feature tests
- [ ] N+1 detection in test output
- [ ] Performance regression alerts

#### 4.3 Cleanup Tasks

**Remove Clutter**:
- [ ] Delete bin/*.bak files
- [ ] Remove unused configuration files
- [ ] Consolidate duplicate CI workflows

---

## Refactoring Priorities

### High Priority Refactors

1. **Event Sourcing Aggregate Return Types**
   - Impact: 40+ PHPStan errors
   - Effort: 2 hours
   - Risk: Low

2. **Banking Domain Service Extraction**
   - Impact: Enables testing
   - Effort: 8 hours
   - Risk: Medium

3. **Governance Domain Decoupling**
   - Impact: Enables independent testing
   - Effort: 4 hours
   - Risk: Low

### Medium Priority Refactors

4. **Static Helper Consolidation**
   - Impact: Better testability
   - Effort: 16 hours
   - Risk: Low

5. **Demo Service Standardization**
   - Impact: Consistent patterns
   - Effort: 8 hours
   - Risk: Low

### Low Priority (Consider for v1.2)

6. **Workflow Package Type Wrappers**
   - Impact: Type safety
   - Effort: 20 hours
   - Risk: Medium

7. **Value Object Migration to Spatie Data**
   - Impact: Consistency
   - Effort: 40 hours
   - Risk: Medium

---

## Release Checklist

### Pre-Release

- [ ] All PHPStan errors in baselines reviewed
- [ ] All tests passing in CI
- [ ] Coverage report generated and reviewed
- [ ] Security audit passing (no critical/high)
- [ ] CHANGELOG.md updated
- [ ] Documentation reviewed
- [ ] Version bumped in composer.json (if applicable)

### Release Process

1. **Feature Branch**: `release/v1.1.0`
2. **PR Reviews**: Minimum 2 reviewers
3. **CI Validation**: All checks green
4. **Merge Strategy**: Squash and merge
5. **Tag**: `v1.1.0`
6. **Release Notes**: Auto-generated + manual additions

### Post-Release

- [ ] Monitor for regression issues
- [ ] Update demo site
- [ ] Announce on GitHub releases
- [ ] Update README badges

---

## Success Metrics

| Metric | Current | Target |
|--------|---------|--------|
| PHPStan baseline lines | 54,632 | <25,000 |
| Test files | 458 | 520+ |
| Tested domains | 25/30 | 30/30 |
| E2E scenarios | 1 | 10+ |
| Code coverage | ~50% | 60%+ |
| TODO/FIXME items | 12 | 0 |
| Static methods | 394 | <250 |
| Security audit | Ignored | Enforced |

---

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Baseline reduction breaks code | Low | High | Incremental changes with CI validation |
| New tests reveal bugs | Medium | Medium | Document and fix or defer |
| Feature completion scope creep | Medium | Low | Strict scope boundaries |
| CI changes cause failures | Low | Medium | Test in separate branch first |

---

## Timeline Recommendation

**Sprint 1 (Week 1-2)**: Code Quality
- PHPStan baseline reduction
- TODO/FIXME resolution
- CI hardening

**Sprint 2 (Week 3-4)**: Test Coverage
- Banking domain tests
- Governance domain tests
- E2E test expansion

**Sprint 3 (Week 5-6)**: Feature Completion
- Phase 6 integration
- Yield optimization
- Documentation updates

**Sprint 4 (Week 7)**: Release Preparation
- Final testing
- Release candidate
- Documentation finalization

---

## Appendix: Files Requiring Changes

### PHPStan Return Type Fixes
```
app/Domain/Account/Aggregates/AssetTransactionAggregate.php
app/Domain/Account/Aggregates/TransactionAggregate.php
app/Domain/Account/Aggregates/TransferAggregate.php
app/Domain/Exchange/Aggregates/OrderAggregate.php
app/Domain/Lending/Aggregates/LoanAggregate.php
app/Domain/Treasury/Aggregates/PortfolioAggregate.php
app/Domain/Stablecoin/Aggregates/StablecoinAggregate.php
app/Domain/AgentProtocol/Aggregates/*.php (multiple)
```

### TODO Resolution Files
```
app/Providers/DomainServiceProvider.php
app/Http/Controllers/PayseraDepositController.php
app/Http/Controllers/Api/YieldOptimizationController.php
app/Http/Controllers/Api/BatchProcessingController.php
app/Domain/AgentProtocol/Workflows/Activities/NotifyReputationChangeActivity.php
app/Domain/Basket/Services/BasketService.php
app/Domain/Exchange/Services/LiquidityRetryPolicy.php
app/Domain/Stablecoin/Repositories/StablecoinAggregateRepository.php
app/Jobs/ProcessCustodianWebhook.php
```

### New Test Files Required
```
tests/Domain/Banking/... (30+ files)
tests/Domain/Governance/... (25+ files)
tests/Domain/Regulatory/... (15+ files)
tests/Domain/Product/... (10+ files)
tests/Domain/User/... (14+ files)
features/*.feature (9 new files)
```

---

*Document Version: 1.0*
*Created: January 10, 2025*
*Author: Claude Code AI Assistant*
