# Architecture Decision Records (ADRs)

This directory contains Architecture Decision Records for the FinAegis Core Banking Platform.

## What is an ADR?

An Architecture Decision Record (ADR) is a document that captures an important architectural decision made along with its context and consequences.

## ADR Index

| ADR | Title | Status | Date |
|-----|-------|--------|------|
| [ADR-001](ADR-001-event-sourcing.md) | Event Sourcing for Financial Transactions | Accepted | 2024-01 |
| [ADR-002](ADR-002-cqrs-pattern.md) | CQRS Pattern Implementation | Accepted | 2024-01 |
| [ADR-003](ADR-003-saga-pattern.md) | Saga Pattern for Distributed Transactions | Accepted | 2024-02 |
| [ADR-004](ADR-004-gcu-basket-design.md) | GCU Basket Currency Design | Accepted | 2024-03 |
| [ADR-005](ADR-005-demo-mode-architecture.md) | Demo Mode Architecture | Accepted | 2024-06 |

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

1. Create a new ADR file: `ADR-XXX-short-title.md`
2. Fill in the template with context and rationale
3. Submit with your PR for review
4. Update this index after acceptance
