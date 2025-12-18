<?php

declare(strict_types=1);

namespace Tests\Unit\AI\MCP\Tools\AgentProtocol;

use App\Domain\AI\MCP\Tools\AgentProtocol\AgentReputationTool;
use App\Domain\AI\Services\AIAgentProtocolBridgeService;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Exception;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for AgentReputationTool MCP Tool.
 *
 * Tests the AI agent reputation query functionality through MCP.
 */
class AgentReputationToolTest extends TestCase
{
    private AgentReputationTool $tool;

    /** @var AIAgentProtocolBridgeService&MockInterface */
    private MockInterface $bridgeServiceMock;

    protected function shouldCreateDefaultAccountsInSetup(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var AIAgentProtocolBridgeService&MockInterface $bridgeServiceMock */
        $bridgeServiceMock = Mockery::mock(AIAgentProtocolBridgeService::class);
        $this->bridgeServiceMock = $bridgeServiceMock;

        $this->tool = new AgentReputationTool($this->bridgeServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_tool_has_correct_name(): void
    {
        $this->assertEquals('agent_protocol.reputation', $this->tool->getName());
    }

    public function test_tool_has_correct_category(): void
    {
        $this->assertEquals('agent_protocol', $this->tool->getCategory());
    }

    public function test_tool_has_description(): void
    {
        $description = $this->tool->getDescription();
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('reputation', strtolower($description));
    }

    public function test_input_schema_has_action_field(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('action', $schema['properties']);
        $this->assertContains('get_reputation', $schema['properties']['action']['enum']);
        $this->assertContains('check_threshold', $schema['properties']['action']['enum']);
        $this->assertContains('calculate_trust', $schema['properties']['action']['enum']);
        $this->assertContains('discover_agents', $schema['properties']['action']['enum']);
    }

    public function test_output_schema_has_expected_fields(): void
    {
        $schema = $this->tool->getOutputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('score', $schema['properties']);
        $this->assertArrayHasKey('level', $schema['properties']);
        $this->assertArrayHasKey('trust_score', $schema['properties']);
    }

    public function test_can_get_agent_reputation(): void
    {
        // Arrange
        $agentName = 'test-agent';

        $this->bridgeServiceMock
            ->shouldReceive('getAIAgentReputation')
            ->with($agentName)
            ->once()
            ->andReturn([
                'score'             => 85.0,
                'level'             => 'trusted',
                'transaction_count' => 42,
            ]);

        // Act
        $result = $this->tool->execute([
            'action'     => 'get_reputation',
            'agent_name' => $agentName,
        ]);

        // Assert
        $this->assertInstanceOf(ToolExecutionResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals('get_reputation', $data['action']);
        $this->assertEquals($agentName, $data['agent_name']);
        $this->assertEquals(85.0, $data['score']);
        $this->assertEquals('trusted', $data['level']);
        $this->assertEquals(42, $data['transaction_count']);
        $this->assertArrayHasKey('queried_at', $data);
    }

    public function test_can_check_reputation_threshold(): void
    {
        // Arrange
        $agentName = 'threshold-agent';
        $thresholdType = 'premium';

        $this->bridgeServiceMock
            ->shouldReceive('meetsReputationThreshold')
            ->with($agentName, $thresholdType)
            ->once()
            ->andReturn(true);

        $this->bridgeServiceMock
            ->shouldReceive('getAIAgentReputation')
            ->with($agentName)
            ->once()
            ->andReturn([
                'score' => 90.0,
                'level' => 'premium',
            ]);

        // Act
        $result = $this->tool->execute([
            'action'         => 'check_threshold',
            'agent_name'     => $agentName,
            'threshold_type' => $thresholdType,
        ]);

        // Assert
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals('check_threshold', $data['action']);
        $this->assertTrue($data['meets_threshold']);
        $this->assertEquals(90.0, $data['current_score']);
    }

    public function test_can_calculate_trust_between_agents(): void
    {
        // Arrange
        $agent1 = 'agent-one';
        $agent2 = 'agent-two';

        $this->bridgeServiceMock
            ->shouldReceive('registerAIAgent')
            ->with($agent1)
            ->once();

        $this->bridgeServiceMock
            ->shouldReceive('registerAIAgent')
            ->with($agent2)
            ->once();

        $this->bridgeServiceMock
            ->shouldReceive('calculateTrustBetweenAIAgents')
            ->with($agent1, $agent2)
            ->once()
            ->andReturn([
                'trust_score'    => 0.85,
                'recommendation' => 'trusted',
            ]);

        // Act
        $result = $this->tool->execute([
            'action'           => 'calculate_trust',
            'agent_name'       => $agent1,
            'other_agent_name' => $agent2,
        ]);

        // Assert
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals('calculate_trust', $data['action']);
        $this->assertEquals($agent1, $data['agent_name']);
        $this->assertEquals($agent2, $data['other_agent']);
        $this->assertEquals(0.85, $data['trust_score']);
        $this->assertEquals('trusted', $data['recommendation']);
    }

    public function test_can_discover_agents_by_capability(): void
    {
        // Arrange
        $capabilities = ['automated_payments', 'escrow_transactions'];

        $this->bridgeServiceMock
            ->shouldReceive('discoverAIAgents')
            ->with($capabilities)
            ->once()
            ->andReturn(new Collection([
                ['did' => 'did:agent:1', 'name' => 'agent-1', 'capabilities' => $capabilities],
                ['did' => 'did:agent:2', 'name' => 'agent-2', 'capabilities' => $capabilities],
            ]));

        // Act
        $result = $this->tool->execute([
            'action'       => 'discover_agents',
            'capabilities' => $capabilities,
        ]);

        // Assert
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals('discover_agents', $data['action']);
        $this->assertCount(2, $data['agents']);
        $this->assertEquals(2, $data['count']);
    }

    public function test_can_filter_discovered_agents_by_reputation(): void
    {
        // Arrange
        $capabilities = ['payments'];
        $minReputation = 70;

        $this->bridgeServiceMock
            ->shouldReceive('discoverAIAgents')
            ->with($capabilities)
            ->once()
            ->andReturn(new Collection([
                ['did' => 'did:agent:1', 'name' => 'high-rep-agent'],
                ['did' => 'did:agent:2', 'name' => 'low-rep-agent'],
            ]));

        // First agent has high reputation
        $this->bridgeServiceMock
            ->shouldReceive('getAIAgentReputation')
            ->with('high-rep-agent')
            ->once()
            ->andReturn(['score' => 85.0]);

        // Second agent has low reputation
        $this->bridgeServiceMock
            ->shouldReceive('getAIAgentReputation')
            ->with('low-rep-agent')
            ->once()
            ->andReturn(['score' => 50.0]);

        // Act
        $result = $this->tool->execute([
            'action'         => 'discover_agents',
            'capabilities'   => $capabilities,
            'min_reputation' => $minReputation,
        ]);

        // Assert
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals(1, $data['count']); // Only high-rep agent
    }

    public function test_get_reputation_requires_agent_name(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_reputation',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('agent_name', $result->getError());
    }

    public function test_calculate_trust_requires_both_agents(): void
    {
        $result = $this->tool->execute([
            'action'     => 'calculate_trust',
            'agent_name' => 'agent1',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('other_agent_name', $result->getError());
    }

    public function test_validates_action_is_required(): void
    {
        $this->assertFalse($this->tool->validateInput([]));
    }

    public function test_validates_get_reputation_requires_agent_name(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'action' => 'get_reputation',
        ]));

        $this->assertTrue($this->tool->validateInput([
            'action'     => 'get_reputation',
            'agent_name' => 'test-agent',
        ]));
    }

    public function test_validates_calculate_trust_requires_different_agents(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'action'           => 'calculate_trust',
            'agent_name'       => 'same-agent',
            'other_agent_name' => 'same-agent',
        ]));
    }

    public function test_validates_threshold_type(): void
    {
        $this->assertTrue($this->tool->validateInput([
            'action'         => 'check_threshold',
            'agent_name'     => 'test-agent',
            'threshold_type' => 'standard',
        ]));

        $this->assertFalse($this->tool->validateInput([
            'action'         => 'check_threshold',
            'agent_name'     => 'test-agent',
            'threshold_type' => 'invalid_type',
        ]));
    }

    public function test_validates_min_reputation_range(): void
    {
        $this->assertTrue($this->tool->validateInput([
            'action'         => 'discover_agents',
            'min_reputation' => 50,
        ]));

        $this->assertFalse($this->tool->validateInput([
            'action'         => 'discover_agents',
            'min_reputation' => -10,
        ]));

        $this->assertFalse($this->tool->validateInput([
            'action'         => 'discover_agents',
            'min_reputation' => 150,
        ]));
    }

    public function test_tool_is_cacheable(): void
    {
        $this->assertTrue($this->tool->isCacheable());
        $this->assertEquals(300, $this->tool->getCacheTtl()); // 5 minutes
    }

    public function test_tool_has_expected_capabilities(): void
    {
        $capabilities = $this->tool->getCapabilities();

        $this->assertContains('read', $capabilities);
        $this->assertContains('reputation-query', $capabilities);
        $this->assertContains('trust-calculation', $capabilities);
        $this->assertContains('agent-discovery', $capabilities);
    }

    public function test_authorize_allows_all_requests(): void
    {
        $this->assertTrue($this->tool->authorize(null));
        $this->assertTrue($this->tool->authorize('user-123'));
    }

    public function test_handles_unknown_action(): void
    {
        $result = $this->tool->execute([
            'action' => 'invalid_action',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Unknown action', $result->getError());
    }

    public function test_handles_execution_errors_gracefully(): void
    {
        /** @phpstan-ignore-next-line */
        $this->bridgeServiceMock
            ->shouldReceive('getAIAgentReputation')
            ->andThrow(new Exception('Service unavailable'));

        $result = $this->tool->execute([
            'action'     => 'get_reputation',
            'agent_name' => 'test-agent',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Service unavailable', $result->getError());
    }
}
