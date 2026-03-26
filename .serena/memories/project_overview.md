# Project Overview

**FinAegis / Zelta** — Core banking prototype with 49 DDD domain modules.

## Tech Stack
- PHP 8.4 / Laravel 12 / MySQL 8 / Redis
- PHPStan Level 8, Pest, php-cs-fixer
- Spatie Event Sourcing v7.7+, Lighthouse GraphQL
- Tailwind CSS, Vite, Livewire, Filament admin

## Key Domains
- **Payment Protocols**: X402 (Coinbase), MachinePay/MPP (Stripe/Tempo), AgentProtocol/AP2 (Google)
- **Financial**: Account, Payment, Exchange, Banking, Treasury, Lending, Stablecoin
- **Security**: KeyManagement (PQ crypto), Privacy (Merkle trees), TrustCert, Fraud
- **Infrastructure**: AI (MCP tools), Monitoring, Webhook, Performance, Relayer
- **Consumer**: Mobile, MobilePayment, Commerce, Rewards, Referral, SMS

## Packages
- `packages/zelta-sdk/` — Composer payment SDK (x402 + MPP auto-handling)
- `packages/zelta-cli/` — 25-command CLI (Symfony Console 7.x)

## Multi-Tenancy
Team-based isolation via stancl/tenancy + UsesTenantConnection trait.
