<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Models\AiPromptTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Manages banking-specific prompt templates for LLM interactions.
 */
class PromptTemplateService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const CACHE_PREFIX = 'ai_prompt_template:';

    /**
     * Get a prompt template by name.
     */
    public function getTemplate(string $name): ?AiPromptTemplate
    {
        return Cache::remember(
            self::CACHE_PREFIX . $name,
            self::CACHE_TTL,
            fn () => AiPromptTemplate::where('name', $name)->active()->first()
        );
    }

    /**
     * Get a prompt template by UUID.
     */
    public function getTemplateByUuid(string $uuid): ?AiPromptTemplate
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'uuid:' . $uuid,
            self::CACHE_TTL,
            fn () => AiPromptTemplate::where('uuid', $uuid)->active()->first()
        );
    }

    /**
     * Get all templates in a category.
     *
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTemplatesByCategory(string $category)
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'category:' . $category,
            self::CACHE_TTL,
            fn () => AiPromptTemplate::category($category)->active()->get()
        );
    }

    /**
     * Render a template with variables.
     *
     * @param string $templateName
     * @param array<string, mixed> $variables
     * @return array{system_prompt: string, user_prompt: string}|null
     */
    public function renderTemplate(string $templateName, array $variables = []): ?array
    {
        $template = $this->getTemplate($templateName);

        if (! $template) {
            return null;
        }

        // Validate required variables
        if (! $template->hasRequiredVariables($variables)) {
            $missing = array_diff($template->getRequiredVariables(), array_keys($variables));
            throw new InvalidArgumentException(
                'Missing required variables for template: ' . implode(', ', $missing)
            );
        }

        // Increment usage
        $template->incrementUsage();

        return [
            'system_prompt' => $template->system_prompt,
            'user_prompt'   => $template->renderUserTemplate($variables),
        ];
    }

    /**
     * Create or update a prompt template.
     *
     * @param array<string, mixed> $data
     */
    public function upsertTemplate(array $data): AiPromptTemplate
    {
        $template = AiPromptTemplate::updateOrCreate(
            ['name' => $data['name']],
            [
                'uuid'          => $data['uuid'] ?? Str::uuid()->toString(),
                'category'      => $data['category'],
                'system_prompt' => $data['system_prompt'],
                'user_template' => $data['user_template'],
                'variables'     => $data['variables'] ?? null,
                'metadata'      => $data['metadata'] ?? null,
                'version'       => $data['version'] ?? '1.0',
                'is_active'     => $data['is_active'] ?? true,
            ]
        );

        // Clear cache
        $this->clearTemplateCache($data['name']);

        return $template;
    }

    /**
     * Deactivate a template.
     */
    public function deactivateTemplate(string $name): bool
    {
        $template = AiPromptTemplate::where('name', $name)->first();

        if (! $template) {
            return false;
        }

        $template->update(['is_active' => false]);
        $this->clearTemplateCache($name);

        return true;
    }

    /**
     * Clear cache for a template.
     */
    public function clearTemplateCache(string $name): void
    {
        Cache::forget(self::CACHE_PREFIX . $name);
    }

    /**
     * Clear all template caches.
     */
    public function clearAllCaches(): void
    {
        // Clear category caches
        foreach (AiPromptTemplate::categories() as $category) {
            Cache::forget(self::CACHE_PREFIX . 'category:' . $category);
        }

        // Clear individual template caches
        AiPromptTemplate::all()->each(function ($template) {
            Cache::forget(self::CACHE_PREFIX . $template->name);
            Cache::forget(self::CACHE_PREFIX . 'uuid:' . $template->uuid);
        });
    }

    /**
     * Seed default banking prompt templates.
     */
    public function seedDefaultTemplates(): void
    {
        $templates = $this->getDefaultTemplates();

        foreach ($templates as $template) {
            $this->upsertTemplate($template);
        }
    }

    /**
     * Get default banking prompt templates.
     *
     * @return array<array<string, mixed>>
     */
    private function getDefaultTemplates(): array
    {
        return [
            // Query Templates
            [
                'name'          => 'transaction_query',
                'category'      => AiPromptTemplate::CATEGORY_QUERY,
                'system_prompt' => <<<'PROMPT'
You are a banking AI assistant helping users understand their transaction history.
Provide clear, concise summaries of transaction data. Format currency amounts properly.
Do not reveal sensitive data. If asked about specific transactions, provide relevant details only.
Always be helpful but maintain security awareness.
PROMPT,
                'user_template' => <<<'PROMPT'
User Query: {{query}}

Transaction Context:
- Account: {{account_id}}
- Date Range: {{date_range}}
- Currency: {{currency}}

{{additional_context}}

Please analyze and respond to the user's query about their transactions.
PROMPT,
                'variables' => [
                    'query'              => 'User query text',
                    'account_id'         => 'Account identifier',
                    'date_range'         => 'Date range for analysis',
                    'currency'           => 'Currency code',
                    'additional_context' => 'Additional transaction data',
                ],
                'version' => '1.0',
            ],
            [
                'name'          => 'balance_query',
                'category'      => AiPromptTemplate::CATEGORY_QUERY,
                'system_prompt' => <<<'PROMPT'
You are a banking AI assistant helping users understand their account balances.
Provide accurate balance information in a friendly, helpful manner.
Include relevant details like available balance, pending transactions, and currency conversions when applicable.
PROMPT,
                'user_template' => <<<'PROMPT'
User Query: {{query}}

Balance Information:
{{balance_data}}

Please provide a helpful response about the user's account balance.
PROMPT,
                'variables' => [
                    'query'        => 'User query text',
                    'balance_data' => 'Current balance information',
                ],
                'version' => '1.0',
            ],
            // Analysis Templates
            [
                'name'          => 'spending_analysis',
                'category'      => AiPromptTemplate::CATEGORY_ANALYSIS,
                'system_prompt' => <<<'PROMPT'
You are a financial analyst AI helping users understand their spending patterns.
Analyze transaction data to identify trends, categories, and provide actionable insights.
Be specific with numbers but avoid being judgmental about spending habits.
Offer constructive suggestions for financial improvement when appropriate.
PROMPT,
                'user_template' => <<<'PROMPT'
Analyze spending patterns for the following data:

Period: {{date_range}}
Total Spending: {{total_spent}}
Categories:
{{category_breakdown}}

User Question: {{query}}

Provide insights and recommendations.
PROMPT,
                'variables' => [
                    'date_range'         => 'Analysis period',
                    'total_spent'        => 'Total amount spent',
                    'category_breakdown' => 'Spending by category',
                    'query'              => 'Specific user question',
                ],
                'version' => '1.0',
            ],
            [
                'name'          => 'cash_flow_prediction',
                'category'      => AiPromptTemplate::CATEGORY_ANALYSIS,
                'system_prompt' => <<<'PROMPT'
You are a financial forecasting AI assistant. Analyze historical transaction patterns
to predict future cash flow. Consider recurring payments, seasonal patterns, and
income regularity. Provide confidence intervals for predictions.
PROMPT,
                'user_template' => <<<'PROMPT'
Generate cash flow prediction:

Historical Data ({{lookback_days}} days):
- Average Daily Income: {{avg_daily_income}}
- Average Daily Expenses: {{avg_daily_expenses}}
- Recurring Payments: {{recurring_payments}}

Prediction Period: {{prediction_days}} days

Provide a detailed cash flow forecast with key dates to watch.
PROMPT,
                'variables' => [
                    'lookback_days'      => 'Days of historical data',
                    'avg_daily_income'   => 'Average daily income',
                    'avg_daily_expenses' => 'Average daily expenses',
                    'recurring_payments' => 'List of recurring payments',
                    'prediction_days'    => 'Days to predict ahead',
                ],
                'version' => '1.0',
            ],
            // Compliance Templates
            [
                'name'          => 'compliance_decision',
                'category'      => AiPromptTemplate::CATEGORY_COMPLIANCE,
                'system_prompt' => <<<'PROMPT'
You are a compliance AI assistant supporting financial regulatory decisions.
Analyze transaction and customer data against compliance rules and regulations.
Flag potential issues but always recommend human review for final decisions.
Be conservative in risk assessment - when in doubt, escalate.
Never approve high-risk transactions automatically.
PROMPT,
                'user_template' => <<<'PROMPT'
Compliance Review Request:

Transaction Details:
{{transaction_details}}

Customer Risk Profile:
{{risk_profile}}

Applicable Rules:
{{compliance_rules}}

Historical Patterns:
{{historical_patterns}}

Provide compliance assessment with risk score and recommendation.
PROMPT,
                'variables' => [
                    'transaction_details' => 'Transaction information',
                    'risk_profile'        => 'Customer risk assessment',
                    'compliance_rules'    => 'Applicable regulations',
                    'historical_patterns' => 'Historical behavior data',
                ],
                'version' => '1.0',
            ],
            [
                'name'          => 'aml_screening',
                'category'      => AiPromptTemplate::CATEGORY_COMPLIANCE,
                'system_prompt' => <<<'PROMPT'
You are an AML (Anti-Money Laundering) screening assistant.
Analyze transactions for potential money laundering indicators.
Consider structuring, layering, integration patterns.
Always err on the side of caution and recommend human review for any suspicious patterns.
PROMPT,
                'user_template' => <<<'PROMPT'
AML Screening Request:

Transaction:
{{transaction_data}}

Account History:
{{account_history}}

Related Parties:
{{related_parties}}

Screen for AML red flags and provide detailed assessment.
PROMPT,
                'variables' => [
                    'transaction_data' => 'Transaction to screen',
                    'account_history'  => 'Account transaction history',
                    'related_parties'  => 'Involved parties information',
                ],
                'version' => '1.0',
            ],
            // Code Generation Templates
            [
                'name'          => 'smart_contract_generation',
                'category'      => AiPromptTemplate::CATEGORY_CODE_GENERATION,
                'system_prompt' => <<<'PROMPT'
You are a smart contract developer assistant specializing in secure financial contracts.
Generate Solidity code following best practices for security and gas efficiency.
Always include appropriate access controls, events, and error handling.
Warn about potential security vulnerabilities and suggest mitigations.
PROMPT,
                'user_template' => <<<'PROMPT'
Generate a smart contract with the following specifications:

Contract Type: {{contract_type}}
Requirements:
{{requirements}}

Security Considerations:
{{security_requirements}}

Token Standard (if applicable): {{token_standard}}

Generate secure, well-documented Solidity code.
PROMPT,
                'variables' => [
                    'contract_type'         => 'Type of contract to generate',
                    'requirements'          => 'Functional requirements',
                    'security_requirements' => 'Security requirements',
                    'token_standard'        => 'ERC standard if applicable',
                ],
                'version' => '1.0',
            ],
        ];
    }

    /**
     * Get template statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $templates = AiPromptTemplate::all();

        $byCategory = [];
        foreach (AiPromptTemplate::categories() as $category) {
            $byCategory[$category] = $templates->where('category', $category)->count();
        }

        return [
            'total_templates'    => $templates->count(),
            'active_templates'   => $templates->where('is_active', true)->count(),
            'by_category'        => $byCategory,
            'total_usage'        => $templates->sum('usage_count'),
            'most_used_template' => $templates->sortByDesc('usage_count')->first()?->name,
        ];
    }
}
