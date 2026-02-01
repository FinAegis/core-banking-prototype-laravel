<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Models;

use App\Domain\AI\Models\AiLlmUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiLlmUsageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_an_llm_usage_record(): void
    {
        $usage = AiLlmUsage::create([
            'conversation_id'   => fake()->uuid(),
            'user_uuid'         => fake()->uuid(),
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
            'cost_usd'          => 0.0045,
            'latency_ms'        => 1500,
            'request_type'      => AiLlmUsage::REQUEST_TYPE_QUERY,
            'success'           => true,
        ]);

        expect($usage)->toBeInstanceOf(AiLlmUsage::class);
        expect($usage->provider)->toBe(AiLlmUsage::PROVIDER_OPENAI);
        expect($usage->total_tokens)->toBe(150);
    }

    #[Test]
    #[DataProvider('costCalculationProvider')]
    public function it_calculates_cost_correctly(string $model, int $promptTokens, int $completionTokens, float $expectedMinCost, float $expectedMaxCost): void
    {
        $cost = AiLlmUsage::calculateCost($model, $promptTokens, $completionTokens);

        expect($cost)->toBeGreaterThanOrEqual($expectedMinCost);
        expect($cost)->toBeLessThanOrEqual($expectedMaxCost);
    }

    public static function costCalculationProvider(): array
    {
        return [
            'gpt-4-1k-tokens' => ['gpt-4', 500, 500, 0.04, 0.05],
            'gpt-3.5-turbo'   => ['gpt-3.5-turbo', 1000, 1000, 0.001, 0.003],
            'claude-3-opus'   => ['claude-3-opus', 1000, 1000, 0.08, 0.10],
            'claude-3-haiku'  => ['claude-3-haiku', 1000, 1000, 0.001, 0.002],
            'unknown-model'   => ['unknown-model', 1000, 1000, 0.03, 0.05], // Default pricing
        ];
    }

    #[Test]
    public function it_logs_usage_with_auto_calculated_cost(): void
    {
        $usage = AiLlmUsage::log([
            'conversation_id'   => fake()->uuid(),
            'user_uuid'         => fake()->uuid(),
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'latency_ms'        => 1500,
        ]);

        expect($usage->total_tokens)->toBe(150);
        expect((float) $usage->cost_usd)->toBeGreaterThan(0);
    }

    #[Test]
    public function it_scopes_by_provider(): void
    {
        AiLlmUsage::create([
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
        ]);

        AiLlmUsage::create([
            'provider'          => AiLlmUsage::PROVIDER_ANTHROPIC,
            'model'             => 'claude-3',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
        ]);

        $openaiUsage = AiLlmUsage::provider(AiLlmUsage::PROVIDER_OPENAI)->get();
        $anthropicUsage = AiLlmUsage::provider(AiLlmUsage::PROVIDER_ANTHROPIC)->get();

        expect($openaiUsage)->toHaveCount(1);
        expect($anthropicUsage)->toHaveCount(1);
    }

    #[Test]
    public function it_scopes_to_successful_requests(): void
    {
        AiLlmUsage::create([
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
            'success'           => true,
        ]);

        AiLlmUsage::create([
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 0,
            'total_tokens'      => 100,
            'success'           => false,
            'error_message'     => 'Rate limit exceeded',
        ]);

        $successful = AiLlmUsage::successful()->get();
        $failed = AiLlmUsage::failed()->get();

        expect($successful)->toHaveCount(1);
        expect($failed)->toHaveCount(1);
    }

    #[Test]
    public function it_calculates_total_cost_for_user(): void
    {
        $userUuid = fake()->uuid();
        $startDate = now()->subDays(7);
        $endDate = now()->addDay(); // Include today plus buffer

        // Create records within date range
        $record1 = AiLlmUsage::create([
            'user_uuid'         => $userUuid,
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
            'cost_usd'          => 0.005,
        ]);

        $record2 = AiLlmUsage::create([
            'user_uuid'         => $userUuid,
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 200,
            'completion_tokens' => 100,
            'total_tokens'      => 300,
            'cost_usd'          => 0.01,
        ]);

        $totalCost = AiLlmUsage::getTotalCostForUser($userUuid, $startDate, $endDate);

        // Should include both records (0.005 + 0.01 = 0.015)
        expect(abs($totalCost - 0.015))->toBeLessThan(0.0001);
    }

    #[Test]
    public function it_calculates_provider_statistics(): void
    {
        $startDate = now()->subDays(7);
        $endDate = now();

        AiLlmUsage::create([
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
            'cost_usd'          => 0.005,
            'latency_ms'        => 1000,
            'success'           => true,
            'created_at'        => now()->subDays(1),
        ]);

        AiLlmUsage::create([
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 200,
            'completion_tokens' => 100,
            'total_tokens'      => 300,
            'cost_usd'          => 0.01,
            'latency_ms'        => 2000,
            'success'           => true,
            'created_at'        => now()->subDays(1),
        ]);

        AiLlmUsage::create([
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 0,
            'total_tokens'      => 100,
            'cost_usd'          => 0.003,
            'latency_ms'        => 500,
            'success'           => false,
            'created_at'        => now()->subDays(1),
        ]);

        $stats = AiLlmUsage::getProviderStats(AiLlmUsage::PROVIDER_OPENAI, $startDate, $endDate);

        expect($stats['total_requests'])->toBe(3);
        expect($stats['total_tokens'])->toBe(550);
        expect(round($stats['total_cost_usd'], 3))->toBe(0.018);
        expect($stats['avg_latency_ms'])->toBeGreaterThan(0);
        expect($stats['success_rate'])->toBe(66.67);
    }

    #[Test]
    public function it_casts_values_correctly(): void
    {
        $usage = AiLlmUsage::create([
            'conversation_id'   => fake()->uuid(),
            'user_uuid'         => fake()->uuid(),
            'provider'          => AiLlmUsage::PROVIDER_DEMO,
            'model'             => 'demo-v1',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
            'cost_usd'          => 0.00123456,
            'latency_ms'        => 1500,
            'success'           => true,
            'metadata'          => ['test' => 'value'],
        ]);

        expect($usage->prompt_tokens)->toBeInt();
        expect($usage->completion_tokens)->toBeInt();
        expect($usage->total_tokens)->toBeInt();
        expect($usage->latency_ms)->toBeInt();
        expect($usage->success)->toBeBool();
        expect($usage->metadata)->toBeArray();
    }

    #[Test]
    public function it_stores_error_messages(): void
    {
        $usage = AiLlmUsage::create([
            'provider'          => AiLlmUsage::PROVIDER_OPENAI,
            'model'             => 'gpt-4',
            'prompt_tokens'     => 100,
            'completion_tokens' => 0,
            'total_tokens'      => 100,
            'success'           => false,
            'error_message'     => 'Rate limit exceeded: Please retry after 60 seconds',
        ]);

        expect($usage->success)->toBeFalse();
        expect($usage->error_message)->toContain('Rate limit exceeded');
    }

    #[Test]
    public function it_supports_all_provider_constants(): void
    {
        expect(AiLlmUsage::PROVIDER_OPENAI)->toBe('openai');
        expect(AiLlmUsage::PROVIDER_ANTHROPIC)->toBe('anthropic');
        expect(AiLlmUsage::PROVIDER_DEMO)->toBe('demo');
    }

    #[Test]
    public function it_supports_all_request_type_constants(): void
    {
        expect(AiLlmUsage::REQUEST_TYPE_QUERY)->toBe('query');
        expect(AiLlmUsage::REQUEST_TYPE_ANALYSIS)->toBe('analysis');
        expect(AiLlmUsage::REQUEST_TYPE_COMPLIANCE)->toBe('compliance');
        expect(AiLlmUsage::REQUEST_TYPE_CODE_GENERATION)->toBe('code_generation');
    }
}
