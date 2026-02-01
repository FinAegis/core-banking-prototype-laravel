<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Services;

use App\Domain\AI\Models\AiPromptTemplate;
use App\Domain\AI\Services\PromptTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromptTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromptTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PromptTemplateService();
        Cache::flush();
    }

    #[Test]
    public function it_creates_a_prompt_template(): void
    {
        $template = $this->service->upsertTemplate([
            'name'          => 'test_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'You are a test assistant.',
            'user_template' => 'User says: {{message}}',
        ]);

        expect($template)->toBeInstanceOf(AiPromptTemplate::class);
        expect($template->name)->toBe('test_template');
        expect($template->category)->toBe(AiPromptTemplate::CATEGORY_QUERY);
        expect($template->is_active)->toBeTrue();
    }

    #[Test]
    public function it_retrieves_template_by_name(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'balance_check',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'Banking assistant',
            'user_template' => 'Check balance for {{account}}',
        ]);

        $template = $this->service->getTemplate('balance_check');

        expect($template)->not->toBeNull();
        expect($template->name)->toBe('balance_check');
    }

    #[Test]
    public function it_returns_null_for_nonexistent_template(): void
    {
        $template = $this->service->getTemplate('nonexistent');

        expect($template)->toBeNull();
    }

    #[Test]
    public function it_retrieves_templates_by_category(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'query_1',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System 1',
            'user_template' => 'Template 1',
        ]);

        $this->service->upsertTemplate([
            'name'          => 'query_2',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System 2',
            'user_template' => 'Template 2',
        ]);

        $this->service->upsertTemplate([
            'name'          => 'analysis_1',
            'category'      => AiPromptTemplate::CATEGORY_ANALYSIS,
            'system_prompt' => 'System 3',
            'user_template' => 'Template 3',
        ]);

        $queryTemplates = $this->service->getTemplatesByCategory(AiPromptTemplate::CATEGORY_QUERY);

        expect($queryTemplates)->toHaveCount(2);
    }

    #[Test]
    public function it_renders_template_with_variables(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'transfer_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'You are a transfer assistant.',
            'user_template' => 'Transfer {{amount}} {{currency}} to {{recipient}}',
        ]);

        $rendered = $this->service->renderTemplate('transfer_template', [
            'amount'    => '500',
            'currency'  => 'USD',
            'recipient' => 'John Doe',
        ]);

        expect($rendered)->not->toBeNull();
        expect($rendered['user_prompt'])->toBe('Transfer 500 USD to John Doe');
        expect($rendered['system_prompt'])->toBe('You are a transfer assistant.');
    }

    #[Test]
    public function it_throws_exception_for_missing_required_variables(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'transfer_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'You are a transfer assistant.',
            'user_template' => 'Transfer {{amount}} to {{recipient}}',
        ]);

        expect(fn () => $this->service->renderTemplate('transfer_template', [
            'amount' => '500',
            // Missing 'recipient'
        ]))->toThrow(InvalidArgumentException::class);
    }

    #[Test]
    public function it_increments_usage_count_on_render(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'usage_test',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Hello {{name}}',
        ]);

        $this->service->renderTemplate('usage_test', ['name' => 'World']);
        $this->service->renderTemplate('usage_test', ['name' => 'Claude']);

        $template = AiPromptTemplate::where('name', 'usage_test')->first();

        expect($template->usage_count)->toBe(2);
    }

    #[Test]
    public function it_deactivates_template(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'to_deactivate',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template',
        ]);

        $result = $this->service->deactivateTemplate('to_deactivate');

        expect($result)->toBeTrue();

        $template = AiPromptTemplate::where('name', 'to_deactivate')->first();
        expect($template->is_active)->toBeFalse();
    }

    #[Test]
    public function it_does_not_retrieve_inactive_templates(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'inactive_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template',
            'is_active'     => false,
        ]);

        Cache::flush();

        $template = $this->service->getTemplate('inactive_template');

        expect($template)->toBeNull();
    }

    #[Test]
    public function it_seeds_default_templates(): void
    {
        $this->service->seedDefaultTemplates();

        $templates = AiPromptTemplate::all();

        expect($templates->count())->toBeGreaterThanOrEqual(6);

        // Check for specific templates
        expect(AiPromptTemplate::where('name', 'transaction_query')->exists())->toBeTrue();
        expect(AiPromptTemplate::where('name', 'balance_query')->exists())->toBeTrue();
        expect(AiPromptTemplate::where('name', 'spending_analysis')->exists())->toBeTrue();
        expect(AiPromptTemplate::where('name', 'compliance_decision')->exists())->toBeTrue();
    }

    #[Test]
    public function it_returns_statistics(): void
    {
        $this->service->seedDefaultTemplates();

        $stats = $this->service->getStatistics();

        expect($stats)->toHaveKey('total_templates');
        expect($stats)->toHaveKey('active_templates');
        expect($stats)->toHaveKey('by_category');
        expect($stats)->toHaveKey('total_usage');
        expect($stats['total_templates'])->toBeGreaterThan(0);
    }

    #[Test]
    public function it_updates_existing_template(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'updatable',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'Original system prompt',
            'user_template' => 'Original user template',
        ]);

        $this->service->upsertTemplate([
            'name'          => 'updatable',
            'category'      => AiPromptTemplate::CATEGORY_ANALYSIS,
            'system_prompt' => 'Updated system prompt',
            'user_template' => 'Updated user template',
        ]);

        $template = AiPromptTemplate::where('name', 'updatable')->first();

        expect($template->system_prompt)->toBe('Updated system prompt');
        expect($template->category)->toBe(AiPromptTemplate::CATEGORY_ANALYSIS);
        expect(AiPromptTemplate::where('name', 'updatable')->count())->toBe(1);
    }

    #[Test]
    public function it_caches_templates(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'cached_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template',
        ]);

        // First call - should cache
        $this->service->getTemplate('cached_template');

        // Verify cache was set
        expect(Cache::has('ai_prompt_template:cached_template'))->toBeTrue();
    }

    #[Test]
    public function it_clears_cache_on_update(): void
    {
        $this->service->upsertTemplate([
            'name'          => 'cache_test',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System 1',
            'user_template' => 'Template 1',
        ]);

        // Populate cache
        $this->service->getTemplate('cache_test');

        // Update
        $this->service->upsertTemplate([
            'name'          => 'cache_test',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System 2',
            'user_template' => 'Template 2',
        ]);

        // Cache should be cleared
        expect(Cache::has('ai_prompt_template:cache_test'))->toBeFalse();
    }
}
