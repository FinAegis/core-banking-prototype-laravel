<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Models;

use App\Domain\AI\Models\AiPromptTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiPromptTemplateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_prompt_template(): void
    {
        $template = AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'test_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'You are a helpful banking assistant.',
            'user_template' => 'User query: {{query}}',
        ]);

        expect($template)->toBeInstanceOf(AiPromptTemplate::class);
        expect($template->name)->toBe('test_template');
        expect($template->category)->toBe(AiPromptTemplate::CATEGORY_QUERY);
        expect($template->is_active)->toBeTrue();
    }

    #[Test]
    public function it_returns_valid_categories(): void
    {
        $categories = AiPromptTemplate::categories();

        expect($categories)->toContain(AiPromptTemplate::CATEGORY_QUERY);
        expect($categories)->toContain(AiPromptTemplate::CATEGORY_ANALYSIS);
        expect($categories)->toContain(AiPromptTemplate::CATEGORY_COMPLIANCE);
        expect($categories)->toContain(AiPromptTemplate::CATEGORY_CODE_GENERATION);
    }

    #[Test]
    public function it_renders_user_template_with_variables(): void
    {
        $template = AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'transfer_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'Banking assistant',
            'user_template' => 'Transfer {{amount}} {{currency}} to {{recipient}}',
        ]);

        $rendered = $template->renderUserTemplate([
            'amount'    => '1000',
            'currency'  => 'USD',
            'recipient' => 'John Doe',
        ]);

        expect($rendered)->toBe('Transfer 1000 USD to John Doe');
    }

    #[Test]
    public function it_extracts_required_variables(): void
    {
        $template = AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'variable_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Hello {{name}}, your balance is {{balance}} {{currency}}',
        ]);

        $variables = $template->getRequiredVariables();

        expect($variables)->toContain('name');
        expect($variables)->toContain('balance');
        expect($variables)->toContain('currency');
        expect($variables)->toHaveCount(3);
    }

    #[Test]
    public function it_validates_required_variables_presence(): void
    {
        $template = AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'validation_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Transfer {{amount}} to {{recipient}}',
        ]);

        // All variables present
        expect($template->hasRequiredVariables([
            'amount'    => 100,
            'recipient' => 'John',
        ]))->toBeTrue();

        // Missing variable
        expect($template->hasRequiredVariables([
            'amount' => 100,
        ]))->toBeFalse();
    }

    #[Test]
    public function it_increments_usage_count(): void
    {
        $template = AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'usage_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template',
        ]);

        expect($template->usage_count ?? 0)->toBe(0);

        $template->incrementUsage();
        $template->refresh();

        expect($template->usage_count)->toBe(1);

        $template->incrementUsage();
        $template->incrementUsage();
        $template->refresh();

        expect($template->usage_count)->toBe(3);
    }

    #[Test]
    public function it_scopes_to_active_templates(): void
    {
        AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'active_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template',
            'is_active'     => true,
        ]);

        AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'inactive_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template',
            'is_active'     => false,
        ]);

        $activeTemplates = AiPromptTemplate::active()->get();

        expect($activeTemplates)->toHaveCount(1);
        expect($activeTemplates->first()->name)->toBe('active_template');
    }

    #[Test]
    public function it_scopes_by_category(): void
    {
        AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'query_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template',
        ]);

        AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'analysis_template',
            'category'      => AiPromptTemplate::CATEGORY_ANALYSIS,
            'system_prompt' => 'System',
            'user_template' => 'Template',
        ]);

        $queryTemplates = AiPromptTemplate::category(AiPromptTemplate::CATEGORY_QUERY)->get();
        $analysisTemplates = AiPromptTemplate::category(AiPromptTemplate::CATEGORY_ANALYSIS)->get();

        expect($queryTemplates)->toHaveCount(1);
        expect($analysisTemplates)->toHaveCount(1);
    }

    #[Test]
    public function it_casts_variables_and_metadata_to_array(): void
    {
        $template = AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'cast_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template {{var}}',
            'variables'     => ['var' => 'Variable description'],
            'metadata'      => ['author' => 'Test', 'version' => '1.0'],
        ]);

        expect($template->variables)->toBeArray();
        expect($template->metadata)->toBeArray();
        expect($template->variables['var'])->toBe('Variable description');
        expect($template->metadata['author'])->toBe('Test');
    }

    #[Test]
    public function it_supports_soft_deletes(): void
    {
        $template = AiPromptTemplate::create([
            'uuid'          => fake()->uuid(),
            'name'          => 'deletable_template',
            'category'      => AiPromptTemplate::CATEGORY_QUERY,
            'system_prompt' => 'System',
            'user_template' => 'Template',
        ]);

        $template->delete();

        // Regular query should not find it
        expect(AiPromptTemplate::where('name', 'deletable_template')->first())->toBeNull();

        // But it should be in trashed
        expect(AiPromptTemplate::withTrashed()->where('name', 'deletable_template')->first())->not->toBeNull();
    }
}
