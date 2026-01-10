# Version Roadmap Strategic Decisions

> **Purpose**: Record key decisions made during roadmap planning. Reference when making future architectural choices.

## Version Philosophy

### Semantic Versioning Strategy
- **MAJOR (x.0.0)**: Breaking changes, significant architecture shifts
- **MINOR (x.y.0)**: New features, non-breaking enhancements  
- **PATCH (x.y.z)**: Bug fixes, security updates, documentation

### Release Cadence
- Minor releases: Every 8-12 weeks
- Patch releases: As needed (security within 24-48 hours)
- Major releases: Every 6-12 months
- LTS: Major versions get 2 years security support

---

## v1.2.0 Decisions (Feature Completion)

### Why Agent Bridges First?
1. Agent Protocol is core differentiator
2. Integration gaps block real-world usage
3. Three bridges complete the feature set:
   - Payment: Agents can transact
   - Compliance: Agents are regulated
   - MCP: Agents use AI tools

### Why Yield Optimization?
- Existing TODO in controller
- Treasury domain is 85% complete
- Completes a major feature set

### Why Observability Now?
- Production readiness requirement
- No dashboards currently exist
- Enables debugging of future issues

---

## v1.3.0 Decisions (Platform Modularity)

### Domain Decoupling Rationale
- Current: Tight coupling between domains
- Target: Interface-based contracts
- Benefit: Pick-and-choose installation

### Module System Design
- Each domain gets `module.json` manifest
- Dependencies declared explicitly
- Installation via artisan commands

### GCU Separation Strategy
- Move to `examples/gcu-basket/`
- Serves as reference implementation
- Shows how to build custom baskets
- Reduces core complexity

---

## v2.0.0 Decisions (Major Evolution)

### Multi-Tenancy Justification
- Enterprise customers need isolation
- Database-level tenant scoping
- Per-tenant configuration

### Hardware Wallet Priority
- Ledger + Trezor first (market leaders)
- Security requirement for institutional
- Multi-signature enables corporate use

### Kubernetes-Native Approach
- Helm charts for deployment
- HPA for auto-scaling
- Service mesh ready (Istio)

---

## Deferred Items (with reasoning)

### Deferred to v1.2.0+
- Multi-agent coordination service (complex, needs bridges first)
- Hardware wallet integration (v2.0.0 feature)
- Multi-signature support (v2.0.0 feature)

### Deferred to v1.3.0+
- ELK Stack log aggregation (after dashboards)
- Advanced Grafana per-domain dashboards
- Paysera full integration (if needed)

### Deferred to v2.0.0+
- Cross-chain bridges (EVM, Solana, Cosmos)
- Smart contract deployment
- Real-time WebSocket streaming

### Deferred to v2.1.0+ (Future Vision)
- AI-powered banking (NLP queries)
- Automated regulatory reporting (MiFID II, MiCA)
- Embedded finance APIs
- DeFi bridge integration

---

## Architecture Principles (for decision-making)

1. **Interface First**: Extract contracts before implementations
2. **Event Sourcing Everywhere**: All state changes through events
3. **Demo Mode Parity**: Every feature works in demo
4. **Test Coverage**: Minimum 50%, 80%+ for financial logic
5. **PHPStan Level 8**: No regressions allowed
6. **Backward Compatibility**: Until major versions

---

## Key Files for Roadmap
- `docs/VERSION_ROADMAP.md` - Full roadmap document
- `docs/ARCHITECTURAL_ROADMAP.md` - Architecture vision
- `CHANGELOG.md` - Version history
- This memory - Decision rationale
