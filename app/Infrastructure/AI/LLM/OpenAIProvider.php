<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\LLM;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\ValueObjects\ConversationContext;
use App\Domain\AI\ValueObjects\LLMResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements LLMProviderInterface
{
    private Client $client;

    private string $apiKey;

    private string $model;

    private float $temperature;

    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
        $this->model = config('services.openai.model', 'gpt-4');
        $this->temperature = (float) config('services.openai.temperature', 0.7);
        $this->maxTokens = (int) config('services.openai.max_tokens', 2000);

        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout'  => 30.0,
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function chat(
        string $message,
        ConversationContext $context,
        array $options = []
    ): LLMResponse {
        // Event sourcing: Record the request
        $aggregate = AIInteractionAggregate::retrieve($context->getConversationId());
        $aggregate->recordLLMRequest($context->getUserId(), 'openai', $message, $options);
        $aggregate->persist();

        try {
            // Build messages array
            $messages = [];

            // Add system prompt if exists
            if (! empty($context->getSystemPrompt())) {
                $messages[] = $context->getSystemPrompt();
            }

            // Add conversation history
            foreach ($context->getMessages() as $msg) {
                $messages[] = $msg;
            }

            // Add current message
            $messages[] = ['role' => 'user', 'content' => $message];

            // Prepare request
            $requestBody = [
                'model'       => $options['model'] ?? $this->model,
                'messages'    => $messages,
                'temperature' => $options['temperature'] ?? $this->temperature,
                'max_tokens'  => $options['max_tokens'] ?? $this->maxTokens,
            ];

            // Check cache for similar requests
            $cacheKey = 'openai:' . md5(json_encode($requestBody) ?: '');
            if ($cached = Cache::get($cacheKey)) {
                Log::info('OpenAI response served from cache', ['conversation_id' => $context->getConversationId()]);

                return $cached;
            }

            // Make API request
            $response = $this->client->post('chat/completions', [
                'json' => $requestBody,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Create response object
            $llmResponse = new LLMResponse(
                content: $data['choices'][0]['message']['content'],
                model: $data['model'],
                promptTokens: $data['usage']['prompt_tokens'],
                completionTokens: $data['usage']['completion_tokens'],
                temperature: $options['temperature'] ?? $this->temperature,
                metadata: [
                    'finish_reason' => $data['choices'][0]['finish_reason'],
                    'created'       => $data['created'],
                    'id'            => $data['id'],
                ]
            );

            // Cache the response for 1 hour
            Cache::put($cacheKey, $llmResponse, 3600);

            // Event sourcing: Record the response
            $aggregate->recordLLMResponse(
                'openai',
                $llmResponse->getContent(),
                $llmResponse->getTotalTokens(),
                $llmResponse->toArray()
            );
            $aggregate->persist();

            return $llmResponse;
        } catch (RequestException $e) {
            Log::error('OpenAI API request failed', [
                'conversation_id' => $context->getConversationId(),
                'error'           => $e->getMessage(),
            ]);

            // Record failure in event sourcing
            $aggregate->recordLLMError('openai', $e->getMessage());
            $aggregate->persist();

            throw new \RuntimeException('Failed to get response from OpenAI: ' . $e->getMessage(), 0, $e);
        }
    }

    public function stream(
        string $message,
        ConversationContext $context,
        array $options = []
    ): \Generator {
        // Build messages array
        $messages = [];

        if (! empty($context->getSystemPrompt())) {
            $messages[] = $context->getSystemPrompt();
        }

        foreach ($context->getMessages() as $msg) {
            $messages[] = $msg;
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $requestBody = [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => $messages,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'max_tokens'  => $options['max_tokens'] ?? $this->maxTokens,
            'stream'      => true,
        ];

        try {
            $response = $this->client->post('chat/completions', [
                'json'   => $requestBody,
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $chunk = $body->read(1024);
                $buffer .= $chunk;

                // Process complete SSE messages
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $message = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    if (str_starts_with($message, 'data: ')) {
                        $data = substr($message, 6);
                        if ($data === '[DONE]') {
                            return;
                        }

                        $json = json_decode($data, true);
                        if (isset($json['choices'][0]['delta']['content'])) {
                            yield $json['choices'][0]['delta']['content'];
                        }
                    }
                }
            }
        } catch (RequestException $e) {
            Log::error('OpenAI streaming failed', [
                'conversation_id' => $context->getConversationId(),
                'error'           => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to stream from OpenAI: ' . $e->getMessage(), 0, $e);
        }
    }

    public function generateEmbeddings(string $text): array
    {
        try {
            $response = $this->client->post('embeddings', [
                'json' => [
                    'model' => 'text-embedding-ada-002',
                    'input' => $text,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['data'][0]['embedding'];
        } catch (RequestException $e) {
            Log::error('OpenAI embeddings generation failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to generate embeddings: ' . $e->getMessage(), 0, $e);
        }
    }

    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            $response = $this->client->get('models');

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::warning('OpenAI availability check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'OpenAI';
    }

    public function getUsageStats(): array
    {
        // Aggregate usage stats from events
        $stats = Cache::remember('openai:usage:stats', 300, function () {
            $events = \DB::table('ai_events')
                ->where('event_type', 'like', '%LLM%')
                ->where('metadata->provider', 'openai')
                ->where('created_at', '>=', now()->subDay())
                ->get();

            $totalTokens = 0;
            $requestCount = 0;
            $errorCount = 0;

            foreach ($events as $event) {
                $metadata = json_decode($event->metadata, true);
                if ($event->event_type === 'LLMResponseReceivedEvent') {
                    $totalTokens += $metadata['total_tokens'] ?? 0;
                    $requestCount++;
                } elseif ($event->event_type === 'LLMErrorEvent') {
                    $errorCount++;
                }
            }

            return [
                'total_tokens'  => $totalTokens,
                'request_count' => $requestCount,
                'error_count'   => $errorCount,
                'success_rate'  => $requestCount > 0 ? (($requestCount - $errorCount) / $requestCount) * 100 : 0,
            ];
        });

        return $stats;
    }
}
