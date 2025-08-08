<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\AI;

use App\Domain\Account\Models\Account;
use App\Domain\AI\MCP\MCPServer;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\MCP\Tools\Account\AccountBalanceTool;
use App\Domain\AI\ValueObjects\MCPRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MCPServerTest extends TestCase
{
    use RefreshDatabase;

    private MCPServer $server;

    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        // Register ToolRegistry as singleton
        $this->app->singleton(ToolRegistry::class);

        // Register MCPServer as singleton with the same ToolRegistry instance
        $this->app->singleton(MCPServer::class, function ($app) {
            return new MCPServer(
                $app->make(ToolRegistry::class),
                $app->make(\App\Domain\AI\MCP\ResourceManager::class),
                null, // CommandBus optional for tests
                null  // DomainEventBus optional for tests
            );
        });

        $this->registry = app(ToolRegistry::class);
        $this->server = app(MCPServer::class);
    }

    #[Test]
    public function it_can_initialize_mcp_server()
    {
        $request = MCPRequest::create('initialize', []);

        $response = $this->server->handle($request);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('1.0', $response->getData()['protocolVersion']);
        $this->assertArrayHasKey('capabilities', $response->getData());
        $this->assertArrayHasKey('serverInfo', $response->getData());
    }

    #[Test]
    public function it_can_list_available_tools()
    {
        // Register a test tool
        $tool = new AccountBalanceTool(app(\App\Domain\Account\Services\AccountService::class));
        $this->registry->register($tool);

        // Verify tool was registered
        $this->assertTrue($this->registry->has('account.balance'));

        // Debug: Check what getAllTools returns
        $allTools = $this->registry->getAllTools();
        $this->assertNotEmpty($allTools, 'Registry getAllTools() returned empty');
        $this->assertArrayHasKey('account.balance', $allTools);

        $request = MCPRequest::create('tools/list', []);

        $response = $this->server->handle($request);

        $this->assertTrue($response->isSuccess());
        $this->assertArrayHasKey('tools', $response->getData());

        $tools = $response->getData()['tools'];
        $this->assertNotEmpty($tools);

        // Find our registered tool
        $accountTool = collect($tools)->firstWhere('name', 'account.balance');
        $this->assertNotNull($accountTool);
        $this->assertEquals('account.balance', $accountTool['name']);
        $this->assertArrayHasKey('inputSchema', $accountTool);
        $this->assertArrayHasKey('outputSchema', $accountTool);
    }

    #[Test]
    public function it_can_execute_account_balance_tool()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_uuid' => $user->uuid,
            'balance'   => 10000,
        ]);

        $this->actingAs($user);

        // Register the tool
        $tool = new AccountBalanceTool(app(\App\Domain\Account\Services\AccountService::class));
        $this->registry->register($tool);

        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.balance',
            'arguments' => [
                'account_uuid' => (string) $account->uuid,
            ],
        ]);
        $request->setUserId((string) $user->id);

        $response = $this->server->handle($request);

        $this->assertTrue($response->isSuccess());
        $this->assertArrayHasKey('toolResult', $response->getData());

        $result = $response->getData()['toolResult'];
        $this->assertEquals($account->uuid, $result['account_uuid']);
        $this->assertArrayHasKey('balances', $result);
    }

    #[Test]
    public function it_validates_tool_input_schema()
    {
        $tool = new AccountBalanceTool(app(\App\Domain\Account\Services\AccountService::class));
        $this->registry->register($tool);

        // Invalid UUID format
        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.balance',
            'arguments' => [
                'account_uuid' => 'invalid-uuid',
            ],
        ]);

        $response = $this->server->handle($request);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('pattern', strtolower($response->getError()));
    }

    #[Test]
    public function it_handles_missing_required_parameters()
    {
        $tool = new AccountBalanceTool(app(\App\Domain\Account\Services\AccountService::class));
        $this->registry->register($tool);

        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.balance',
            'arguments' => [], // Missing account_uuid
        ]);

        $response = $this->server->handle($request);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('required', strtolower($response->getError()));
    }

    #[Test]
    public function it_tracks_conversation_context()
    {
        $conversationId = 'test-conversation-123';

        $request = MCPRequest::create('initialize', []);
        $request->setConversationId($conversationId);

        $response = $this->server->handle($request);

        $this->assertTrue($response->isSuccess());

        // Verify conversation is tracked
        $this->assertDatabaseHas('stored_events', [
            'event_class' => 'ai_conversation_started',
        ]);
    }

    #[Test]
    public function it_records_tool_execution_in_event_store()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_uuid' => $user->uuid,
        ]);

        $this->actingAs($user);

        $tool = new AccountBalanceTool(app(\App\Domain\Account\Services\AccountService::class));
        $this->registry->register($tool);

        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.balance',
            'arguments' => [
                'account_uuid' => (string) $account->uuid,
            ],
        ]);
        $request->setUserId((string) $user->id);
        $request->setConversationId('test-conv-456');

        $response = $this->server->handle($request);

        $this->assertTrue($response->isSuccess());

        // Verify tool execution is recorded
        $this->assertDatabaseHas('stored_events', [
            'event_class' => 'ai_tool_executed',
        ]);
    }

    #[Test]
    public function it_handles_tool_not_found_error()
    {
        $request = MCPRequest::create('tools/call', [
            'name'      => 'non.existent.tool',
            'arguments' => [],
        ]);

        $response = $this->server->handle($request);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('not found', strtolower($response->getError()));
    }

    #[Test]
    public function it_caches_tool_results_when_cacheable()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_uuid' => $user->uuid,
            'balance'   => 5000,
        ]);

        $this->actingAs($user);

        $tool = new AccountBalanceTool(app(\App\Domain\Account\Services\AccountService::class));
        $this->registry->register($tool);

        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.balance',
            'arguments' => [
                'account_uuid' => (string) $account->uuid,
            ],
        ]);
        $request->setUserId((string) $user->id);
        $request->setConversationId('test-cache-123');

        // First call - not cached
        $response1 = $this->server->handle($request);
        $this->assertTrue($response1->isSuccess());
        $metadata1 = $response1->getData()['metadata'] ?? [];
        $this->assertFalse($metadata1['cache_hit'] ?? false);

        // Second call - should be cached
        $response2 = $this->server->handle($request);
        $this->assertTrue($response2->isSuccess());
        $metadata2 = $response2->getData()['metadata'] ?? [];
        $this->assertTrue($metadata2['cache_hit'] ?? false);
    }

    #[Test]
    public function it_provides_prompts_list()
    {
        $request = MCPRequest::create('prompts/list', []);

        $response = $this->server->handle($request);

        $this->assertTrue($response->isSuccess());
        $this->assertArrayHasKey('prompts', $response->getData());

        $prompts = $response->getData()['prompts'];
        $this->assertNotEmpty($prompts);

        // Check for expected prompts
        $balancePrompt = collect($prompts)->firstWhere('name', 'account_balance');
        $this->assertNotNull($balancePrompt);
        $this->assertArrayHasKey('arguments', $balancePrompt);
    }

    #[Test]
    public function it_measures_tool_execution_performance()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_uuid' => $user->uuid,
        ]);

        $this->actingAs($user);

        $tool = new AccountBalanceTool(app(\App\Domain\Account\Services\AccountService::class));
        $this->registry->register($tool);

        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.balance',
            'arguments' => [
                'account_uuid' => (string) $account->uuid,
            ],
        ]);
        $request->setUserId((string) $user->id);

        $response = $this->server->handle($request);

        $this->assertTrue($response->isSuccess());
        $this->assertArrayHasKey('metadata', $response->getData());
        $this->assertArrayHasKey('duration_ms', $response->getData()['metadata']);

        $duration = $response->getData()['metadata']['duration_ms'];
        $this->assertIsInt($duration);
        $this->assertLessThan(1000, $duration); // Should complete within 1 second
    }
}
