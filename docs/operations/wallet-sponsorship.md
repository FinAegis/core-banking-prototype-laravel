# Wallet Send Sponsorship Operator Runbook

This runbook is for operators. It covers gas and fee sponsorship for non-custodial wallet sends, which shipped in v7.12.0. Every sponsored send costs the platform real money. The runbook explains how sponsorship works on each chain, which caps limit abuse, how to monitor the spend and what to do when a cap or an alert trips. It also explains sends whose network result is unclear, which v7.18.0 changed.

## When to use this runbook

- The hourly `solana:check-sponsor-balance` check logs CRITICAL because the sponsor SOL balance is low.
- The mobile team reports that users get HTTP 429 on send (`SEND_DAILY_LIMIT_REACHED` or `SEND_TEMPORARILY_UNAVAILABLE`).
- The global daily sponsorship ceiling trips and logs `wallet.send global daily sponsorship ceiling reached` at CRITICAL.
- A user reports `NETWORK_DISABLED` on a network you expected to be live.
- A send stays `submitted` for a long time, or a user says a send failed although the money left the wallet.
- You are preparing a new environment and want to switch on Solana fee-payer sponsorship.

## How sponsorship works

### EVM networks through the Pimlico paymaster

EVM sends are ERC-4337 v0.6 user operations from passkey-controlled smart accounts. At prepare time (`POST /api/v1/wallet/transactions/prepare`), `EvmUserOpPreparer` asks the **Pimlico** paymaster to sponsor the operation. The sponsorship response supplies `paymasterAndData` and the gas limits, and both become part of the user operation hash that the device signs. Pimlico bills the platform's account for the gas.

- The credential is `PIMLICO_API_KEY`, read in the `pimlico` block of `config/relayer.php`. Leave `PIMLICO_BUNDLER_URL` empty. The bundler URL is then derived for each chain as `https://api.pimlico.io/v2/{chainId}/rpc`. A pinned URL sends every network to one chain, and `ops:verify-env` warns when it is set together with more than one EVM network.
- `WALLET_SEND_EVM_NETWORKS` lists the enabled networks. The default is `polygon,base,arbitrum`. **Ethereum L1 is left out on purpose**, because a single L1 send can cost $1 to $20 or more in sponsored gas, while an L2 send costs a fraction of a cent. Add `ethereum` back only after a deliberate cost decision.
- A send on a network that is not in the list returns **422 `NETWORK_DISABLED`**. The same code appears when a wallet `NetworkDisabledException` reaches the API. This is the expected response, not a bug.

### Solana through a co-signing fee payer

A non-custodial Solana wallet holds SPL tokens such as USDC and USDT but usually **no SOL**. Without SOL it cannot pay its own transaction fee, and the network answers "Attempt to debit an account but found no record of a prior credit". Setting `WALLET_SOLANA_SPONSOR_SECRET_KEY` solves this.

- The sponsor account becomes the transaction **fee payer at account index 0**. The user's device still signs as the transfer authority. Both sign the same message bytes, so the mobile signing contract does not change.
- `SolanaSendSubmitter` adds the sponsor's signature on the server through `SolanaSponsorSigner`. The two signatures are ordered `[sponsor, sender]`.
- The key is the **base58-encoded 64-byte ed25519 secret key**. It is the 32-byte seed followed by the 32-byte public key, which is the standard Solana layout. Generate it on a separate machine and store it only in the secret manager. The public address is derived from the last 32 bytes and is never stored separately.
- **When the key is unset, the old single-signer flow applies.** The sender pays its own fee, and that fails for a wallet with no SOL. `ops:verify-env` reports SKIP when the key is unset and FAIL when it is set but cannot be decoded.

The configuration lives in `config/wallet.php` under `wallet.solana.sponsor`.

## Caps that limit abuse

The `sponsorship` block in `config/wallet.php` sets the caps. `App\Domain\Wallet\Services\SponsorshipBudget` enforces them for every entry point that spends platform gas. Those entry points are the wallet send and generic relayer sponsorship. Generic sponsorship is the REST endpoint `POST /api/v1/relayer/sponsor` and the GraphQL mutation `sponsorTransaction`, and it is available only when `RELAYER_GENERIC_SPONSOR_ENABLED=true`. When the caps are used up, generic sponsorship answers 429 with `ERR_RELAYER_305` or `ERR_RELAYER_306`. GraphQL returns an error with the same message.

| Cap | Env | Default | On breach |
|---|---|---|---|
| Sends per user per UTC day | `WALLET_SEND_PER_USER_DAILY_LIMIT` | 30 | **429 `SEND_DAILY_LIMIT_REACHED`** with "resets at 00:00 UTC", logged as WARNING |
| Sends for the whole platform per UTC day | `WALLET_SEND_GLOBAL_DAILY_LIMIT` | 5000 | **429 `SEND_TEMPORARILY_UNAVAILABLE`**, logged as **CRITICAL**, which reaches Slack through the log stack |
| Estimated sponsored gas in US dollars per UTC day | `WALLET_SPONSORSHIP_DAILY_BUDGET_USD` | empty (off) | **429 `SEND_TEMPORARILY_UNAVAILABLE`** |

The wallet send takes its units in two steps.

1. Prepare spends no gas. It reserves one unit of the caller's own daily count and only reads the global count. The unit is given back when the prepare fails or is an idempotent replay.
2. Submit takes the shared global unit right before the platform broadcasts. The unit is given back when the broadcast is refused. If the global ceiling is reached at submit, the answer is 429 `SEND_TEMPORARILY_UNAVAILABLE` and the intent stays pending.

Abandoned prepares therefore use up only the caller's own allowance. A pool of throwaway accounts cannot exhaust the platform-wide ceiling with prepares alone. Generic sponsorship spends gas in the same request, so it takes both units at once after the request is validated.

These details help when you debug a cap:

- The counters are cache keys `wallet_send:count:user:{id}:{Y-m-d}` and `wallet_send:count:global:{Y-m-d}`. The date in the key is in **UTC**, so the counters reset at **00:00 UTC** when the date rolls over. Their TTL is one day.
- Increments use `Cache::add(key, 0, ttl)` and then `Cache::increment()`. This is safe under concurrency because it never reads and then writes.
- The counters live in the default cache, which is Redis. **`php artisan cache:clear` or a Redis flush resets the day's counters.** That forgives a tripped cap, and it also forgets real usage.
- The dollar budget grows when a send is confirmed, because only then is the real fee known. A burst of sends in flight can therefore overshoot the budget a little. The count caps bound that overshoot. `SponsorshipCostTracker` converts the fee to US dollars through the exchange rate service and skips the increment when no rate is available.

## Watching the spend

`php artisan wallet:sponsorship-spend` shows today's sponsored send count and the gas cost per network for the current UTC day. Add `--json` for machine-readable output.

There is no Pimlico balance check in this codebase. Watch the Pimlico dashboard and its billing alerts separately.

## Sponsor balance monitoring and top-up

`routes/console.php` runs this check every hour:

```bash
php artisan solana:check-sponsor-balance
# output appended to storage/logs/solana-sponsor-balance.log
```

- When the balance is below `WALLET_SOLANA_SPONSOR_LOW_BALANCE_LAMPORTS` (default `100000000`, which is **0.1 SOL**), the command logs CRITICAL (`Solana sponsor account balance is LOW — top it up to keep sends flowing`) and exits with a non-zero code. The schedule's `onFailure` hook then adds a second CRITICAL. An unreachable RPC node also makes it exit non-zero, with an ERROR log.
- When no sponsor key is configured, the command does nothing and reports `nothing to check`.

**To top up the sponsor account:**

1. Find the sponsor address. Every check writes it to the log in the `address` field. You can also print it from tinker:
   ```php
   php artisan tinker
   >>> app(\App\Domain\Wallet\Services\Send\SolanaSponsorSigner::class)->publicKeyBase58();
   ```
2. Send SOL to that address from the treasury wallet. A plain native SOL transfer is enough. Size the top-up for the runway you want. A sponsored SPL send costs about 5,000 lamports of base fee plus a priority fee. The priority fee is `SOLANA_PRIORITY_FEE_MICROLAMPORTS` (default 1000 microlamports per compute unit) times at most `SOLANA_COMPUTE_UNIT_LIMIT` (200,000 compute units). At the defaults, 1 SOL covers tens of thousands of sends.
3. Run `php artisan solana:check-sponsor-balance` again. It should print `Solana sponsor balance OK: <n> SOL (<address>)` and exit with 0.
4. If the check keeps alerting at a sensible balance, tune `WALLET_SOLANA_SPONSOR_LOW_BALANCE_LAMPORTS` instead of ignoring the alert.

## When the global cap trips

Every user gets `SEND_TEMPORARILY_UNAVAILABLE` until 00:00 UTC. Work through these steps.

1. **Decide whether it is abuse or growth.** Look at how today's count is spread across users. If a handful of users own most of it, it is abuse. If it is spread widely, it is organic growth.
   ```php
   php artisan tinker
   >>> use Illuminate\Support\Facades\Cache;
   >>> $day = now()->utc()->format('Y-m-d');
   >>> Cache::get("wallet_send:count:global:{$day}");          // total
   >>> Cache::get("wallet_send:count:user:{$userId}:{$day}");  // per suspect user
   ```
   The cache stores only counts. For the full picture, group today's rows in `wallet_send_records` by user.
2. **If it is abuse**, leave the cap tripped, because stopping abuse is its job, and disable the offending accounts. The per-user cap of 30 should throttle a single account first. A tripped global cap with low per-user counts points to many coordinated accounts, so escalate to security.
3. **If it is organic growth**, raise `WALLET_SEND_GLOBAL_DAILY_LIMIT` in `.env`. Then run `php artisan config:cache` if the configuration is cached, and restart the workers and PHP-FPM. The counter keeps its value, and the new limit applies from the next request. You do not need to reset the counter.
4. **To pause all sends in an emergency**, set `WALLET_SEND_GLOBAL_DAILY_LIMIT=0`. Every request then gets 429 `SEND_TEMPORARILY_UNAVAILABLE`. The mobile app shows "temporarily paused", which is the honest message.
5. After the incident, check that the CRITICAL log line reached the Slack channel (`LOG_SLACK_WEBHOOK_URL`). The log line is the alert. If it did not arrive, fix alerting before you close the incident.

## Sends with an unclear outcome

A broadcast that times out or gets a server error may still have been accepted by the network. Since v7.18.0 such a send is not marked failed. It stays `submitted`, and its record gets `metadata.submit_outcome = unknown`. The confirmation jobs `PollEvmWalletSendConfirmations` and `PollSolanaWalletSendConfirmations` run every minute and settle it.

- **On EVM networks**, the send fails with `BUNDLER_NOT_ACCEPTED` only when it can provably no longer land. Two things must both be true. Its paymaster sponsorship must have expired (Pimlico's default validity is 10 minutes, and the job waits another 5 minutes), and the account's EntryPoint nonce must show that the operation never ran. Pimlico's `eth_getUserOperationByHash` and receipt lookups see mined operations only, so they prove nothing about an operation that is still queued.
- **When the EVM nonce was used but no receipt was found**, the job cannot tell whether this operation or another one used it. It writes `metadata.submit_review = nonce_used_without_receipt`, logs an ERROR and stops settling that send. An operator must check the account on a block explorer and decide.
- **On Solana**, the send fails with `SOLANA_TX_DROPPED` only after the network has passed the transaction's `last_valid_block_height`, so its blockhash can no longer be used.
- **A second submit of the same intent** while the first is still running gets 409 `SUBMIT_IN_PROGRESS`. The app should poll the intent instead of submitting again.

`php artisan wallet:inspect-user <email>` shows a user's addresses and send records when you investigate one of these sends.

## Disabled-network behaviour

`WALLET_SEND_EVM_NETWORKS` is the allowlist, with the default `polygon,base,arbitrum`. A prepare or submit on any other network returns **422 `NETWORK_DISABLED`**.

- The `network` value is case-sensitive against `PaymentNetwork::values()`. `SOLANA` and `TRON` are uppercase, and the EVM networks are lowercase. This inconsistency is old, and the mobile app must send the exact value.
- If you disable a network while sends are in flight, intents that were already prepared for it fail at submit with the same code. Coordinate with the mobile team before you change the list.
- Solana is not part of this list. The Solana wallet surface enables it, and the sponsorship key only changes who pays the fee.

## Files referenced

- `config/wallet.php` has the `sponsorship`, `solana.sponsor` and `evm.enabled_networks` settings.
- `config/relayer.php` has the Pimlico credentials.
- `app/Http/Controllers/Api/Wallet/MobileWalletController.php` has `enforceSendRateLimit` (the 429 answers) and the prepare and submit endpoints.
- `app/Domain/Wallet/Services/SponsorshipBudget.php` enforces the shared caps.
- `app/Domain/Wallet/Services/SponsorshipCostTracker.php` tracks the dollar spend.
- `app/Domain/Wallet/Services/Send/SolanaSponsorSigner.php` holds the sponsor keypair and makes its signature.
- `app/Domain/Wallet/Services/Send/SolanaSendSubmitter.php` assembles the two-signer transaction and broadcasts it.
- `app/Domain/Wallet/Services/Send/EvmUserOpPreparer.php` calls Pimlico for sponsorship.
- `app/Domain/Wallet/Jobs/PollEvmWalletSendConfirmations.php` and `app/Domain/Wallet/Jobs/PollSolanaWalletSendConfirmations.php` confirm sends and settle unclear ones.
- `app/Console/Commands/SolanaSponsorBalanceCheckCommand.php` implements `solana:check-sponsor-balance`.
- `app/Console/Commands/WalletSponsorshipSpendCommand.php` implements `wallet:sponsorship-spend`.
- `routes/console.php` schedules the balance check and the confirmation jobs.
- `docs/operations/bridge-ramp.md` covers the fiat side of the same wallet surface.
