<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Services\DIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DIDServiceTest extends TestCase
{
    use RefreshDatabase;

    private DIDService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DIDService();
    }

    public function test_can_generate_did(): void
    {
        $did = $this->service->generateDID();

        $this->assertStringStartsWith('did:finaegis:key:', $did);

        // Extract identifier part
        $parts = explode(':', $did);
        $this->assertCount(4, $parts);
        $this->assertEquals('did', $parts[0]);
        $this->assertEquals('finaegis', $parts[1]);
        $this->assertEquals('key', $parts[2]);

        // Identifier should be 32 characters hex
        $identifier = $parts[3];
        $this->assertEquals(32, strlen($identifier));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $identifier);
    }

    public function test_can_generate_did_with_different_method(): void
    {
        $didKey = $this->service->generateDID('key');
        $didWeb = $this->service->generateDID('web');
        $didAgent = $this->service->generateDID('agent');

        $this->assertStringContainsString(':key:', $didKey);
        $this->assertStringContainsString(':web:', $didWeb);
        $this->assertStringContainsString(':agent:', $didAgent);
    }

    public function test_generated_dids_are_unique(): void
    {
        $did1 = $this->service->generateDID();
        $did2 = $this->service->generateDID();
        $did3 = $this->service->generateDID();

        $this->assertNotEquals($did1, $did2);
        $this->assertNotEquals($did2, $did3);
        $this->assertNotEquals($did1, $did3);
    }

    public function test_can_validate_did(): void
    {
        $validDid = 'did:finaegis:key:' . str_repeat('a', 32);
        $this->assertTrue($this->service->validateDID($validDid));

        $validWebDid = 'did:finaegis:web:' . str_repeat('b', 32);
        $this->assertTrue($this->service->validateDID($validWebDid));

        $validAgentDid = 'did:finaegis:agent:' . str_repeat('c', 32);
        $this->assertTrue($this->service->validateDID($validAgentDid));
    }

    public function test_validates_invalid_dids(): void
    {
        // Wrong prefix
        $this->assertFalse($this->service->validateDID('did:example:key:abc123'));

        // Invalid structure
        $this->assertFalse($this->service->validateDID('did:finaegis'));
        $this->assertFalse($this->service->validateDID('did:finaegis:key'));

        // Invalid method
        $this->assertFalse($this->service->validateDID('did:finaegis:invalid:abc123'));

        // Invalid identifier format
        $this->assertFalse($this->service->validateDID('did:finaegis:key:xyz')); // Not hex
        $this->assertFalse($this->service->validateDID('did:finaegis:key:' . str_repeat('a', 31))); // Too short
        $this->assertFalse($this->service->validateDID('did:finaegis:key:' . str_repeat('a', 33))); // Too long
    }

    public function test_can_resolve_did(): void
    {
        $did = $this->service->generateDID();
        $document = $this->service->resolveDID($did);

        $this->assertNotNull($document);
        $this->assertIsArray($document);

        // Check required DID document fields
        $this->assertArrayHasKey('@context', $document);
        $this->assertArrayHasKey('id', $document);
        $this->assertArrayHasKey('verificationMethod', $document);
        $this->assertArrayHasKey('authentication', $document);
        $this->assertArrayHasKey('assertionMethod', $document);
        $this->assertArrayHasKey('service', $document);
        $this->assertArrayHasKey('created', $document);
        $this->assertArrayHasKey('updated', $document);

        $this->assertEquals($did, $document['id']);

        // Check context includes required schemas
        $this->assertContains('https://www.w3.org/ns/did/v1', $document['@context']);
        $this->assertContains('https://w3id.org/security/v1', $document['@context']);

        // Check service endpoint
        $this->assertCount(1, $document['service']);
        $this->assertEquals('AP2Service', $document['service'][0]['type']);
    }

    public function test_resolving_invalid_did_returns_null(): void
    {
        $document = $this->service->resolveDID('invalid:did:format');
        $this->assertNull($document);
    }

    public function test_resolved_did_is_cached(): void
    {
        $did = $this->service->generateDID();

        // First resolution should cache
        $document1 = $this->service->resolveDID($did);

        // Second resolution should use cache
        Cache::shouldReceive('get')
            ->once()
            ->with('did:' . $did)
            ->andReturn($document1);

        $document2 = $this->service->resolveDID($did);

        $this->assertEquals($document1, $document2);
    }

    public function test_can_create_did_document(): void
    {
        $attributes = [
            'did'                => 'did:finaegis:key:' . str_repeat('a', 32),
            'verificationMethod' => [
                [
                    'id'         => 'did:finaegis:key:abc#keys-1',
                    'type'       => 'Ed25519VerificationKey2020',
                    'controller' => 'did:finaegis:key:abc',
                ],
            ],
            'authentication' => ['did:finaegis:key:abc#keys-1'],
            'service'        => [
                [
                    'id'              => 'did:finaegis:key:abc#ap2',
                    'type'            => 'AP2Service',
                    'serviceEndpoint' => 'https://example.com/ap2',
                ],
            ],
        ];

        $document = $this->service->createDIDDocument($attributes);

        $this->assertIsArray($document);
        $this->assertEquals($attributes['did'], $document['id']);
        $this->assertEquals($attributes['verificationMethod'], $document['verificationMethod']);
        $this->assertEquals($attributes['authentication'], $document['authentication']);
        $this->assertEquals($attributes['service'], $document['service']);
        $this->assertArrayHasKey('created', $document);
        $this->assertArrayHasKey('updated', $document);
    }

    public function test_can_store_did_document(): void
    {
        $did = $this->service->generateDID();
        $document = [
            'id'                 => $did,
            '@context'           => ['https://www.w3.org/ns/did/v1'],
            'verificationMethod' => [],
        ];

        $result = $this->service->storeDIDDocument($did, $document);

        $this->assertTrue($result);

        // Verify it was cached
        $cacheKey = 'did:document:' . $did;
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_can_extract_agent_id_from_did(): void
    {
        $identifier = str_repeat('f', 32);
        $did = 'did:finaegis:key:' . $identifier;

        $extractedId = $this->service->extractAgentIdFromDID($did);

        $this->assertEquals($identifier, $extractedId);
    }

    public function test_extract_agent_id_from_invalid_did_returns_null(): void
    {
        $extractedId = $this->service->extractAgentIdFromDID('invalid:did');
        $this->assertNull($extractedId);
    }

    public function test_can_get_did_method(): void
    {
        $didKey = 'did:finaegis:key:' . str_repeat('a', 32);
        $didWeb = 'did:finaegis:web:' . str_repeat('b', 32);
        $didAgent = 'did:finaegis:agent:' . str_repeat('c', 32);

        $this->assertEquals('key', $this->service->getDIDMethod($didKey));
        $this->assertEquals('web', $this->service->getDIDMethod($didWeb));
        $this->assertEquals('agent', $this->service->getDIDMethod($didAgent));
    }

    public function test_get_did_method_from_invalid_did_returns_null(): void
    {
        $method = $this->service->getDIDMethod('invalid:did');
        $this->assertNull($method);
    }

    public function test_verify_did_signature_placeholder(): void
    {
        $did = $this->service->generateDID();
        $signature = 'dummy_signature';
        $message = 'test_message';

        // This is a placeholder implementation that always returns true
        // In production, this would perform actual cryptographic verification
        $result = $this->service->verifyDIDSignature($did, $signature, $message);

        $this->assertTrue($result);
    }
}
