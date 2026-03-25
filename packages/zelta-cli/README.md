# Zelta CLI

Manage payments, SMS, wallets, and API monetization from the terminal. Built for humans and AI agents.

## Install

```bash
# curl (recommended)
curl -fsSL https://cli.zelta.app/install.sh | bash

# npm
npm install -g @zelta/cli

# Homebrew
brew install zelta-app/tap/zelta

# Composer
composer global require zelta/cli
```

## Quick Start

```bash
zelta auth:login --key zk_live_xxx
zelta pay:list --status settled
zelta sms:send --to +37060012345 --message "Your code: 847291"
zelta endpoints:list
```

## Commands

| Group | Commands |
|-------|----------|
| `auth` | login, logout, status, token |
| `pay` | send, status, list, stats |
| `sms` | send, status, rates |
| `wallet` | balance, send, tokens |
| `limits` | list, set, remove |
| `endpoints` | list |
| `agents` | register, discover |
| `sdk` | generate |

## AI Agent Support

```bash
# JSON output for pipes
zelta pay:list --json | jq '.[] | select(.status == "settled")'

# Structured exit codes: 0=success, 1=error, 2=auth, 3=payment, 4=validation
zelta pay:stats --json --period day | jq -e '.failed == 0'
```

## Documentation

- [Zelta CLI Feature Page](https://zelta.app/features/zelta-cli)
- [Developer Docs](https://zelta.app/developers)
- [x402 Protocol](https://zelta.app/features/x402-protocol)

## License

Apache-2.0
