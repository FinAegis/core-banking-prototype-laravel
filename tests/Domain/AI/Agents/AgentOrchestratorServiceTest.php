<?php

declare(strict_types=1);

namespace Tests\Domain\AI\Agents;

use App\Domain\AI\Agents\GeneralAgent;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\Services\AgentOrchestratorService;
use App\Domain\AI\Services\AgentResponseComposerService;
use App\Domain\AI\Services\AgentRouterService;
use App\Domain\AI\Services\LLMOrchestrationService;
use Mockery;
use Tests\TestCase;

class AgentOrchestratorServiceTest extends TestCase
{
    private AgentOrchestratorService $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $toolRegistry = Mockery::mock(ToolRegistry::class);
        $llmService = Mockery::mock(LLMOrchestrationService::class);

        $router = new AgentRouterService();
        $router->registerAgent(new GeneralAgent($toolRegistry, $llmService)); // @phpstan-ignore argument.type, argument.type

        $composer = new AgentResponseComposerService();

        $this->orchestrator = new AgentOrchestratorService($router, $composer, $llmService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_process_returns_correct_structure(): void
    {
        $response = $this->orchestrator->process('What can you do?');

        $this->assertArrayHasKey('message_id', $response);
        $this->assertArrayHasKey('content', $response);
        $this->assertArrayHasKey('confidence', $response);
        $this->assertArrayHasKey('tools_used', $response);
        $this->assertArrayHasKey('agents_used', $response);
        $this->assertArrayHasKey('response_time_ms', $response);
    }

    public function test_process_returns_uuid_message_id(): void
    {
        $response = $this->orchestrator->process('Hello');

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $response['message_id']
        );
    }

    public function test_process_returns_non_empty_content(): void
    {
        $response = $this->orchestrator->process('What are your capabilities?');

        $this->assertNotEmpty($response['content']);
    }

    public function test_process_returns_general_agent_for_capabilities_query(): void
    {
        $response = $this->orchestrator->process('What can you do?');

        $this->assertContains('General Assistant', $response['agents_used']);
    }

    public function test_process_includes_response_time(): void
    {
        $response = $this->orchestrator->process('Hello');

        $this->assertIsInt($response['response_time_ms']);
        $this->assertGreaterThanOrEqual(0, $response['response_time_ms']);
    }
}
