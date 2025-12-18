<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Middleware;

use App\Http\Middleware\CheckAgentScope;
use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckAgentScopeTest extends TestCase
{
    use RefreshDatabase;

    private CheckAgentScope $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckAgentScope();
    }

    public function test_allows_agent_with_required_scope(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);
        $request = $this->createRequestWithAgent($agent, ['payments:read']);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:read');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allows_agent_with_universal_scope(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);
        $request = $this->createRequestWithAgent($agent, ['*']);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'any:scope');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allows_agent_with_category_wildcard_scope(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);
        $request = $this->createRequestWithAgent($agent, ['payments:*']);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:create');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_agent_without_required_scope(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);
        $request = $this->createRequestWithAgent($agent, ['payments:read']);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'escrow:create');

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Forbidden', $data['error']);
        $this->assertEquals('SCOPE_REQUIRED', $data['code']);
        $this->assertStringContainsString('escrow:create', $data['message']);
    }

    public function test_rejects_request_without_authenticated_agent(): void
    {
        // Arrange
        $request = Request::create('/api/test', 'GET');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:read');

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertEquals('AGENT_NOT_AUTHENTICATED', $data['code']);
    }

    public function test_checks_multiple_required_scopes(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);
        $request = $this->createRequestWithAgent($agent, ['payments:read']);

        // Act - require both payments:read and escrow:read
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:read', 'escrow:read');

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertStringContainsString('escrow:read', $data['message']);
    }

    public function test_allows_agent_with_all_required_scopes(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);
        $request = $this->createRequestWithAgent($agent, ['payments:read', 'escrow:read', 'wallet:read']);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:read', 'escrow:read');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handles_comma_separated_scopes(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);
        $request = $this->createRequestWithAgent($agent, ['payments:read', 'escrow:read']);

        // Act - scopes as comma-separated string
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:read,escrow:read');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allows_empty_scopes_for_backward_compatibility(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);
        $request = $this->createRequestWithAgent($agent, []);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:read');

        // Assert - Empty scopes means all allowed for backward compatibility
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_validates_payment_scopes(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        // Test various payment scopes
        $testCases = [
            ['available' => ['payments:read'], 'required' => 'payments:read', 'allowed' => true],
            ['available' => ['payments:create'], 'required' => 'payments:create', 'allowed' => true],
            ['available' => ['payments:*'], 'required' => 'payments:cancel', 'allowed' => true],
            ['available' => ['payments:read'], 'required' => 'payments:create', 'allowed' => false],
        ];

        foreach ($testCases as $case) {
            $request = $this->createRequestWithAgent($agent, $case['available']);
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            }, $case['required']);

            $expectedStatus = $case['allowed'] ? 200 : 403;
            $this->assertEquals(
                $expectedStatus,
                $response->getStatusCode(),
                sprintf(
                    'Failed for available: %s, required: %s',
                    implode(',', $case['available']),
                    $case['required']
                )
            );
        }
    }

    public function test_validates_messaging_scopes(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        // Test messaging scopes
        $testCases = [
            ['available' => ['messages:read'], 'required' => 'messages:read', 'allowed' => true],
            ['available' => ['messages:send'], 'required' => 'messages:send', 'allowed' => true],
            ['available' => ['messages:*'], 'required' => 'messages:write', 'allowed' => true],
            ['available' => ['messages:read'], 'required' => 'messages:send', 'allowed' => false],
        ];

        foreach ($testCases as $case) {
            $request = $this->createRequestWithAgent($agent, $case['available']);
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            }, $case['required']);

            $expectedStatus = $case['allowed'] ? 200 : 403;
            $this->assertEquals(
                $expectedStatus,
                $response->getStatusCode(),
                sprintf(
                    'Failed for available: %s, required: %s',
                    implode(',', $case['available']),
                    $case['required']
                )
            );
        }
    }

    public function test_validates_escrow_scopes(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        // Test escrow scopes
        $testCases = [
            ['available' => ['escrow:read'], 'required' => 'escrow:read', 'allowed' => true],
            ['available' => ['escrow:create'], 'required' => 'escrow:create', 'allowed' => true],
            ['available' => ['escrow:*'], 'required' => 'escrow:dispute', 'allowed' => true],
            ['available' => ['escrow:read'], 'required' => 'escrow:release', 'allowed' => false],
        ];

        foreach ($testCases as $case) {
            $request = $this->createRequestWithAgent($agent, $case['available']);
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            }, $case['required']);

            $expectedStatus = $case['allowed'] ? 200 : 403;
            $this->assertEquals(
                $expectedStatus,
                $response->getStatusCode(),
                sprintf(
                    'Failed for available: %s, required: %s',
                    implode(',', $case['available']),
                    $case['required']
                )
            );
        }
    }

    public function test_validates_reputation_scopes(): void
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        // Test reputation scopes
        $testCases = [
            ['available' => ['reputation:read'], 'required' => 'reputation:read', 'allowed' => true],
            ['available' => ['reputation:review'], 'required' => 'reputation:review', 'allowed' => true],
            ['available' => ['reputation:*'], 'required' => 'reputation:write', 'allowed' => true],
            ['available' => ['reputation:read'], 'required' => 'reputation:review', 'allowed' => false],
        ];

        foreach ($testCases as $case) {
            $request = $this->createRequestWithAgent($agent, $case['available']);
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            }, $case['required']);

            $expectedStatus = $case['allowed'] ? 200 : 403;
            $this->assertEquals(
                $expectedStatus,
                $response->getStatusCode(),
                sprintf(
                    'Failed for available: %s, required: %s',
                    implode(',', $case['available']),
                    $case['required']
                )
            );
        }
    }

    /**
     * Helper to create request with authenticated agent and scopes.
     *
     * @param array<string> $scopes
     */
    private function createRequestWithAgent(Agent $agent, array $scopes): Request
    {
        $request = Request::create('/api/test', 'GET');
        $request->attributes->set('agent', $agent);
        $request->attributes->set('authenticated_agent', ['scopes' => $scopes]);

        return $request;
    }
}
