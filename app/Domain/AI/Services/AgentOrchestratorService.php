<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AgentOrchestratorService
{
    public function __construct(
        private readonly AgentRouterService $router,
        private readonly AgentResponseComposerService $composer,
        private readonly LLMOrchestrationService $llmService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function process(string $query, array $context = []): array
    {
        $startTime = microtime(true);

        try {
            $agents = $this->router->route($query);
            $useLlm = $this->isLlmEnabled();

            $results = [];
            foreach ($agents as $agent) {
                $results[] = $agent->handle($query, $context, $useLlm);
            }

            $response = $this->composer->compose($results, $query);
            $response['message_id'] = Str::uuid()->toString();
            $response['response_time_ms'] = (int) ((microtime(true) - $startTime) * 1000);

            return $response;
        } catch (Throwable $e) {
            Log::error('AgentOrchestrator: processing failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'message_id'       => Str::uuid()->toString(),
                'content'          => 'I encountered an error processing your request. Please try again.',
                'confidence'       => 0.0,
                'tools_used'       => [],
                'agents_used'      => [],
                'metadata'         => ['error' => true],
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];
        }
    }

    /**
     * Agents call a language model only when demo mode is off and the primary
     * or fallback provider has an API key (`ai.{provider}.api_key`) and is
     * registered with LLMOrchestrationService. Otherwise they keep their
     * template answer: an unregistered provider would only produce the
     * "All LLM providers are currently unavailable" message.
     */
    private function isLlmEnabled(): bool
    {
        if (config('ai.demo_mode', true)) {
            return false;
        }

        $registered = $this->llmService->getAvailableProviders();

        foreach ([config('ai.primary_provider', 'openai'), config('ai.fallback_provider', 'anthropic')] as $provider) {
            if (! is_string($provider) || ! in_array($provider, $registered, true)) {
                continue;
            }

            $apiKey = config("ai.{$provider}.api_key");

            if (is_string($apiKey) && $apiKey !== '') {
                return true;
            }
        }

        return false;
    }
}
