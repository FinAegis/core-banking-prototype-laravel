<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Middleware;

use App\Domain\AgentProtocol\Services\AgentAuthenticationService;
use App\Http\Middleware\AuthenticateAgentDID;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticateAgentDIDTest extends TestCase
{
    private AuthenticateAgentDID $middleware;

    /** @var AgentAuthenticationService&MockInterface */
    private AgentAuthenticationService|MockInterface $authService;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var AgentAuthenticationService&MockInterface $mock */
        $mock = Mockery::mock(AgentAuthenticationService::class);
        $this->authService = $mock;
        $this->middleware = new AuthenticateAgentDID(
            $this->authService // @phpstan-ignore argument.type
        );

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_authenticates_with_valid_session_token()
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        $this->authService->shouldReceive('validateSession')
            ->with('valid-session-token')
            ->andReturn([
                'valid' => true,
                'agent' => $agent,
                'error' => null,
            ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Agent-Session', 'valid-session-token');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($agent, $request->attributes->get('agent'));
    }

    #[Test]
    public function it_authenticates_with_valid_api_key()
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        $this->authService->shouldReceive('validateSession')
            ->andReturn(['valid' => false]);

        $this->authService->shouldReceive('authenticateWithApiKey')
            ->with('test-api-key')
            ->andReturn([
                'success' => true,
                'agent'   => $agent,
                'scopes'  => ['payments:read'],
                'error'   => null,
            ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'AgentKey test-api-key');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($agent, $request->attributes->get('agent'));
    }

    #[Test]
    public function it_authenticates_with_did_signature()
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        $this->authService->shouldReceive('validateSession')
            ->with(null)
            ->andReturn(['valid' => false]);

        $this->authService->shouldReceive('authenticateWithDID')
            ->with('did:agent:test:alice', 'signature', 'challenge', 'nonce')
            ->andReturn([
                'success' => true,
                'agent'   => $agent,
                'error'   => null,
            ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Agent-DID', 'did:agent:test:alice');
        $request->headers->set('X-Agent-Signature', 'signature');
        $request->headers->set('X-Agent-Challenge', 'challenge');
        $request->headers->set('X-Agent-Nonce', 'nonce');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($agent, $request->attributes->get('agent'));
    }

    #[Test]
    public function it_rejects_request_without_credentials()
    {
        // Arrange
        $this->authService->shouldReceive('validateSession')
            ->andReturn(['valid' => false]);

        $request = Request::create('/api/test', 'GET');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('No authentication credentials', $data['message']);
    }

    #[Test]
    public function it_rejects_invalid_session_token()
    {
        // Arrange
        $this->authService->shouldReceive('validateSession')
            ->with('invalid-token')
            ->andReturn([
                'valid' => false,
                'agent' => null,
                'error' => 'Invalid session',
            ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Agent-Session', 'invalid-token');

        // Act - Session invalid, but no other auth method provided
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_invalid_api_key()
    {
        // Arrange
        $this->authService->shouldReceive('validateSession')
            ->andReturn(['valid' => false]);

        $this->authService->shouldReceive('authenticateWithApiKey')
            ->with('invalid-key')
            ->andReturn([
                'success' => false,
                'agent'   => null,
                'error'   => 'Invalid API key',
            ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'AgentKey invalid-key');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Invalid API key', $data['message']);
    }

    #[Test]
    public function it_rejects_inactive_agent()
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'suspended']);

        $this->authService->shouldReceive('validateSession')
            ->with('valid-token')
            ->andReturn([
                'valid' => true,
                'agent' => $agent,
                'error' => null,
            ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Agent-Session', 'valid-token');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Forbidden', $data['error']);
        $this->assertEquals('Agent is not active', $data['message']);
    }

    #[Test]
    public function it_checks_required_scope()
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        $this->authService->shouldReceive('validateSession')
            ->andReturn(['valid' => false]);

        $this->authService->shouldReceive('authenticateWithApiKey')
            ->andReturn([
                'success' => true,
                'agent'   => $agent,
                'scopes'  => ['payments:read'], // Only has read scope
                'error'   => null,
            ]);

        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'AgentKey test-key');

        // Act - Require payments:create scope
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:create');

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertStringContainsString('Missing required scope', $data['message']);
    }

    #[Test]
    public function it_allows_request_with_wildcard_scope()
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        $this->authService->shouldReceive('validateSession')
            ->andReturn(['valid' => false]);

        $this->authService->shouldReceive('authenticateWithApiKey')
            ->andReturn([
                'success' => true,
                'agent'   => $agent,
                'scopes'  => ['payments:*'], // Has wildcard payments scope
                'error'   => null,
            ]);

        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'AgentKey test-key');

        // Act - Require payments:create scope
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'payments:create');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_sets_agent_attributes_on_request()
    {
        // Arrange
        $agent = Agent::factory()->create(['status' => 'active']);

        $this->authService->shouldReceive('validateSession')
            ->with('valid-token')
            ->andReturn([
                'valid' => true,
                'agent' => $agent,
                'error' => null,
            ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Agent-Session', 'valid-token');

        // Act
        $this->middleware->handle($request, function ($req) use ($agent) {
            // Assert inside callback
            $this->assertEquals($agent, $req->attributes->get('agent'));
            $this->assertEquals($agent->did, $req->attributes->get('agent_did'));
            $this->assertEquals('session', $req->get('agent_auth_method'));

            return response()->json(['success' => true]);
        });
    }
}
