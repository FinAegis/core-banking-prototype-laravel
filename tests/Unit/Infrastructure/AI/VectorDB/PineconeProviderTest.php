<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\AI\VectorDB;

use App\Infrastructure\AI\VectorDB\PineconeProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PineconeProviderTest extends TestCase
{
    private PineconeProvider $provider;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pinecone.api_key', 'test-api-key');
        Config::set('services.pinecone.environment', 'us-east-1');
        Config::set('services.pinecone.index_name', 'test-index');
        Config::set('services.pinecone.index_host', 'test-index.svc.pinecone.io');

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $this->provider = new PineconeProvider();

        // Use reflection to inject mock client
        $reflection = new \ReflectionClass($this->provider);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->provider, $client);
    }

    /** @test */
    public function it_can_store_vector(): void
    {
        // Arrange
        $id = 'vec-123';
        $vector = array_fill(0, 1536, 0.1);
        $metadata = ['type' => 'document', 'source' => 'test'];

        $this->mockHandler->append(
            new Response(200, [], json_encode(['upserted_count' => 1]) ?: '')
        );

        // Act
        $this->provider->store($id, $vector, $metadata);

        // Assert
        $request = $this->mockHandler->getLastRequest();
        $this->assertNotNull($request);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertStringContainsString('/vectors/upsert', (string) $request->getUri());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertEquals($id, $body['vectors'][0]['id']);
        $this->assertEquals($vector, $body['vectors'][0]['values']);
        $this->assertEquals($metadata, $body['vectors'][0]['metadata']);
    }

    /** @test */
    public function it_can_store_batch_vectors(): void
    {
        // Arrange
        $items = [
            ['id' => 'vec-1', 'vector' => array_fill(0, 10, 0.1), 'metadata' => ['type' => 'A']],
            ['id' => 'vec-2', 'vector' => array_fill(0, 10, 0.2), 'metadata' => ['type' => 'B']],
            ['id' => 'vec-3', 'vector' => array_fill(0, 10, 0.3), 'metadata' => ['type' => 'C']],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode(['upserted_count' => 3]) ?: '')
        );

        // Act
        $this->provider->storeBatch($items);

        // Assert
        $request = $this->mockHandler->getLastRequest();
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertCount(3, $body['vectors']);
        $this->assertEquals('vec-1', $body['vectors'][0]['id']);
        $this->assertEquals('vec-2', $body['vectors'][1]['id']);
        $this->assertEquals('vec-3', $body['vectors'][2]['id']);
    }

    /** @test */
    public function it_splits_large_batches(): void
    {
        // Arrange
        $items = [];
        for ($i = 0; $i < 150; $i++) {
            $items[] = [
                'id'       => "vec-$i",
                'vector'   => array_fill(0, 10, 0.1),
                'metadata' => ['index' => $i],
            ];
        }

        // Expect 2 requests (100 + 50)
        $this->mockHandler->append(
            new Response(200, [], json_encode(['upserted_count' => 100]) ?: ''),
            new Response(200, [], json_encode(['upserted_count' => 50]) ?: '')
        );

        // Act
        $this->provider->storeBatch($items);

        // Assert
        $this->assertCount(0, $this->mockHandler); // All responses consumed
    }

    /** @test */
    public function it_can_search_vectors(): void
    {
        // Arrange
        $queryVector = array_fill(0, 10, 0.5);
        $topK = 5;

        $responseData = [
            'matches' => [
                ['id' => 'vec-1', 'score' => 0.95, 'metadata' => ['type' => 'A']],
                ['id' => 'vec-2', 'score' => 0.85, 'metadata' => ['type' => 'B']],
                ['id' => 'vec-3', 'score' => 0.75, 'metadata' => ['type' => 'C']],
            ],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($responseData) ?: '')
        );

        // Act
        $results = $this->provider->search($queryVector, $topK);

        // Assert
        $this->assertCount(3, $results);
        $this->assertEquals('vec-1', $results[0]['id']);
        $this->assertEquals(0.95, $results[0]['score']);
        $this->assertEquals(['type' => 'A'], $results[0]['metadata']);
    }

    /** @test */
    public function it_caches_search_results(): void
    {
        // Arrange
        $queryVector = array_fill(0, 10, 0.5);

        $responseData = [
            'matches' => [
                ['id' => 'vec-1', 'score' => 0.95, 'metadata' => []],
            ],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($responseData) ?: '')
        );

        // Act
        $results1 = $this->provider->search($queryVector);
        $results2 = $this->provider->search($queryVector); // Should use cache

        // Assert
        $this->assertEquals($results1, $results2);
        $this->assertCount(0, $this->mockHandler); // Only one request made
    }

    /** @test */
    public function it_can_delete_vector(): void
    {
        // Arrange
        $id = 'vec-123';

        $this->mockHandler->append(
            new Response(200, [], json_encode(['deleted' => 1]) ?: '')
        );

        // Act
        $this->provider->delete($id);

        // Assert
        $request = $this->mockHandler->getLastRequest();
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertEquals([$id], $body['ids']);
    }

    /** @test */
    public function it_can_delete_by_filter(): void
    {
        // Arrange
        $filters = ['type' => 'document', 'source' => 'test'];

        $this->mockHandler->append(
            new Response(200, [], json_encode(['deleted' => 5]) ?: '')
        );

        // Act
        $count = $this->provider->deleteByFilter($filters);

        // Assert
        $this->assertEquals(5, $count);

        $request = $this->mockHandler->getLastRequest();
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertArrayHasKey('filter', $body);
        $this->assertEquals(['$eq' => 'document'], $body['filter']['type']);
        $this->assertEquals(['$eq' => 'test'], $body['filter']['source']);
    }

    /** @test */
    public function it_can_get_vector_by_id(): void
    {
        // Arrange
        $id = 'vec-123';
        $vector = array_fill(0, 10, 0.1);
        $metadata = ['type' => 'test'];

        $responseData = [
            'vectors' => [
                $id => [
                    'values'   => $vector,
                    'metadata' => $metadata,
                ],
            ],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($responseData) ?: '')
        );

        // Act
        $result = $this->provider->get($id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($id, $result['id']);
        $this->assertEquals($vector, $result['vector']);
        $this->assertEquals($metadata, $result['metadata']);
    }

    /** @test */
    public function it_returns_null_for_non_existent_vector(): void
    {
        // Arrange
        $this->mockHandler->append(
            new Response(200, [], json_encode(['vectors' => []]) ?: '')
        );

        // Act
        $result = $this->provider->get('non-existent');

        // Assert
        $this->assertNull($result);
    }

    /** @test */
    public function it_can_create_index(): void
    {
        // Arrange
        $name = 'new-index';
        $dimensions = 1536;
        $metric = 'cosine';

        // First request checks if index exists (404)
        $this->mockHandler->append(
            new Response(404, [], json_encode(['error' => 'Not found']) ?: ''),
            new Response(201, [], json_encode(['name' => $name, 'dimension' => $dimensions]) ?: '')
        );

        // Act
        $this->provider->createIndex($name, $dimensions, $metric);

        // Assert
        $this->assertCount(0, $this->mockHandler); // Both requests consumed
    }

    /** @test */
    public function it_skips_creating_existing_index(): void
    {
        // Arrange
        $name = 'existing-index';
        $dimensions = 1536;

        // Index already exists
        $this->mockHandler->append(
            new Response(200, [], json_encode(['name' => $name, 'dimension' => $dimensions]) ?: '')
        );

        // Act
        $this->provider->createIndex($name, $dimensions);

        // Assert
        $this->assertCount(0, $this->mockHandler); // Only one request made
    }

    /** @test */
    public function it_checks_availability(): void
    {
        // Arrange
        $this->mockHandler->append(
            new Response(200, [], json_encode(['indexes' => []]) ?: '')
        );

        // Act
        $isAvailable = $this->provider->isAvailable();

        // Assert
        $this->assertTrue($isAvailable);
    }

    /** @test */
    public function it_returns_false_when_api_key_missing(): void
    {
        // Arrange
        Config::set('services.pinecone.api_key', '');
        $provider = new PineconeProvider();

        // Act
        $isAvailable = $provider->isAvailable();

        // Assert
        $this->assertFalse($isAvailable);
    }

    /** @test */
    public function it_can_get_stats(): void
    {
        // Arrange
        $responseData = [
            'totalVectorCount' => 10000,
            'dimension'        => 1536,
            'indexFullness'    => 0.25,
            'namespaces'       => ['default' => ['vectorCount' => 10000]],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($responseData) ?: '')
        );

        // Act
        $stats = $this->provider->getStats();

        // Assert
        $this->assertEquals(10000, $stats['total_vectors']);
        $this->assertEquals(1536, $stats['dimensions']);
        $this->assertEquals(0.25, $stats['index_fullness']);
        $this->assertArrayHasKey('default', $stats['namespaces']);
    }
}
