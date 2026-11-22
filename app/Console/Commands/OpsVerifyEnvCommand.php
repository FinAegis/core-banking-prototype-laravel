<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\CardIssuance\Providers\CardProviderSelector;
use App\Domain\Wallet\Services\Send\SolanaSponsorSigner;
use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use RuntimeException;

/**
 * ops:verify-env — deploy-time production preflight gate.
 *
 * Every production guard in this platform is lazy: IapReceiptPseudonymiser
 * hard-throws on the first IAP verify request, QuoteSigner refuses to sign the
 * first pricing quote, BridgeWebhookVerifier fails closed on the first ramp
 * webhook — all hours after the deploy looked green. This command front-loads
 * those guards into a single CI/CD gate that runs before traffic does.
 *
 * Exit semantics: exit 0 = safe to deploy, exit 1 = blocked. FAIL results only
 * block when the app environment is production (or when --strict is passed, so
 * staging pipelines can opt in). WARN never blocks; checks whose feature is
 * disabled report SKIP.
 *
 * The `switches` category lists the production switches for demo-only or
 * platform-billed surfaces (direct ledger funding, mock custodian, card
 * waitlist deposits, the investor page, generic gas sponsorship, SMS, the
 * MCP money and SMS tools, the trading engine) and runtime module and plugin
 * management by admins. Each WARNs while it is on or while its env var is
 * not set explicitly, so the operator sees the effective value. Two of them
 * block a production deploy while on, unless an acknowledgement confirms
 * them: direct ledger funding (LEDGER_DIRECT_FUNDING_ACKNOWLEDGED=true) and
 * MCP sms.send, which is billed to the platform
 * (MCP_TOOL_SMS_SEND_ACKNOWLEDGED=true). An explicit MCP_TOOL_*=true
 * (the env templates of v7.16.0 and earlier set the money tools to true)
 * overrides the production-off default, so the WARN says so; an MCP tool
 * that is on while the switch it needs (`requires_config`, e.g.
 * TRADING_ENABLED for exchange.trade) is off stays unavailable and WARNs
 * as inconsistent. API sign-ups (REGISTRATION_API_ENABLED) are a product
 * decision instead: either value passes, but leaving it unset FAILs,
 * because unset silently closes new sign-ups; with a cached config the
 * check reads registration.api_enabled_explicit. The switches are checked
 * whenever APP_ENV is not local, testing or demo (the environments where
 * they default to on), or with --strict.
 */
class OpsVerifyEnvCommand extends Command
{
    protected $signature = 'ops:verify-env
        {--json : Machine-readable output}
        {--strict : Treat FAIL results as blocking even outside production}';

    protected $description = 'Production preflight: verify env/config guards at deploy time instead of on the first customer request';

    private const PASS = 'PASS';

    private const FAIL = 'FAIL';

    private const WARN = 'WARN';

    private const SKIP = 'SKIP';

    private const CATEGORY_CORE = 'core';

    private const CATEGORY_SECRETS = 'secrets';

    private const CATEGORY_BYPASSES = 'bypasses';

    private const CATEGORY_CONDITIONAL = 'conditional';

    private const CATEGORY_FILES = 'files';

    private const CATEGORY_SWITCHES = 'switches';

    /**
     * Production switches: config key => [env var, what is live while on].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PRODUCTION_SWITCHES = [
        'ledger.direct_funding_enabled'      => ['LEDGER_DIRECT_FUNDING_ENABLED', 'the direct ledger funding endpoints (deposit/withdraw, BIAN, custodian transfer, reversal, ledger GraphQL mutations) move balances without a payment rail'],
        'custodians.connectors.mock.enabled' => ['CUSTODIAN_MOCK_ENABLED', 'the Mock Bank custodian connector is enabled in config (MockBankConnector::isPermitted() still never registers it in production)'],
        'cards.waitlist_deposit_enabled'     => ['CARDS_WAITLIST_DEPOSIT_ENABLED', 'POST /api/v1/cards/waitlist/deposit accepts card waitlist deposits'],
        'relayer.generic_sponsor_enabled'    => ['RELAYER_GENERIC_SPONSOR_ENABLED', 'REST /api/v1/relayer/sponsor and GraphQL sponsorTransaction spend platform gas'],
        'investor.page_enabled'              => ['INVESTOR_PAGE_ENABLED', 'the public /invest investor page and its lead form are served'],
        'sms.enabled'                        => ['SMS_ENABLED', 'POST /api/v1/sms/send and MCP sms.send send SMS billed to the platform'],
        'trading.enabled'                    => ['TRADING_ENABLED', 'the demo trading engine (order book, liquidity pools, GraphQL placeOrder, MCP exchange.trade) is live'],
        'modules.runtime_management_enabled' => ['ADMIN_RUNTIME_MANAGEMENT_ENABLED', 'admins can enable or disable modules and plugins at runtime (REST /api/v2/modules and /api/v2/plugins, GraphQL plugin mutations, admin panel), and a runtime module change overrides MODULES_DISABLED'],
    ];

    /**
     * Production switches that FAIL in production while on, unless the
     * operator confirms them: config key => [acknowledgement config key,
     * acknowledgement env var].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const ACKNOWLEDGED_SWITCHES = [
        'ledger.direct_funding_enabled' => ['ledger.direct_funding_acknowledged', 'LEDGER_DIRECT_FUNDING_ACKNOWLEDGED'],
        'mcp.tools.sms.send'            => ['mcp.sms_send_acknowledged', 'MCP_TOOL_SMS_SEND_ACKNOWLEDGED'],
    ];

    /**
     * Product switches: an explicit operator decision. Either value passes;
     * unset FAILs. Config key => [env var, effect while on, effect while off,
     * config key that records whether the env var was set (read when the
     * config is cached)].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const PRODUCT_SWITCHES = [
        'registration.api_enabled' => [
            'REGISTRATION_API_ENABLED',
            'new accounts can be created through the API, Privy and social sign-up',
            'new accounts cannot be created through the API, Privy or social sign-up (403 ERR_REG_001)',
            'registration.api_enabled_explicit',
        ],
    ];

    /**
     * MCP tools that move internal ledger balances or are billed to the
     * platform: tool name => [env var, what is live while on].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const MCP_MONEY_TOOLS = [
        'account.create'   => ['MCP_TOOL_ACCOUNT_CREATE', 'the MCP account.create tool moves internal ledger balances'],
        'payment.transfer' => ['MCP_TOOL_PAYMENT_TRANSFER', 'the MCP payment.transfer tool moves internal ledger balances'],
        'exchange.trade'   => ['MCP_TOOL_EXCHANGE_TRADE', 'the MCP exchange.trade tool moves internal ledger balances'],
        'sms.send'         => ['MCP_TOOL_SMS_SEND', 'the MCP sms.send tool sends SMS billed to the platform while SMS_ENABLED is on'],
    ];

    /** @var list<array{name: string, category: string, result: string, detail: string}> */
    private array $checks = [];

    public function handle(SolanaSponsorSigner $solanaSponsor): int
    {
        $this->checks = [];
        $isProduction = $this->laravel->environment('production');

        $this->checkCore($isProduction);
        $this->checkSecrets();
        $this->checkBypasses();
        $this->checkConditional($solanaSponsor);
        $this->checkLepoCardProvider();
        $this->checkFiles();
        $this->checkProductionSwitches();

        $failed = array_values(array_filter(
            $this->checks,
            static fn (array $check): bool => $check['result'] === self::FAIL,
        ));
        $blocking = $failed !== [] && ($isProduction || (bool) $this->option('strict'));

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'status' => $blocking ? 'fail' : 'pass',
                'checks' => $this->checks,
            ], JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderHuman($failed, $blocking);
        }

        return $blocking ? self::FAILURE : self::SUCCESS;
    }

    // ── Category: core ───────────────────────────────────────────────────

    private function checkCore(bool $isProduction): void
    {
        if ((bool) config('app.debug', false)) {
            $this->add(self::CATEGORY_CORE, 'app.debug', self::FAIL, 'APP_DEBUG=true leaks stack traces, config values and SQL to end users — set APP_DEBUG=false.');
        } else {
            $this->add(self::CATEGORY_CORE, 'app.debug', self::PASS);
        }

        if ($this->configString('app.key') === '') {
            $this->add(self::CATEGORY_CORE, 'app.key', self::FAIL, 'APP_KEY is empty — encryption and signed cookies are broken. Generate with `php artisan key:generate`.');
        } else {
            $this->add(self::CATEGORY_CORE, 'app.key', self::PASS);
        }

        if ($isProduction) {
            $this->add(self::CATEGORY_CORE, 'app.env', self::PASS, 'APP_ENV=production');
        } else {
            $this->add(self::CATEGORY_CORE, 'app.env', self::WARN, sprintf(
                'APP_ENV=%s — expected production. FAIL results are non-blocking here unless --strict is passed.',
                $this->environmentName(),
            ));
        }
    }

    // ── Category: secrets ────────────────────────────────────────────────

    private function checkSecrets(): void
    {
        $this->requireNonEmpty(
            self::CATEGORY_SECRETS,
            'subscription.iap.receipt_pepper',
            'IAP_RECEIPT_PEPPER is empty — IapReceiptPseudonymiser hard-throws on the first /api/v1/subscription/iap/verify request. Generate with `openssl rand -hex 32` (one-way: never rotate).',
        );

        $this->requireNonEmpty(
            self::CATEGORY_SECRETS,
            'services.stripe.trial_fingerprint_pepper',
            'TRIAL_FINGERPRINT_PEPPER is empty — trial-card fingerprint hashing fails on the first trial signup. Generate with `openssl rand -hex 32`.',
        );

        $this->requireNonEmpty(
            self::CATEGORY_SECRETS,
            'services.pricing.quote_pepper',
            'PRICING_QUOTE_PEPPER is empty — QuoteSigner refuses to sign on the first pricing quote. Generate with `openssl rand -hex 32`.',
        );

        $this->requireNonEmpty(
            self::CATEGORY_SECRETS,
            'mobile.biometric_jwt.secret',
            'BIOMETRIC_JWT_SECRET is empty — biometric JWT signing fails on the first mobile biometric login. Set a random secret of at least 32 bytes.',
        );
    }

    // ── Category: bypasses ───────────────────────────────────────────────

    private function checkBypasses(): void
    {
        if ((bool) config('subscription.iap.apple_jws_verification_bypass', false)) {
            $this->add(self::CATEGORY_BYPASSES, 'subscription.iap.apple_jws_verification_bypass', self::FAIL, 'APPLE_JWS_VERIFICATION_BYPASS=true disables Apple receipt chain validation — any authenticated user can forge a Pro subscription. Staging-only; must be false in production.');
        } else {
            $this->add(self::CATEGORY_BYPASSES, 'subscription.iap.apple_jws_verification_bypass', self::PASS);
        }

        $this->checkBridgeWebhookCredentials();
        $this->checkOndatoWebhookSecret();

        $this->requireFalse(self::CATEGORY_BYPASSES, 'demo.mode', 'demo.mode is true — the DemoMode middleware treats the whole app as a demo (blocked webhooks, demo banner). Must be off in production.');
        $this->requireFalse(self::CATEGORY_BYPASSES, 'demo.sandbox.enabled', 'SANDBOX_MODE=true routes payments through SandboxPaymentService instead of the production rail. Must be false in production.');
        $this->checkDemoFeatureToggles();

        $this->requireFalse(self::CATEGORY_BYPASSES, 'keymanagement.demo_mode', 'KEY_MANAGEMENT_DEMO_MODE resolves to true (config default is true) — a simulated HSM signs in place of the real cloud HSM. Set KEY_MANAGEMENT_DEMO_MODE=false explicitly.');
        $this->requireFalse(self::CATEGORY_BYPASSES, 'regtech.demo_mode', 'REGTECH_DEMO_MODE resolves to true (config default is true) — regulatory filings/screening run in demo mode. Set REGTECH_DEMO_MODE=false explicitly.');
        $this->requireFalse(self::CATEGORY_BYPASSES, 'ai.demo_mode', 'AI_DEMO_MODE resolves to true (config default is true) — AI services return canned demo output. Set AI_DEMO_MODE=false explicitly.');
        $this->requireFalse(self::CATEGORY_BYPASSES, 'compliance-certification.soc2.demo_mode', 'SOC2_DEMO_MODE=true — the SOC 2 evidence endpoint returns fabricated audit data stamped as production (collectEvidence() also throws in prod). Set SOC2_DEMO_MODE=false.');
    }

    private function checkBridgeWebhookCredentials(): void
    {
        $routing = config('kyc.routing');
        $routesToBridge = is_array($routing) && in_array('bridge', $routing, true);

        if (! $routesToBridge) {
            $this->add(self::CATEGORY_BYPASSES, 'kyc.providers.bridge', self::SKIP, 'No KYC purpose routes to the bridge provider (kyc.routing).');

            return;
        }

        $missing = [];

        if ($this->configString('kyc.providers.bridge.api_key') === '') {
            $missing[] = 'BRIDGE_API_KEY';
        }

        if (
            $this->configString('kyc.providers.bridge.webhook_public_key') === ''
            && $this->configString('kyc.providers.bridge.webhook_secret') === ''
        ) {
            $missing[] = 'BRIDGE_WEBHOOK_PUBLIC_KEY (or legacy BRIDGE_WEBHOOK_SECRET)';
        }

        if ($missing !== []) {
            $this->add(self::CATEGORY_BYPASSES, 'kyc.providers.bridge', self::FAIL, sprintf(
                'Bridge is the active ramp/KYC provider but %s empty. With no webhook credential BridgeWebhookVerifier rejects every webhook (401) in production — KYC approvals and ramp activity never land.',
                implode(' and ', $missing) . (count($missing) > 1 ? ' are' : ' is'),
            ));

            return;
        }

        $this->add(self::CATEGORY_BYPASSES, 'kyc.providers.bridge', self::PASS);
    }

    /**
     * Outside local and testing, OndatoService refuses every webhook without
     * a valid signature, so a configured Ondato needs its webhook secret or
     * no KYC result ever lands.
     */
    private function checkOndatoWebhookSecret(): void
    {
        $configured = $this->configString('services.ondato.application_id') !== ''
            || $this->configString('services.ondato.secret') !== '';

        if (! $configured) {
            $this->add(self::CATEGORY_BYPASSES, 'services.ondato.webhook_secret', self::SKIP, 'Ondato is not configured (ONDATO_APPLICATION_ID and ONDATO_SECRET are empty).');

            return;
        }

        $this->requireNonEmpty(
            self::CATEGORY_BYPASSES,
            'services.ondato.webhook_secret',
            'Ondato is configured but ONDATO_WEBHOOK_SECRET is empty. Outside local and testing every Ondato webhook is refused (401), so KYC and TrustCert results never arrive. Set it to the webhook secret from the Ondato dashboard.',
        );
    }

    private function checkDemoFeatureToggles(): void
    {
        $features = config('demo.features');
        $enabled = [];

        if (is_array($features)) {
            foreach ($features as $feature => $value) {
                if ((bool) $value) {
                    $enabled[] = (string) $feature;
                }
            }
        }

        if ($enabled !== []) {
            $this->add(self::CATEGORY_BYPASSES, 'demo.features', self::FAIL, sprintf(
                'Demo feature toggles enabled: %s. These default to true — set DEMO_INSTANT_DEPOSITS, DEMO_SKIP_KYC, DEMO_MOCK_EXTERNAL_APIS, DEMO_FIXED_EXCHANGE_RATES and DEMO_AUTO_APPROVE to false in production.',
                implode(', ', $enabled),
            ));

            return;
        }

        $this->add(self::CATEGORY_BYPASSES, 'demo.features', self::PASS);
    }

    // ── Category: conditional ────────────────────────────────────────────

    /**
     * Lepo Finance (virtual cards). In use when it is the default card issuer
     * or an API key is set. Without the key every card call fails; without the
     * webhook secret every Lepo webhook is refused outside local and testing,
     * so identity, card and funding updates never arrive.
     */
    private function checkLepoCardProvider(): void
    {
        $enabled = in_array('lepo', CardProviderSelector::listFromConfig('cardissuance.providers.enabled'), true);
        $inUse = $enabled
            || $this->configString('cardissuance.default_issuer') === 'lepo'
            || $this->configString('cardissuance.issuers.lepo.api_key') !== '';

        if (! $inUse) {
            $this->add(self::CATEGORY_CONDITIONAL, 'cards.lepo', self::SKIP, 'Lepo is not an enabled card provider (CARD_PROVIDERS_ENABLED) nor the card issuer (CARD_ISSUER), and LEPO_API_KEY is empty.');

            return;
        }

        $missing = [];
        if ($this->configString('cardissuance.issuers.lepo.api_key') === '') {
            $missing[] = 'LEPO_API_KEY';
        }

        if ($this->configString('cardissuance.issuers.lepo.webhook_secret') === '') {
            $missing[] = 'LEPO_WEBHOOK_SECRET';
        }

        if ($missing !== []) {
            $this->add(self::CATEGORY_CONDITIONAL, 'cards.lepo', self::FAIL, sprintf(
                'Lepo is in use but %s %s empty. Card calls fail without the API key, and every Lepo webhook is refused (401) without the webhook secret. Set them from the Lepo partner portal (API keys, Endpoints).',
                implode(' and ', $missing),
                count($missing) === 1 ? 'is' : 'are',
            ));

            return;
        }

        if ($this->configString('cardissuance.issuers.lepo.card_type_id_virtual') === '') {
            $this->add(self::CATEGORY_CONDITIONAL, 'cards.lepo', self::WARN, 'LEPO_CARD_TYPE_ID_VIRTUAL is empty, so virtual cards cannot be opened. Set it to the consumer virtual card type of the Lepo tier.');

            return;
        }

        $emptyPilot = $enabled
            && (bool) config('cardissuance.providers.lepo_pilot_only', true)
            && CardProviderSelector::listFromConfig('cardissuance.providers.lepo_pilot_users') === [];

        if ($emptyPilot) {
            $this->add(self::CATEGORY_CONDITIONAL, 'cards.lepo', self::WARN, 'Lepo cards are on in pilot mode but CARD_PROVIDER_LEPO_PILOT_USERS is empty, so nobody can get a card. Add the pilot users or set CARD_PROVIDER_LEPO_PILOT_ONLY=false.');

            return;
        }

        $this->add(self::CATEGORY_CONDITIONAL, 'cards.lepo', self::PASS);
    }

    private function checkConditional(SolanaSponsorSigner $solanaSponsor): void
    {
        if ((bool) config('hyperswitch.enabled', false)) {
            $missing = [];

            if ($this->configString('hyperswitch.api_key') === '') {
                $missing[] = 'HYPERSWITCH_API_KEY';
            }

            if ($this->configString('hyperswitch.webhook_secret') === '') {
                $missing[] = 'HYPERSWITCH_WEBHOOK_SECRET';
            }

            if ($missing !== []) {
                $this->add(self::CATEGORY_CONDITIONAL, 'hyperswitch.credentials', self::FAIL, sprintf(
                    'HYPERSWITCH_ENABLED=true but %s empty — card deposits route to HyperSwitch and either fail at intent creation or webhook credits are rejected as unverifiable.',
                    implode(' and ', $missing) . (count($missing) > 1 ? ' are' : ' is'),
                ));
            } else {
                $this->add(self::CATEGORY_CONDITIONAL, 'hyperswitch.credentials', self::PASS);
            }
        } else {
            $this->add(self::CATEGORY_CONDITIONAL, 'hyperswitch.credentials', self::SKIP, 'HYPERSWITCH_ENABLED=false — Stripe remains the deposit rail.');
        }

        if (config('cardissuance.default_issuer') === 'fincard') {
            $required = [
                'FINCARD_TENANT_ID'          => 'cardissuance.issuers.fincard.tenant_id',
                'FINCARD_USERNAME'           => 'cardissuance.issuers.fincard.username',
                'FINCARD_PASSWORD'           => 'cardissuance.issuers.fincard.password',
                'FINCARD_WEBHOOK_PUBLIC_KEY' => 'cardissuance.issuers.fincard.webhook_public_key',
            ];
            $missing = [];
            foreach ($required as $env => $key) {
                if ($this->configString($key) === '') {
                    $missing[] = $env;
                }
            }

            if ($missing !== []) {
                $this->add(self::CATEGORY_CONDITIONAL, 'fincard.credentials', self::FAIL, sprintf(
                    'CARD_ISSUER=fincard but %s empty — the card issuer cannot authenticate to FinCard%s.',
                    implode(', ', $missing),
                    in_array('FINCARD_WEBHOOK_PUBLIC_KEY', $missing, true)
                        ? ' and inbound webhooks would be rejected as unverifiable'
                        : '',
                ));
            } else {
                $this->add(self::CATEGORY_CONDITIONAL, 'fincard.credentials', self::PASS);
            }
        } else {
            $this->add(self::CATEGORY_CONDITIONAL, 'fincard.credentials', self::SKIP, 'CARD_ISSUER is not fincard.');
        }

        if ($solanaSponsor->isEnabled()) {
            try {
                $address = $solanaSponsor->publicKeyBase58();
                $this->add(self::CATEGORY_CONDITIONAL, 'wallet.solana.sponsor.secret_key', self::PASS, sprintf('Sponsor fee-payer address %s.', $address));
            } catch (RuntimeException $e) {
                $this->add(self::CATEGORY_CONDITIONAL, 'wallet.solana.sponsor.secret_key', self::FAIL, $e->getMessage() . ' Every sponsored Solana send would fail at signing time.');
            }
        } else {
            $this->add(self::CATEGORY_CONDITIONAL, 'wallet.solana.sponsor.secret_key', self::SKIP, 'WALLET_SOLANA_SPONSOR_SECRET_KEY not set — Solana sends fall back to sender-pays-fee.');
        }

        if ($this->configString('services.helius.api_key') === '') {
            $this->add(self::CATEGORY_CONDITIONAL, 'services.helius.api_key', self::WARN, 'HELIUS_API_KEY is empty — Solana webhook sync, balance lookups and transaction backfill will fail if the Solana wallet surface is live.');
        } else {
            $this->add(self::CATEGORY_CONDITIONAL, 'services.helius.api_key', self::PASS);
        }

        if ((bool) config('mobile.attestation.enabled', false)) {
            $missing = [];
            $required = [
                'mobile.attestation.apple.team_id'           => 'APPLE_TEAM_ID',
                'mobile.attestation.apple.bundle_id'         => 'APPLE_BUNDLE_ID',
                'mobile.attestation.google.package_name'     => 'GOOGLE_PACKAGE_NAME',
                'mobile.attestation.google.decryption_key'   => 'GOOGLE_INTEGRITY_DECRYPTION_KEY',
                'mobile.attestation.google.verification_key' => 'GOOGLE_INTEGRITY_VERIFICATION_KEY',
            ];

            foreach ($required as $configKey => $envName) {
                if ($this->configString($configKey) === '') {
                    $missing[] = $envName;
                }
            }

            if ($missing !== []) {
                $this->add(self::CATEGORY_CONDITIONAL, 'mobile.attestation', self::FAIL, sprintf(
                    'MOBILE_ATTESTATION_ENABLED=true but missing: %s — device attestation rejects every client.',
                    implode(', ', $missing),
                ));
            } else {
                $this->add(self::CATEGORY_CONDITIONAL, 'mobile.attestation', self::PASS);
            }
        } else {
            $this->add(self::CATEGORY_CONDITIONAL, 'mobile.attestation', self::SKIP, 'MOBILE_ATTESTATION_ENABLED=false.');
        }

        $this->checkBackupDestination();

        if ((bool) config('privy.web_login_enabled', false)) {
            $missing = [];
            $required = [
                'privy.app_id'     => 'PRIVY_APP_ID',
                'privy.app_secret' => 'PRIVY_APP_SECRET',
                'privy.jwks_url'   => 'PRIVY_JWKS_URL',
            ];

            foreach ($required as $configKey => $envName) {
                if ($this->configString($configKey) === '') {
                    $missing[] = $envName;
                }
            }

            if ($missing !== []) {
                $this->add(self::CATEGORY_CONDITIONAL, 'privy.web_login', self::FAIL, sprintf(
                    'MCP_WEB_PRIVY_LOGIN=true but missing: %s — the web /login surface 500s on the first OTP request.',
                    implode(', ', $missing),
                ));
            } else {
                $this->add(self::CATEGORY_CONDITIONAL, 'privy.web_login', self::PASS);
            }
        } else {
            $this->add(self::CATEGORY_CONDITIONAL, 'privy.web_login', self::SKIP, 'MCP_WEB_PRIVY_LOGIN=false — legacy Jetstream login in use.');
        }

        $this->checkPrivacyProviders();
        $this->checkPimlicoBundlerUrl();
        $this->checkWalletOwnershipVerification();
    }

    /**
     * PIMLICO_BUNDLER_URL is optional: empty, EthRpcClient derives the
     * per-chain endpoint. A pinned URL serves one chain only, so it misroutes
     * every other network's bundler calls when several EVM networks are on.
     */
    private function checkPimlicoBundlerUrl(): void
    {
        if ($this->configString('relayer.pimlico.bundler_url') === '') {
            $this->add(self::CATEGORY_CONDITIONAL, 'relayer.pimlico.bundler_url', self::PASS, 'PIMLICO_BUNDLER_URL is empty — the bundler URL is derived per chain.');

            return;
        }

        $networks = config('wallet.evm.enabled_networks');
        $networks = is_array($networks)
            ? array_values(array_unique(array_filter(array_map(
                static fn (mixed $network): string => is_scalar($network) ? strtolower(trim((string) $network)) : '',
                $networks,
            ))))
            : [];

        if (count($networks) > 1) {
            $this->add(self::CATEGORY_CONDITIONAL, 'relayer.pimlico.bundler_url', self::WARN, sprintf(
                'PIMLICO_BUNDLER_URL is set while %d EVM networks are enabled (WALLET_SEND_EVM_NETWORKS=%s) — a pinned bundler URL serves one chain, so every other network\'s UserOps go to the wrong chain. Leave PIMLICO_BUNDLER_URL empty.',
                count($networks),
                implode(',', $networks),
            ));

            return;
        }

        $this->add(self::CATEGORY_CONDITIONAL, 'relayer.pimlico.bundler_url', self::PASS, 'PIMLICO_BUNDLER_URL is pinned and at most one EVM network is enabled.');
    }

    /**
     * POST /api/v1/wallet/addresses checks each new address against the
     * caller's Privy wallets. `monitor` (the release default) and `off` only
     * log a mismatch, so production WARNs until the logs show real users pass
     * and the operator switches to `enforce`. `enforce` needs the Privy server
     * credentials, otherwise every new registration fails closed (503).
     */
    private function checkWalletOwnershipVerification(): void
    {
        $name = 'wallet.address_registration.ownership_verification';
        $mode = strtolower($this->configString($name));
        $mode = in_array($mode, ['enforce', 'monitor', 'off'], true) ? $mode : 'monitor';

        if ($mode === 'enforce') {
            $missing = [];

            foreach (['privy.app_id' => 'PRIVY_APP_ID', 'privy.app_secret' => 'PRIVY_APP_SECRET'] as $configKey => $envName) {
                if ($this->configString($configKey) === '') {
                    $missing[] = $envName;
                }
            }

            if ($missing !== []) {
                $this->add(self::CATEGORY_CONDITIONAL, $name, self::FAIL, sprintf(
                    'WALLET_ADDRESS_OWNERSHIP_VERIFICATION=enforce but missing: %s. Every new wallet address registration fails closed (503 ADDRESS_VERIFICATION_UNAVAILABLE).',
                    implode(', ', $missing),
                ));

                return;
            }

            $this->add(self::CATEGORY_CONDITIONAL, $name, self::PASS, 'WALLET_ADDRESS_OWNERSHIP_VERIFICATION=enforce.');

            return;
        }

        if (! $this->laravel->environment('production')) {
            $this->add(self::CATEGORY_CONDITIONAL, $name, self::SKIP, sprintf('WALLET_ADDRESS_OWNERSHIP_VERIFICATION=%s (checked in production only).', $mode));

            return;
        }

        $this->add(self::CATEGORY_CONDITIONAL, $name, self::WARN, sprintf(
            'WALLET_ADDRESS_OWNERSHIP_VERIFICATION=%s: POST /api/v1/wallet/addresses does not refuse an address that is not one of the caller\'s Privy wallets; mismatches are only logged ("wallet.addresses: submitted address is not a wallet of the caller\'s Privy account"). Switch to enforce once those logs show that real users pass.',
            $mode,
        ));
    }

    /**
     * RAILGUN privacy provider consistency.
     *
     * Two independent toggles drive the privacy stack: ZK_PROVIDER (gates
     * shield/unshield/transfer/balances via isRailgunMode) and MERKLE_PROVIDER
     * (gates the Merkle service binding). Both default to 'demo'. A deploy that
     * sets one to 'railgun' but not the other — or enables railgun mode without
     * RAILGUN_BRIDGE_SECRET — silently serves a mix of real and demo data with
     * no runtime error (the worst kind of misconfig: it looks like it works).
     * Front-load that into the deploy gate.
     */
    private function checkPrivacyProviders(): void
    {
        $zk = $this->configString('privacy.zk.provider');
        $merkle = $this->configString('privacy.merkle.provider');

        $zkRailgun = $zk === 'railgun';
        $merkleRailgun = $merkle === 'railgun';

        // Custody-neutral in production (Wave 0B): ZK_PROVIDER=railgun enables the
        // CUSTODIAL money path (isRailgunMode → server-side seed derived from
        // app.key). Forbidden in production regardless of the rest of the stack —
        // privacy is non-custodial / on-device, and the custodial shield/unshield/
        // transfer/viewing-key endpoints hard-return 501 in prod.
        if ($zkRailgun && ($this->laravel->environment('production') || (bool) $this->option('strict'))) {
            $this->add(self::CATEGORY_CONDITIONAL, 'privacy.railgun.custody', self::FAIL, 'ZK_PROVIDER=railgun enables the custodial RAILGUN path (server-side seed derived from app.key) — forbidden in production. Set ZK_PROVIDER=demo. Privacy is non-custodial/on-device; the custodial shield/unshield/transfer/viewing-key endpoints hard-return 501 in prod regardless.');

            return;
        }

        if (! $zkRailgun && ! $merkleRailgun) {
            $this->add(self::CATEGORY_CONDITIONAL, 'privacy.railgun.providers', self::SKIP, sprintf(
                'Privacy stack in demo mode (ZK_PROVIDER=%s, MERKLE_PROVIDER=%s) — no real RAILGUN privacy.',
                $zk !== '' ? $zk : 'demo',
                $merkle !== '' ? $merkle : 'demo',
            ));

            return;
        }

        if ($zkRailgun !== $merkleRailgun) {
            $this->add(self::CATEGORY_CONDITIONAL, 'privacy.railgun.providers', self::FAIL, sprintf(
                'Inconsistent privacy providers: ZK_PROVIDER=%s but MERKLE_PROVIDER=%s. RAILGUN mode is gated on ZK_PROVIDER while Merkle ops use MERKLE_PROVIDER — a half-railgun deploy mixes real and demo data silently. Set both to railgun (or both off).',
                $zk !== '' ? $zk : 'demo',
                $merkle !== '' ? $merkle : 'demo',
            ));

            return;
        }

        if ($this->configString('privacy.railgun.bridge_secret') === '') {
            $this->add(
                self::CATEGORY_CONDITIONAL,
                'privacy.railgun.providers',
                self::FAIL,
                'RAILGUN mode is on (ZK_PROVIDER=railgun, MERKLE_PROVIDER=railgun) but RAILGUN_BRIDGE_SECRET is empty — the bridge bearer auth is unprotected and a bridge outage falls back to demo responses with no error.',
            );

            return;
        }

        $this->add(self::CATEGORY_CONDITIONAL, 'privacy.railgun.providers', self::PASS, 'ZK_PROVIDER=railgun + MERKLE_PROVIDER=railgun with a bridge secret set.');
    }

    /**
     * Backup destination sanity. Cheap config checks only: the nightly
     * `backup:run --only-db` job (routes/console.php) writes to the disks in
     * backup.backup.destination.disks. A local-ONLY destination in production
     * FAILs the gate (dumps die with the box — no offsite copy); other issues
     * (empty/undefined disk) WARN. No live S3 call — that's the restore drill.
     */
    private function checkBackupDestination(): void
    {
        $disks = config('backup.backup.destination.disks');

        if (! is_array($disks) || $disks === []) {
            $this->add(self::CATEGORY_CONDITIONAL, 'backup.destination', self::WARN, 'No backup destination disk configured (backup.backup.destination.disks is empty) — the nightly backup:run job has nowhere to write. Set BACKUP_DISK.');

            return;
        }

        $undefined = [];

        foreach ($disks as $disk) {
            if (! is_string($disk) || $disk === '' || ! is_array(config('filesystems.disks.' . $disk))) {
                $undefined[] = is_string($disk) ? $disk : gettype($disk);
            }
        }

        if ($undefined !== []) {
            $this->add(self::CATEGORY_CONDITIONAL, 'backup.destination', self::WARN, sprintf(
                'Backup destination disk(s) %s not defined in config/filesystems.php — the nightly backup:run job will fail at 01:30 UTC.',
                implode(', ', $undefined),
            ));

            return;
        }

        if ($disks === ['local']) {
            // Local-only dumps die with the box (no offsite copy): block a
            // production deploy; warn (non-blocking) outside production.
            $result = ($this->laravel->environment('production') || (bool) $this->option('strict'))
                ? self::FAIL
                : self::WARN;
            $this->add(self::CATEGORY_CONDITIONAL, 'backup.destination', $result, 'Backup destination is the local disk only — database dumps never leave the box. Set BACKUP_DISK=s3 (+ bucket credentials) in production and run one restore drill.');

            return;
        }

        $this->add(self::CATEGORY_CONDITIONAL, 'backup.destination', self::PASS, sprintf(
            'Nightly DB backup writes to disk(s): %s.',
            implode(', ', array_map('strval', $disks)),
        ));
    }

    // ── Category: files ──────────────────────────────────────────────────

    private function checkFiles(): void
    {
        $path = $this->configString('subscription.iap.apple.root_ca_path');

        if ($path === '') {
            $this->add(self::CATEGORY_FILES, 'subscription.iap.apple.root_ca', self::SKIP, 'subscription.iap.apple.root_ca_path is empty — Apple IAP not configured.');

            return;
        }

        if (! is_file($path)) {
            $this->add(self::CATEGORY_FILES, 'subscription.iap.apple.root_ca', self::FAIL, sprintf(
                'Apple Root CA missing at %s — Apple JWS chain validation fails on every IAP receipt and store notification.',
                $path,
            ));

            return;
        }

        $actual = hash_file('sha256', $path);

        if ($actual === false) {
            $this->add(self::CATEGORY_FILES, 'subscription.iap.apple.root_ca', self::FAIL, sprintf('Could not read %s to compute its sha256 fingerprint.', $path));

            return;
        }

        $pinned = $this->pinnedAppleFingerprints();

        if ($pinned === []) {
            $this->add(self::CATEGORY_FILES, 'subscription.iap.apple.root_ca', self::FAIL, 'subscription.iap.apple.root_ca_fingerprints is empty — nothing to pin the Apple root certificate against.');

            return;
        }

        if (! in_array(strtolower($actual), $pinned, true)) {
            $this->add(self::CATEGORY_FILES, 'subscription.iap.apple.root_ca', self::FAIL, sprintf(
                'sha256(%s) = %s does not match any pinned fingerprint (%s) — the bundled certificate was replaced or corrupted.',
                $path,
                $actual,
                implode(', ', $pinned),
            ));

            return;
        }

        $this->add(self::CATEGORY_FILES, 'subscription.iap.apple.root_ca', self::PASS, 'Bundled Apple Root CA matches the pinned sha256 fingerprint.');
    }

    /**
     * Pinned Apple root fingerprints, lowercased with colon separators removed.
     *
     * @return list<string>
     */
    private function pinnedAppleFingerprints(): array
    {
        $configured = config('subscription.iap.apple.root_ca_fingerprints');

        if (! is_array($configured)) {
            return [];
        }

        $normalized = [];

        foreach ($configured as $fingerprint) {
            if (! is_string($fingerprint)) {
                continue;
            }

            $fingerprint = strtolower(str_replace(':', '', trim($fingerprint)));

            if ($fingerprint !== '') {
                $normalized[] = $fingerprint;
            }
        }

        return $normalized;
    }

    // ── Category: switches ───────────────────────────────────────────────

    /**
     * Production switches. WARN while a switch is on, or while its env var
     * is not set explicitly (ACKNOWLEDGED_SWITCHES FAIL in production while
     * on and not acknowledged); product switches FAIL only while unset. Checked for every APP_ENV except local, testing and demo (which
     * run with these on), or with --strict; SKIP otherwise.
     */
    private function checkProductionSwitches(): void
    {
        // The switches default to on only in local, testing and demo; every
        // other environment gets the production defaults, so it is checked.
        $applies = (bool) $this->option('strict') || ! $this->laravel->environment(['local', 'testing', 'demo']);
        $skipped = 'Not checked for APP_ENV=local, testing or demo (pass --strict to check).';

        foreach (self::PRODUCTION_SWITCHES as $configKey => [$envName, $whenOn]) {
            if (! $applies) {
                $this->add(self::CATEGORY_SWITCHES, $configKey, self::SKIP, $skipped);

                continue;
            }

            $this->checkSwitch($configKey, $envName, config($configKey), $whenOn);
        }

        foreach (self::PRODUCT_SWITCHES as $configKey => [$envName, $whenOn, $whenOff, $explicitKey]) {
            if (! $applies) {
                $this->add(self::CATEGORY_SWITCHES, $configKey, self::SKIP, $skipped);

                continue;
            }

            $explicit = $this->envIsSet($envName) ?? (bool) config($explicitKey, false);

            $this->checkProductSwitch($configKey, $envName, (bool) config($configKey), $explicit, $whenOn, $whenOff);
        }

        $tools = config('mcp.tools');
        $tools = is_array($tools) ? $tools : [];

        foreach (self::MCP_MONEY_TOOLS as $tool => [$envName, $whenOn]) {
            $name = 'mcp.tools.' . $tool;

            if (! $applies) {
                $this->add(self::CATEGORY_SWITCHES, $name, self::SKIP, $skipped);

                continue;
            }

            $entry = $tools[$tool] ?? null;
            $enabled = is_array($entry) ? ($entry['enabled'] ?? null) : null;
            $requires = is_array($entry) ? ($entry['requires_config'] ?? null) : null;

            if ((bool) $enabled && is_string($requires) && $requires !== '' && ! (bool) config($requires)) {
                $this->add(self::CATEGORY_SWITCHES, $name, self::WARN, sprintf(
                    '%s is ON, but the tool stays unavailable while %s is off. Env templates of v7.16.0 and earlier set it to true, and an explicit value overrides the production-off default. Set %s=false.',
                    $envName,
                    $requires,
                    $envName,
                ));

                continue;
            }

            $this->checkSwitch($name, $envName, $enabled, $whenOn, ' Env templates of v7.16.0 and earlier set it to true, and an explicit value overrides the production-off default.');
        }
    }

    private function checkSwitch(string $name, string $envName, mixed $value, string $whenOn, string $whenOnNote = ''): void
    {
        if ($value === null) {
            $this->add(self::CATEGORY_SWITCHES, $name, self::WARN, sprintf('%s is not configured (%s unset). Set %s=false explicitly unless %s is intended.', $name, $envName, $envName, $whenOn));

            return;
        }

        if ((bool) $value) {
            [$ackKey, $ackEnv] = self::ACKNOWLEDGED_SWITCHES[$name] ?? [null, null];

            if ($ackKey !== null && $this->laravel->environment('production') && config($ackKey) !== true) {
                $this->add(self::CATEGORY_SWITCHES, $name, self::FAIL, sprintf('%s is ON in production: %s. Set %s=false, or set %s=true to confirm that this is intended.', $envName, $whenOn, $envName, $ackEnv));

                return;
            }

            $this->add(self::CATEGORY_SWITCHES, $name, self::WARN, sprintf('%s is ON: %s. Set %s=false unless this is intended.%s', $envName, $whenOn, $envName, $whenOnNote));

            return;
        }

        $explicit = $this->envIsSet($envName);

        if ($explicit === false) {
            $this->add(self::CATEGORY_SWITCHES, $name, self::WARN, sprintf('%s is not set — it resolves to off (secure default). Set %s=false (or true) explicitly so the deployed value is visible.', $envName, $envName));

            return;
        }

        $this->add(self::CATEGORY_SWITCHES, $name, self::PASS, $explicit === null
            ? sprintf('%s resolves to off (config is cached; explicit setting not checked).', $envName)
            : sprintf('%s=false.', $envName));
    }

    /**
     * A product switch must be set explicitly: either value passes, unset
     * FAILs (it would silently resolve to the secure default, off). With a
     * cached config, whether it was set comes from the config itself.
     */
    private function checkProductSwitch(string $name, string $envName, bool $value, bool $explicit, string $whenOn, string $whenOff): void
    {
        if (! $explicit) {
            $this->add(self::CATEGORY_SWITCHES, $name, self::FAIL, sprintf('%s is not set, so it resolves to off: %s. Set %s=true or %s=false explicitly.', $envName, $whenOff, $envName, $envName));

            return;
        }

        $effect = ucfirst($value ? $whenOn : $whenOff) . '.';

        $this->add(self::CATEGORY_SWITCHES, $name, self::PASS, sprintf('%s=%s. %s', $envName, $value ? 'true' : 'false', $effect));
    }

    /**
     * Whether the env var is set explicitly, or null when that cannot be told
     * (with a cached config the .env file is not loaded).
     */
    private function envIsSet(string $name): ?bool
    {
        if ($this->laravel instanceof Application && $this->laravel->configurationIsCached()) {
            return null;
        }

        return Env::get($name) !== null;
    }

    // ── Plumbing ─────────────────────────────────────────────────────────

    private function requireNonEmpty(string $category, string $configKey, string $failDetail): void
    {
        if ($this->configString($configKey) === '') {
            $this->add($category, $configKey, self::FAIL, $failDetail);

            return;
        }

        $this->add($category, $configKey, self::PASS);
    }

    private function requireFalse(string $category, string $configKey, string $failDetail): void
    {
        if ((bool) config($configKey, false)) {
            $this->add($category, $configKey, self::FAIL, $failDetail);

            return;
        }

        $this->add($category, $configKey, self::PASS);
    }

    private function configString(string $key): string
    {
        $value = config($key);

        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function environmentName(): string
    {
        $environment = $this->laravel->environment();

        return is_string($environment) ? $environment : 'unknown';
    }

    private function add(string $category, string $name, string $result, string $detail = ''): void
    {
        $this->checks[] = [
            'name'     => $name,
            'category' => $category,
            'result'   => $result,
            'detail'   => $detail,
        ];
    }

    /**
     * @param list<array{name: string, category: string, result: string, detail: string}> $failed
     */
    private function renderHuman(array $failed, bool $blocking): void
    {
        $counts = [self::PASS => 0, self::FAIL => 0, self::WARN => 0, self::SKIP => 0];

        foreach ($this->checks as $check) {
            $counts[$check['result']]++;

            $line = sprintf('[%s] %-12s %s', $check['result'], $check['category'], $check['name']);

            if ($check['detail'] !== '') {
                $line .= ' — ' . $check['detail'];
            }

            if ($check['result'] === self::FAIL) {
                $this->error($line);
            } elseif ($check['result'] === self::WARN) {
                $this->warn($line);
            } else {
                $this->line($line);
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '%d passed, %d failed, %d warnings, %d skipped.',
            $counts[self::PASS],
            $counts[self::FAIL],
            $counts[self::WARN],
            $counts[self::SKIP],
        ));

        if ($blocking) {
            $this->error('ops:verify-env FAILED — deploy blocked. Fix the FAIL results above before shipping.');
        } elseif ($failed !== []) {
            $this->warn(sprintf(
                '%d FAIL result(s) present but non-blocking in the "%s" environment — pass --strict to block.',
                count($failed),
                $this->environmentName(),
            ));
        } else {
            $this->info('ops:verify-env: all preflight checks passed.');
        }
    }
}
