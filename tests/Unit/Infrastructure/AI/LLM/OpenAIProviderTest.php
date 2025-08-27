<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\AI\LLM;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\ValueObjects\ConversationContext;
use App\Domain\AI\ValueObjects\LLMResponse;
use App\Infrastructure\AI\LLM\OpenAIProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    private OpenAIProvider $provider;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'test-api-key');
        Config::set('services.openai.model', 'gpt-4');
        Config::set('services.openai.temperature', 0.7);
        Config::set('services.openai.max_tokens', 2000);

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $this->provider = new OpenAIProvider();

        // Use reflection to inject mock client
        $reflection = new ReflectionClass($this->provider);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->provider, $client);
    }

    #[Test]
    public function it_can_send_chat_request(): void
    {
        // Arrange
        $conversationId = 'test-conversation-' . uniqid();
        $userId = 'user-123';
        $message = 'What is the weather today?';

        $context = new ConversationContext(
            $conversationId,
            $userId,
            [],
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            []
        );

        $responseData = [
            'id'      => 'chatcmpl-123',
            'object'  => 'chat.completion',
            'created' => time(),
            'model'   => 'gpt-4',
            'choices' => [
                [
                    'index'   => 0,
                    'message' => [
                        'role'    => 'assistant',
                        'content' => 'I cannot provide real-time weather information.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens'     => 10,
                'completion_tokens' => 20,
                'total_tokens'      => 30,
            ],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($responseData) ?: '')
        );

        // Mock the aggregate
        $this->mock(AIInteractionAggregate::class, function ($mock) use ($conversationId) {
            $mock->shouldReceive('retrieve')->with($conversationId)->andReturn($mock);
            $mock->shouldReceive('recordLLMRequest')->andReturn($mock);
            $mock->shouldReceive('recordLLMResponse')->andReturn($mock);
            $mock->shouldReceive('persist')->andReturn($mock);
        });

        // Act
        $response = $this->provider->chat($message, $context);

        // Assert
        $this->assertInstanceOf(LLMResponse::class, $response);
        $this->assertEquals('I cannot provide real-time weather information.', $response->getContent());
        $this->assertEquals('gpt-4', $response->getModel());
        $this->assertEquals(10, $response->getPromptTokens());
        $this->assertEquals(20, $response->getCompletionTokens());
        $this->assertEquals(30, $response->getTotalTokens());
    }

    #[Test]
    public function it_caches_responses(): void
    {
        // Clear cache to ensure clean test
        Cache::flush();

        // Arrange
        $conversationId = 'test-conversation-' . uniqid();
        $userId = 'user-123';
        $message = 'Test message';

        $context = new ConversationContext($conversationId, $userId);

        $responseData = [
            'id'      => 'chatcmpl-123',
            'object'  => 'chat.completion',
            'created' => time(),
            'model'   => 'gpt-4',
            'choices' => [
                [
                    'message'       => ['content' => 'Test response'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens'     => 5,
                'completion_tokens' => 10,
            ],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($responseData) ?: '')
        );

        // Mock the aggregate - cache hit on second call won't record response
        $this->mock(AIInteractionAggregate::class, function ($mock) use ($conversationId) {
            $mock->shouldReceive('retrieve')->with($conversationId)->andReturn($mock);
            $mock->shouldReceive('recordLLMRequest')->andReturn($mock);
            $mock->shouldReceive('recordLLMResponse')->andReturn($mock);
            $mock->shouldReceive('persist')->andReturn($mock);
        });

        // Act
        $response1 = $this->provider->chat($message, $context);

        // Second call should use cache
        $response2 = $this->provider->chat($message, $context);

        // Assert
        $this->assertEquals($response1->getContent(), $response2->getContent());
        $this->assertEquals($response1->getModel(), $response2->getModel());
        $this->assertEquals($response1->getTotalTokens(), $response2->getTotalTokens());
    }

    #[Test]
    public function it_can_generate_embeddings(): void
    {
        // Arrange
        $text = 'This is a test text for embeddings';

        $responseData = [
            'object' => 'list',
            'data'   => [
                [
                    'object'    => 'embedding',
                    'embedding' => array_fill(0, 1536, 0.1),
                    'index'     => 0,
                ],
            ],
            'model' => 'text-embedding-ada-002',
            'usage' => [
                'prompt_tokens' => 8,
                'total_tokens'  => 8,
            ],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($responseData) ?: '')
        );

        // Act
        $embeddings = $this->provider->generateEmbeddings($text);

        // Assert
        $this->assertCount(1536, $embeddings);
        $this->assertEquals(0.1, $embeddings[0]);
    }

    #[Test]
    public function it_checks_availability(): void
    {
        // Arrange
        $this->mockHandler->append(
            new Response(200, [], json_encode(['data' => []]) ?: '')
        );

        // Act
        $isAvailable = $this->provider->isAvailable();

        // Assert
        $this->assertTrue($isAvailable);
    }

    #[Test]
    public function it_returns_false_when_api_key_is_missing(): void
    {
        // Arrange
        Config::set('services.openai.api_key', '');
        $provider = new OpenAIProvider();

        // Act
        $isAvailable = $provider->isAvailable();

        // Assert
        $this->assertFalse($isAvailable);
    }

    #[Test]
    public function it_returns_provider_name(): void
    {
        // Act
        $name = $this->provider->getName();

        // Assert
        $this->assertEquals('OpenAI', $name);
    }
}
