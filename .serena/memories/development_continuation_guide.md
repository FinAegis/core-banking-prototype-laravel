# FinAegis Development Continuation Guide

> Last Updated: June 2, 2026

## Quick Recovery
```bash
git status && git log --oneline -5
gh pr list --state open
./vendor/bin/pest --parallel --stop-on-failure
```

## Current State
- Latest release tag: v7.14.1 (Bridge.xyz ramp documented as v7.15.0 in CLAUDE.md)
- Recent milestones: public MCP server (v7.11.0), non-custodial wallet send via Privy (v7.12.0), Apple/Google IAP subscriptions (v7.13.0), Bridge.xyz fiat on-ramp (v7.15.0)
- Bridge webhook verifier now matches Bridge's **asymmetric** `X-Webhook-Signature` (v0 / RSA-SHA256 against `BRIDGE_WEBHOOK_PUBLIC_KEY`); legacy HMAC kept as fallback. Set the public key in prod before activating the webhook.
- **HyperSwitch** (`app/Domain/Payment/Services/HyperSwitch/`) is EXPERIMENTAL scaffolding — real client + HMAC-verified webhook, but NOT wired into the deposit flow (webhook only logs, no binding, `HYPERSWITCH_ENABLED=false`, no tests). A dedicated multi-connection-tested wire-up PR is planned; discussion #346 stays open until then.

## Architecture
- 61 domains in `app/Domain/`, 45 GraphQL schema imports (`graphql/schema.graphql`)
- Stack: PHP 8.4 / Laravel 12 / MySQL 8 / Redis / Pest / PHPStan Level 8
- Patterns: Event Sourcing (Spatie), CQRS, DDD, GraphQL (Lighthouse), Redis Streams, multi-tenancy (`UsesTenantConnection`)

## Key Conventions
- `Sanctum::actingAs($user, ['read', 'write', 'delete'])` — always pass abilities
- Code quality before commit: `php-cs-fixer fix` → PHPStan Level 8 (analyses `tests/` too — guard `openssl_*`/`array|false` returns, no `@var`/assert/cast to silence) → Pest. CI also runs **PHPCS v4** (`vendor/bin/phpcs`) — run it locally to match.
- Conventional commits: feat/fix/test/refactor + `Co-Authored-By`
- Multi-connection: never wrap a write to a `UsesTenantConnection` model (e.g. `Account`, `AccountBalance`, tenant-aware stored events) inside a `DB::transaction()` that also holds default-connection rows — it self-deadlocks. Add `tests/MultiConnection/` coverage (required PR check).

## Other Serena Memories
- `project_architecture_overview`, `project_overview` — architecture references
- `coding_standards_and_conventions`, `code_style_and_conventions` — code style
- `code_quality_workflow`, `task_completion_checklist`, `suggested_commands` — workflow
- `cqrs_and_patterns_documentation`, `event_sourcing_patterns` — DDD/ES patterns
- `infrastructure-patterns`, `distributed-tracing-implementation` — infra/observability
- `hardware_wallet_integration`, `account_provisioning_domain` — domain deep-dives
