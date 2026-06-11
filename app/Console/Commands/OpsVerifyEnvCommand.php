<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Wallet\Services\Send\SolanaSponsorSigner;
use Illuminate\Console\Command;
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
        $this->checkFiles();

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
            'IAP_RECEIPT_PEPPER is empty — IapReceiptPseudonymiser hard-throws on the first /subscriptions/iap/verify request. Generate with `openssl rand -hex 32` (one-way: never rotate).',
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

        $this->requireFalse(self::CATEGORY_BYPASSES, 'demo.mode', 'demo.mode is true — the DemoMode middleware treats the whole app as a demo (blocked webhooks, demo banner). Must be off in production.');
        $this->requireFalse(self::CATEGORY_BYPASSES, 'demo.sandbox.enabled', 'SANDBOX_MODE=true routes payments through SandboxPaymentService instead of the production rail. Must be false in production.');
        $this->checkDemoFeatureToggles();

        $this->requireFalse(self::CATEGORY_BYPASSES, 'keymanagement.demo_mode', 'KEY_MANAGEMENT_DEMO_MODE resolves to true (config default is true) — a simulated HSM signs in place of the real cloud HSM. Set KEY_MANAGEMENT_DEMO_MODE=false explicitly.');
        $this->requireFalse(self::CATEGORY_BYPASSES, 'regtech.demo_mode', 'REGTECH_DEMO_MODE resolves to true (config default is true) — regulatory filings/screening run in demo mode. Set REGTECH_DEMO_MODE=false explicitly.');
        $this->requireFalse(self::CATEGORY_BYPASSES, 'ai.demo_mode', 'AI_DEMO_MODE resolves to true (config default is true) — AI services return canned demo output. Set AI_DEMO_MODE=false explicitly.');
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
