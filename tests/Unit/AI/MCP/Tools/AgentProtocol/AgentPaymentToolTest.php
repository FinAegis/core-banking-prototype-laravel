<?php

declare(strict_types=1);

namespace Tests\Unit\AI\MCP\Tools\AgentProtocol;

use App\Domain\AI\MCP\Tools\AgentProtocol\AgentPaymentTool;
use App\Domain\AI\Services\AIAgentProtocolBridgeService;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Exception;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for AgentPaymentTool MCP Tool.
 *
 * Tests the AI-to-AI payment functionality through MCP.
 */
class AgentPaymentToolTest extends TestCase
{
    private AgentPaymentTool $tool;

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

        $this->tool = new AgentPaymentTool($this->bridgeServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_tool_has_correct_name(): void
    {
        $this->assertEquals('agent_protocol.payment', $this->tool->getName());
    }

    public function test_tool_has_correct_category(): void
    {
        $this->assertEquals('agent_protocol', $this->tool->getCategory());
    }

    public function test_tool_has_description(): void
    {
        $description = $this->tool->getDescription();
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('payment', strtolower($description));
    }

    public function test_input_schema_has_required_fields(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('from_agent', $schema['properties']);
        $this->assertArrayHasKey('to_agent', $schema['properties']);
        $this->assertArrayHasKey('amount', $schema['properties']);
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('from_agent', $schema['required']);
        $this->assertContains('to_agent', $schema['required']);
        $this->assertContains('amount', $schema['required']);
    }

    public function test_output_schema_has_expected_fields(): void
    {
        $schema = $this->tool->getOutputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('transaction_id', $schema['properties']);
        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertArrayHasKey('from_did', $schema['properties']);
        $this->assertArrayHasKey('to_did', $schema['properties']);
        $this->assertArrayHasKey('fees', $schema['properties']);
    }

    public function test_can_execute_payment_between_agents(): void
    {
        // Arrange
        $fromAgent = 'buyer-agent';
        $toAgent = 'seller-agent';
        $amount = 100.00;
        $currency = 'USD';
        $purpose = 'service_payment';
        $transactionId = 'txn-' . Str::uuid()->toString();

        $this->bridgeServiceMock
            ->shouldReceive('registerAIAgent')
            ->with($fromAgent)
            ->once()
            ->andReturn(['did' => 'did:agent:buyer']);

        $this->bridgeServiceMock
            ->shouldReceive('registerAIAgent')
            ->with($toAgent)
            ->once()
            ->andReturn(['did' => 'did:agent:seller']);

        $this->bridgeServiceMock
            ->shouldReceive('initiateAIAgentPayment')
            ->with($fromAgent, $toAgent, $amount, $currency, $purpose)
            ->once()
            ->andReturn([
                'transaction_id' => $transactionId,
                'status'         => 'initiated',
                'from_did'       => 'did:agent:buyer',
                'to_did'         => 'did:agent:seller',
                'amount'         => $amount,
                'currency'       => $currency,
                'fees'           => 2.50,
            ]);

        // Act
        $result = $this->tool->execute([
            'from_agent' => $fromAgent,
            'to_agent'   => $toAgent,
            'amount'     => $amount,
            'currency'   => $currency,
            'purpose'    => $purpose,
        ]);

        // Assert
        $this->assertInstanceOf(ToolExecutionResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals($transactionId, $data['transaction_id']);
        $this->assertEquals('initiated', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertEquals(102.50, $data['total_amount']);
    }

    public function test_uses_default_currency_when_not_specified(): void
    {
        // Arrange
        $this->bridgeServiceMock
            ->shouldReceive('registerAIAgent')
            ->twice();

        $this->bridgeServiceMock
            ->shouldReceive('initiateAIAgentPayment')
            ->with('agent1', 'agent2', 50.00, 'USD', 'payment')
            ->once()
            ->andReturn([
                'transaction_id' => 'txn-123',
                'status'         => 'initiated',
                'fees'           => 1.25,
            ]);

        // Act
        $result = $this->tool->execute([
            'from_agent' => 'agent1',
            'to_agent'   => 'agent2',
            'amount'     => 50.00,
        ]);

        // Assert
        $this->assertTrue($result->isSuccess());
    }

    public function test_validates_required_from_agent(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'to_agent' => 'agent2',
            'amount'   => 100.00,
        ]));
    }

    public function test_validates_required_to_agent(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'from_agent' => 'agent1',
            'amount'     => 100.00,
        ]));
    }

    public function test_validates_positive_amount(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'from_agent' => 'agent1',
            'to_agent'   => 'agent2',
            'amount'     => 0,
        ]));

        $this->assertFalse($this->tool->validateInput([
            'from_agent' => 'agent1',
            'to_agent'   => 'agent2',
            'amount'     => -10,
        ]));
    }

    public function test_validates_different_agents(): void
    {
        $this->assertFalse($this->tool->validateInput([
            'from_agent' => 'same-agent',
            'to_agent'   => 'same-agent',
            'amount'     => 100.00,
        ]));
    }

    public function test_validates_currency_format(): void
    {
        // Valid currency
        $this->assertTrue($this->tool->validateInput([
            'from_agent' => 'agent1',
            'to_agent'   => 'agent2',
            'amount'     => 100.00,
            'currency'   => 'USD',
        ]));

        // Invalid currency (lowercase)
        $this->assertFalse($this->tool->validateInput([
            'from_agent' => 'agent1',
            'to_agent'   => 'agent2',
            'amount'     => 100.00,
            'currency'   => 'usd',
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
        $this->assertContains('transactional', $capabilities);
        $this->assertContains('agent-to-agent', $capabilities);
    }

    public function test_authorize_allows_all_requests(): void
    {
        $this->assertTrue($this->tool->authorize(null));
        $this->assertTrue($this->tool->authorize('user-123'));
    }

    public function test_handles_execution_errors_gracefully(): void
    {
        // Arrange
        /** @phpstan-ignore-next-line */
        $this->bridgeServiceMock
            ->shouldReceive('registerAIAgent')
            ->andThrow(new Exception('Registration failed'));

        // Act
        $result = $this->tool->execute([
            'from_agent' => 'agent1',
            'to_agent'   => 'agent2',
            'amount'     => 100.00,
        ]);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Registration failed', $result->getError());
    }
}
