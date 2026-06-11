# Wallet Send Sponsorship Operator Runbook

Operator-only runbook for gas/fee sponsorship on non-custodial wallet sends (v7.12.0+). Sponsored sends cost the platform real money per transaction; this doc covers how sponsorship works on each chain, the abuse caps, monitoring, and what to do when something trips.

## When to use this runbook

- The `solana:check-sponsor-balance` hourly check fires CRITICAL (sponsor SOL running low).
- Mobile reports users getting HTTP 429 on send (`SEND_DAILY_LIMIT_REACHED` / `SEND_TEMPORARILY_UNAVAILABLE`).
- The global daily sponsorship ceiling trips (`wallet.send global daily sponsorship ceiling reached` at CRITICAL).
- A user reports `NETWORK_DISABLED` on a network you expected to be live.
- Pre-prod checklist before enabling Solana fee-payer sponsorship on a new environment.

## How sponsorship works

### EVM — Pimlico paymaster

EVM sends are ERC-4337 v0.6 UserOps from passkey-controlled smart accounts. At prepare time (`POST /api/v1/wallet/transactions/prepare`), `EvmUserOpPreparer` asks the **Pimlico** paymaster to sponsor the op — the sponsorship response supplies `paymasterAndData` plus the gas limits baked into the UserOp hash the device signs. The platform's Pimlico account is billed for the gas.

- Credentials: `PIMLICO_API_KEY` (+ `PIMLICO_BUNDLER_URL`) in `config/relayer.php` (`pimlico` block).
- Enabled networks: `WALLET_SEND_EVM_NETWORKS`, default `polygon,base,arbitrum`. **Ethereum L1 is deliberately excluded** — a single L1 send can cost $1–$20+ in sponsored gas vs fractions of a cent on the L2s. Re-add `ethereum` only with a deliberate cost decision.
- A send on a network not in the list returns **422 `NETWORK_DISABLED`** (also surfaced when a Wallet `NetworkDisabledException` bubbles up). This is the expected response, not a bug.

### Solana — fee-payer co-sign

A non-custodial Solana wallet holds SPL tokens (USDC/USDT) but typically **zero SOL**, so it cannot pay its own transaction fee ("Attempt to debit an account but found no record of a prior credit"). When `WALLET_SOLANA_SPONSOR_SECRET_KEY` is set:

- The sponsor account becomes the transaction **fee payer (account index 0)**; the user's device still signs as the transfer authority. Both sign the SAME message bytes — the mobile signing contract is unchanged.
- `SolanaSendSubmitter` co-signs server-side via `SolanaSponsorSigner`; signatures are ordered `[sponsor, sender]` on the two-signer message.
- The key is the **base58-encoded 64-byte ed25519 secret key** (32-byte seed ‖ 32-byte public key, standard Solana layout). Generate it OFF-machine and store it only in the secret manager. The public address is derived from the trailing 32 bytes — never stored separately.
- **Unset = legacy single-signer**: the sender pays its own fee, which fails for a 0-SOL wallet. `ops:verify-env` reports SKIP when unset and FAILs when the key is set but undecodable.

Config: `config/wallet.php` → `wallet.solana.sponsor`.

## Count caps (anti-abuse guardrail)

`config/wallet.php` `sponsorship` block, enforced in `MobileWalletController::enforceSendRateLimit` **at prepare time** (abandoned prepares count — deliberately conservative):

| Cap | Env | Default | On breach |
|---|---|---|---|
| Per-user sends / UTC day | `WALLET_SEND_PER_USER_DAILY_LIMIT` | 30 | **429 `SEND_DAILY_LIMIT_REACHED`** — "resets at 00:00 UTC"; logged WARNING |
| Global sends / UTC day | `WALLET_SEND_GLOBAL_DAILY_LIMIT` | 5000 | **429 `SEND_TEMPORARILY_UNAVAILABLE`** — kill-switch against a mass-abuse spike; logged **CRITICAL** (reaches Slack via the log stack) |

Mechanics worth knowing when debugging:

- Counters are cache keys `wallet_send:count:user:{id}:{Y-m-d}` and `wallet_send:count:global:{Y-m-d}`, date-keyed in **UTC** — they reset at **00:00 UTC** simply because the date in the key rolls over (TTL 1 day).
- Increment uses `Cache::add(key, 0, ttl)` + `Cache::increment()` (concurrency-safe; never read-then-write).
- They live in the default cache (Redis). **`php artisan cache:clear` / a Redis flush resets the day's counters** — be aware of this both ways (it forgives a tripped cap, and it forgets real usage).
- The global counter increments before the per-user counter is checked; both checks run on every prepare.

## Sponsor balance monitoring + top-up

The hourly schedule (`routes/console.php`):

```bash
php artisan solana:check-sponsor-balance
# output appended to storage/logs/solana-sponsor-balance.log
```

- Below `WALLET_SOLANA_SPONSOR_LOW_BALANCE_LAMPORTS` (default `100000000` = **0.1 SOL**): logs CRITICAL (`Solana sponsor account balance is LOW — top it up to keep sends flowing`) and exits non-zero; the schedule's `onFailure` adds a second CRITICAL. RPC unreachable also exits non-zero (ERROR log).
- Sponsor key unconfigured: the command is a clean no-op (`nothing to check`).

**Top-up procedure**:

1. Get the sponsor address — it's in every check's log line (`address` field), or:
   ```php
   php artisan tinker
   >>> app(\App\Domain\Wallet\Services\Send\SolanaSponsorSigner::class)->publicKeyBase58();
   ```
2. Send SOL to that address from the treasury wallet (plain native-SOL transfer; no program interaction needed). Size the top-up for runway: a sponsored SPL send costs ~5,000 lamports base fee + priority fee (`SOLANA_PRIORITY_FEE_MICROLAMPORTS`, default 1000 µlam/CU × up to `SOLANA_COMPUTE_UNIT_LIMIT` 200k CU) — 1 SOL covers tens of thousands of sends at defaults.
3. Confirm recovery: `php artisan solana:check-sponsor-balance` → `Solana sponsor balance OK: <n> SOL (<address>)` and exit 0.
4. If the threshold keeps alerting at sane balances, tune `WALLET_SOLANA_SPONSOR_LOW_BALANCE_LAMPORTS` rather than ignoring the alert.

There is no Pimlico-side balance check in this codebase — monitor the Pimlico dashboard/billing alerts separately.

## When the global cap trips

`SEND_TEMPORARILY_UNAVAILABLE` for **everyone** until 00:00 UTC. Triage:

1. **Decide: abuse or growth?** Inspect the distribution — if a handful of users own most of today's count, it's abuse; if it's broad, it's organic:
   ```php
   php artisan tinker
   >>> use Illuminate\Support\Facades\Cache;
   >>> $day = now()->utc()->format('Y-m-d');
   >>> Cache::get("wallet_send:count:global:{$day}");          // total
   >>> Cache::get("wallet_send:count:user:{$userId}:{$day}");  // per suspect user
   ```
   Cross-check `wallet_send_records` created today grouped by user for the full picture (cache only stores counts).
2. **Abuse** — leave the cap tripped (that's its job), and disable the offending accounts. The per-user cap (30) should have throttled a single account first; a tripped global cap with low per-user counts suggests a botnet of accounts → escalate to security.
3. **Organic growth** — raise `WALLET_SEND_GLOBAL_DAILY_LIMIT` in `.env`, then `php artisan config:cache` (if cached) + restart workers/PHP-FPM. The already-incremented counter keeps counting; the new limit applies on the next prepare. No counter reset needed.
4. **Emergency pause of all sends**: set `WALLET_SEND_GLOBAL_DAILY_LIMIT=0` — every prepare 429s with `SEND_TEMPORARILY_UNAVAILABLE`. Mobile shows "temporarily paused", which is the honest message.
5. Post-incident: the CRITICAL log line is the alert hook — confirm it reached the Slack channel (`LOG_SLACK_WEBHOOK_URL`); if not, fix alerting before closing.

## Disabled-network behavior

`WALLET_SEND_EVM_NETWORKS` is the allowlist (default `polygon,base,arbitrum`). Prepare/submit on anything else → **422 `NETWORK_DISABLED`**. Notes:

- The `network` value is case-sensitive against `PaymentNetwork::values()` — `SOLANA`/`TRON` uppercase, EVM networks lowercase (pre-existing enum inconsistency; mobile must send the exact value).
- Disabling a network mid-flight: already-prepared intents for that network will fail at submit with the same code. Coordinate with mobile before toggling.
- Solana is not part of this list — it is enabled by the Solana wallet surface itself; the sponsorship key only changes who pays the fee.

## Files referenced

- `config/wallet.php` — `sponsorship`, `solana.sponsor`, `evm.enabled_networks`
- `config/relayer.php` — Pimlico credentials
- `app/Http/Controllers/Api/Wallet/MobileWalletController.php` — `enforceSendRateLimit` (429s), prepare/submit
- `app/Domain/Wallet/Services/Send/SolanaSponsorSigner.php` — sponsor keypair + co-signature
- `app/Domain/Wallet/Services/Send/SolanaSendSubmitter.php` — two-signer assembly + broadcast
- `app/Domain/Wallet/Services/Send/EvmUserOpPreparer.php` — Pimlico sponsorship call
- `app/Console/Commands/SolanaSponsorBalanceCheckCommand.php` — `solana:check-sponsor-balance`
- `routes/console.php` — hourly balance check schedule
- `docs/operations/bridge-ramp.md` — the fiat side of the same wallet surface
