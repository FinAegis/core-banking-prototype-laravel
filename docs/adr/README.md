# Architecture Decision Records (ADRs)

This directory contains Architecture Decision Records for the FinAegis Core Banking Platform.

## What is an ADR?

An Architecture Decision Record (ADR) is a document that captures an important architectural decision made along with its context and consequences.

## ADR Index

> **Numbering note (2026-06):** This directory (`docs/adr/`) is the canonical ADR home.
> The five foundational ADRs originally filed in `docs/ADR/` as `ADR-001…ADR-005` were
> merged here and renumbered to `0007…0011` to avoid colliding with the Plan-B-era ADRs
> `0001…0006` that already occupied those numbers. Filenames use the `NNNN-short-title.md`
> convention.

| ADR | Title | Status | Date |
|-----|-------|--------|------|
| [0001](0001-fee-collector-wallets.md) | Fee Collector Wallets | Accepted | 2026-05 |
| [0002](0002-revenue-projection-dual-upstream.md) | Revenue Projection — Dual Upstream | Accepted | 2026-05 |
| [0003](0003-pricing-bounded-context.md) | Pricing Bounded Context | Accepted | 2026-05 |
| [0004](0004-money-on-the-wire.md) | Money on the Wire | Accepted | 2026-05 |
| [0005](0005-bridge-xyz-over-stripe-crypto-onramp.md) | Bridge.xyz over Stripe Crypto Onramp | Accepted | 2026-05 |
| [0006](0006-bridge-developer-fees-as-markup-mechanism.md) | Bridge Developer Fees as Markup Mechanism | Accepted | 2026-05 |
| [0007](0007-event-sourcing.md) | Event Sourcing for Financial Transactions | Accepted | 2024-01 |
| [0008](0008-cqrs-pattern.md) | CQRS Pattern Implementation | Accepted | 2024-01 |
| [0009](0009-saga-pattern.md) | Saga Pattern for Distributed Transactions | Accepted | 2024-02 |
| [0010](0010-gcu-basket-design.md) | GCU Basket Currency Design | Accepted | 2024-03 |
| [0011](0011-demo-mode-architecture.md) | Demo Mode Architecture | Accepted | 2024-06 |

## ADR Template

When creating a new ADR, use this template:

```markdown
# ADR-XXX: Title

## Status
[Proposed | Accepted | Deprecated | Superseded]

## Context
What is the issue that we're seeing that is motivating this decision?

## Decision
What is the change that we're proposing and/or doing?

## Consequences
What becomes easier or more difficult to do because of this change?

## Alternatives Considered
What other options were evaluated?
```

## Contributing

When making significant architectural decisions:

1. Create a new ADR file: `NNNN-short-title.md` (next free number after the index above)
2. Fill in the template with context and rationale
3. Submit with your PR for review
4. Update this index after acceptance
