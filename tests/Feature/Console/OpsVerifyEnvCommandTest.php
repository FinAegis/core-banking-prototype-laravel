<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * Configure every check the preflight gate runs to a production-safe value.
 *
 * The testing environment intentionally ships with several "FAIL" defaults
 * (empty peppers, demo modes defaulting to true), so the happy path must set
 * the full surface explicitly.
 */
function opsVerifyEnvAllGoodConfig(): void
{
    config([
        // core
        'app.debug' => false,
        'app.key'   => 'base64:' . base64_encode(random_bytes(32)),

        // secrets
        'subscription.iap.receipt_pepper'          => 'test-iap-pepper',
        'services.stripe.trial_fingerprint_pepper' => 'test-trial-pepper',
        'services.pricing.quote_pepper'            => 'test-quote-pepper',
        'mobile.biometric_jwt.secret'              => str_repeat('s', 32),

        // bypasses
        'subscription.iap.apple_jws_verification_bypass' => false,
        'kyc.routing'                                    => ['trustcert' => 'ondato', 'ramp' => 'bridge', 'cards' => 'bridge'],
        'kyc.providers.bridge.api_key'                   => 'bridge-api-key',
        'kyc.providers.bridge.webhook_public_key'        => '-----BEGIN PUBLIC KEY-----test',
        'kyc.providers.bridge.webhook_secret'            => '',
        'demo.mode'                                      => false,
        'demo.sandbox.enabled'                           => false,
        'demo.features'                                  => [
            'instant_deposits'     => false,
            'skip_kyc'             => false,
            'mock_external_apis'   => false,
            'fixed_exchange_rates' => false,
            'auto_approve'         => false,
        ],
        'keymanagement.demo_mode'                 => false,
        'regtech.demo_mode'                       => false,
        'ai.demo_mode'                            => false,
        'compliance-certification.soc2.demo_mode' => false,

        // conditional
        'hyperswitch.enabled'              => false,
        'wallet.solana.sponsor.secret_key' => '',
        'services.helius.api_key'          => 'test-helius-key',
        'mobile.attestation.enabled'       => false,
        'privy.web_login_enabled'          => false,
        'backup.backup.destination.disks'  => ['s3'],
        'relayer.pimlico.bundler_url'      => '',

        // switches (testing defaults several of these to on)
        'ledger.direct_funding_enabled'      => false,
        'custodians.connectors.mock.enabled' => false,
        'cards.waitlist_deposit_enabled'     => false,
        'relayer.generic_sponsor_enabled'    => false,
        'registration.api_enabled'           => false,
        'investor.page_enabled'              => false,
        'sms.enabled'                        => false,
        'trading.enabled'                    => false,
        'modules.runtime_management_enabled' => false,

        // files — the repo ships storage/app/apple/AppleRootCA-G3.cer, whose
        // sha256 matches the default pinned fingerprint in config/subscription.php.
    ]);

    opsVerifyEnvMcpMoneyTools(false);
}

/**
 * The MCP tool names contain dots, so they cannot be set with dot notation.
 */
function opsVerifyEnvMcpMoneyTools(bool $enabled): void
{
    /** @var array<string, array<string, mixed>> $tools */
    $tools = config('mcp.tools');

    foreach (['account.create', 'payment.transfer', 'exchange.trade', 'sms.send'] as $tool) {
        $tools[$tool]['enabled'] = $enabled;
    }

    config(['mcp.tools' => $tools]);
}

/**
 * Run ops:verify-env --json and return the exit code plus the checks keyed
 * by name.
 *
 * @param  array<string, mixed>  $options
 * @return array{0: int, 1: array<string, array{name: string, category: string, result: string, detail: string}>}
 */
function opsVerifyEnvRun(array $options = []): array
{
    $exitCode = Artisan::call('ops:verify-env', array_merge(['--json' => true], $options));

    /** @var array{checks: list<array{name: string, category: string, result: string, detail: string}>} $decoded */
    $decoded = json_decode(Artisan::output(), true);

    return [$exitCode, collect($decoded['checks'])->keyBy('name')->all()];
}

/**
 * Run the callback with the given variables set in the process environment
 * (as if they were in .env) and restore it afterwards.
 *
 * @param  array<string, string>  $env
 */
function opsVerifyEnvWithEnv(array $env, callable $callback): mixed
{
    $saved = [];

    foreach ($env as $key => $value) {
        $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];
        $_SERVER[$key] = $value;
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    try {
        return $callback();
    } finally {
        foreach ($saved as $key => [$server, $envValue, $getenv]) {
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }
            if ($envValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $envValue;
            }
            if ($getenv === false) {
                putenv($key);
            } else {
                putenv("{$key}={$getenv}");
            }
        }
    }
}

const OPS_VERIFY_ENV_SWITCHES = [
    'ledger.direct_funding_enabled'      => 'LEDGER_DIRECT_FUNDING_ENABLED',
    'custodians.connectors.mock.enabled' => 'CUSTODIAN_MOCK_ENABLED',
    'cards.waitlist_deposit_enabled'     => 'CARDS_WAITLIST_DEPOSIT_ENABLED',
    'relayer.generic_sponsor_enabled'    => 'RELAYER_GENERIC_SPONSOR_ENABLED',
    'investor.page_enabled'              => 'INVESTOR_PAGE_ENABLED',
    'sms.enabled'                        => 'SMS_ENABLED',
    'trading.enabled'                    => 'TRADING_ENABLED',
    'modules.runtime_management_enabled' => 'ADMIN_RUNTIME_MANAGEMENT_ENABLED',
];

const OPS_VERIFY_ENV_MCP_TOOLS = [
    'mcp.tools.account.create'   => 'MCP_TOOL_ACCOUNT_CREATE',
    'mcp.tools.payment.transfer' => 'MCP_TOOL_PAYMENT_TRANSFER',
    'mcp.tools.exchange.trade'   => 'MCP_TOOL_EXCHANGE_TRADE',
    'mcp.tools.sms.send'         => 'MCP_TOOL_SMS_SEND',
];

/**
 * Set (or with null unset) one variable in the process environment and
 * return its previous state for opsVerifyEnvRestoreEnv().
 *
 * @return array{0: string, 1: string|null, 2: string|null, 3: string|false}
 */
function opsVerifyEnvPutEnv(string $key, ?string $value): array
{
    $previous = [$key, $_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];

    if ($value === null) {
        unset($_SERVER[$key], $_ENV[$key]);
        putenv($key);
    } else {
        $_SERVER[$key] = $value;
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    return $previous;
}

/**
 * @param  array{0: string, 1: string|null, 2: string|null, 3: string|false}  $previous
 */
function opsVerifyEnvRestoreEnv(array $previous): void
{
    [$key, $server, $envValue, $getenv] = $previous;

    if ($server === null) {
        unset($_SERVER[$key]);
    } else {
        $_SERVER[$key] = $server;
    }
    if ($envValue === null) {
        unset($_ENV[$key]);
    } else {
        $_ENV[$key] = $envValue;
    }
    if ($getenv === false) {
        putenv($key);
    } else {
        putenv("{$key}={$getenv}");
    }
}

/**
 * Evaluate a config file with the given variables set (null = unset) in the
 * process environment, as `config:cache` would, and restore it afterwards.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function opsVerifyEnvWithConfigFile(string $file, array $env): array
{
    $previous = [];
    foreach ($env as $key => $value) {
        $previous[] = opsVerifyEnvPutEnv($key, $value);
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path("config/{$file}.php");

        return $config;
    } finally {
        foreach (array_reverse($previous) as $saved) {
            opsVerifyEnvRestoreEnv($saved);
        }
    }
}

// API sign-ups are a product decision that must be set explicitly in
// production (an unset value FAILs), so the happy path sets it.
beforeEach(function (): void {
    $this->opsRegistrationEnv = opsVerifyEnvPutEnv('REGISTRATION_API_ENABLED', 'false');
});

afterEach(function (): void {
    opsVerifyEnvRestoreEnv($this->opsRegistrationEnv);
});

it('exits 0 in production when every check passes', function (): void {
    opsVerifyEnvAllGoodConfig();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('all preflight checks passed')
        ->assertExitCode(0);
});

it('blocks the deploy in production when app.debug is true', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['app.debug' => true]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('app.debug')
        ->assertExitCode(1);
});

it('blocks the deploy in production when IAP_RECEIPT_PEPPER is empty', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['subscription.iap.receipt_pepper' => '']);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('subscription.iap.receipt_pepper')
        ->assertExitCode(1);
});

it('blocks the deploy in production when the Apple JWS verification bypass is on', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['subscription.iap.apple_jws_verification_bypass' => true]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('apple_jws_verification_bypass')
        ->assertExitCode(1);
});

it('blocks the deploy in production when SOC2_DEMO_MODE is on (fabricated evidence)', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['compliance-certification.soc2.demo_mode' => true]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('compliance-certification.soc2.demo_mode')
        ->assertExitCode(1);
});

it('blocks the deploy in production when ZK_PROVIDER=railgun (custodial privacy path)', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['privacy.zk.provider' => 'railgun']);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('privacy.railgun.custody')
        ->assertExitCode(1);
});

it('blocks the deploy in production when HyperSwitch is enabled without a webhook secret', function (): void {
    opsVerifyEnvAllGoodConfig();
    config([
        'hyperswitch.enabled'        => true,
        'hyperswitch.api_key'        => 'hs-api-key',
        'hyperswitch.webhook_secret' => '',
    ]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('HYPERSWITCH_WEBHOOK_SECRET')
        ->assertExitCode(1);
});

it('reports HyperSwitch as skipped when the flag is off', function (): void {
    opsVerifyEnvAllGoodConfig();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('HYPERSWITCH_ENABLED=false')
        ->assertExitCode(0);
});

it('blocks the deploy in production when the Solana sponsor key is malformed', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['wallet.solana.sponsor.secret_key' => 'not-valid-base58-0OIl']);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('wallet.solana.sponsor.secret_key')
        ->assertExitCode(1);
});

it('blocks the deploy in production when Bridge is routed but has no credentials', function (): void {
    opsVerifyEnvAllGoodConfig();
    config([
        'kyc.providers.bridge.api_key'            => '',
        'kyc.providers.bridge.webhook_public_key' => '',
        'kyc.providers.bridge.webhook_secret'     => '',
    ]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('kyc.providers.bridge')
        ->assertExitCode(1);
});

it('blocks the deploy in production when backups stay on the local disk only', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['backup.backup.destination.disks' => ['local']]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('Backup destination is the local disk only')
        ->assertExitCode(1);
});

it('only warns (never blocks) about a local-only backup disk outside production', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['backup.backup.destination.disks' => ['local']]);
    // testing env (non-production) → WARN, non-blocking.

    $exitCode = Artisan::call('ops:verify-env', ['--json' => true]);
    $decoded = json_decode(Artisan::output(), true);
    $check = collect($decoded['checks'])->firstWhere('name', 'backup.destination');

    expect($exitCode)->toBe(0)
        ->and($check)->not->toBeNull()
        ->and($check['result'])->toBe('WARN');
});

it('only warns (never blocks) when the backup destination disk is not defined in filesystems', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['backup.backup.destination.disks' => ['nonexistent-disk']]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('nonexistent-disk')
        ->assertExitCode(0);
});

it('passes the backup check when the destination is a defined non-local disk', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['backup.backup.destination.disks' => ['s3']]);
    app()->detectEnvironment(fn () => 'production');

    $exitCode = Artisan::call('ops:verify-env', ['--json' => true]);
    $decoded = json_decode(Artisan::output(), true);

    $check = collect($decoded['checks'])->firstWhere('name', 'backup.destination');
    expect($exitCode)->toBe(0)
        ->and($check)->not->toBeNull()
        ->and($check['result'])->toBe('PASS');
});

it('only warns (never blocks) on a missing Helius API key', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['services.helius.api_key' => '']);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('HELIUS_API_KEY')
        ->assertExitCode(0);
});

it('does not block outside production without --strict', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['subscription.iap.receipt_pepper' => '']); // a real FAIL

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('non-blocking')
        ->assertExitCode(0);
});

it('blocks outside production when --strict is passed', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['subscription.iap.receipt_pepper' => '']); // a real FAIL

    $this->artisan('ops:verify-env --strict')
        ->assertExitCode(1);
});

it('emits parseable JSON with name/category/result/detail per check', function (): void {
    opsVerifyEnvAllGoodConfig();
    app()->detectEnvironment(fn () => 'production');

    $exitCode = Artisan::call('ops:verify-env', ['--json' => true]);
    $decoded = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('pass')
        ->and($decoded['checks'])->toBeArray()
        ->and(count($decoded['checks']))->toBeGreaterThanOrEqual(16)
        ->and($decoded['checks'][0])->toHaveKeys(['name', 'category', 'result', 'detail']);
});

it('reports status fail in JSON when a blocking failure exists in production', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['app.debug' => true]);
    app()->detectEnvironment(fn () => 'production');

    $exitCode = Artisan::call('ops:verify-env', ['--json' => true]);
    $decoded = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($decoded['status'])->toBe('fail');

    $debugCheck = collect($decoded['checks'])->firstWhere('name', 'app.debug');
    expect($debugCheck)->not->toBeNull()
        ->and($debugCheck['result'])->toBe('FAIL');
});

it('skips the privacy provider check when the stack is in demo mode', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['privacy.zk.provider' => 'demo', 'privacy.merkle.provider' => 'demo']);
    app()->detectEnvironment(fn () => 'production');

    $exitCode = Artisan::call('ops:verify-env', ['--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('name', 'privacy.railgun.providers');

    expect($exitCode)->toBe(0)
        ->and($check)->not->toBeNull()
        ->and($check['result'])->toBe('SKIP');
});

// Wave 0B: in production the custodial RAILGUN path is forbidden outright
// (privacy is non-custodial/on-device), so ZK_PROVIDER=railgun blocks with a
// `privacy.railgun.custody` FAIL that preempts the consistency/secret checks.
// Those checks remain as non-blocking diagnostics OUTSIDE production.

it('flags inconsistent railgun providers as a non-blocking FAIL outside production', function (): void {
    opsVerifyEnvAllGoodConfig();
    config([
        'privacy.zk.provider'           => 'railgun',
        'privacy.merkle.provider'       => 'demo',
        'privacy.railgun.bridge_secret' => 'a-secret',
    ]);
    // Not production/strict → custody rule does not fire; consistency check reports FAIL (non-blocking).

    $exitCode = Artisan::call('ops:verify-env', ['--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('name', 'privacy.railgun.providers');

    expect($exitCode)->toBe(0)
        ->and($check)->not->toBeNull()
        ->and($check['result'])->toBe('FAIL');
});

it('flags railgun mode without a bridge secret as a non-blocking FAIL outside production', function (): void {
    opsVerifyEnvAllGoodConfig();
    config([
        'privacy.zk.provider'           => 'railgun',
        'privacy.merkle.provider'       => 'railgun',
        'privacy.railgun.bridge_secret' => '',
    ]);

    $exitCode = Artisan::call('ops:verify-env', ['--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('name', 'privacy.railgun.providers');

    expect($exitCode)->toBe(0)
        ->and($check)->not->toBeNull()
        ->and($check['result'])->toBe('FAIL');
});

it('blocks railgun in production even with a bridge secret (custodial path forbidden)', function (): void {
    opsVerifyEnvAllGoodConfig();
    config([
        'privacy.zk.provider'           => 'railgun',
        'privacy.merkle.provider'       => 'railgun',
        'privacy.railgun.bridge_secret' => 'a-real-secret',
    ]);
    app()->detectEnvironment(fn () => 'production');

    $exitCode = Artisan::call('ops:verify-env', ['--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('name', 'privacy.railgun.custody');

    expect($exitCode)->toBe(1)
        ->and($check)->not->toBeNull()
        ->and($check['result'])->toBe('FAIL');
});

it('warns (never blocks) in production about each switch that is on', function (string $configKey, string $envName): void {
    opsVerifyEnvAllGoodConfig();
    // Direct ledger funding FAILs without the acknowledgement (tested below).
    config([$configKey => true, 'ledger.direct_funding_acknowledged' => true]);
    app()->detectEnvironment(fn () => 'production');

    [$exitCode, $checks] = opsVerifyEnvRun();

    expect($exitCode)->toBe(0)
        ->and($checks[$configKey]['category'])->toBe('switches')
        ->and($checks[$configKey]['result'])->toBe('WARN')
        ->and($checks[$configKey]['detail'])->toContain("{$envName} is ON");
})->with(array_map(null, array_keys(OPS_VERIFY_ENV_SWITCHES), array_values(OPS_VERIFY_ENV_SWITCHES)));

it('warns (never blocks) in production about each MCP money tool that is on', function (string $tool, string $envName): void {
    opsVerifyEnvAllGoodConfig();
    /** @var array<string, array<string, mixed>> $tools */
    $tools = config('mcp.tools');
    $tools[substr($tool, strlen('mcp.tools.'))]['enabled'] = true;
    // MCP sms.send FAILs without the acknowledgement (tested in Ghsa385wAgentsMcpTest).
    config(['mcp.tools' => $tools, 'mcp.sms_send_acknowledged' => true]);
    app()->detectEnvironment(fn () => 'production');

    [$exitCode, $checks] = opsVerifyEnvRun();

    expect($exitCode)->toBe(0)
        ->and($checks[$tool]['result'])->toBe('WARN')
        ->and($checks[$tool]['detail'])->toContain("{$envName} is ON");
})->with(array_map(null, array_keys(OPS_VERIFY_ENV_MCP_TOOLS), array_values(OPS_VERIFY_ENV_MCP_TOOLS)));

it('warns in production when a switch is off only because its env var is unset', function (): void {
    opsVerifyEnvAllGoodConfig();
    app()->detectEnvironment(fn () => 'production');

    $envNames = array_merge(array_values(OPS_VERIFY_ENV_SWITCHES), array_values(OPS_VERIFY_ENV_MCP_TOOLS));
    $saved = [];
    foreach ($envNames as $name) {
        $saved[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);
    }

    try {
        [$exitCode, $checks] = opsVerifyEnvRun();
    } finally {
        foreach ($saved as $name => [$server, $envValue, $getenv]) {
            if ($server !== null) {
                $_SERVER[$name] = $server;
            }
            if ($envValue !== null) {
                $_ENV[$name] = $envValue;
            }
            if ($getenv !== false) {
                putenv("{$name}={$getenv}");
            }
        }
    }

    expect($exitCode)->toBe(0);
    foreach (array_merge(OPS_VERIFY_ENV_SWITCHES, OPS_VERIFY_ENV_MCP_TOOLS) as $name => $envName) {
        expect($checks[$name]['result'])->toBe('WARN')
            ->and($checks[$name]['detail'])->toContain("{$envName} is not set");
    }
});

it('passes a switch in production when it is explicitly off', function (): void {
    opsVerifyEnvAllGoodConfig();
    app()->detectEnvironment(fn () => 'production');

    $env = array_fill_keys(array_merge(array_values(OPS_VERIFY_ENV_SWITCHES), array_values(OPS_VERIFY_ENV_MCP_TOOLS)), 'false');

    [$exitCode, $checks] = opsVerifyEnvWithEnv($env, fn (): array => opsVerifyEnvRun());

    expect($exitCode)->toBe(0);
    foreach (array_keys(array_merge(OPS_VERIFY_ENV_SWITCHES, OPS_VERIFY_ENV_MCP_TOOLS)) as $name) {
        expect($checks[$name]['result'])->toBe('PASS');
    }
});

it('skips the switch checks for local, testing and demo', function (string $env): void {
    opsVerifyEnvAllGoodConfig();
    config(['ledger.direct_funding_enabled' => true, 'sms.enabled' => true]);
    app()->detectEnvironment(fn () => $env);

    [$exitCode, $checks] = opsVerifyEnvRun();

    expect($exitCode)->toBe(0)
        ->and($checks['ledger.direct_funding_enabled']['result'])->toBe('SKIP')
        ->and($checks['sms.enabled']['result'])->toBe('SKIP')
        ->and($checks['registration.api_enabled']['result'])->toBe('SKIP')
        ->and($checks['mcp.tools.payment.transfer']['result'])->toBe('SKIP');
})->with(['local', 'testing', 'demo']);

it('checks the switches for any other environment, without blocking outside production', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['ledger.direct_funding_enabled' => true]);
    app()->detectEnvironment(fn () => 'staging');
    $previous = opsVerifyEnvPutEnv('REGISTRATION_API_ENABLED', null);

    try {
        [$exitCode, $checks] = opsVerifyEnvRun();
    } finally {
        opsVerifyEnvRestoreEnv($previous);
    }

    expect($exitCode)->toBe(0)
        ->and($checks['ledger.direct_funding_enabled']['result'])->toBe('WARN')
        ->and($checks['registration.api_enabled']['result'])->toBe('FAIL');
});

it('blocks the deploy in production when REGISTRATION_API_ENABLED is not set', function (bool $configValue): void {
    opsVerifyEnvAllGoodConfig();
    config(['registration.api_enabled' => $configValue]);
    app()->detectEnvironment(fn () => 'production');
    $previous = opsVerifyEnvPutEnv('REGISTRATION_API_ENABLED', null);

    try {
        [$exitCode, $checks] = opsVerifyEnvRun();
    } finally {
        opsVerifyEnvRestoreEnv($previous);
    }

    expect($exitCode)->toBe(1)
        ->and($checks['registration.api_enabled']['category'])->toBe('switches')
        ->and($checks['registration.api_enabled']['result'])->toBe('FAIL')
        ->and($checks['registration.api_enabled']['detail'])->toContain('REGISTRATION_API_ENABLED is not set');
})->with(['resolved off' => [false], 'resolved on' => [true]]);

it('passes REGISTRATION_API_ENABLED in production when it is set explicitly to either value', function (string $envValue, bool $configValue): void {
    opsVerifyEnvAllGoodConfig();
    config(['registration.api_enabled' => $configValue]);
    app()->detectEnvironment(fn () => 'production');

    [$exitCode, $checks] = opsVerifyEnvWithEnv(['REGISTRATION_API_ENABLED' => $envValue], fn (): array => opsVerifyEnvRun());

    expect($exitCode)->toBe(0)
        ->and($checks['registration.api_enabled']['result'])->toBe('PASS')
        ->and($checks['registration.api_enabled']['detail'])->toContain("REGISTRATION_API_ENABLED={$envValue}")
        ->and($checks['registration.api_enabled']['detail'])->not->toContain('unless this is intended');
})->with([
    'sign-ups open (zelta.app)' => ['true', true],
    'sign-ups closed'           => ['false', false],
]);

// GHSA-385w review D1: with `php artisan config:cache` the .env file is not
// loaded, so whether REGISTRATION_API_ENABLED was set is read from the
// cached config (registration.api_enabled_explicit).
it('blocks the deploy in production when REGISTRATION_API_ENABLED was unset when the config was cached', function (bool $configValue): void {
    opsVerifyEnvAllGoodConfig();
    config(['registration.api_enabled' => $configValue, 'registration.api_enabled_explicit' => false]);
    app()->detectEnvironment(fn () => 'production');
    app()->instance('config_loaded_from_cache', true);

    [$exitCode, $checks] = opsVerifyEnvRun();

    expect($exitCode)->toBe(1)
        ->and($checks['registration.api_enabled']['result'])->toBe('FAIL')
        ->and($checks['registration.api_enabled']['detail'])->toContain('REGISTRATION_API_ENABLED is not set');
})->with(['resolved off' => [false], 'resolved on' => [true]]);

it('passes REGISTRATION_API_ENABLED with a cached config when it was set explicitly', function (bool $configValue): void {
    opsVerifyEnvAllGoodConfig();
    config(['registration.api_enabled' => $configValue, 'registration.api_enabled_explicit' => true]);
    app()->detectEnvironment(fn () => 'production');
    app()->instance('config_loaded_from_cache', true);
    $previous = opsVerifyEnvPutEnv('REGISTRATION_API_ENABLED', null);

    try {
        [$exitCode, $checks] = opsVerifyEnvRun();
    } finally {
        opsVerifyEnvRestoreEnv($previous);
    }

    $value = $configValue ? 'true' : 'false';
    expect($exitCode)->toBe(0)
        ->and($checks['registration.api_enabled']['result'])->toBe('PASS')
        ->and($checks['registration.api_enabled']['detail'])->toContain("REGISTRATION_API_ENABLED={$value}");
})->with(['sign-ups open' => [true], 'sign-ups closed' => [false]]);

it('reads the explicit REGISTRATION_API_ENABLED setting into the config', function (): void {
    $config = fn (?string $value): array => opsVerifyEnvWithConfigFile('registration', ['REGISTRATION_API_ENABLED' => $value]);

    expect($config(null)['api_enabled_explicit'])->toBeFalse()
        ->and($config('true')['api_enabled_explicit'])->toBeTrue()
        ->and($config('false')['api_enabled_explicit'])->toBeTrue();
});

// GHSA-385w review D2: direct ledger funding moves balances without a payment
// rail, so in production it needs an explicit acknowledgement.
it('blocks the deploy in production while direct ledger funding is on without the acknowledgement', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['ledger.direct_funding_enabled' => true, 'ledger.direct_funding_acknowledged' => false]);
    app()->detectEnvironment(fn () => 'production');

    [$exitCode, $checks] = opsVerifyEnvRun();

    expect($exitCode)->toBe(1)
        ->and($checks['ledger.direct_funding_enabled']['result'])->toBe('FAIL')
        ->and($checks['ledger.direct_funding_enabled']['detail'])->toContain('LEDGER_DIRECT_FUNDING_ACKNOWLEDGED=true');
});

it('only warns in production about direct ledger funding once the operator acknowledged it', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['ledger.direct_funding_enabled' => true, 'ledger.direct_funding_acknowledged' => true]);
    app()->detectEnvironment(fn () => 'production');

    [$exitCode, $checks] = opsVerifyEnvRun();

    expect($exitCode)->toBe(0)
        ->and($checks['ledger.direct_funding_enabled']['result'])->toBe('WARN')
        ->and($checks['ledger.direct_funding_enabled']['detail'])->toContain('LEDGER_DIRECT_FUNDING_ENABLED is ON');
});

it('reads the direct ledger funding acknowledgement into the config, off by default', function (): void {
    expect(opsVerifyEnvWithConfigFile('ledger', ['LEDGER_DIRECT_FUNDING_ACKNOWLEDGED' => null])['direct_funding_acknowledged'])->toBeFalse()
        ->and(opsVerifyEnvWithConfigFile('ledger', ['LEDGER_DIRECT_FUNDING_ACKNOWLEDGED' => 'true'])['direct_funding_acknowledged'])->toBeTrue();
});

it('still exits 0 in production with every switch on', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(array_fill_keys(array_keys(OPS_VERIFY_ENV_SWITCHES), true));
    config(['ledger.direct_funding_acknowledged' => true, 'mcp.sms_send_acknowledged' => true]);
    opsVerifyEnvMcpMoneyTools(true);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('all preflight checks passed')
        ->assertExitCode(0);
});

it('warns when PIMLICO_BUNDLER_URL is pinned while several EVM networks are enabled', function (): void {
    opsVerifyEnvAllGoodConfig();
    config([
        'relayer.pimlico.bundler_url' => 'https://api.pimlico.io/v2/137/rpc',
        'wallet.evm.enabled_networks' => ['polygon', 'base', 'arbitrum'],
    ]);
    app()->detectEnvironment(fn () => 'production');

    [$exitCode, $checks] = opsVerifyEnvRun();

    expect($exitCode)->toBe(0)
        ->and($checks['relayer.pimlico.bundler_url']['result'])->toBe('WARN')
        ->and($checks['relayer.pimlico.bundler_url']['detail'])->toContain('polygon,base,arbitrum');
});

it('passes PIMLICO_BUNDLER_URL when it is empty or only one EVM network is enabled', function (string $url, array $networks): void {
    opsVerifyEnvAllGoodConfig();
    config([
        'relayer.pimlico.bundler_url' => $url,
        'wallet.evm.enabled_networks' => $networks,
    ]);
    app()->detectEnvironment(fn () => 'production');

    [$exitCode, $checks] = opsVerifyEnvRun();

    expect($exitCode)->toBe(0)
        ->and($checks['relayer.pimlico.bundler_url']['result'])->toBe('PASS');
})->with([
    'empty, three networks' => ['', ['polygon', 'base', 'arbitrum']],
    'pinned, one network'   => ['https://api.pimlico.io/v2/137/rpc', ['polygon']],
]);

describe('Lepo card provider', function (): void {
    it('fails in production when lepo is the issuer without a key or webhook secret', function (): void {
        opsVerifyEnvAllGoodConfig();
        config([
            'cardissuance.default_issuer'                    => 'lepo',
            'cardissuance.issuers.lepo.api_key'              => '',
            'cardissuance.issuers.lepo.webhook_secret'       => '',
            'cardissuance.issuers.lepo.card_type_id_virtual' => '999',
        ]);
        app()->detectEnvironment(fn () => 'production');

        [$exitCode, $checks] = opsVerifyEnvRun();

        expect($exitCode)->toBe(1)
            ->and($checks['cards.lepo']['result'])->toBe('FAIL')
            ->and($checks['cards.lepo']['detail'])->toContain('LEPO_API_KEY')
            ->and($checks['cards.lepo']['detail'])->toContain('LEPO_WEBHOOK_SECRET');
    });

    it('fails in production when a lepo key is set without a webhook secret', function (): void {
        opsVerifyEnvAllGoodConfig();
        config([
            'cardissuance.issuers.lepo.api_key'              => 'test-key',
            'cardissuance.issuers.lepo.webhook_secret'       => '',
            'cardissuance.issuers.lepo.card_type_id_virtual' => '999',
        ]);
        app()->detectEnvironment(fn () => 'production');

        [, $checks] = opsVerifyEnvRun();

        expect($checks['cards.lepo']['result'])->toBe('FAIL')
            ->and($checks['cards.lepo']['detail'])->toContain('LEPO_WEBHOOK_SECRET')
            ->and($checks['cards.lepo']['detail'])->not->toContain('LEPO_API_KEY');
    });

    it('passes when lepo is not in use', function (): void {
        opsVerifyEnvAllGoodConfig();
        config(['cardissuance.default_issuer' => 'demo', 'cardissuance.issuers.lepo.api_key' => '']);
        app()->detectEnvironment(fn () => 'production');

        [$exitCode, $checks] = opsVerifyEnvRun();

        expect($exitCode)->toBe(0)
            ->and($checks['cards.lepo']['result'])->toBe('SKIP');
    });

    it('warns without a virtual card type id', function (): void {
        opsVerifyEnvAllGoodConfig();
        config([
            'cardissuance.default_issuer'                    => 'lepo',
            'cardissuance.issuers.lepo.api_key'              => 'test-key',
            'cardissuance.issuers.lepo.webhook_secret'       => 'whsec_test',
            'cardissuance.issuers.lepo.card_type_id_virtual' => '',
        ]);
        app()->detectEnvironment(fn () => 'production');

        [$exitCode, $checks] = opsVerifyEnvRun();

        expect($exitCode)->toBe(0)
            ->and($checks['cards.lepo']['result'])->toBe('WARN')
            ->and($checks['cards.lepo']['detail'])->toContain('LEPO_CARD_TYPE_ID_VIRTUAL');
    });
});

describe('Card providers', function (): void {
    it('treats lepo in the enabled providers as in use', function (): void {
        opsVerifyEnvAllGoodConfig();
        config([
            'cardissuance.providers.enabled'           => 'lepo',
            'cardissuance.issuers.lepo.api_key'        => '',
            'cardissuance.issuers.lepo.webhook_secret' => '',
        ]);
        app()->detectEnvironment(fn () => 'production');

        [$exitCode, $checks] = opsVerifyEnvRun();

        expect($exitCode)->toBe(1)
            ->and($checks['cards.lepo']['result'])->toBe('FAIL');
    });

    it('warns about an empty pilot allowlist', function (): void {
        opsVerifyEnvAllGoodConfig();
        config([
            'cardissuance.providers.enabled'                 => 'lepo',
            'cardissuance.providers.lepo_pilot_only'         => true,
            'cardissuance.providers.lepo_pilot_users'        => '',
            'cardissuance.issuers.lepo.api_key'              => 'test-key',
            'cardissuance.issuers.lepo.webhook_secret'       => 'whsec_test',
            'cardissuance.issuers.lepo.card_type_id_virtual' => '999',
        ]);
        app()->detectEnvironment(fn () => 'production');

        [$exitCode, $checks] = opsVerifyEnvRun();

        expect($exitCode)->toBe(0)
            ->and($checks['cards.lepo']['result'])->toBe('WARN')
            ->and($checks['cards.lepo']['detail'])->toContain('CARD_PROVIDER_LEPO_PILOT_USERS');
    });
});
