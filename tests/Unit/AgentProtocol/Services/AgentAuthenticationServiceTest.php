<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Services;

use App\Domain\AgentProtocol\Services\AgentAuthenticationService;
use App\Domain\AgentProtocol\Services\DIDService;
use App\Models\Agent;
use App\Models\AgentApiKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentAuthenticationServiceTest extends TestCase
{
    private AgentAuthenticationService $service;

    /** @var DIDService&MockInterface */
    private DIDService|MockInterface $didService;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var DIDService&MockInterface $mock */
        $mock = Mockery::mock(DIDService::class);
        $this->didService = $mock;

        $this->service = new AgentAuthenticationService(
            $this->didService // @phpstan-ignore argument.type
        );

        // Clear cache before each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_api_key_for_agent()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        // Act
        $result = $this->service->generateApiKey(
            $agent,
            'Test Key',
            ['payments:read', 'wallet:read'],
            now()->addDays(30)
        );

        // Assert
        $this->assertArrayHasKey('api_key', $result);
        $this->assertArrayHasKey('key_id', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertEquals(64, strlen($result['api_key']));
        $this->assertStringStartsWith('ak_', $result['key_id']);

        // Verify key was stored
        $storedKey = AgentApiKey::where('key_id', $result['key_id'])->first();
        $this->assertNotNull($storedKey);
        $this->assertEquals($agent->agent_id, $storedKey->agent_id);
        $this->assertEquals('Test Key', $storedKey->name);
        $this->assertEquals(['payments:read', 'wallet:read'], $storedKey->scopes);
        $this->assertTrue($storedKey->is_active);
    }

    #[Test]
    public function it_authenticates_with_valid_api_key()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $keyResult = $this->service->generateApiKey($agent, 'Test Key');
        $apiKey = $keyResult['api_key'];

        // Act
        $result = $this->service->authenticateWithApiKey($apiKey);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($agent->agent_id, $result['agent']->agent_id);
        $this->assertNotNull($result['session_token']);
        $this->assertNotNull($result['expires_at']);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function it_rejects_invalid_api_key()
    {
        // Act
        $result = $this->service->authenticateWithApiKey('invalid-api-key');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertNull($result['agent']);
        $this->assertNull($result['session_token']);
        $this->assertEquals('Invalid or expired API key', $result['error']);
    }

    #[Test]
    public function it_rejects_expired_api_key()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $apiKey = Str::random(64);
        AgentApiKey::create([
            'key_id'     => 'ak_' . Str::random(16),
            'agent_id'   => $agent->agent_id,
            'name'       => 'Expired Key',
            'key_hash'   => hash('sha256', $apiKey),
            'key_prefix' => substr($apiKey, 0, 8),
            'scopes'     => [],
            'is_active'  => true,
            'expires_at' => now()->subDay(), // Expired
        ]);

        // Act
        $result = $this->service->authenticateWithApiKey($apiKey);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid or expired API key', $result['error']);
    }

    #[Test]
    public function it_rejects_inactive_api_key()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $apiKey = Str::random(64);
        AgentApiKey::create([
            'key_id'     => 'ak_' . Str::random(16),
            'agent_id'   => $agent->agent_id,
            'name'       => 'Inactive Key',
            'key_hash'   => hash('sha256', $apiKey),
            'key_prefix' => substr($apiKey, 0, 8),
            'scopes'     => [],
            'is_active'  => false, // Inactive
        ]);

        // Act
        $result = $this->service->authenticateWithApiKey($apiKey);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid or expired API key', $result['error']);
    }

    #[Test]
    public function it_revokes_api_key()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $keyResult = $this->service->generateApiKey($agent, 'Test Key');

        // Act
        $revoked = $this->service->revokeApiKey($keyResult['key_id'], $agent->agent_id);

        // Assert
        $this->assertTrue($revoked);

        $key = AgentApiKey::where('key_id', $keyResult['key_id'])->first();
        $this->assertFalse($key->is_active);
        $this->assertNotNull($key->revoked_at);
    }

    #[Test]
    public function it_lists_api_keys_for_agent()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $this->service->generateApiKey($agent, 'Key 1', ['payments:read']);
        $this->service->generateApiKey($agent, 'Key 2', ['wallet:read']);

        // Act
        $keys = $this->service->listApiKeys($agent->agent_id);

        // Assert
        $this->assertCount(2, $keys);
        // Check that both keys exist (order may vary due to timestamp precision)
        $keyNames = array_column($keys, 'name');
        $this->assertContains('Key 1', $keyNames);
        $this->assertContains('Key 2', $keyNames);
    }

    #[Test]
    public function it_validates_session_token()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $keyResult = $this->service->generateApiKey($agent, 'Test Key');
        $authResult = $this->service->authenticateWithApiKey($keyResult['api_key']);

        // Act
        $result = $this->service->validateSession($authResult['session_token']);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals($agent->agent_id, $result['agent']->agent_id);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function it_rejects_invalid_session_token()
    {
        // Act
        $result = $this->service->validateSession('invalid-token');

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertNull($result['agent']);
        $this->assertEquals('Invalid or expired session', $result['error']);
    }

    #[Test]
    public function it_revokes_session()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $keyResult = $this->service->generateApiKey($agent, 'Test Key');
        $authResult = $this->service->authenticateWithApiKey($keyResult['api_key']);
        $sessionToken = $authResult['session_token'];

        // Act
        $revoked = $this->service->revokeSession($sessionToken);

        // Assert
        $this->assertTrue($revoked);

        // Verify session is no longer valid
        $result = $this->service->validateSession($sessionToken);
        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function it_generates_challenge_for_did_auth()
    {
        // Arrange
        $did = 'did:agent:test:alice';

        // Act
        $result = $this->service->generateChallenge($did);

        // Assert
        $this->assertArrayHasKey('challenge', $result);
        $this->assertArrayHasKey('nonce', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertEquals(32, strlen($result['nonce']));

        // Verify challenge is base64 encoded JSON
        $decoded = json_decode(base64_decode($result['challenge']), true);
        $this->assertEquals($did, $decoded['did']);
        $this->assertEquals($result['nonce'], $decoded['nonce']);
        $this->assertEquals('authenticate', $decoded['action']);
    }

    #[Test]
    public function it_authenticates_with_did_signature()
    {
        // Arrange
        $did = 'did:agent:test:alice';
        $challenge = 'test-challenge';
        $signature = 'test-signature';
        $nonce = 'test-nonce';

        // Create agent
        $agent = Agent::factory()->create([
            'did'    => $did,
            'status' => 'active',
        ]);

        // Store nonce in cache
        Cache::put('agent_auth:nonce:' . $nonce, ['did' => $did], 300);

        // Mock DID resolution
        $this->didService->shouldReceive('resolveDID')
            ->with($did)
            ->andReturn(['id' => $did, 'publicKey' => 'test-key']);

        // Mock signature verification
        $this->didService->shouldReceive('verifyDIDSignature')
            ->with($did, $signature, $challenge)
            ->andReturn(true);

        // Act
        $result = $this->service->authenticateWithDID($did, $signature, $challenge, $nonce);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($agent->agent_id, $result['agent']->agent_id);
        $this->assertNotNull($result['session_token']);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function it_rejects_did_auth_with_invalid_signature()
    {
        // Arrange
        $did = 'did:agent:test:alice';
        $challenge = 'test-challenge';
        $signature = 'invalid-signature';

        // Create agent
        Agent::factory()->create([
            'did'    => $did,
            'status' => 'active',
        ]);

        // Mock DID resolution
        $this->didService->shouldReceive('resolveDID')
            ->with($did)
            ->andReturn(['id' => $did, 'publicKey' => 'test-key']);

        // Mock signature verification - fails
        $this->didService->shouldReceive('verifyDIDSignature')
            ->with($did, $signature, $challenge)
            ->andReturn(false);

        // Act
        $result = $this->service->authenticateWithDID($did, $signature, $challenge);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid signature', $result['error']);
    }

    #[Test]
    public function it_rejects_did_auth_for_unregistered_agent()
    {
        // Arrange
        $did = 'did:agent:test:unknown';
        $challenge = 'test-challenge';
        $signature = 'test-signature';

        // Mock DID resolution
        $this->didService->shouldReceive('resolveDID')
            ->with($did)
            ->andReturn(['id' => $did, 'publicKey' => 'test-key']);

        // Act
        $result = $this->service->authenticateWithDID($did, $signature, $challenge);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Agent not registered', $result['error']);
    }

    #[Test]
    public function it_rejects_did_auth_for_inactive_agent()
    {
        // Arrange
        $did = 'did:agent:test:inactive';

        Agent::factory()->create([
            'did'    => $did,
            'status' => 'suspended',
        ]);

        // Mock DID resolution
        $this->didService->shouldReceive('resolveDID')
            ->with($did)
            ->andReturn(['id' => $did, 'publicKey' => 'test-key']);

        // Act
        $result = $this->service->authenticateWithDID($did, 'signature', 'challenge');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Agent is not active', $result['error']);
    }

    #[Test]
    public function it_gets_active_sessions_for_agent()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $keyResult = $this->service->generateApiKey($agent, 'Test Key');

        // Create multiple sessions by authenticating multiple times
        $this->service->authenticateWithApiKey($keyResult['api_key']);
        $this->service->authenticateWithApiKey($keyResult['api_key']);

        // Act
        $sessions = $this->service->getActiveSessions($agent->agent_id);

        // Assert
        $this->assertCount(2, $sessions);
        $this->assertArrayHasKey('session_id', $sessions[0]);
        $this->assertArrayHasKey('created_at', $sessions[0]);
        $this->assertArrayHasKey('expires_at', $sessions[0]);
    }

    #[Test]
    public function it_revokes_all_sessions_for_agent()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $keyResult = $this->service->generateApiKey($agent, 'Test Key');

        // Create multiple sessions
        $auth1 = $this->service->authenticateWithApiKey($keyResult['api_key']);
        $auth2 = $this->service->authenticateWithApiKey($keyResult['api_key']);

        // Act
        $count = $this->service->revokeAllSessions($agent->agent_id);

        // Assert
        $this->assertEquals(2, $count);

        // Verify sessions are revoked
        $this->assertFalse($this->service->validateSession($auth1['session_token'])['valid']);
        $this->assertFalse($this->service->validateSession($auth2['session_token'])['valid']);
    }

    #[Test]
    public function it_updates_last_used_at_on_api_key_auth()
    {
        // Arrange
        $agent = Agent::factory()->create([
            'status' => 'active',
        ]);

        $keyResult = $this->service->generateApiKey($agent, 'Test Key');

        // Verify last_used_at is initially null
        $key = AgentApiKey::where('key_id', $keyResult['key_id'])->first();
        $this->assertNull($key->last_used_at);

        // Act
        $this->service->authenticateWithApiKey($keyResult['api_key']);

        // Assert
        $key->refresh();
        $this->assertNotNull($key->last_used_at);
    }
}
