<?php

declare(strict_types=1);

use App\Domain\AI\Contracts\LLMProviderInterface;
use App\Domain\AI\Services\AgentOrchestratorService;
use App\Domain\AI\Services\LLMOrchestrationService;
use App\Domain\AI\ValueObjects\LLMResponse;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/*
 * AgentOrchestratorService decided whether agents call a language model
 * from `ai.providers.*` keys that config/ai.php does not define, so agents
 * never used one. It now reads `ai.{provider}.api_key` for the primary and
 * fallback providers, and only when that provider is registered with
 * LLMOrchestrationService. Demo mode (production today) and a missing key
 * keep the canned template answer. All HTTP is faked.
 */

const LLM_TEST_ENDPOINT = 'https://llm.test/v1/chat/completions';

/**
 * A provider that calls a (faked) chat completions endpoint over Laravel's
 * HTTP client, standing in for a real OpenAI provider.
 */
function fakeHttpLlmProvider(): LLMProviderInterface
{
    return new class () implements LLMProviderInterface {
        public function getName(): string
        {
            return 'openai';
        }

        public function getDefaultModel(): string
        {
            return 'test-model';
        }

        public function getAvailableModels(): array
        {
            return ['test-model'];
        }

        public function complete(string $systemPrompt, string $userMessage, array $options = []): LLMResponse
        {
            $response = Http::post(LLM_TEST_ENDPOINT, [
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

            return new LLMResponse(
                content: (string) $response->json('choices.0.message.content'),
                provider: 'openai',
                model: 'test-model',
            );
        }

        public function chat(array $messages, array $options = []): LLMResponse
        {
            $last = $messages === [] ? '' : $messages[array_key_last($messages)]['content'];

            return $this->complete('', $last, $options);
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function getPricing(string $model): array
        {
            return ['input' => 0.0, 'output' => 0.0];
        }

        public function getMaxContextLength(string $model): int
        {
            return 8192;
        }

        public function supportsStreaming(): bool
        {
            return false;
        }

        public function supportsToolCalling(): bool
        {
            return false;
        }
    };
}

/**
 * @param  array<string, mixed>  $config
 */
function orchestratorWith(array $config, bool $registerProvider): AgentOrchestratorService
{
    config(array_merge([
        'ai.primary_provider'  => 'openai',
        'ai.fallback_provider' => 'anthropic',
        'ai.openai.api_key'    => null,
        'ai.anthropic.api_key' => null,
    ], $config));

    // Rebuild the AI services so they read the config above.
    app()->forgetInstance(LLMOrchestrationService::class);
    app()->forgetInstance(App\Domain\AI\Services\AgentRouterService::class);
    app()->forgetInstance(AgentOrchestratorService::class);

    if ($registerProvider) {
        app(LLMOrchestrationService::class)->registerProvider('openai', fakeHttpLlmProvider());
    }

    return app(AgentOrchestratorService::class);
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake([
        LLM_TEST_ENDPOINT => Http::response([
            'choices' => [['message' => ['content' => 'Answer from the language model.']]],
        ]),
    ]);
});

it('keeps the canned answer in demo mode even with a key and a provider', function (): void {
    $orchestrator = orchestratorWith(['ai.demo_mode' => true, 'ai.openai.api_key' => 'sk-test'], registerProvider: true);

    $response = $orchestrator->process('hello there');

    expect($response['metadata']['mode'] ?? null)->toBe('template')
        ->and($response['content'])->not->toContain('Answer from the language model.');
    Http::assertNothingSent();
});

it('keeps the canned answer when no API key is set', function (): void {
    $orchestrator = orchestratorWith(['ai.demo_mode' => false], registerProvider: true);

    $response = $orchestrator->process('hello there');

    expect($response['metadata']['mode'] ?? null)->toBe('template');
    Http::assertNothingSent();
});

it('keeps the canned answer when a key is set but no provider is registered', function (): void {
    // Without a registered provider the LLM service could only answer
    // "All LLM providers are currently unavailable", which is worse.
    $orchestrator = orchestratorWith(['ai.demo_mode' => false, 'ai.openai.api_key' => 'sk-test'], registerProvider: false);

    $response = $orchestrator->process('hello there');

    expect($response['metadata']['mode'] ?? null)->toBe('template')
        ->and($response['content'])->not->toContain('unavailable');
    Http::assertNothingSent();
});

it('uses the configured provider when demo mode is off and the key is set', function (): void {
    $orchestrator = orchestratorWith(['ai.demo_mode' => false, 'ai.openai.api_key' => 'sk-test'], registerProvider: true);

    $response = $orchestrator->process('hello there');

    expect($response['metadata']['mode'] ?? null)->toBe('llm')
        ->and($response['content'])->toBe('Answer from the language model.');
    Http::assertSent(fn ($request): bool => $request->url() === LLM_TEST_ENDPOINT);
});
