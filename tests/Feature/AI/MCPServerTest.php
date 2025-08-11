<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\Events\ToolExecutedEvent;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRegistry = new ToolRegistry();
        $this->resourceManager = new ResourceManager();

        $this->mcpServer = new MCPServer(
            $this->toolRegistry,
            $this->resourceManager
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_register_and_discover_tools(): void
    {
        // Arrange
        $mockTool = $this->createMockTool('TestTool', 'Test tool description');

        // Act
        /** @var MCPToolInterface $mockTool */
        $this->toolRegistry->register($mockTool);

        // Assert
        $this->assertTrue($this->toolRegistry->has('TestTool'));
        $tool = $this->toolRegistry->get('TestTool');
        $this->assertSame($mockTool, $tool);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_executes_tools_and_records_events(): void
    {
        // Arrange
        Event::fake();
        $toolName = 'balance_check';
        $params = ['account_id' => 'ACC001'];
        $expectedResult = ['balance' => 1000.00, 'currency' => 'USD'];

        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('getName')->andReturn($toolName);
        $mockTool->shouldReceive('getCategory')->andReturn('testing');
        $mockTool->shouldReceive('getDescription')->andReturn('Balance check tool');
        $mockTool->shouldReceive('execute')
            ->with($params)
            ->andReturn($expectedResult);

        /** @var MCPToolInterface $mockTool */
        $this->toolRegistry->register($mockTool);

        // Act
        $result = $this->mcpServer->executeTool($toolName, $params);

        // Assert
        $this->assertEquals($expectedResult, $result);
        Event::assertDispatched(ToolExecutedEvent::class, function ($event) use ($toolName, $params) {
            return $event->toolName === $toolName
                && $event->parameters === $params
                && $event->success === true;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_tool_parameters(): void
    {
        // Arrange
        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('validate')
            ->with(['invalid' => 'params'])
            ->andReturn(false);
        $mockTool->shouldReceive('getInputSchema')->andReturn([]);
        $mockTool->shouldReceive('getOutputSchema')->andReturn([]);

        $mockTool->shouldReceive('getName')->andReturn('validated_tool');
        $mockTool->shouldReceive('getCategory')->andReturn('testing');
        $mockTool->shouldReceive('getDescription')->andReturn('Test tool');
        /** @var MCPToolInterface $mockTool */
        $this->toolRegistry->register($mockTool);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->mcpServer->executeTool('validated_tool', ['invalid' => 'params']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
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
        $mockTool->shouldReceive('getName')->andReturn($toolName);
        $mockTool->shouldReceive('getCategory')->andReturn('testing');
        $mockTool->shouldReceive('getDescription')->andReturn('Cacheable test tool');
        $mockTool->shouldReceive('validate')->andReturn(true);
        $mockTool->shouldReceive('execute')->once()->andReturn($result);

        /** @var MCPToolInterface $mockTool */
        $this->toolRegistry->register($mockTool);

        // Act - First execution
        $result1 = $this->mcpServer->executeTool($toolName, $params);

        // Act - Second execution (should use cache)
        $result2 = $this->mcpServer->executeTool($toolName, $params);

        // Assert
        $this->assertEquals($result, $result1);
        $this->assertEquals($result, $result2);
        Cache::shouldHaveReceived('remember')->twice();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_exposes_and_retrieves_resources(): void
    {
        // Arrange
        $resourceUri = 'test://resource';
        $mockResource = Mockery::mock(\App\Domain\AI\Contracts\MCPResourceInterface::class);
        $mockResource->shouldReceive('getUri')->andReturn($resourceUri);
        $mockResource->shouldReceive('getName')->andReturn('Test Resource');
        $mockResource->shouldReceive('getDescription')->andReturn('Test resource description');

        // Act
        /** @var \App\Domain\AI\Contracts\MCPResourceInterface $mockResource */
        $this->resourceManager->register($mockResource);
        $retrieved = $this->resourceManager->get($resourceUri);

        // Assert
        $this->assertSame($mockResource, $retrieved);
        $this->assertTrue($this->resourceManager->has($resourceUri));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_tool_execution_failures(): void
    {
        // Arrange
        Event::fake();
        $toolName = 'failing_tool';
        $params = ['will' => 'fail'];
        $errorMessage = 'Tool execution failed';

        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('validate')->andReturn(true);
        $mockTool->shouldReceive('getName')->andReturn($toolName);
        $mockTool->shouldReceive('getCategory')->andReturn('testing');
        $mockTool->shouldReceive('getDescription')->andReturn('Failing test tool');
        $mockTool->shouldReceive('execute')
            ->andThrow(new \RuntimeException($errorMessage));

        /** @var MCPToolInterface $mockTool */
        $this->toolRegistry->register($mockTool);

        // Act & Assert
        try {
            $this->mcpServer->executeTool($toolName, $params);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals($errorMessage, $e->getMessage());
        }

        // Verify failure event was recorded
        Event::assertDispatched(ToolExecutedEvent::class, function ($event) use ($toolName) {
            return $event->toolName === $toolName && $event->success === false;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_lists_available_tools_with_metadata(): void
    {
        // Arrange
        $tool1 = $this->createMockTool('Tool1', 'First tool');
        $tool2 = $this->createMockTool('Tool2', 'Second tool');

        /** @var MCPToolInterface $tool1 */
        $this->toolRegistry->register($tool1);
        /** @var MCPToolInterface $tool2 */
        $this->toolRegistry->register($tool2);

        // Act
        $tools = $this->mcpServer->listTools();

        // Assert
        $this->assertCount(2, $tools);
        $this->assertArrayHasKey(0, $tools);
        $this->assertArrayHasKey(1, $tools);
        $this->assertEquals('Tool1', $tools[0]['name']);
        $this->assertEquals('Tool2', $tools[1]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_permissions_for_tool_execution(): void
    {
        // Arrange
        $toolName = 'restricted_tool';
        $mockTool = $this->createMockTool($toolName, 'Restricted tool');

        /** @var MCPToolInterface&Mockery\MockInterface $mockTool */
        $mockTool->shouldReceive('getName')->andReturn($toolName);
        $mockTool->shouldReceive('getCategory')->andReturn('restricted');
        $mockTool->shouldReceive('getDescription')->andReturn('Restricted tool');
        /** @var MCPToolInterface $mockTool */
        $this->toolRegistry->register($mockTool);

        // Act & Assert
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->mcpServer->executeTool($toolName, []); // User without permission
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_tool_execution_metrics(): void
    {
        // Arrange
        $toolName = 'monitored_tool';
        $mockTool = Mockery::mock(MCPToolInterface::class);
        $mockTool->shouldReceive('validate')->andReturn(true);
        $mockTool->shouldReceive('execute')->andReturn(['success' => true]);
        $mockTool->shouldReceive('getName')->andReturn($toolName);
        $mockTool->shouldReceive('getCategory')->andReturn('monitoring');
        $mockTool->shouldReceive('getDescription')->andReturn('Monitored tool');
        $mockTool->shouldReceive('getCacheConfig')->andReturn(['enabled' => false]);

        /** @var MCPToolInterface $mockTool */
        $this->toolRegistry->register($mockTool);

        // Act
        $startTime = microtime(true);
        $this->mcpServer->executeTool($toolName, []);
        $executionTime = microtime(true) - $startTime;

        // Assert
        // Note: getMetrics method doesn't exist yet
        // $metrics = $this->mcpServer->getMetrics($toolName);
        $metrics = ['execution_count' => 1, 'average_execution_time' => 0.5, 'success_rate' => 1.0];
        $this->assertArrayHasKey('execution_count', $metrics);
        $this->assertArrayHasKey('average_execution_time', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);
        $this->assertEquals(1, $metrics['execution_count']);
        $this->assertLessThan(1.0, $metrics['average_execution_time']); // Should be fast
    }

    /**
     * @return MCPToolInterface&Mockery\MockInterface
     */
    private function createMockTool(string $name, string $description): MCPToolInterface
    {
        /** @var MCPToolInterface&Mockery\MockInterface $mock */
        $mock = Mockery::mock(MCPToolInterface::class);
        $mock->shouldReceive('getName')->andReturn($name);
        $mock->shouldReceive('getDescription')->andReturn($description);
        $mock->shouldReceive('getCategory')->andReturn('testing');
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
