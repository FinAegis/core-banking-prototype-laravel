# Wallet Management User Guide

## Overview

This guide describes the wallet features in FinAegis at v7.18.1. There are three separate wallet surfaces, and each one works differently.

1. The **Zelta mobile wallet** is the hosted product. It is non-custodial, which means the person's device holds the keys.
2. The **FinAegis web wallet** at `/wallet` is a demo of ledger accounts. Its balances live in the FinAegis database.
3. The **blockchain wallet module** offers HD wallets, hardware wallets and multi-signature wallets through the API and the `/wallet/blockchain` pages.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Table of Contents

1. [Zelta Mobile Wallet](#zelta-mobile-wallet)
2. [FinAegis Web Wallet](#finaegis-web-wallet)
3. [Blockchain Wallets](#blockchain-wallets)
4. [Hardware Wallets](#hardware-wallets)
5. [Multi-Signature Wallets](#multi-signature-wallets)
6. [Security Advice](#security-advice)
7. [Troubleshooting](#troubleshooting)
8. [Getting Support](#getting-support)

## Zelta Mobile Wallet

> Zelta is non-custodial wallet software. Zelta and its operator are not a bank, electronic money institution,
> payment institution or crypto-asset service provider, and do not hold, exchange or transmit users' funds.
> Any card or off-ramp services will be provided by licensed third parties under their own terms.

### How it works

The person's keys stay on their own device, and Privy manages them there. On EVM networks the wallet is a smart account that the person controls with a passkey. On Solana the wallet uses an ed25519 key bound to the device. The device signs every transaction, and the backend never sees private key material.

The app signs in through `POST /api/v1/auth/privy-login`. That endpoint checks the Privy token and returns a FinAegis API token.

### Supported networks and assets

- Sends work on Polygon, Base and Arbitrum, and on Solana.
- Ethereum mainnet sends are off. The setting `WALLET_SEND_EVM_NETWORKS` defaults to `polygon,base,arbitrum`, and a disabled network returns `422 NETWORK_DISABLED`.
- The supported assets are USDC and USDT.

### Receiving

The app shows the person's address and a QR code from `GET /api/v1/wallet/receive`. On Solana the QR code follows the Solana Pay format.

Very small inbound SOL transfers are recorded but kept out of the activity feed and push notifications. The limit is `WALLET_SOLANA_DUST_MIN_INBOUND_SOL`, which defaults to 0.001 SOL. This filters address-poisoning spam. Token transfers are never filtered.

### Sending

A send has two steps.
1. `POST /api/v1/wallet/transactions/prepare` returns an unsigned transaction.
2. The device signs it, and `POST /api/v1/wallet/transactions/submit` sends the signed transaction to the network.

The `Idempotency-Key` HTTP header protects a send from running twice.

### Gas sponsorship and limits

The platform pays the network fee for EVM sends, so the person does not need ETH or POL. Each person can make 30 sponsored sends per day, and the whole platform can make 5,000 per day. The counters reset at 00:00 UTC. A send over the limit returns HTTP 429 with `SEND_DAILY_LIMIT_REACHED` or `SEND_TEMPORARILY_UNAVAILABLE`.

On Solana the platform can pay the fee when an operator sets a sponsor key. Without it, the sender's wallet must hold SOL for the fee.

### Send outcomes (v7.18.0)

Since v7.18.0 a send no longer shows "failed" when the network may have accepted it after a timeout or a server error. Such a send stays submitted until the chain settles it.

- On EVM networks the send fails only once its sponsorship has expired and the account's nonce shows it never ran.
- On Solana the send fails only once its blockhash can no longer be used.
- A second submit of the same send while the first is still running returns `409 SUBMIT_IN_PROGRESS`.

### Activity

The app reads the person's history from `GET /api/v1/wallet/transactions`. Inbound transfers are mirrored from the Helius webhook on Solana and the Alchemy webhook on EVM networks. Both send a push notification.

Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.

## FinAegis Web Wallet

### What it is

The web wallet at `/wallet` is part of the FinAegis demo. It shows ledger balances in the FinAegis database, including the GCU demo balance. It does not hold crypto on a blockchain.

The pages that move ledger balances sit behind `LEDGER_DIRECT_FUNDING_ENABLED`. The switch is on by default only for `APP_ENV=local`, `testing` and `demo`. It is off in production and for Zelta.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

### Pages

| Page | What it does |
|------|--------------|
| `/wallet` | Shows balances and quick actions. |
| `/wallet/deposit` | Offers a card deposit and a bank deposit. The bank deposit page leads on to Open Banking, Paysera, crypto and manual transfer. |
| `/wallet/withdraw` | Lets the person choose between a bank withdrawal and an Open Banking withdrawal. |
| `/wallet/transfer` | Moves money between accounts. |
| `/wallet/convert` | Converts between currencies. |
| `/wallet/transactions` | Lists the transaction history. |
| `/wallet/bank-allocation` | Shows the simulated GCU custodian allocation. |
| `/wallet/stablecoin-operations` | Shows the demo stablecoin mint and burn pages to staff with a stablecoin operator or admin role. |

### Withdrawing to a bank

1. Open **Wallet**, then **Withdraw**.
2. Choose **Traditional Bank Transfer**.
3. Enter the amount and the currency. The minimum is 10, and the currency is USD, EUR or GBP.
4. Type the bank name, the account number and the account holder's name. The routing number, IBAN and SWIFT code are optional.
5. Submit the form.

Since v7.18.0 the page does not save bank accounts. The saved-account feature never worked, because it wrote columns the `bank_accounts` table does not have. Type the bank details for each withdrawal.

The withdrawal only debits the FinAegis ledger. The bank payout step is a stub, so no money reaches a bank. The Open Banking option is a demo flow that does not work against real banks yet. See [OPENBANKING_WITHDRAWAL.md](../03-FEATURES/OPENBANKING_WITHDRAWAL.md).

## Blockchain Wallets

### What they are

The blockchain wallet module creates wallets and addresses on Ethereum, Polygon, BSC and Bitcoin. Its API sits behind Sanctum sign-in and the `sub_product:blockchain` check.

A wallet is either `custodial` or `non-custodial`. A non-custodial wallet can be created from a mnemonic that the person supplies, and the API validates the mnemonic first.

### API

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/blockchain-wallets` | List wallets |
| POST | `/api/blockchain-wallets` | Create a wallet |
| GET | `/api/blockchain-wallets/{walletId}` | Show a wallet |
| PUT | `/api/blockchain-wallets/{walletId}` | Update wallet settings |
| GET | `/api/blockchain-wallets/{walletId}/addresses` | List addresses |
| POST | `/api/blockchain-wallets/{walletId}/addresses` | Generate an address |
| GET | `/api/blockchain-wallets/{walletId}/transactions` | List transactions |
| POST | `/api/blockchain-wallets/{walletId}/backup` | Create a backup |
| POST | `/api/blockchain-wallets/generate-mnemonic` | Generate a mnemonic |

### Web pages

The `/wallet/blockchain` pages list addresses, create an address, show an address and its transactions, and export a backup. The send form at `/wallet/blockchain/address/{addressId}/send` also needs `LEDGER_DIRECT_FUNDING_ENABLED`.

## Hardware Wallets

### Supported devices

- Ledger Nano S and Ledger Nano X
- Trezor One and Trezor Model T

`GET /api/hardware-wallet/supported` returns the supported devices and chains, and it does not need a sign-in.

### How signing works

1. Register the device with `POST /api/hardware-wallet/register`.
2. Create a signing request with `POST /api/hardware-wallet/signing-request`.
3. Sign the transaction on the device.
4. Send the signature with `POST /api/hardware-wallet/signing-request/{id}/submit`.

A signing request expires after 300 seconds by default (`HARDWARE_WALLET_SIGNING_TTL`). A request can be cancelled with `POST /api/hardware-wallet/signing-request/{id}/cancel`. Registered devices are listed with `GET /api/hardware-wallet/associations` and removed with `DELETE /api/hardware-wallet/associations/{uuid}`.

## Multi-Signature Wallets

### What they are

A multi-signature wallet needs several approvals before a transaction is sent. A wallet has between 2 and 10 signers. The supported schemes are 2-of-2, 2-of-3, 3-of-4 and 3-of-5.

### How approval works

1. Create the wallet with `POST /api/multi-sig/wallets`.
2. Add signers with `POST /api/multi-sig/wallets/{id}/signers`.
3. Create an approval request with `POST /api/multi-sig/wallets/{id}/approval-requests`.
4. Each signer approves or rejects it with `POST /api/multi-sig/approval-requests/{id}/approve` or `/reject`.
5. Once enough signers approve, broadcast the transaction with `POST /api/multi-sig/approval-requests/{id}/broadcast`.

An approval request expires after 24 hours by default (`MULTI_SIG_APPROVAL_TTL`). A wallet can have at most five pending requests. `GET /api/multi-sig/pending-approvals` lists the requests that wait for the signed-in person.

## Security Advice

### Recovery phrases

A recovery phrase restores a wallet. Anyone who has it controls the funds.

- Write the phrase on paper or metal and keep it offline.
- Do not store it in photos, cloud storage or email.
- Keep copies in more than one safe place.
- Test the recovery before you move large amounts.

### Account security

- Turn on two-factor authentication for the FinAegis web account.
- Use a passkey for the Zelta app.
- Check your transaction history for activity you do not recognise.

## Troubleshooting

### A send stays pending

A send can stay submitted while the network decides. Since v7.18.0 the app waits for the chain instead of reporting a failure too early. Do not submit the same send again while it is pending, because a second submit returns `409 SUBMIT_IN_PROGRESS`.

### A send is refused

- `422 NETWORK_DISABLED` means the network is turned off for sends, as Ethereum mainnet is by default.
- `429 SEND_DAILY_LIMIT_REACHED` means the person used today's sponsored sends. The counter resets at 00:00 UTC.
- `429 SEND_TEMPORARILY_UNAVAILABLE` means the platform-wide daily limit was reached.

### A balance does not show

1. Check that the right network is selected.
2. Check that the transaction is confirmed on the chain.
3. Refresh the wallet.

### An address is rejected

The address may belong to another network, or it may contain a typo. EVM addresses are not case-sensitive, but Solana addresses are. Scan the QR code instead of typing the address.

### A web wallet page returns "not found"

The money-moving web wallet pages need `LEDGER_DIRECT_FUNDING_ENABLED`, and the exchange and liquidity pages need `TRADING_ENABLED`. Both are off in production.

## Getting Support

- [GitHub Issues](https://github.com/FinAegis/core-banking-prototype-laravel/issues)

Include these details in a support request:
- The wallet address
- The transaction ID
- A screenshot of the error
- The steps to reproduce it

## Related Documentation

- [OPENBANKING_WITHDRAWAL.md](../03-FEATURES/OPENBANKING_WITHDRAWAL.md) describes the bank and Open Banking withdrawal pages.
- [MOBILE_API_COMPATIBILITY.md](../MOBILE_API_COMPATIBILITY.md) describes the mobile wallet API.
- [wallet-sponsorship.md](../operations/wallet-sponsorship.md) describes gas sponsorship for operators.
