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

class ClaudeProvider implements LLMProviderInterface
{
    private Client $client;

    private string $apiKey;

    private string $model;

    private float $temperature;

    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key', '');
        $this->model = config('services.claude.model', 'claude-3-opus-20240229');
        $this->temperature = (float) config('services.claude.temperature', 0.7);
        $this->maxTokens = (int) config('services.claude.max_tokens', 4000);

        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com/v1/',
            'timeout'  => 30.0,
            'headers'  => [
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
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
        $aggregate->recordLLMRequest($context->getUserId(), 'claude', $message, $options);
        $aggregate->persist();

        try {
            // Build messages array for Claude format
            $messages = [];

            // Add conversation history
            foreach ($context->getMessages() as $msg) {
                $messages[] = [
                    'role'    => $msg['role'] === 'system' ? 'assistant' : $msg['role'],
                    'content' => $msg['content'],
                ];
            }

            // Add current message
            $messages[] = ['role' => 'user', 'content' => $message];

            // Prepare request
            $requestBody = [
                'model'       => $options['model'] ?? $this->model,
                'messages'    => $messages,
                'max_tokens'  => $options['max_tokens'] ?? $this->maxTokens,
                'temperature' => $options['temperature'] ?? $this->temperature,
            ];

            // Add system prompt if exists
            if (! empty($context->getSystemPrompt())) {
                $requestBody['system'] = $context->getSystemPrompt()['content'];
            }

            // Check cache
            $cacheKey = 'claude:' . md5(json_encode($requestBody) ?: '');
            if ($cached = Cache::get($cacheKey)) {
                Log::info('Claude response served from cache', ['conversation_id' => $context->getConversationId()]);

                return $cached;
            }

            // Make API request
            $response = $this->client->post('messages', [
                'json' => $requestBody,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Calculate token usage (Claude provides this differently)
            $promptTokens = $data['usage']['input_tokens'] ?? 0;
            $completionTokens = $data['usage']['output_tokens'] ?? 0;

            // Create response object
            $llmResponse = new LLMResponse(
                content: $data['content'][0]['text'],
                model: $data['model'],
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                temperature: $options['temperature'] ?? $this->temperature,
                metadata: [
                    'stop_reason' => $data['stop_reason'],
                    'id'          => $data['id'],
                    'type'        => $data['type'],
                ]
            );

            // Cache the response
            Cache::put($cacheKey, $llmResponse, 3600);

            // Event sourcing: Record the response
            $aggregate->recordLLMResponse(
                'claude',
                $llmResponse->getContent(),
                $llmResponse->getTotalTokens(),
                $llmResponse->toArray()
            );
            $aggregate->persist();

            return $llmResponse;
        } catch (RequestException $e) {
            Log::error('Claude API request failed', [
                'conversation_id' => $context->getConversationId(),
                'error'           => $e->getMessage(),
            ]);

            // Record failure
            $aggregate->recordLLMError('claude', $e->getMessage());
            $aggregate->persist();

            throw new \RuntimeException('Failed to get response from Claude: ' . $e->getMessage(), 0, $e);
        }
    }

    public function stream(
        string $message,
        ConversationContext $context,
        array $options = []
    ): \Generator {
        $messages = [];

        foreach ($context->getMessages() as $msg) {
            $messages[] = [
                'role'    => $msg['role'] === 'system' ? 'assistant' : $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $requestBody = [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => $messages,
            'max_tokens'  => $options['max_tokens'] ?? $this->maxTokens,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'stream'      => true,
        ];

        if (! empty($context->getSystemPrompt())) {
            $requestBody['system'] = $context->getSystemPrompt()['content'];
        }

        try {
            $response = $this->client->post('messages', [
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
                        $json = json_decode($data, true);

                        if (isset($json['type']) && $json['type'] === 'content_block_delta') {
                            if (isset($json['delta']['text'])) {
                                yield $json['delta']['text'];
                            }
                        }
                    }
                }
            }
        } catch (RequestException $e) {
            Log::error('Claude streaming failed', [
                'conversation_id' => $context->getConversationId(),
                'error'           => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to stream from Claude: ' . $e->getMessage(), 0, $e);
        }
    }

    public function generateEmbeddings(string $text): array
    {
        // Claude doesn't provide embeddings API directly
        // We can use a fallback or throw an exception
        throw new \RuntimeException('Claude does not support embeddings generation. Use OpenAI provider instead.');
    }

    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            // Test with a minimal request
            $response = $this->client->post('messages', [
                'json' => [
                    'model'      => $this->model,
                    'messages'   => [['role' => 'user', 'content' => 'test']],
                    'max_tokens' => 1,
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::warning('Claude availability check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'Claude';
    }

    public function getUsageStats(): array
    {
        // Aggregate usage stats from events
        $stats = Cache::remember('claude:usage:stats', 300, function () {
            $events = \DB::table('ai_events')
                ->where('event_type', 'like', '%LLM%')
                ->where('metadata->provider', 'claude')
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
