<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Middleware;

use App\Http\Middleware\CheckAgentCapability;
use App\Models\Agent;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckAgentCapabilityTest extends TestCase
{
    private CheckAgentCapability $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckAgentCapability();
    }

    #[Test]
    public function it_allows_agent_with_required_capability()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => ['payments', 'escrow'],
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_allows_agent_with_wildcard_capability()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => ['*'],
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'anything');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_allows_agent_with_hierarchical_wildcard()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => ['payments:*'],
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:advanced');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_agent_without_required_capability()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => ['payments'],
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'escrow');

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Forbidden', $data['error']);
        $this->assertStringContainsString('escrow', $data['message']);
    }

    #[Test]
    public function it_rejects_request_without_authenticated_agent()
    {
        // Arrange
        $request = Request::create('/api/test', 'GET');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments');

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
    }

    #[Test]
    public function it_checks_multiple_required_capabilities()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => ['payments'],
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act - require both payments and escrow
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments', 'escrow');

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertStringContainsString('escrow', $data['message']);
    }

    #[Test]
    public function it_allows_agent_with_all_required_capabilities()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => ['payments', 'escrow', 'governance'],
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments', 'escrow');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_comma_separated_capabilities()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => ['payments', 'escrow'],
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act - capabilities as comma-separated string
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments,escrow');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_empty_capabilities_array()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => [],
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments');

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_null_capabilities()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status'       => 'active',
            'capabilities' => null,
        ]);

        $request = $this->createRequestWithAgent($agent);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments');

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Helper to create request with authenticated agent.
     */
    private function createRequestWithAgent(Agent $agent): Request
    {
        $request = Request::create('/api/test', 'GET');
        $request->attributes->set('agent', $agent);

        return $request;
    }
}
