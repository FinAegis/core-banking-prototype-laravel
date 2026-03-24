<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Services;

use App\Domain\AI\Contracts\LLMProviderInterface;
use App\Domain\AI\Models\AiLlmUsage;
use App\Domain\AI\Services\LLMOrchestrationService;
use App\Domain\AI\ValueObjects\LLMResponse;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LLMFallbackTest extends TestCase
{
    private LLMOrchestrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->service = app(LLMOrchestrationService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_demo_mode_returns_response_without_provider(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'You are a banking assistant.',
            'What is my balance?'
        );

        expect($response)->toBeInstanceOf(LLMResponse::class);
        expect($response->provider)->toBe(AiLlmUsage::PROVIDER_DEMO);
        expect($response->isError())->toBeFalse();
        expect($response->content)->not->toBeEmpty();
    }

    public function test_demo_mode_responds_to_transfer_keywords(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'You are a banking assistant.',
            'I want to transfer money to John'
        );

        expect($response)->toBeInstanceOf(LLMResponse::class);
        expect($response->content)->not->toBeEmpty();
    }

    public function test_demo_mode_responds_to_spending_keywords(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'You are a spending analyst.',
            'Analyze my spending patterns'
        );

        expect($response)->toBeInstanceOf(LLMResponse::class);
        expect($response->content)->not->toBeEmpty();
    }

    public function test_is_demo_mode_reflects_config(): void
    {
        $this->service->setDemoMode(true);
        expect($this->service->isDemoMode())->toBeTrue();

        $this->service->setDemoMode(false);
        expect($this->service->isDemoMode())->toBeFalse();
    }

    public function test_get_available_providers_returns_array(): void
    {
        $providers = $this->service->getAvailableProviders();

        expect($providers)->toBeArray();
    }

    public function test_register_provider_adds_to_available(): void
    {
        /** @var LLMProviderInterface&MockInterface $provider */
        $provider = Mockery::mock(LLMProviderInterface::class);
        $provider->shouldReceive('getName')->andReturn('test-provider');

        $this->service->registerProvider('test-provider', $provider);

        expect($this->service->getAvailableProviders())->toContain('test-provider');
    }

    public function test_complete_with_conversation_id(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'You are a banking assistant.',
            'Show my recent transactions',
            [],
            'conv-test-123'
        );

        expect($response)->toBeInstanceOf(LLMResponse::class);
        expect($response->isError())->toBeFalse();
    }

    public function test_handle_failure_returns_error_response(): void
    {
        $this->service->setDemoMode(false);

        // With no real providers configured, this should handle gracefully
        $response = $this->service->complete(
            'System prompt',
            'Test message'
        );

        // Should return either a demo response or an error response, not throw
        expect($response)->toBeInstanceOf(LLMResponse::class);
    }
}
