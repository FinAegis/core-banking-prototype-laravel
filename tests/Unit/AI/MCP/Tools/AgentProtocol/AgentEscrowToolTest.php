<?php

declare(strict_types=1);

namespace Tests\Unit\AI\MCP\Tools\AgentProtocol;

use App\Domain\AI\MCP\Tools\AgentProtocol\AgentEscrowTool;
use App\Domain\AI\Services\AIAgentProtocolBridgeService;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Exception;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for AgentEscrowTool MCP Tool.
 *
 * Tests the AI agent escrow functionality through MCP.
 */
class AgentEscrowToolTest extends TestCase
{
    private AgentEscrowTool $tool;

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

        $this->tool = new AgentEscrowTool($this->bridgeServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_tool_has_correct_name(): void
    {
        $this->assertEquals('agent_protocol.escrow', $this->tool->getName());
    }

    public function test_tool_has_correct_category(): void
    {
        $this->assertEquals('agent_protocol', $this->tool->getCategory());
    }

    public function test_tool_has_description(): void
    {
        $description = $this->tool->getDescription();
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('escrow', strtolower($description));
    }

    public function test_input_schema_has_action_field(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('action', $schema['properties']);
        $this->assertContains('create', $schema['properties']['action']['enum']);
        $this->assertContains('status', $schema['properties']['action']['enum']);
        $this->assertContains('release', $schema['properties']['action']['enum']);
        $this->assertContains('dispute', $schema['properties']['action']['enum']);
    }

    public function test_output_schema_has_expected_fields(): void
    {
        $schema = $this->tool->getOutputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('escrow_id', $schema['properties']);
        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertArrayHasKey('buyer_did', $schema['properties']);
        $this->assertArrayHasKey('seller_did', $schema['properties']);
    }

    public function test_can_create_escrow(): void
    {
        // Arrange
        $buyerAgent = 'buyer-agent';
        $sellerAgent = 'seller-agent';
        $amount = 500.00;
        $conditions = ['delivery_confirmed', 'quality_approved'];
        $escrowId = 'escrow-' . Str::uuid()->toString();

        $this->bridgeServiceMock
            ->shouldReceive('registerAIAgent')
            ->with($buyerAgent)
            ->once();

        $this->bridgeServiceMock
            ->shouldReceive('registerAIAgent')
            ->with($sellerAgent)
            ->once();

        $this->bridgeServiceMock
            ->shouldReceive('createAIAgentEscrow')
            ->with($buyerAgent, $sellerAgent, $amount, $conditions, 604800)
            ->once()
            ->andReturn([
                'escrow_id'  => $escrowId,
                'buyer_did'  => 'did:agent:buyer',
                'seller_did' => 'did:agent:seller',
                'amount'     => $amount,
                'currency'   => 'USD',
                'conditions' => $conditions,
                'status'     => 'created',
            ]);

        // Act
        $result = $this->tool->execute([
            'action'       => 'create',
            'buyer_agent'  => $buyerAgent,
            'seller_agent' => $sellerAgent,
            'amount'       => $amount,
            'conditions'   => $conditions,
        ]);

        // Assert
        $this->assertInstanceOf(ToolExecutionResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals($escrowId, $data['escrow_id']);
        $this->assertEquals('create', $data['action']);
        $this->assertArrayHasKey('created_at', $data);
    }

    public function test_can_get_escrow_status(): void
    {
        // Arrange
        $escrowId = 'escrow-123';

        // Act
        $result = $this->tool->execute([
            'action'    => 'status',
            'escrow_id' => $escrowId,
        ]);

        // Assert
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals($escrowId, $data['escrow_id']);
        $this->assertEquals('status', $data['action']);
    }

    public function test_can_release_escrow(): void
    {
        // Arrange
        $escrowId = 'escrow-456';

        // Act
        $result = $this->tool->execute([
            'action'    => 'release',
            'escrow_id' => $escrowId,
        ]);

        // Assert
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals($escrowId, $data['escrow_id']);
        $this->assertEquals('release', $data['action']);
        $this->assertEquals('release_initiated', $data['status']);
        $this->assertArrayHasKey('released_at', $data);
    }

    public function test_can_dispute_escrow(): void
    {
        // Arrange
        $escrowId = 'escrow-789';
        $reason = 'Service not delivered as promised';

        // Act
        $result = $this->tool->execute([
            'action'         => 'dispute',
            'escrow_id'      => $escrowId,
            'dispute_reason' => $reason,
        ]);

        // Assert
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals($escrowId, $data['escrow_id']);
        $this->assertEquals('dispute', $data['action']);
        $this->assertEquals('dispute_filed', $data['status']);
        $this->assertEquals($reason, $data['reason']);
        $this->assertArrayHasKey('disputed_at', $data);
    }

    public function test_create_requires_buyer_and_seller(): void
    {
        // Missing seller
        $result = $this->tool->execute([
            'action'      => 'create',
            'buyer_agent' => 'buyer',
            'amount'      => 100.00,
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('required', $result->getError());
    }

    public function test_create_requires_amount(): void
    {
        $result = $this->tool->execute([
            'action'       => 'create',
            'buyer_agent'  => 'buyer',
            'seller_agent' => 'seller',
        ]);

        $this->assertFalse($result->isSuccess());
    }

    public function test_status_requires_escrow_id(): void
    {
        $result = $this->tool->execute([
            'action' => 'status',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('escrow_id', $result->getError());
    }

    public function test_validates_action_is_required(): void
    {
        $this->assertFalse($this->tool->validateInput([]));
    }

    public function test_validates_create_requires_different_agents(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'action'       => 'create',
            'buyer_agent'  => 'same-agent',
            'seller_agent' => 'same-agent',
            'amount'       => 100.00,
        ]));
    }

    public function test_validates_create_requires_positive_amount(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'action'       => 'create',
            'buyer_agent'  => 'buyer',
            'seller_agent' => 'seller',
            'amount'       => 0,
        ]));
    }

    public function test_validates_status_requires_escrow_id(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'action' => 'status',
        ]));

        $this->assertTrue($this->tool->validateInput([
            'action'    => 'status',
            'escrow_id' => 'escrow-123',
        ]));
    }

    public function test_tool_is_not_cacheable(): void
    {
        $this->assertFalse($this->tool->isCacheable());
        $this->assertEquals(0, $this->tool->getCacheTtl());
    }

    public function test_tool_has_expected_capabilities(): void
    {
        $capabilities = $this->tool->getCapabilities();

        $this->assertContains('write', $capabilities);
        $this->assertContains('read', $capabilities);
        $this->assertContains('condition-based', $capabilities);
        $this->assertContains('dispute-resolution', $capabilities);
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
            ->shouldReceive('registerAIAgent')
            ->andThrow(new Exception('Bridge service error'));

        $result = $this->tool->execute([
            'action'       => 'create',
            'buyer_agent'  => 'buyer',
            'seller_agent' => 'seller',
            'amount'       => 100.00,
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Bridge service error', $result->getError());
    }
}
