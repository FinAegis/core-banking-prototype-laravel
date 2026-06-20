@extends('layouts.public')

@section('title', 'Changelog | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Changelog | ' . config('brand.name', 'Zelta'),
        'description' => 'Release history for the Zelta core banking platform. Track every feature shipped, bug fixed, and improvement made — v7.0 through v7.16.0.',
        'keywords' => 'changelog, release notes, updates, ' . config('brand.name', 'Zelta') . ', version history, core banking',
    ])

    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Changelog', 'url' => url('/changelog')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 right-1/4 w-72 h-72 bg-teal-500/6 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-teal-500/10 border border-teal-500/20 text-sm text-teal-400 mb-8">
                    Release History
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Changelog
                </h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    A complete record of every release — features shipped, bugs fixed, and improvements made to the {{ config('brand.name', 'Zelta') }} core banking platform.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Timeline -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            @php
                $releases = [
                    [
                        'version' => 'v7.16.0',
                        'date' => 'June 21, 2026',
                        'label' => 'Non-Custodial RAILGUN Privacy — Backend Migration',
                        'label_color' => 'violet',
                        'badge_color' => 'bg-violet-100 text-violet-700 border-violet-200',
                        'dot_color' => 'bg-violet-500',
                        'items' => [
                            'Non-custodial RAILGUN privacy (backend) — the RAILGUN privacy stack is moving to the platform\'s non-custodial model: the device holds all keys and proves on-device, and the backend stops holding wallet seeds. Proving cannot be delegated without surrendering custody, so the on-device engine does it; the backend becomes a set of support services. The user-facing private-transaction flow ships once the mobile on-device engine lands — this release is the backend groundwork.',
                            'New privacy endpoints — <code>POST /api/v1/privacy/wallet/register</code> registers the device\'s <strong>public</strong> <code>0zk</code> address (the server stores no seed); <code>GET /api/v1/privacy/engine-config</code> returns SDK-exact bootstrap for the on-device engine (provider config, POI node URLs, networks); and <code>POST /api/v1/privacy/rpc/{network}</code> is a signed-URL JSON-RPC proxy that keeps the provider API key server-side with a method whitelist and per-user rate limiting.',
                            'iOS deep-link &amp; passkey fix — the <code>/.well-known/apple-app-site-association</code> file was served with an empty Apple Team ID prefix (<code>.com.zelta.wallet</code>), which broke iOS passkey sign-in and the <code>/pay</code> + <code>/verify</code> universal links. The Team ID prefix is now baked in so an unset env can\'t reproduce it.',
                            'Deploy-gate &amp; ops hardening — <code>ops:verify-env</code> now fails when the privacy providers are inconsistently configured, an ops runbook documents the self-hosted POI node + artifact mirror + key-safe RPC, and an adversarial security review of the RPC proxy closed a provider-key-in-logs leak and tightened its signed-URL token model.',
                        ],
                    ],
                    [
                        'version' => 'v7.15.0',
                        'date' => 'June 3, 2026',
                        'label' => 'Bridge.xyz Fiat Ramp — Bank Transfers, KYC &amp; Virtual Accounts',
                        'label_color' => 'emerald',
                        'badge_color' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                        'dot_color' => 'bg-emerald-500',
                        'items' => [
                            'Bridge.xyz fiat ramp — Bridge.xyz becomes the primary v1 fiat &harr; stablecoin rail: bank transfers in, USDC on Polygon. New <code>bridge_customers</code> persistence, KYC provider adapters under the Compliance domain, and a shared Bridge HTTP client + webhook verifier. A single <code>POST /api/v1/webhooks/bridge</code> endpoint handles both KYC (<code>customer.kyc_link_*</code>) and ramp (<code>virtual_account.activity</code>, <code>transfer.*</code>) events with event-level dedupe via <code>processed_webhook_events</code>. v1 is bank-rail onramp only; offramp, SWIFT, and additional networks land in v1.1. ADR-0005 records why Bridge over Stripe Crypto Onramp.',
                            'Bridge-hosted KYC and virtual accounts — mobile calls <code>GET /api/v1/user/bridge-setup-status</code> + <code>POST /api/v1/user/bridge-kyc-link</code> (lazy, idempotent Bridge customer creation). On KYC approval a virtual account is auto-provisioned against the user\'s Polygon address — with an observer retry if the address registers later — and a retry endpoint, push deep-link, and quote expiration round out the flow. WebSocket events <code>bridge.kyc.completed</code> / <code>bridge.kyc.rejected</code> / <code>bridge.virtual_account.ready</code> fire on the user\'s private channel with a push-notification fallback on the KYC terminals.',
                            'Developer-fee markup mechanism (ADR-0006) — a 0.75% Zelta markup flows through Bridge\'s per-customer <code>developer_fee_bps</code> (Free tier = 75 bps, Pro = 0). Tier changes auto-PATCH the Bridge customer via a <code>SubscriptionTierChanged</code> listener; operator commands <code>bridge:sync-dev-fee</code> (batch reconciliation with <code>--dry-run</code>) and <code>bridge:inspect-user</code> cover manual ops.',
                            'Bridge webhook signature verification — webhooks are verified against Bridge\'s current asymmetric scheme (<code>X-Webhook-Signature: t=&lt;unix_ms&gt;,v0=&lt;base64&gt;</code>, RSA-SHA256 over <code>&lt;t&gt;.&lt;body&gt;</code> with the per-endpoint public key). The legacy HMAC scheme remains as an auto-detected fallback; in production the verifier fails closed when no credential is configured.',
                            'HyperSwitch wired into the real deposit flow — card deposits can route through <code>HyperSwitchPaymentService</code>, opt-in via <code>HYPERSWITCH_ENABLED</code> (off by default; Stripe remains the default rail). The webhook credits accounts idempotently from the stored intent, and credit failures surface as <code>completion_failed</code> for operator reconciliation instead of being disguised as completed. Closes the long-standing #346.',
                            'Landing truth-pass — page copy, SEO meta, schema.org data, and MCP claims realigned with the shipped product; README and GitHub presentation refreshed for accuracy; redesigned <code>#get-the-app</code> section with Android open-testing CTAs; fixed a malformed Android install URL, a duplicate footer on <code>/support</code>, and missing <code>noindex</code> + SEO meta on the OAuth consent screen.',
                        ],
                    ],
                    [
                        'version' => 'v7.14.1',
                        'date' => 'May 19, 2026',
                        'label' => 'Privy Login Hotfix — Personal Team Provisioning',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'Fixed a 500 error when signing in with a new email. Signing in at <code>/login</code> with a previously-unused email cleared the email-OTP step, then returned a &ldquo;500 Server Error&rdquo; the instant the freshly-created account reached the dashboard. Privy email-OTP signups — both the web flow and the mobile <code>POST /api/v1/auth/privy-login</code> flow — created the user with a bare <code>User::create()</code> and skipped the Jetstream personal-team provisioning that password signups inherit from <code>CreateNewUser</code>. Team features are enabled, so every team-aware view dereferences <code>currentTeam</code> — a team-less user crashed rendering <code>navigation-menu.blade.php</code>. A new <code>ProvisionsPersonalTeam</code> trait provisions the personal team idempotently on every Privy login (not just signup), which also heals users left team-less by the earlier code path on their next sign-in. (#1088)',
                        ],
                    ],
                    [
                        'version' => 'v7.14.0',
                        'date' => 'May 18, 2026',
                        'label' => 'Wallet Transaction Mirror — EVM Deposits, Admin Visibility, Receipts &amp; Dust Filtering',
                        'label_color' => 'blue',
                        'badge_color' => 'bg-blue-100 text-blue-700 border-blue-200',
                        'dot_color' => 'bg-blue-500',
                        'items' => [
                            'EVM inbound deposits are now mirrored into transaction history. Previously the Alchemy webhook fired a balance update and a push notification for an inbound USDC/USDT transfer on Polygon, Base, Arbitrum, or Ethereum — then discarded the transaction. The deposit never appeared in <code>GET /api/v1/wallet/transactions</code>: the balance moved but no matching entry was shown. The new <code>EvmTransactionProcessor</code> (the EVM counterpart of <code>HeliusTransactionProcessor</code>) persists every transfer to <code>blockchain_address_transactions</code> and <code>activity_feed_items</code>, bringing EVM to parity with Solana. Outbound sends that already own a feed item via <code>WalletSendRecordObserver</code> keep their audit row but skip the duplicate. A new <code>php artisan evm:backfill-transactions</code> command seeds history that predates the live mirror via the Alchemy Transfers API.',
                            'Per-user wallet visibility in the admin panel. Support staff had no screen for a customer\'s crypto activity — <code>/admin/accounts</code> is the fiat ledger, and no resource surfaced blockchain data. Opening a user in the admin panel now shows three read-only tabs: <strong>Wallet Addresses</strong> (registered addresses per chain), <strong>Blockchain Transactions</strong> (the cross-chain mirror of on-chain activity, including dust hidden from the customer feed), and <strong>Wallet Sends</strong> (the full send lifecycle with <code>error_code</code> / <code>error_message</code>, so support can see exactly why a transfer failed).',
                            'Hosted receipt page and downloadable PDF. <code>POST /api/v1/transactions/{txId}/receipt</code> returned a <code>sharePayload</code> link pointing at an unregistered <code>/receipt/{id}</code> route — every share link 404\'d — and <code>pdfUrl</code> was always null because no PDF was ever generated. A new public <code>GET /receipt/{shareToken}</code> renders a branded, <code>noindex</code> receipt page keyed on an unguessable token; <code>ReceiptService</code> renders the same view to a PDF (dompdf) stored on the public disk, so <code>pdfUrl</code> is now a real downloadable file.',
                            'Solana inbound dust filter. A wallet was hit by address-poisoning spam — six unsolicited 0.00001 SOL transfers — and each one became an activity-feed entry <em>and</em> a push notification. Inbound native-SOL transfers below <code>WALLET_SOLANA_DUST_MIN_INBOUND_SOL</code> (default 0.001 SOL) are now recorded as a <code>BlockchainTransaction</code> for audit but kept out of the activity feed, and the push is suppressed. Token transfers (USDC/USDT) are never filtered.',
                        ],
                    ],
                    [
                        'version' => 'v7.13.2',
                        'date' => 'May 15, 2026',
                        'label' => 'Mobile Wallet Bug-Fix Patch — Solana Send, Receipts, Privacy Gating',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'Wallet send network-casing fix. <code>POST /api/v1/wallet/transactions/prepare</code> previously validated <code>network</code> against a hard-coded <code>in:solana,polygon,base,arbitrum,ethereum</code> list (lowercase only) while the <code>quote</code> endpoint accepted the canonical <code>PaymentNetwork</code> enum values (<code>SOLANA</code> / <code>TRON</code> uppercase, <code>polygon</code> / <code>base</code> / <code>arbitrum</code> / <code>ethereum</code> lowercase). Mobile sending <code>network: "SOLANA"</code> to both endpoints (as it does for quote) was rejected with "selected network is invalid" on prepare, and lowercase <code>"solana"</code> wasn\'t a valid enum case at all. Both endpoints now reference <code>Rule::enum(PaymentNetwork::class)</code> so the wire contract is identical end-to-end. CLAUDE.md gains a pitfall row locking the network-casing + snake/camel field-name conventions for future asset additions.',
                            'Receipt endpoint now works for Solana inbound transfers and any non-intent activity. <code>POST /api/v1/transactions/{txId}/receipt</code> previously looked up <code>PaymentIntent::where(\'public_id\', $txId)</code>, but Solana inbound USDC and USDT are written directly to <code>activity_feed_items</code> + <code>blockchain_address_transactions</code> by <code>HeliusTransactionProcessor</code> with no <code>PaymentIntent</code> in between. The receipt service now resolves the <code>ActivityFeedItem</code> first (matching the unified ID mobile already gets back from <code>GET /wallet/transactions</code> and <code>GET /transactions/{id}</code>), then either pulls merchant + fee details from the linked <code>PaymentIntent</code> (preserving existing merchant-payment behaviour) or builds the receipt from the activity row + Helius <code>metadata.tx_hash</code> / <code>metadata.fee_usd</code> when no intent exists. <code>payment_intent_id</code> was already nullable in the schema; idempotency keys on <code>(user_id, tx_hash)</code> for non-intent receipts.',
                            'Privacy merkle-root endpoint now returns a clean 503 instead of a generic 500. <code>GET /api/v1/privacy/merkle-root?network=…</code> or <code>?chain_id=…</code> previously surfaced an uncaught <code>RuntimeException</code> from <code>MerkleTreeService::fetchMerkleRootFromChain</code> when the provider binding wasn\'t operational. The controller now catches and returns <code>HTTP 503 { error: { code: "ERR_PRIVACY_310", message: "Privacy pool is not available on this deployment." } }</code> so mobile\'s shield-feature gating has a stable code to branch on without parsing 500s.',
                        ],
                    ],
                    [
                        'version' => 'v7.13.1',
                        'date' => 'May 15, 2026',
                        'label' => 'USDT Enablement + Solana Pay QR Spec Fix',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'USDT added to the mobile-wallet send and receive surfaces. The <code>PaymentAsset</code> enum gains a USDT case (decimals=6, label "Tether USD"); <code>POST /api/v1/wallet/transactions/quote</code> and <code>GET /api/v1/wallet/receive</code> now accept <code>asset=USDT</code> alongside USDC. The EVM and Solana token registries (<code>EvmTokens</code>, <code>SolanaTokens::KNOWN_MINTS</code>) already had USDT contract addresses and mint wired in since the non-custodial Wallet Send work — only the enum-backed validators were gating it. The hard-coded <code>"in:USDC"</code> validator + "currently only USDC supported" OpenAPI annotation on <code>WalletReceiveController</code> are removed; both validators now reference <code>PaymentAsset::values()</code> so future asset additions auto-track.',
                            'Solana Pay QR spec compliance. <code>ReceiveAddressService::buildQrValue</code> previously emitted <code>solana:{address}?spl-token=USDC</code> — the <code>spl-token</code> parameter per <a href="https://docs.solanapay.com/spec" target="_blank" rel="noopener" class="underline">solanapay.com/spec</a> must be the SPL mint address, not the symbol. Now resolves <code>USDC</code> → <code>EPjF…Dt1v</code> and <code>USDT</code> → <code>Es9v…NYB</code> via a new <code>SolanaTokens::mintFor()</code> helper. Strict Solana Pay wallets (Phantom, Solflare) will now scan USDC and USDT QRs correctly.',
                            'Operational follow-up for full USDT enablement on EVM: Pimlico\'s sponsorship policy needs the USDT contract addresses added on polygon, arbitrum, and ethereum mainnets (Polygon <code>0xc213…58e8f</code>, Arbitrum <code>0xfd08…fcbb9</code>, Ethereum <code>0xdac1…31ec7</code>). Without this, <code>pm_sponsorUserOperation</code> will decline to sponsor USDT transfers on EVM. Tracked outside the repo.',
                        ],
                    ],
                    [
                        'version' => 'v7.13.0',
                        'date' => 'May 15, 2026',
                        'label' => 'Mobile-Driven IAP Subscriptions + Auth Hardening',
                        'label_color' => 'purple',
                        'badge_color' => 'bg-purple-100 text-purple-700 border-purple-200',
                        'dot_color' => 'bg-purple-500',
                        'items' => [
                            'In-App Purchase verification — <code>POST /api/v1/subscriptions/iap/verify</code> exchanges an Apple StoreKit 2 JWS or Google Play subscription token for a Zelta subscription record. Idempotent via <code>(provider, original_transaction_id)</code> unique index; Family-Sharing / stale-receipt / cross-account conflicts surface as HTTP 409 with the mobile-aligned <code>ERR_SUB_002 { kind, attemptedSource, existingSubscription }</code> envelope.',
                            'Apple JWS chain validation — fail-closed verifier pins the trust anchor to Apple Root CA G3 (fingerprint <code>63343ABF…E9179</code>) and performs an ES256 signature check with x5c chain validation, no new vendor deps. Staging-only <code>APPLE_JWS_VERIFICATION_BYPASS</code> is hard-rejected in production.',
                            'Google Play RTDN ingestion — Cloud Pub/Sub push subscription delivers subscription state transitions through the same <code>processed_webhook_events</code> idempotency path as Apple App Store Server Notifications V2. Receipts are HMAC-pseudonymised by <code>IapReceiptPseudonymiser</code> before persistence or logging.',
                            'Revenue outbox + event ledger — <code>revenue_outbox_events</code> queues downstream cue grants and analytics; <code>revenue_events</code> is the append-only ledger that survives reconciliation reruns. Trial-card-fingerprint anti-abuse rejects repeat free-trial attempts before they reach the store. GDPR consent payload captured per-purchase via <code>subscription_consent_log</code>.',
                            'Privy web <code>/login</code> Origin header — <code>PrivyEmailOtpClient</code> now sends <code>Origin: &lt;web origin&gt;</code> (config: <code>privy.web_origin</code>, env: <code>PRIVY_WEB_ORIGIN</code>, falls back to <code>app.url</code>) so Privy\'s REST allowlist accepts server-to-server <code>/passwordless/{init,authenticate}</code> requests. Mobile JWT path is unaffected.',
                            'Device-takeover guard contract fix — <code>DeviceTakeoverAttemptException</code> declared <code>getHttpStatusCode(): 409</code> since the v2.2.0 security hardening (#348) but had no <code>render()</code> method and no <code>HttpException</code> parent, so the guard fell through to Laravel\'s default handler and emitted a generic 500. <code>POST /api/v1/notifications/register-device</code> now returns <code>{ error: "DEVICE_REGISTERED_TO_DIFFERENT_USER" }</code> with HTTP 409 so mobile can distinguish a takeover-blocked registration from a transient outage.',
                            'Rewards quest-completion lockdown — the public <code>POST /api/v1/rewards/quests/{id}/complete</code> endpoint and the GraphQL <code>completeQuest</code> mutation are removed. Both credited XP from <code>RewardsService::completeQuest</code> without checking the underlying domain action; any authenticated caller could one-shot ~290 XP + 590 points from the seeded quests. Completion is now reachable only via domain-event listeners. New <code>TriggerQuestOnProfileUpdated</code> listener closes the <code>complete-profile</code> quest gap; new <code>QuestCompleted</code> broadcast fires on <code>private-user.{userId}</code> after successful auto-completion so mobile can show the celebration modal in real time.',
                        ],
                    ],
                    [
                        'version' => 'v7.12.0',
                        'date' => 'May 5, 2026',
                        'label' => 'Non-Custodial Wallet Send (Privy Embedded Wallets)',
                        'label_color' => 'teal',
                        'badge_color' => 'bg-teal-100 text-teal-700 border-teal-200',
                        'dot_color' => 'bg-teal-500',
                        'items' => [
                            'Privy passkey login — <code>POST /api/v1/auth/privy-login</code> exchanges a Privy JWT (verified via JWKS with <code>iss</code> / <code>aud</code> / <code>exp</code> checks) for a Sanctum token. Auto-creates the user on first login via <code>privy_user_id</code> lookup; cross-client account merging works for the same Privy DID across mobile and web.',
                            'Non-custodial address registration — <code>POST /api/v1/wallet/addresses</code> registers Privy-derived addresses. EVM smart-account address mirrors across polygon / base / arbitrum / ethereum, plus one Solana ed25519 row in <code>blockchain_addresses</code> so existing webhook sync, balance lookups, and tx indexers continue unchanged.',
                            'Two-step send flow — <code>POST /api/v1/wallet/transactions/prepare</code> returns an unsigned payload (Solana legacy tx message bytes; EVM ERC-4337 v0.6 UserOperation with Pimlico paymaster sponsorship), persists a <code>wallet_send_records</code> row in <code>pending</code> state, honors the <code>Idempotency-Key</code> HTTP header. <code>POST /api/v1/wallet/transactions/submit</code> accepts the device-signed payload and broadcasts via Helius (Solana) or Pimlico bundler (EVM). Wire contract is camelCase end-to-end (<code>quoteId</code>, <code>intentId</code>, <code>evm.ownerPasskeyCredentialId</code>) to match mobile RN/TS request types.',
                            'Confirmation tracking — <code>HeliusTransactionProcessor</code> flips Solana records to <code>confirmed</code> from the existing webhook. <code>PollEvmWalletSendConfirmations</code> polls the Pimlico bundler for in-flight EVM UserOps every minute.',
                            'Stripped surface (hard cutover, project not yet live) — the custodial signing endpoint <code>POST /api/v1/auth/sign-userop</code>, the custodial dispatch endpoint <code>POST /api/v1/wallet/transactions/send</code>, and all <code>/api/v1/wallet/recovery-shard-backup/*</code> Shamir endpoints are removed. Privy holds the keys; the device signs every transaction; the backend never sees private key material.',
                            'Operator commands — <code>php artisan privy:verify-jwt &lt;token&gt;</code> verifies a Privy JWT against the live issuer and dumps claims; <code>php artisan wallet:inspect-user &lt;email&gt;</code> shows Privy linkage + addresses + recent send records read-only.',
                        ],
                    ],
                    [
                        'version' => 'v7.11.0',
                        'date' => 'April 30, 2026',
                        'label' => 'Public MCP Server + Brand Polish',
                        'label_color' => 'indigo',
                        'badge_color' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                        'dot_color' => 'bg-indigo-500',
                        'items' => [
                            'Public MCP server — <code>https://mcp.zelta.app/mcp</code> serves a 12-tool catalog (accounts, payments, exchange, baskets, mcp ramp) over OAuth 2.1 with RFC 7591 dynamic client registration and RFC 9728 protected resource metadata. Designed for Claude Desktop, Cursor, and any spec-compliant AI agent. Spec: <code>docs/superpowers/specs/2026-04-27-mcp-server-design.md</code>.',
                            '<code>@finaegis/mcp</code> npm wrapper — published stdio bridge for MCP clients that don\'t speak HTTP+SSE directly. Styled OAuth callback pages, file-based token storage by default (no keytar dependency), and a published <code>FinAegis/mcp</code> mirror for clean install.',
                            'Brand polish on auth surfaces — login page, application logo, and footer no longer hard-code "FinAegis"; production deployments now read <code>config(\'brand.name\')</code> end-to-end. Open-source / Apache-2.0 marketing copy is gated to demo environments.',
                            'Admin panel hardening — non-admin users hitting <code>/admin</code> now redirect to <code>/dashboard</code> with a flash error instead of a bare 403. Filament widgets gain module-aware visibility (<code>WidgetRespectsModuleVisibility</code> trait), matching the existing resource gating; production deployments can scope the dashboard via <code>ADMIN_MODULES</code>.',
                            'Production env hygiene — <code>SHOW_PROMO_PAGES=false</code> and <code>ADMIN_MODULES</code> guidance now ship in <code>.env.production.example</code> and <code>.env.zelta.example</code> so customer-facing deployments derived from these templates pick the right scope by default.',
                            'Auto-discovery for domain console commands — <code>app/Domain/*/Console/Commands/</code> now register automatically via <code>bootstrap/app.php</code>, fixing intermittent "namespace not found" errors during scheduled runs. Ramp tools skip self-registration when only the mock provider is configured.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.8',
                        'date' => 'April 19, 2026',
                        'label' => 'Public SDK Distribution',
                        'label_color' => 'emerald',
                        'badge_color' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                        'dot_color' => 'bg-emerald-500',
                        'items' => [
                            'Public registry packages — every SDK and the CLI now install from public registries for the first time: <code>npm install -g @finaegis/cli</code>, <code>npm install @finaegis/sdk</code>, <code>pip install finaegis</code>, <code>composer require finaegis/payment-sdk</code>, and <code>composer require finaegis/php-sdk</code>. Prior <code>@zelta/*</code> references were aspirational — the npm <code>@zelta</code> scope was owned by a third party, so nothing actually shipped.',
                            'npm scope migration — <code>@zelta/cli</code> → <code>@finaegis/cli</code> and <code>@zelta/sdk</code> → <code>@finaegis/sdk</code>. The user-facing brand name "Zelta" is unchanged in the UI, PSR-4 namespaces (<code>Zelta\\</code>, <code>FinAegis\\</code>) are unchanged, and the CLI binary name <code>zelta</code> is unchanged — this is purely a distribution identifier change.',
                            'Packagist vendor migration — <code>zelta/payment-sdk</code> → <code>finaegis/payment-sdk</code>, <code>zelta/cli</code> → <code>finaegis/cli</code>. Developer portal and partner docs now show the real public install commands instead of monorepo path-repository dependencies.',
                            'Monorepo split-mirror workflow — new <code>.github/workflows/monorepo-split.yml</code> uses splitsh/lite to auto-mirror <code>packages/zelta-{sdk,cli}/</code> and <code>sdks/php/</code> into dedicated Packagist-readable repos (<code>github.com/FinAegis/{payment-sdk,cli,php-sdk}</code>) on every main push and release tag. Packagist only reads root composer.json, so the three PHP packages need their own repos to be installable.',
                            'CLI release pipeline fixes — replaced the decade-old Box 2 installer with a direct PHAR download from box-project/box (#937); PHAR now bundles <code>vendor/</code> so Symfony Console is actually present at runtime (#939); npm <code>version</code> field is now valid semver instead of the raw tag name (#936); PHAR artifact is uploaded in <code>build-phar</code> and downloaded in <code>publish-npm</code> so the published tarball actually contains the binary (#936); splitsh/lite pinned to v1.0.1 (#940) and checkout credential helper bypassed so the cross-repo PAT push works (#941).',
                            'Zero breaking API changes — no library method signatures, return types, or public interfaces changed. PSR-4 namespaces, the CLI binary, and all documented APIs are byte-compatible with v7.10.7.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.7',
                        'date' => 'April 15, 2026',
                        'label' => 'Safe-Major Composer Trio',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'laravel/tinker ^2.9 → ^3.0 — Dev-only REPL. v3 requires PHP 8.4, which the project is already on. Zero runtime impact.',
                            'resend/resend-php ^0.23.0 → ^1.0 — v1.0 is the stable release, not a breaking rewrite. Bounded to the Resend mail adapter — email verification notification path verified via the EmailVerificationControllerTest suite.',
                            'darkaonline/l5-swagger ^10.1 → ^11.0 — Pulls in swagger-php v5, which is stricter about OpenAPI annotation validation. php artisan l5-swagger:generate regenerates a clean 2.7 MB api-docs.json.',
                            'X402StatusController Annotation Fix — The l5-swagger upgrade surfaced one pre-existing bug: the #[OA\\Get] + #[OA\\Response] attributes for /api/v1/x402/supported were misattached to the wellKnown() method instead of supported(), because PHP attributes attach to the next declaration and the docblock between them did not reset the anchor. Old swagger-php v4 tolerated the duplicate response="200" silently; v5 errors out. Moved the attributes down to the right method — each now carries exactly one #[OA\\Get] + one #[OA\\Response(response: 200)].',
                            'Still-Deferred Majors — Laravel 12 → 13 (ecosystem lock-step), Filament 3 → 5 (two majors), Livewire 3 → 4, Pest 3 → 4, Predis 2 → 3, Cashier 15 → 16, Scout 10 → 11, Symfony http-client 7 → 8, Tailwind 3 → 4, Vite 6 → 8 + laravel-vite-plugin 1 → 3, and @ledgerhq/hw-app-eth 6 → 7 each remain their own dedicated migration project.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.6',
                        'date' => 'April 14, 2026',
                        'label' => 'Composer Dependency Sweep',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'Composer Semver-Safe Update — 230 packages upgraded within the existing composer.json ranges. Notable bumps include Laravel 12.55.1 → 12.56.0, Filament 3.3.49 → 3.3.50, Livewire 3.7.11 → 3.7.15, Larastan 3.9.3 → 3.9.5, PHPStan 2.1.42 → 2.1.47, php-cs-fixer 3.94.2 → 3.95.1, AWS SDK 3.373.8 → 3.379.0, Lighthouse 6.65.0 → 6.66.0, Behat 3.29.0 → 3.30.0, Laravel Passport 13.6.0 → 13.7.4, plus 220+ smaller / transitive bumps.',
                            'PHPStan Level 8 Kept Clean — Larastan 3.9.5 tightened several rules, surfacing 6 pre-existing errors across RecordPaymentActivity, MessageDeliveryWorkflow, RegulatoryCalendarService, BasketController, StatusController, and the Account Exceptions test. All fixed in-PR per the CLAUDE.md policy against suppression — dead null-coalesces removed, Collection covariance resolved by closure type hints and by flattening to plain arrays where invariance bit, and the Pest toThrow() dead-code false positive worked around with try/catch assertions.',
                            'php-cs-fixer 3.95.1 Repo-Wide Reformat — The new fixer version tightened match-arm alignment, flagging 65 files where existing match expressions had inconsistent whitespace around the fat arrow. 218 insertions / 218 deletions — pure whitespace normalization, no semantic changes.',
                            'Zero Security Advisories — composer update reported "No security vulnerability advisories found." No CVE-driven bumps in this release; strictly maintenance within the Laravel 12 / PHP 8.4 line.',
                            'Out of Scope — Major-version backend bumps are each dedicated migration projects and deliberately deferred: Laravel 12 → 13, Filament 3 → 5 (two majors), Livewire 3 → 4, Pest 3 → 4, Predis 2 → 3, Cashier 15 → 16, Scout 10 → 11, Tinker 2 → 3, Symfony http-client 7 → 8, L5-Swagger 10 → 11, Resend 0 → 1. Each of these needs its own migration plan and review pass.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.5',
                        'date' => 'April 14, 2026',
                        'label' => 'npm Dependency Sweep',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'npm Semver-Safe Update — Ran npm update within the existing package.json ranges, which pulled minor/patch bumps for @ledgerhq/hw-transport-webusb (6.32.0 → 6.33.0), autoprefixer (10.4.27 → 10.5.0), postcss (8.5.8 → 8.5.9), plus transitive dedup across the @ledgerhq/* tree.',
                            'Lockfile Dedup — package-lock.json shrank by ~500 lines as newer axios/vite/ledger versions consolidated redundant transitive dep trees.',
                            'follow-redirects Cleared — The remaining moderate-severity follow-redirects advisory carried over from the v7.10.4 cycle was resolved by this update. Audit now reports zero critical, zero high, zero moderate — 18 lows remain in deep transitive dev tooling.',
                            'Zero Runtime Impact — Lockfile-only change. No package.json edits, no application code touched. Production bundle rebuilds byte-equivalent under the existing Vite 6.4.2 configuration.',
                            'Out of Scope — Major-version bumps (@ledgerhq/hw-app-eth 6.x → 7.x, laravel-vite-plugin 1.x → 3.x, tailwindcss 3.x → 4.x, vite 6.x → 8.x) are deliberately deferred. Each is a dedicated migration project — Tailwind v4 and the laravel-vite-plugin/vite alignment in particular need their own brainstorming pass.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.4',
                        'date' => 'April 14, 2026',
                        'label' => 'Frontend Security Patch',
                        'label_color' => 'red',
                        'badge_color' => 'bg-red-100 text-red-700 border-red-200',
                        'dot_color' => 'bg-red-500',
                        'items' => [
                            'axios Security Patch — Raised axios from ^1.13.5 to ^1.15.0 (also mirrored in the npm overrides block) to close GHSA-3p68-rc4w-qgx5, a NO_PROXY hostname normalization bypass that could be leveraged into SSRF. The single advisory cascaded through 4 @ledgerhq/* hardware-wallet packages, so bumping axios resolved all 5 critical severity entries at once.',
                            'Vite Security Patch — Bumped Vite from ^6.4.1 to ^6.4.2 to close a high-severity path traversal in the Optimized Deps .map handling code path.',
                            'Zero Runtime Impact — Both affected packages are dev/build-time only. axios is a devDependency and Vite is the build bundler; neither ships in the Laravel runtime. No application code or PHP dependencies were touched in this release.',
                            'npm Audit Before/After — 23 vulnerabilities (16 low, 1 moderate, 1 high, 5 critical) → 19 vulnerabilities (18 low, 1 moderate). Remaining lows and the 1 moderate (follow-redirects) live in deep transitive dev tooling and are scheduled for the next general npm bump PR.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.3',
                        'date' => 'April 14, 2026',
                        'label' => 'Onboarding Welcome Modal Fix',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'Dashboard Welcome Modal — Removed the broken "Take Tour" header button and startTour() placeholder from the new-user welcome modal. The placeholder contained PHP string-concat syntax dropped into a raw <script> block, which JavaScript parsed as member access on a string literal and threw TypeError at runtime, silently breaking the onboarding flow for every new registrant.',
                            'Onboarding Flow — Stripped the startTour() fallback from startOnboarding()\'s .catch branch so a failed /onboarding/complete POST no longer cascades into a second runtime error.',
                            'Registration Verified — RegistrationTest suite confirmed green end-to-end (POST /register → CreateNewUser → team provisioning → dashboard). The bug was confined to the post-registration welcome modal JavaScript, not the registration pipeline itself.',
                            'Tour Deferred — A real interactive tour (Shepherd.js / Intro.js etc.) is parked for a future design pass. Removing the stub is the honest minimum fix.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.2',
                        'date' => 'April 13, 2026',
                        'label' => 'Deployment Pipeline Fix',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'Deploy Pipeline — Fixed a 1GB OOM in the Pre-deployment Validation job that had been blocking the Deploy to Production workflow since v7.10.0. Unit tests now run in batched PHP processes via the shared bin/pest-batch runner, mirroring the CI Pipeline unit test step.',
                            'Cache Driver — Switched the Pre-deployment Validation step to the array cache driver (matching .env.testing). The previous redis driver broke tests that mock the Redis facade because Cache::put silently failed through the swapped facade.',
                            'Dotenv Quoting — Wrapped BRAND_LEGAL_JURISDICTION in .env.example and BRAND_TAGLINE in .env.zelta.example in double quotes. The unquoted whitespace was tripping vlucas/phpdotenv with "Failed to parse dotenv file. Encountered unexpected whitespace". Affects anyone copying .env.example for a fresh setup.',
                            'First Deployable Release — v7.10.2 is the first release tag since v7.10.0 whose Deploy to Production pipeline is actually functional. v7.10.0 and v7.10.1 both had Pre-deployment Validation failures that blocked the deployment artifact build.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.1',
                        'date' => 'April 13, 2026',
                        'label' => 'Stripe Bridge Ramp Hardening',
                        'label_color' => 'teal',
                        'badge_color' => 'bg-teal-100 text-teal-700 border-teal-200',
                        'dot_color' => 'bg-teal-500',
                        'items' => [
                            'Stripe Bridge Ramp — Working Stripe Crypto Onramp integration with proper t=,v1= signature verification (HMAC-SHA256, 300s replay window, multi-v1 support), real session status fetch, and event envelope normalization. Replaces broken scaffolding that would have rejected every real Stripe webhook.',
                            'Platform-Generic Webhook Abstraction — Widened RampProviderInterface with normalizeWebhookPayload() and getWebhookSignatureHeader(); validator callable now receives raw HTTP body bytes (not re-encoded JSON) and the full signature header. Onramper, Stripe Bridge, and Mock providers all share the same contract.',
                            'Lazy RampProviderRegistry — Webhook controller resolves providers by name via a registry that lazily instantiates only the provider for the inbound webhook. Production deployments using only Stripe no longer need fake Onramper credentials to boot.',
                            'Provider-Aware Validation — RampService::validateRampParams() reads supported currencies from the active provider instead of global config; requesting BTC through Stripe now returns a clear 422 naming the active provider.',
                            'Race Safety — Both webhook processing and session status polling wrap DB updates in DB::transaction() + lockForUpdate() with terminal-state idempotency; webhooks arriving during a poll are no longer clobbered.',
                            'Parallel Webhook Cleanup — Deleted the legacy StripeBridgeWebhookController and its /api/webhooks/stripe/bridge route. The old code path used the wrong event type prefix and had a non-production signature bypass. The generic /api/v1/ramp/webhook/{provider} route is now the single Stripe webhook entry point.',
                            'Test Coverage — 95 ramp tests passing including a parameterized provider-contract suite (every provider inherits the same checks), Stripe signature verification, raw-body preservation, and a non-custody regression test asserting zero row growth in ledgers/transactions tables on ramp completion.',
                        ],
                    ],
                    [
                        'version' => 'v7.10.0',
                        'date' => 'April 7, 2026',
                        'label' => 'Webhook Architecture Refactor',
                        'label_color' => 'blue',
                        'badge_color' => 'bg-blue-100 text-blue-700 border-blue-200',
                        'dot_color' => 'bg-blue-500',
                        'items' => [
                            'Webhook Infrastructure — webhook_endpoints table with per-user address monitoring, AlchemyWebhookManager, SmartAccountObserver, evm:sync-webhooks command, and config cleanup',
                            'Webhook Hardening — Unique (tx_hash, chain) constraint, ProcessAlchemyWebhookJob and ProcessHeliusWebhookJob for async queue-based processing, Cache-based deduplication, spam filter, and reorg detection',
                            'Per-User Sharding — Per-user webhook endpoints with encrypted signing keys and 100K address sharding for scalable on-chain monitoring',
                            'Mobile Backend — CardWaitlistController, TrustCertPaymentController with 3 payment methods (wallet, card, IAP), and RequireKycVerification middleware for Level 0 user restrictions',
                            'Ramp Migration — StripeBridgeService and generic RampWebhookController replacing Onramper with Stripe Bridge for fiat on/off-ramp',
                            'Security — bcmath for all financial amounts, encrypted stripe_client_secret, webhook timestamp tolerance, IAP production gate, 15 security findings identified and fixed via post-phase review',
                        ],
                    ],
                    [
                        'version' => 'v7.9.0',
                        'date' => 'April 1, 2026',
                        'label' => 'Compliance & Polish',
                        'label_color' => 'emerald',
                        'badge_color' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                        'dot_color' => 'bg-emerald-500',
                        'items' => [
                            'Address Screening — Multi-layer OFAC SDN + GoPlus Security API + Chainalysis sanctions checking',
                            'Website — Professional copywriting pass, fixed broken social links, consistent branding across all pages',
                            'Developer Portal — Fixed Blade compile error on code examples, corrected API URLs and env vars',
                            'SEO — Schema.org markup and breadcrumbs added to subproduct pages',
                            'Mobile Jobs — Fixed tenant context verification in global scheduled jobs',
                        ],
                    ],
                    [
                        'version' => 'v7.8.2',
                        'date' => 'April 1, 2026',
                        'label' => 'Maintenance',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'Registration — Mobile API signup no longer blocked by admin registration gate',
                            'Developer Portal — Honest SDK install commands, OpenAPI spec link, consolidated rate limits',
                            'Infrastructure — Daily log rotation, CRON expression fix, CORS header update',
                        ],
                    ],
                    [
                        'version' => 'v7.8.1',
                        'date' => 'March 31, 2026',
                        'label' => 'Website Polish',
                        'label_color' => 'violet',
                        'badge_color' => 'bg-violet-100 text-violet-700 border-violet-200',
                        'dot_color' => 'bg-violet-500',
                        'items' => [
                            'GCU page redesigned — migrated to unified brand layout',
                            'Platform page simplified — module cards replaced with features link',
                            'Public changelog page added at /changelog',
                        ],
                    ],
                    [
                        'version' => 'v7.8.0',
                        'date' => 'March 30, 2026',
                        'label' => 'Standards & Compliance',
                        'label_color' => 'indigo',
                        'badge_color' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                        'dot_color' => 'bg-indigo-500',
                        'items' => [
                            'ISO 20022 — Full pacs, pain, and camt message suite with standards-compliant schema validation',
                            'Open Banking PSD2 — AISP/PISP consent lifecycle, Berlin Group and UK Open Banking adapters',
                            'ISO 8583 — Card transaction messaging with PIN management and authorization flows',
                            'US Payment Rails — ACH, Fedwire, RTP, FedNow with intelligent rail routing and same-day settlement',
                            'Interledger Protocol — ILP connector, Open Payments (GNAP), and cross-currency streaming quotes',
                            'Double-Entry Ledger — Chart of accounts, journal entries, GL auto-posting, optional TigerBeetle driver',
                            'Microfinance — Group lending, IFRS provisioning, share accounts, teller operations, field officer tools',
                        ],
                    ],
                    [
                        'version' => 'v7.1.1',
                        'date' => 'March 29, 2026',
                        'label' => 'Security Patch',
                        'label_color' => 'red',
                        'badge_color' => 'bg-red-100 text-red-700 border-red-200',
                        'dot_color' => 'bg-red-500',
                        'items' => [
                            'JIT funding TOCTOU fix — Eliminated race condition in just-in-time funding that could allow double-spend',
                            'Webhook SSRF prevention — Added URL allowlist validation and DNS rebinding protection for outbound webhooks',
                            'Rate limiter hardening — Switched to atomic Cache::add + increment pattern to prevent counter bypass under concurrency',
                            'Threat model remediation — All 15 items from the v7.6 threat model audit resolved and verified',
                        ],
                    ],
                    [
                        'version' => 'v7.1.0',
                        'date' => 'March 29, 2026',
                        'label' => 'Production Hardening',
                        'label_color' => 'amber',
                        'badge_color' => 'bg-amber-100 text-amber-700 border-amber-200',
                        'dot_color' => 'bg-amber-500',
                        'items' => [
                            'Prometheus observability — Full metrics export for API latency, queue depth, error rates, and domain events',
                            'Mobile compatibility — Responsive layout fixes across dashboard, account, and GCU trading screens',
                            'Smoke test suite — Production environment smoke tests covering auth, payments, GCU, and API health checks',
                            'Security audit preparation — PHPStan Level 8 compliance, PHPCS clean, zero critical findings pre-audit',
                            'Helm chart — Kubernetes deployment chart with horizontal pod autoscaling and Redis Streams support',
                        ],
                    ],
                    [
                        'version' => 'v7.0.0',
                        'date' => 'March 28, 2026',
                        'label' => 'Production Release',
                        'label_color' => 'green',
                        'badge_color' => 'bg-green-100 text-green-700 border-green-200',
                        'dot_color' => 'bg-green-500',
                        'items' => [
                            'Web3 consolidation — Unified EthRpcClient and AbiEncoder under app/Infrastructure/Web3/ with legacy adapter shim',
                            'Zelta SDK v1.0 — Composer-installable payment SDK with transparent x402 + MPP auto-retry and fallback logic',
                            'Production guards — Demo-only service gates check app()->environment(\'production\') and throw safely',
                            'Post-quantum crypto — ML-KEM-768 and ML-DSA-65 hybrid encryption integrated into key storage and signing flows',
                            'Event sourcing v7.7+ — Domain-specific event tables, Spatie upgrade, full aggregate replay support',
                        ],
                    ],
                ];
            @endphp

            <div class="relative">
                <!-- Timeline line -->
                <div class="absolute left-6 top-0 bottom-0 w-px bg-slate-200 hidden sm:block"></div>

                <div class="space-y-16">
                    @foreach($releases as $release)
                    <div class="relative sm:pl-16">
                        <!-- Dot -->
                        <div class="absolute left-4 top-1 w-4 h-4 rounded-full border-2 border-white shadow-sm {{ $release['dot_color'] }} hidden sm:block"></div>

                        <!-- Header -->
                        <div class="flex flex-wrap items-center gap-3 mb-6">
                            <span class="font-display text-2xl font-bold text-slate-900">{{ $release['version'] }}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $release['badge_color'] }}">
                                {{ $release['label'] }}
                            </span>
                            <span class="text-sm text-slate-400">{{ $release['date'] }}</span>
                        </div>

                        <!-- Items -->
                        <div class="card-feature !p-6">
                            <ul class="space-y-3">
                                @foreach($release['items'] as $item)
                                <li class="flex items-start gap-3 text-sm text-slate-600">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ $item }}
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-slate-50 border-t border-slate-100 py-16">
        <div class="max-w-3xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-2xl font-bold text-slate-900 mb-4">Stay up to date</h2>
            <p class="text-slate-500 mb-8">
                Follow releases on GitHub or star the repository to get notified when new versions ship.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ config('brand.github_url') }}" target="_blank" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-fa-navy text-white rounded-lg hover:bg-opacity-90 transition font-semibold text-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    Star on GitHub
                </a>
                <a href="{{ route('platform') }}" class="inline-flex items-center justify-center px-6 py-3 border border-slate-300 text-slate-700 rounded-lg hover:border-slate-400 transition font-semibold text-sm">
                    View Platform
                </a>
            </div>
        </div>
    </section>

@endsection
