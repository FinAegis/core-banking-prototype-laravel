<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Services;

use App\Domain\AI\Contracts\LLMProviderInterface;
use App\Domain\AI\Models\AiLlmUsage;
use App\Domain\AI\Services\LLMOrchestrationService;
use App\Domain\AI\ValueObjects\LLMResponse;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LLMOrchestrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private LLMOrchestrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LLMOrchestrationService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_demo_response_in_demo_mode(): void
    {
        config(['ai.demo_mode' => true]);
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'You are a helpful assistant.',
            'What is my balance?'
        );

        expect($response)->toBeInstanceOf(LLMResponse::class);
        expect($response->provider)->toBe(AiLlmUsage::PROVIDER_DEMO);
        expect($response->content)->toContain('balance');
        expect($response->finishReason)->toBe('stop');
    }

    #[Test]
    public function it_generates_balance_related_demo_response(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'System prompt',
            'What is my current account balance?'
        );

        expect($response->content)->toContain('balance');
        expect($response->content)->toContain('USD');
    }

    #[Test]
    public function it_generates_transaction_related_demo_response(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'System prompt',
            'Show me my recent spending'
        );

        expect($response->content)->toContain('transaction');
    }

    #[Test]
    public function it_generates_transfer_related_demo_response(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'System prompt',
            'I want to transfer money'
        );

        expect($response->content)->toContain('transfer');
    }

    #[Test]
    public function it_generates_loan_related_demo_response(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'System prompt',
            'Tell me about loan options'
        );

        expect($response->content)->toContain('Loan');
    }

    #[Test]
    public function it_generates_investment_related_demo_response(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'System prompt',
            'How is my portfolio performing?'
        );

        expect($response->content)->toContain('portfolio');
    }

    #[Test]
    public function it_generates_compliance_related_demo_response(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'System prompt',
            'What is my KYC status?'
        );

        expect($response->content)->toContain('KYC');
    }

    #[Test]
    public function it_generates_generic_demo_response(): void
    {
        $this->service->setDemoMode(true);

        $response = $this->service->complete(
            'System prompt',
            'Random unrelated query about weather'
        );

        expect($response->content)->toContain('demo mode');
    }

    #[Test]
    public function it_registers_providers(): void
    {
        $mockProvider = Mockery::mock(LLMProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('test_provider');

        $this->service->registerProvider('test', $mockProvider);

        expect($this->service->getAvailableProviders())->toContain('test');
    }

    #[Test]
    public function it_tracks_demo_mode_state(): void
    {
        $this->service->setDemoMode(true);
        expect($this->service->isDemoMode())->toBeTrue();

        $this->service->setDemoMode(false);
        expect($this->service->isDemoMode())->toBeFalse();
    }

    #[Test]
    public function it_logs_usage_for_demo_requests(): void
    {
        $this->service->setDemoMode(true);

        $this->service->complete(
            'System prompt',
            'What is my balance?',
            [],
            'test-conversation-id',
            'test-user-uuid'
        );

        $usage = AiLlmUsage::where('conversation_id', 'test-conversation-id')->first();

        expect($usage)->not->toBeNull();
        expect($usage->provider)->toBe(AiLlmUsage::PROVIDER_DEMO);
        expect($usage->user_uuid)->toBe('test-user-uuid');
        expect($usage->success)->toBeTrue();
    }

    #[Test]
    public function it_uses_primary_provider_first(): void
    {
        $this->service->setDemoMode(false);

        $primaryProvider = Mockery::mock(LLMProviderInterface::class);
        $primaryProvider->shouldReceive('complete')
            ->once()
            ->andReturn(new LLMResponse(
                content: 'Response from primary',
                provider: 'openai',
                model: 'gpt-4',
                promptTokens: 10,
                completionTokens: 20
            ));

        $fallbackProvider = Mockery::mock(LLMProviderInterface::class);
        $fallbackProvider->shouldReceive('complete')->never();

        $this->service->registerProvider('openai', $primaryProvider);
        $this->service->registerProvider('anthropic', $fallbackProvider);

        $response = $this->service->complete('System', 'User message');

        expect($response->provider)->toBe('openai');
        expect($response->content)->toBe('Response from primary');
    }

    #[Test]
    public function it_falls_back_to_secondary_provider_on_primary_failure(): void
    {
        $this->service->setDemoMode(false);

        $primaryProvider = Mockery::mock(LLMProviderInterface::class);
        $primaryProvider->shouldReceive('complete')
            ->once()
            ->andThrow(new Exception('Primary provider error'));

        $fallbackProvider = Mockery::mock(LLMProviderInterface::class);
        $fallbackProvider->shouldReceive('complete')
            ->once()
            ->andReturn(new LLMResponse(
                content: 'Response from fallback',
                provider: 'anthropic',
                model: 'claude-3',
                promptTokens: 10,
                completionTokens: 20
            ));

        $this->service->registerProvider('openai', $primaryProvider);
        $this->service->registerProvider('anthropic', $fallbackProvider);

        $response = $this->service->complete('System', 'User message');

        expect($response->provider)->toBe('anthropic');
        expect($response->content)->toBe('Response from fallback');
    }

    #[Test]
    public function it_handles_all_providers_failing(): void
    {
        $this->service->setDemoMode(false);

        $primaryProvider = Mockery::mock(LLMProviderInterface::class);
        $primaryProvider->shouldReceive('complete')
            ->once()
            ->andThrow(new Exception('Primary provider error'));

        $fallbackProvider = Mockery::mock(LLMProviderInterface::class);
        $fallbackProvider->shouldReceive('complete')
            ->once()
            ->andThrow(new Exception('Fallback provider error'));

        $this->service->registerProvider('openai', $primaryProvider);
        $this->service->registerProvider('anthropic', $fallbackProvider);

        $response = $this->service->complete('System', 'User message');

        expect($response->isError())->toBeTrue();
        expect($response->content)->toContain('unavailable');
    }
}
