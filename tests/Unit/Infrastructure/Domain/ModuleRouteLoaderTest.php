<?php

declare(strict_types=1);

use App\Infrastructure\Domain\DomainManager;
use App\Infrastructure\Domain\ModuleRouteLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * Load routes from the test fixture domain tree
 * (tests/Fixtures/ModuleRouteLoader contains EnabledFixture + DisabledFixture).
 */
function loadFixtureModuleRoutes(): void
{
    $loader = new ModuleRouteLoader(
        app(DomainManager::class),
        'tests/Fixtures/ModuleRouteLoader',
    );

    $loader->loadRoutes();

    // Route names assigned fluently (->name()) are indexed lazily; refresh so
    // Route::has() sees the fixture routes (RouteServiceProvider does this
    // after the real route files load).
    Route::getRoutes()->refreshNameLookups();
}

describe('ModuleRouteLoader respects MODULES_DISABLED', function () {
    it('skips the route file of a disabled domain and loads the rest', function () {
        config(['modules.disabled' => ['DisabledFixture']]);

        loadFixtureModuleRoutes();

        expect(Route::has('module-loader-fixture.enabled'))->toBeTrue();
        expect(Route::has('module-loader-fixture.disabled'))->toBeFalse();
    });

    it('returns 404 for a disabled domain route while an enabled one resolves', function () {
        config(['modules.disabled' => ['DisabledFixture']]);

        loadFixtureModuleRoutes();

        $this->getJson('/_fixtures/module-loader/enabled')
            ->assertOk()
            ->assertJson(['module' => 'enabled-fixture']);

        $this->getJson('/_fixtures/module-loader/disabled')
            ->assertNotFound();
    });

    it('loads every route file when nothing is disabled', function () {
        config(['modules.disabled' => []]);

        loadFixtureModuleRoutes();

        expect(Route::has('module-loader-fixture.enabled'))->toBeTrue();
        expect(Route::has('module-loader-fixture.disabled'))->toBeTrue();
    });

    it('matches MODULES_DISABLED entries case-insensitively and with the vendor prefix', function () {
        config(['modules.disabled' => [' finaegis/disabledfixture ']]);

        loadFixtureModuleRoutes();

        expect(Route::has('module-loader-fixture.disabled'))->toBeFalse();
        expect(Route::has('module-loader-fixture.enabled'))->toBeTrue();
    });
});

describe('Zelta production surface template (.env.zelta.example)', function () {
    /** @return array<int, string> */
    function zeltaDisabledModules(): array
    {
        $template = file_get_contents(base_path('.env.zelta.example'));

        if ($template === false) {
            throw new RuntimeException('.env.zelta.example is missing');
        }

        preg_match('/^MODULES_DISABLED="?([^"\n]*)"?$/m', $template, $matches);

        if (! isset($matches[1])) {
            throw new RuntimeException('MODULES_DISABLED not found in .env.zelta.example');
        }

        return array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
    }

    it('lists only real domain directories (no typos)', function () {
        foreach (zeltaDisabledModules() as $domain) {
            expect(is_dir(base_path("app/Domain/{$domain}")))->toBeTrue(
                "MODULES_DISABLED entry '{$domain}' is not a directory under app/Domain"
            );
        }
    });

    it('never disables domains the mobile app, MCP server or payment protocols need', function () {
        $critical = [
            'Account', 'AgentProtocol', 'AI', 'CardIssuance', 'Commerce', 'Compliance',
            'Exchange', 'MachinePay', 'MCP', 'Mobile', 'MobilePayment', 'Payment',
            'Privacy', 'Relayer', 'Rewards', 'Security', 'SMS', 'Subscription',
            'TrustCert', 'User', 'Wallet', 'Webhook', 'X402',
        ];

        $disabled = zeltaDisabledModules();

        foreach ($critical as $domain) {
            expect($disabled)->not->toContain(
                $domain,
                "Critical production domain '{$domain}' must not appear in MODULES_DISABLED"
            );
        }
    });

    it('disables the demo trading scheduler and GraphQL introspection', function () {
        $template = (string) file_get_contents(base_path('.env.zelta.example'));

        expect($template)->toContain('TRADING_ENABLED=false');
        expect($template)->toContain('LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION=true');
    });

    it('keeps introspection disabled in the generic production template too', function () {
        $template = (string) file_get_contents(base_path('.env.production.example'));

        expect($template)->toContain('LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION=true');
    });
});

describe('DomainManager::isDisabledByConfig (boot-time cron gate)', function () {
    it('reads modules.disabled from config only', function () {
        config(['modules.disabled' => ['Governance', 'finaegis/basket', 'CGO']]);

        expect(DomainManager::isDisabledByConfig('Governance'))->toBeTrue();
        expect(DomainManager::isDisabledByConfig('Basket'))->toBeTrue();
        expect(DomainManager::isDisabledByConfig('Cgo'))->toBeTrue();
        expect(DomainManager::isDisabledByConfig('Exchange'))->toBeFalse();
        expect(DomainManager::isDisabledByConfig('Wallet'))->toBeFalse();
    });

    it('treats an empty MODULES_DISABLED as everything enabled', function () {
        config(['modules.disabled' => []]);

        expect(DomainManager::isDisabledByConfig('Governance'))->toBeFalse();
        expect(DomainManager::isDisabledByConfig('Basket'))->toBeFalse();
    });
});
