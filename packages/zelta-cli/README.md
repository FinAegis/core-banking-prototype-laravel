# Zelta CLI

Manage payments, SMS, wallets, and API monetization from the terminal. Built for humans and AI agents.

> **Mirror repo notice** — if you're viewing this at `github.com/FinAegis/cli`, that repo is a read-only split of `packages/zelta-cli/` from the [FinAegis core banking monorepo](https://github.com/FinAegis/core-banking-prototype-laravel). Please file issues and PRs against the monorepo.

## Install

```bash
# npm (recommended, requires PHP 8.4+ on PATH)
npm install -g @finaegis/cli

# Composer
composer global require finaegis/cli
```

## Authentication

The CLI authenticates with a **Sanctum personal access token**:

1. Sign in to the Zelta dashboard and open **Profile → API Tokens** (`/user/api-tokens`).
2. Create a token with the abilities you need (`read`, `write`, `delete`).
3. Log in — the token is verified against the API before being stored in `~/.zelta/credentials.json`:

```bash
zelta auth:login --key <api-token>
```

A rejected token fails at login time (exit code 2), not on first use. `zelta auth:status` and `zelta whoami` re-verify the stored token server-side.

## Quick Start

```bash
zelta auth:login --key <api-token>
zelta pay:list --status settled
zelta sms:send --to +37060012345 --message "Your code: 847291"
zelta wallet:transactions --limit 10
zelta endpoints:list
```

## Commands

| Group | Commands |
|-------|----------|
| `auth` | login, logout, status, token |
| `pay` | send, status, list, stats |
| `sms` | send, status, rates |
| `wallet` | balance, transactions, intent, tokens |
| `limits` | list, set, remove |
| `endpoints` | list |
| `agents` | register, discover |
| `sdk` | generate |

> **Note on wallet sends** — Zelta wallets are non-custodial: every transaction is signed on-device (Privy passkey / device key), so the CLI cannot send funds. `wallet:transactions` and `wallet:intent` give read-only visibility into wallet activity and send intents.

## AI Agent Support

```bash
# JSON output for pipes
zelta pay:list --json | jq '.[] | select(.status == "settled")'

# Structured exit codes: 0=success, 1=error, 2=auth, 3=payment, 4=validation
zelta pay:stats --json --period day | jq -e '.failed == 0'
```

## Documentation

- [Zelta CLI Feature Page](https://finaegis.org/features/zelta-cli)
- [Developer Docs](https://finaegis.org/developers)
- [x402 Protocol](https://finaegis.org/features/x402-protocol)

## License

Apache-2.0
