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
        'keymanagement.demo_mode' => false,
        'regtech.demo_mode'       => false,
        'ai.demo_mode'            => false,

        // conditional
        'hyperswitch.enabled'              => false,
        'wallet.solana.sponsor.secret_key' => '',
        'services.helius.api_key'          => 'test-helius-key',
        'mobile.attestation.enabled'       => false,
        'privy.web_login_enabled'          => false,

        // files — the repo ships storage/app/apple/AppleRootCA-G3.cer, whose
        // sha256 matches the default pinned fingerprint in config/subscription.php.
    ]);
}

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

it('only warns (never blocks) when the backup destination is the local disk', function (): void {
    opsVerifyEnvAllGoodConfig();
    config(['backup.backup.destination.disks' => ['local']]);
    app()->detectEnvironment(fn () => 'production');

    // Note: a single output line only satisfies ONE expectsOutputToContain
    // (Mockery consumes the first matching expectation), so assert the most
    // specific substring of the WARN line.
    $this->artisan('ops:verify-env')
        ->expectsOutputToContain('Backup destination is the local disk only')
        ->assertExitCode(0);
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
