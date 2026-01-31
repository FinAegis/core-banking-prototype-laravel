<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Contracts\LLMProviderInterface;
use App\Domain\AI\Models\AiLlmUsage;
use App\Domain\AI\ValueObjects\LLMResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates LLM provider selection, fallback logic, and request handling.
 */
class LLMOrchestrationService
{
    /** @var array<string, LLMProviderInterface> */
    private array $providers = [];

    private string $primaryProvider;

    private string $fallbackProvider;

    private bool $demoMode;

    public function __construct()
    {
        $this->primaryProvider = config('ai.primary_provider', 'openai');
        $this->fallbackProvider = config('ai.fallback_provider', 'anthropic');
        $this->demoMode = config('ai.demo_mode', true);
    }

    /**
     * Register an LLM provider.
     */
    public function registerProvider(string $name, LLMProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Send a completion request to the LLM.
     *
     * @param string $systemPrompt
     * @param string $userMessage
     * @param array<string, mixed> $options
     * @param string|null $conversationId
     * @param string|null $userUuid
     * @return LLMResponse
     */
    public function complete(
        string $systemPrompt,
        string $userMessage,
        array $options = [],
        ?string $conversationId = null,
        ?string $userUuid = null
    ): LLMResponse {
        $startTime = microtime(true);
        $conversationId = $conversationId ?? Str::uuid()->toString();

        if ($this->demoMode) {
            return $this->handleDemoMode($systemPrompt, $userMessage, $conversationId, $userUuid, $startTime);
        }

        // Try primary provider first
        $response = $this->tryProvider(
            $this->primaryProvider,
            $systemPrompt,
            $userMessage,
            $options,
            $conversationId,
            $userUuid
        );

        if ($response !== null) {
            return $response;
        }

        // Fallback to secondary provider
        Log::warning('Primary LLM provider failed, falling back to secondary', [
            'primary'         => $this->primaryProvider,
            'fallback'        => $this->fallbackProvider,
            'conversation_id' => $conversationId,
        ]);

        $response = $this->tryProvider(
            $this->fallbackProvider,
            $systemPrompt,
            $userMessage,
            $options,
            $conversationId,
            $userUuid
        );

        if ($response !== null) {
            return $response;
        }

        // Both providers failed
        return $this->handleFailure($conversationId, $userUuid, $startTime);
    }

    /**
     * Try a specific provider.
     *
     * @param string $providerName
     * @param string $systemPrompt
     * @param string $userMessage
     * @param array<string, mixed> $options
     * @param string $conversationId
     * @param string|null $userUuid
     * @return LLMResponse|null
     */
    private function tryProvider(
        string $providerName,
        string $systemPrompt,
        string $userMessage,
        array $options,
        string $conversationId,
        ?string $userUuid
    ): ?LLMResponse {
        if (! isset($this->providers[$providerName])) {
            Log::warning('LLM provider not registered', ['provider' => $providerName]);

            return null;
        }

        $startTime = microtime(true);

        try {
            $provider = $this->providers[$providerName];
            $response = $provider->complete($systemPrompt, $userMessage, $options);

            // Log usage
            $this->logUsage(
                $providerName,
                $conversationId,
                $userUuid,
                $response,
                (int) ((microtime(true) - $startTime) * 1000),
                true
            );

            // Record in aggregate
            $this->recordInAggregate($conversationId, $userUuid, $providerName, $userMessage, $response);

            return $response;
        } catch (Throwable $e) {
            Log::error('LLM provider error', [
                'provider' => $providerName,
                'error'    => $e->getMessage(),
            ]);

            $this->logUsage(
                $providerName,
                $conversationId,
                $userUuid,
                null,
                (int) ((microtime(true) - $startTime) * 1000),
                false,
                $e->getMessage()
            );

            return null;
        }
    }

    /**
     * Handle demo mode - return simulated responses.
     */
    private function handleDemoMode(
        string $systemPrompt,
        string $userMessage,
        string $conversationId,
        ?string $userUuid,
        float $startTime
    ): LLMResponse {
        $content = $this->generateDemoResponse($userMessage);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000) + rand(100, 500); // Simulated latency

        $response = new LLMResponse(
            content: $content,
            provider: AiLlmUsage::PROVIDER_DEMO,
            model: 'demo-v1',
            promptTokens: (int) (strlen($userMessage) / 4), // Rough estimate
            completionTokens: (int) (strlen($content) / 4),
            finishReason: 'stop',
            metadata: ['demo_mode' => true]
        );

        $this->logUsage(
            AiLlmUsage::PROVIDER_DEMO,
            $conversationId,
            $userUuid,
            $response,
            $latencyMs,
            true
        );

        return $response;
    }

    /**
     * Handle complete failure of all providers.
     */
    private function handleFailure(
        string $conversationId,
        ?string $userUuid,
        float $startTime
    ): LLMResponse {
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        $errorMessage = 'All LLM providers are currently unavailable. Please try again later.';

        $this->logUsage(
            'none',
            $conversationId,
            $userUuid,
            null,
            $latencyMs,
            false,
            $errorMessage
        );

        return new LLMResponse(
            content: $errorMessage,
            provider: 'none',
            model: 'none',
            promptTokens: 0,
            completionTokens: 0,
            finishReason: 'error',
            metadata: ['error' => true, 'all_providers_failed' => true]
        );
    }

    /**
     * Generate a demo response based on message content.
     */
    private function generateDemoResponse(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'balance') || str_contains($lowerMessage, 'account')) {
            return 'Based on my analysis, your current account balance is $12,456.78 USD. ' .
                'You have 3 active accounts with a total balance of $25,234.56 across all currencies. ' .
                'Your GCU holdings are worth approximately €4,500.00.';
        }

        if (str_contains($lowerMessage, 'transaction') || str_contains($lowerMessage, 'spend')) {
            return "Analyzing your recent transactions:\n\n" .
                "- **Last 7 days**: 23 transactions totaling $2,345.67\n" .
                "- **Top categories**: Groceries ($456.78), Dining ($234.56), Transportation ($123.45)\n" .
                "- **Largest transaction**: Transfer to John Smith - $1,500.00\n\n" .
                'Your spending is 12% higher than last week. Would you like me to identify specific areas to reduce spending?';
        }

        if (str_contains($lowerMessage, 'transfer') || str_contains($lowerMessage, 'send money')) {
            return "I can help you transfer money. To complete a transfer, I'll need:\n\n" .
                "1. **Recipient**: Name or account number\n" .
                "2. **Amount**: How much would you like to send?\n" .
                "3. **Currency**: USD, EUR, GBP, or GCU\n\n" .
                "Please provide these details and I'll process your transfer request.";
        }

        if (str_contains($lowerMessage, 'loan') || str_contains($lowerMessage, 'borrow')) {
            return "Based on your profile, you're eligible for the following loan options:\n\n" .
                "- **Personal Loan**: Up to $25,000 at 8.5% APR\n" .
                "- **Credit Line**: Up to $10,000 at 12.9% APR\n\n" .
                'Your credit score of 742 qualifies you for our premium rates. ' .
                'Would you like me to start an application for any of these options?';
        }

        if (str_contains($lowerMessage, 'invest') || str_contains($lowerMessage, 'portfolio')) {
            return "Your investment portfolio analysis:\n\n" .
                "- **Total Value**: $45,678.90\n" .
                "- **YTD Return**: +12.3%\n" .
                "- **Asset Allocation**: 60% Stocks, 30% Bonds, 10% GCU\n\n" .
                'Based on market conditions, I recommend rebalancing to increase your GCU allocation ' .
                'for better stability. Shall I prepare a detailed recommendation?';
        }

        if (str_contains($lowerMessage, 'compliance') || str_contains($lowerMessage, 'kyc')) {
            return "Your compliance status:\n\n" .
                "- **KYC Level**: Tier 2 (Verified)\n" .
                "- **Documents**: All current\n" .
                "- **Next Review**: March 15, 2027\n\n" .
                'You have full access to all platform features. ' .
                'To increase your transaction limits, you can upgrade to Tier 3 verification.';
        }

        return 'I understand your query: "' . substr($message, 0, 100) . "...\"\n\n" .
            'In a production environment, I would process this using advanced AI models ' .
            "with access to your banking data and tools. For now, I'm running in demo mode.\n\n" .
            'How else can I assist you with your banking needs?';
    }

    /**
     * Log LLM usage to database.
     */
    private function logUsage(
        string $provider,
        string $conversationId,
        ?string $userUuid,
        ?LLMResponse $response,
        int $latencyMs,
        bool $success,
        ?string $errorMessage = null
    ): void {
        try {
            AiLlmUsage::log([
                'conversation_id'   => $conversationId,
                'user_uuid'         => $userUuid,
                'provider'          => $provider,
                'model'             => $response?->model ?? 'unknown',
                'prompt_tokens'     => (int) ($response?->promptTokens ?? 0),
                'completion_tokens' => (int) ($response?->completionTokens ?? 0),
                'latency_ms'        => $latencyMs,
                'request_type'      => AiLlmUsage::REQUEST_TYPE_QUERY,
                'success'           => $success,
                'error_message'     => $errorMessage,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to log LLM usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record interaction in event sourcing aggregate.
     */
    private function recordInAggregate(
        string $conversationId,
        ?string $userUuid,
        string $provider,
        string $message,
        LLMResponse $response
    ): void {
        try {
            $aggregate = AIInteractionAggregate::retrieve($conversationId);

            if (! $aggregate->isActive()) {
                $aggregate->startConversation(
                    $conversationId,
                    'llm_orchestration',
                    $userUuid,
                    ['provider' => $provider]
                );
            }

            $aggregate->recordLLMRequest($userUuid ?? 'anonymous', $provider, $message);
            $aggregate->recordLLMResponse($provider, $response->content, $response->promptTokens + $response->completionTokens);
            $aggregate->persist();
        } catch (Throwable $e) {
            Log::error('Failed to record in aggregate', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get available providers.
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if demo mode is enabled.
     */
    public function isDemoMode(): bool
    {
        return $this->demoMode;
    }

    /**
     * Set demo mode.
     */
    public function setDemoMode(bool $enabled): void
    {
        $this->demoMode = $enabled;
    }
}
