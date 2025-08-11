<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Domain\AI\Events\ToolExecutedEvent;
use App\Domain\AI\MCP\CacheManager;
use App\Domain\AI\MCP\Interfaces\MCPToolInterface;
use App\Domain\AI\MCP\MCPServer;
use App\Domain\AI\MCP\ResourceManager;
use App\Domain\AI\MCP\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class MCPServerTest extends TestCase
{
    use RefreshDatabase;

    private MCPServer $mcpServer;

    private ToolRegistry $toolRegistry;

    private ResourceManager $resourceManager;

    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRegistry = new ToolRegistry();
        $this->resourceManager = new ResourceManager();
        $this->cacheManager = new CacheManager();

        $this->mcpServer = new MCPServer(
            $this->toolRegistry,
            $this->resourceManager,
            $this->cacheManager
        );
    }

    /** @test */
    public function it_can_register_and_discover_tools(): void
    {
        // Arrange
        $mockTool = $this->createMockTool('TestTool', 'Test tool description');

        // Act
        $this->toolRegistry->register('test_tool', $mockTool, [
            'category'      => 'testing',
            'requires_auth' => true,
        ]);

        // Assert
        $this->assertTrue($this->toolRegistry->has('test_tool'));
        $tool = $this->toolRegistry->get('test_tool');
        $this->assertSame($mockTool, $tool);

        $allTools = $this->toolRegistry->all();
        $this->assertArrayHasKey('test_tool', $allTools);
    }

    /** @test */
    public function it_executes_tools_and_records_events(): void
    {
        // Arrange
        Event::fake();
        $toolName = 'balance_check';
        $params = ['account_id' => 'ACC001'];
        $expectedResult = ['balance' => 1000.00, 'currency' => 'USD'];

        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('execute')
            ->with($params)
            ->andReturn($expectedResult);

        $this->toolRegistry->register($toolName, $mockTool);

        // Act
        $result = $this->mcpServer->executeTool($toolName, $params, 'conv_123');

        // Assert
        $this->assertEquals($expectedResult, $result);
        Event::assertDispatched(ToolExecutedEvent::class, function ($event) use ($toolName, $params) {
            return $event->toolName === $toolName
                && $event->parameters === $params
                && $event->success === true;
        });
    }

    /** @test */
    public function it_validates_tool_parameters(): void
    {
        // Arrange
        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('validate')
            ->with(['invalid' => 'params'])
            ->andReturn(false);

        $this->toolRegistry->register('validated_tool', $mockTool);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->mcpServer->executeTool('validated_tool', ['invalid' => 'params'], 'conv_123');
    }

    /** @test */
    public function it_caches_tool_results_when_configured(): void
    {
        // Arrange
        Cache::spy();
        $toolName = 'cacheable_tool';
        $params = ['param' => 'value'];
        $result = ['data' => 'cached_result'];

        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('getCacheConfig')
            ->andReturn([
                'enabled'    => true,
                'ttl'        => 300,
                'key_prefix' => 'tool:cache:',
            ]);
        $mockTool->shouldReceive('validate')->andReturn(true);
        $mockTool->shouldReceive('execute')->once()->andReturn($result);

        $this->toolRegistry->register($toolName, $mockTool);

        // Act - First execution
        $result1 = $this->mcpServer->executeTool($toolName, $params, 'conv_123');

        // Act - Second execution (should use cache)
        $result2 = $this->mcpServer->executeTool($toolName, $params, 'conv_123');

        // Assert
        $this->assertEquals($result, $result1);
        $this->assertEquals($result, $result2);
        Cache::shouldHaveReceived('remember')->twice();
    }

    /** @test */
    public function it_exposes_and_retrieves_resources(): void
    {
        // Arrange
        $resourceName = 'test_resource';
        $resourceData = ['key' => 'value', 'data' => [1, 2, 3]];

        // Act
        $this->resourceManager->exposeResource($resourceName, $resourceData);
        $retrieved = $this->resourceManager->getResource($resourceName);

        // Assert
        $this->assertEquals($resourceData, $retrieved);
        $this->assertTrue($this->resourceManager->hasResource($resourceName));
    }

    /** @test */
    public function it_handles_tool_execution_failures(): void
    {
        // Arrange
        Event::fake();
        $toolName = 'failing_tool';
        $params = ['will' => 'fail'];
        $errorMessage = 'Tool execution failed';

        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('validate')->andReturn(true);
        $mockTool->shouldReceive('execute')
            ->andThrow(new \RuntimeException($errorMessage));

        $this->toolRegistry->register($toolName, $mockTool);

        // Act & Assert
        try {
            $this->mcpServer->executeTool($toolName, $params, 'conv_123');
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals($errorMessage, $e->getMessage());
        }

        // Verify failure event was recorded
        Event::assertDispatched(ToolExecutedEvent::class, function ($event) use ($toolName) {
            return $event->toolName === $toolName && $event->success === false;
        });
    }

    /** @test */
    public function it_lists_available_tools_with_metadata(): void
    {
        // Arrange
        $tool1 = $this->createMockTool('Tool1', 'First tool');
        $tool2 = $this->createMockTool('Tool2', 'Second tool');

        $this->toolRegistry->register('tool1', $tool1, [
            'category'      => 'banking',
            'requires_auth' => true,
        ]);
        $this->toolRegistry->register('tool2', $tool2, [
            'category'      => 'payments',
            'requires_auth' => false,
        ]);

        // Act
        $tools = $this->mcpServer->listTools();

        // Assert
        $this->assertCount(2, $tools);
        $this->assertArrayHasKey('tool1', $tools);
        $this->assertArrayHasKey('tool2', $tools);
        $this->assertEquals('banking', $tools['tool1']['metadata']['category']);
        $this->assertEquals('payments', $tools['tool2']['metadata']['category']);
    }

    /** @test */
    public function it_enforces_permissions_for_tool_execution(): void
    {
        // Arrange
        $toolName = 'restricted_tool';
        $mockTool = $this->createMockTool($toolName, 'Restricted tool');

        $this->toolRegistry->register($toolName, $mockTool, [
            'requires_permission' => 'execute_restricted_tools',
        ]);

        // Act & Assert
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->mcpServer->executeTool($toolName, [], 'conv_123', 1); // User without permission
    }

    /** @test */
    public function it_tracks_tool_execution_metrics(): void
    {
        // Arrange
        $toolName = 'monitored_tool';
        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('validate')->andReturn(true);
        $mockTool->shouldReceive('execute')->andReturn(['success' => true]);
        $mockTool->shouldReceive('getCacheConfig')->andReturn(['enabled' => false]);

        $this->toolRegistry->register($toolName, $mockTool);

        // Act
        $startTime = microtime(true);
        $this->mcpServer->executeTool($toolName, [], 'conv_123');
        $executionTime = microtime(true) - $startTime;

        // Assert
        $metrics = $this->mcpServer->getMetrics($toolName);
        $this->assertArrayHasKey('execution_count', $metrics);
        $this->assertArrayHasKey('average_execution_time', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);
        $this->assertEquals(1, $metrics['execution_count']);
        $this->assertLessThan(1.0, $metrics['average_execution_time']); // Should be fast
    }

    private function createMockTool(string $name, string $description): MCPToolInterface
    {
        $mock = Mockery::mock(MCPToolInterface::class);
        $mock->shouldReceive('getSchema')->andReturn([
            'name'        => $name,
            'description' => $description,
            'parameters'  => [],
        ]);
        $mock->shouldReceive('validate')->andReturn(true);
        $mock->shouldReceive('execute')->andReturn(['success' => true]);
        $mock->shouldReceive('getCacheConfig')->andReturn([
            'enabled' => false,
            'ttl'     => 0,
        ]);

        return $mock;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
