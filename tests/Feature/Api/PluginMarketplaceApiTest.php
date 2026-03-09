<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Shared\Models\Plugin;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PluginMarketplaceApiTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
    }

    #[Test]
    public function test_plugin_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $pluginRoutes = $routes->filter(
            fn ($route) => str_starts_with($route->uri(), 'api/v2/plugins')
        );

        $this->assertGreaterThanOrEqual(4, $pluginRoutes->count());
    }

    #[Test]
    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/v2/plugins');

        $response->assertUnauthorized();
    }

    #[Test]
    public function test_authenticated_list_returns_plugins(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Plugin::create([
            'vendor'       => 'finaegis',
            'name'         => 'test-plugin',
            'version'      => '1.0.0',
            'display_name' => 'Test Plugin',
            'description'  => 'A test plugin',
            'status'       => 'active',
            'is_system'    => false,
            'permissions'  => ['cache:read'],
            'path'         => base_path('plugins/finaegis/test-plugin'),
            'entry_point'  => 'TestPluginServiceProvider',
            'installed_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/plugins');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'vendor',
                        'name',
                        'full_name',
                        'version',
                        'display_name',
                        'description',
                        'status',
                        'is_system',
                        'permissions',
                        'installed_at',
                    ],
                ],
                'meta' => ['total', 'active'],
            ]);
    }

    #[Test]
    public function test_show_specific_plugin_by_id(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $plugin = Plugin::create([
            'vendor'       => 'finaegis',
            'name'         => 'audit-exporter',
            'version'      => '2.0.0',
            'display_name' => 'Audit Exporter',
            'description'  => 'Exports audit logs',
            'status'       => 'inactive',
            'is_system'    => true,
            'permissions'  => ['database:read', 'filesystem:write'],
            'path'         => base_path('plugins/finaegis/audit-exporter'),
            'entry_point'  => 'AuditExporterServiceProvider',
            'installed_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/plugins/{$plugin->id}");

        $response->assertOk()
            ->assertJsonPath('data.vendor', 'finaegis')
            ->assertJsonPath('data.name', 'audit-exporter')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.is_system', true);
    }
}
