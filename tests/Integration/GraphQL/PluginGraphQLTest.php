<?php

declare(strict_types=1);

namespace Tests\Integration\GraphQL;

use App\Domain\Shared\Models\Plugin;
use App\Infrastructure\Plugins\PluginHookManager;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PluginGraphQLTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
    }

    #[Test]
    public function test_unauthenticated_plugins_query_returns_error(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => '{ plugins { id vendor name } }',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('errors'));
    }

    #[Test]
    public function test_authenticated_plugins_query_returns_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Plugin::create([
            'vendor'       => 'finaegis',
            'name'         => 'webhook-notifier',
            'version'      => '1.0.0',
            'display_name' => 'Webhook Notifier',
            'status'       => 'active',
            'is_system'    => false,
            'permissions'  => ['api:external'],
            'path'         => base_path('plugins/finaegis/webhook-notifier'),
            'entry_point'  => 'WebhookNotifierServiceProvider',
            'installed_at' => now(),
        ]);

        $response = $this->postJson('/graphql', [
            'query' => '{ plugins { id vendor name status is_system } }',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.plugins.0.vendor', 'finaegis')
            ->assertJsonPath('data.plugins.0.name', 'webhook-notifier')
            ->assertJsonPath('data.plugins.0.status', 'active');
    }

    #[Test]
    public function test_marketplace_stats_returns_hook_point_count(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Plugin::create([
            'vendor'       => 'finaegis',
            'name'         => 'test-active',
            'version'      => '1.0.0',
            'status'       => 'active',
            'is_system'    => false,
            'permissions'  => [],
            'path'         => base_path('plugins/finaegis/test-active'),
            'entry_point'  => 'TestActiveServiceProvider',
            'installed_at' => now(),
        ]);

        Plugin::create([
            'vendor'       => 'finaegis',
            'name'         => 'test-inactive',
            'version'      => '1.0.0',
            'status'       => 'inactive',
            'is_system'    => false,
            'permissions'  => [],
            'path'         => base_path('plugins/finaegis/test-inactive'),
            'entry_point'  => 'TestInactiveServiceProvider',
            'installed_at' => now(),
        ]);

        $response = $this->postJson('/graphql', [
            'query' => '{ pluginMarketplaceStats { total active inactive failed hook_point_count } }',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pluginMarketplaceStats.total', 2)
            ->assertJsonPath('data.pluginMarketplaceStats.active', 1)
            ->assertJsonPath('data.pluginMarketplaceStats.inactive', 1)
            ->assertJsonPath('data.pluginMarketplaceStats.failed', 0)
            ->assertJsonPath(
                'data.pluginMarketplaceStats.hook_point_count',
                count(PluginHookManager::HOOK_POINTS)
            );
    }
}
