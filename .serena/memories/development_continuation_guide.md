# FinAegis Development Continuation Guide

> Last Updated: March 9, 2026

## Quick Recovery
```bash
git status && git log --oneline -5
gh pr list --state open
./vendor/bin/pest --parallel --stop-on-failure
```

## Current State
- Branch: `fix/ci-pipeline-green` (PR #735 — fixing CI for first green build)
- Latest release tag: v5.11.0 (v5.12.0 was documented but never tagged)
- 29 PRs merged since v5.11.0 (#706-#734): notifications, banners, gas sponsorship, referrals, ramp, Foodo, Onramper, design system v2
- CI Pipeline historically never passed — PR #735 fixes PHPCS, PHPStan, 4 test failures

## Architecture
- 43 domains in `app/Domain/`, 35 GraphQL schemas
- Stack: PHP 8.4 / Laravel 12 / MySQL 8 / Redis / Pest / PHPStan Level 8
- Patterns: Event Sourcing (Spatie), CQRS, DDD, GraphQL (Lighthouse), Redis Streams

## Key Conventions
- `Sanctum::actingAs($user, ['read', 'write', 'delete'])` — always pass abilities
- Code quality: php-cs-fixer → PHPStan → Pest (in that order)
- Conventional commits: feat/fix/test/refactor + Co-Authored-By

## Remaining Serena Memories (9)
- `coding_standards_and_conventions` — code style details
- `project_architecture_overview` — deep architecture reference
- `code_quality_workflow` — CI/CD patterns
- `cqrs_and_patterns_documentation` — CQRS/ES patterns
- `event_sourcing_patterns` — event store details
- `infrastructure-patterns` — infrastructure reference
- `distributed-tracing-implementation` — observability
- `hardware_wallet_integration` — Ledger/Trezor details
