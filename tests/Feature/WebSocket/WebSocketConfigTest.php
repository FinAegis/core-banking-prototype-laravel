<?php

declare(strict_types=1);

namespace Tests\Feature\WebSocket;

use App\Models\Team;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebSocketConfigTest extends TestCase
{
    #[Test]
    public function it_returns_websocket_configuration(): void
    {
        $response = $this->getJson('/api/websocket/config');

        $response->assertOk()
            ->assertJsonStructure([
                'enabled',
                'key',
                'cluster',
                'ws_host',
                'ws_port',
                'wss_port',
                'force_tls',
                'encrypted',
                'auth_endpoint',
            ]);
    }

    #[Test]
    public function it_returns_websocket_status(): void
    {
        $response = $this->getJson('/api/websocket/status');

        $response->assertOk()
            ->assertJsonStructure([
                'enabled',
                'connected',
                'server',
                'rate_limits' => [
                    'order_book',
                    'trades',
                    'portfolio',
                    'balance',
                    'transactions',
                ],
            ]);
    }

    #[Test]
    public function it_returns_channel_info_for_exchange(): void
    {
        $response = $this->getJson('/api/websocket/channels/exchange');

        $response->assertOk()
            ->assertJsonStructure([
                'type',
                'suffix',
                'events',
                'rate_limit' => [
                    'max_per_second',
                    'batch_window_ms',
                ],
            ])
            ->assertJsonPath('type', 'exchange')
            ->assertJsonPath('suffix', 'exchange');
    }

    #[Test]
    public function it_returns_channel_info_for_accounts(): void
    {
        $response = $this->getJson('/api/websocket/channels/accounts');

        $response->assertOk()
            ->assertJsonPath('type', 'accounts')
            ->assertJsonPath('suffix', 'accounts');
    }

    #[Test]
    public function it_returns_404_for_unknown_channel_type(): void
    {
        $response = $this->getJson('/api/websocket/channels/unknown');

        $response->assertNotFound()
            ->assertJsonPath('error', 'Channel type not found');
    }

    #[Test]
    public function it_requires_authentication_for_channels_list(): void
    {
        $response = $this->getJson('/api/websocket/channels');

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_returns_available_channels_for_authenticated_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $this->assertNotNull($team);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/websocket/channels');

        $response->assertOk()
            ->assertJsonStructure([
                'channels' => [
                    '*' => [
                        'name',
                        'type',
                        'description',
                        'events',
                    ],
                ],
            ]);

        // Verify user gets expected channels
        $channels = $response->json('channels');
        $channelNames = collect($channels)->pluck('name');

        // Should have base tenant channel
        $this->assertTrue($channelNames->contains("private-tenant.{$team->id}"));

        // Should have exchange channel
        $this->assertTrue($channelNames->contains("private-tenant.{$team->id}.exchange"));

        // Should have accounts channel
        $this->assertTrue($channelNames->contains("private-tenant.{$team->id}.accounts"));

        // Should have transactions channel
        $this->assertTrue($channelNames->contains("private-tenant.{$team->id}.transactions"));

        // Should have multi-sig wallet channel
        $this->assertTrue($channelNames->contains("private-tenant.{$team->id}.wallet.multi-sig"));
    }

    #[Test]
    public function it_includes_compliance_channel_for_admin_users(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $this->assertNotNull($team);

        // User is team owner, so should be admin
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/websocket/channels');

        $response->assertOk();

        $channels = $response->json('channels');
        $channelNames = collect($channels)->pluck('name');

        // Admin should have compliance channel
        $this->assertTrue($channelNames->contains("private-tenant.{$team->id}.compliance"));
    }

    #[Test]
    public function it_excludes_compliance_channel_for_non_admin_users(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $this->assertNotNull($team);

        // Create a regular team member
        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'member']);
        $member->switchTeam($team);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson('/api/websocket/channels');

        $response->assertOk();

        $channels = $response->json('channels');
        $channelNames = collect($channels)->pluck('name');

        // Non-admin should not have compliance channel
        $this->assertFalse($channelNames->contains("private-tenant.{$team->id}.compliance"));
    }
}
