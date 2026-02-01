<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\ValueObjects\LLMResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes natural language input for banking queries.
 * Handles intent extraction, entity recognition, and query interpretation.
 */
class NaturalLanguageProcessorService
{
    /**
     * Intent categories for banking queries.
     */
    public const INTENT_BALANCE_QUERY = 'balance_query';

    public const INTENT_TRANSACTION_QUERY = 'transaction_query';

    public const INTENT_TRANSFER_REQUEST = 'transfer_request';

    public const INTENT_LOAN_INQUIRY = 'loan_inquiry';

    public const INTENT_INVESTMENT_QUERY = 'investment_query';

    public const INTENT_COMPLIANCE_QUERY = 'compliance_query';

    public const INTENT_GENERAL_QUERY = 'general_query';

    public const INTENT_UNKNOWN = 'unknown';

    /**
     * Entity types for extraction.
     */
    public const ENTITY_AMOUNT = 'amount';

    public const ENTITY_CURRENCY = 'currency';

    public const ENTITY_DATE = 'date';

    public const ENTITY_DATE_RANGE = 'date_range';

    public const ENTITY_ACCOUNT = 'account';

    public const ENTITY_RECIPIENT = 'recipient';

    public const ENTITY_CATEGORY = 'category';

    public function __construct(
        private readonly LLMOrchestrationService $llmService
    ) {
    }

    /**
     * Process a natural language query and extract intent and entities.
     *
     * @param string $query
     * @param array<string, mixed> $context
     * @return array{intent: string, entities: array<string, mixed>, confidence: float, explanation: string}
     */
    public function processQuery(string $query, array $context = []): array
    {
        $intent = $this->detectIntent($query);
        $entities = $this->extractEntities($query, $intent);
        $confidence = $this->calculateConfidence($intent, $entities, $query);

        return [
            'intent'      => $intent,
            'entities'    => $entities,
            'confidence'  => $confidence,
            'explanation' => $this->generateExplanation($query, $intent, $entities),
        ];
    }

    /**
     * Detect the intent of a natural language query.
     *
     * @param string $query
     * @return string
     */
    public function detectIntent(string $query): string
    {
        $lowerQuery = strtolower($query);

        // Investment-related patterns (check FIRST as they're specific)
        $investmentPatterns = [
            'invest', 'portfolio', 'stock', 'bond', 'yield',
            'return on', 'gcu', 'basket', 'asset',
        ];

        foreach ($investmentPatterns as $pattern) {
            if (str_contains($lowerQuery, $pattern)) {
                return self::INTENT_INVESTMENT_QUERY;
            }
        }

        // Balance-related patterns
        $balancePatterns = [
            'balance', 'how much', 'do i have', 'what\'s in my', 'total funds',
            'available', 'current balance', 'account balance', 'money do i have',
        ];

        foreach ($balancePatterns as $pattern) {
            if (str_contains($lowerQuery, $pattern)) {
                return self::INTENT_BALANCE_QUERY;
            }
        }

        // Transaction-related patterns
        $transactionPatterns = [
            'transaction', 'spending', 'spent', 'purchase', 'payment',
            'recent activity', 'history', 'what did i buy',
            'where did my money go', 'expenses',
        ];

        foreach ($transactionPatterns as $pattern) {
            if (str_contains($lowerQuery, $pattern)) {
                return self::INTENT_TRANSACTION_QUERY;
            }
        }

        // Transfer-related patterns
        $transferPatterns = [
            'transfer', 'send money', 'send to', 'pay', 'wire',
            'move funds', 'send funds', 'make a payment',
        ];

        foreach ($transferPatterns as $pattern) {
            if (str_contains($lowerQuery, $pattern)) {
                return self::INTENT_TRANSFER_REQUEST;
            }
        }

        // Loan-related patterns
        $loanPatterns = [
            'loan', 'borrow', 'credit', 'financing', 'mortgage',
            'interest rate', 'repayment', 'lending',
        ];

        foreach ($loanPatterns as $pattern) {
            if (str_contains($lowerQuery, $pattern)) {
                return self::INTENT_LOAN_INQUIRY;
            }
        }

        // Compliance-related patterns
        $compliancePatterns = [
            'kyc', 'verify', 'document', 'compliance', 'limit',
            'identity', 'verification status', 'aml',
        ];

        foreach ($compliancePatterns as $pattern) {
            if (str_contains($lowerQuery, $pattern)) {
                return self::INTENT_COMPLIANCE_QUERY;
            }
        }

        // General query patterns
        $generalPatterns = [
            'help', 'how do i', 'what is', 'explain', 'tell me about',
        ];

        foreach ($generalPatterns as $pattern) {
            if (str_contains($lowerQuery, $pattern)) {
                return self::INTENT_GENERAL_QUERY;
            }
        }

        return self::INTENT_UNKNOWN;
    }

    /**
     * Extract entities from a query based on intent.
     *
     * @param string $query
     * @param string $intent
     * @return array<string, mixed>
     */
    public function extractEntities(string $query, string $intent): array
    {
        $entities = [];

        // Extract amount
        if (preg_match('/\$?([\d,]+(?:\.\d{2})?)\s*(?:dollars?|usd|eur|gbp|gcu)?/i', $query, $matches)) {
            $entities[self::ENTITY_AMOUNT] = [
                'value' => (float) str_replace(',', '', $matches[1]),
                'raw'   => $matches[0],
            ];
        }

        // Extract currency
        if (preg_match('/\b(usd|eur|gbp|gcu|dollars?|euros?|pounds?)\b/i', $query, $matches)) {
            $currency = strtoupper($matches[1]);
            $currency = match (strtolower($matches[1])) {
                'dollar', 'dollars' => 'USD',
                'euro', 'euros' => 'EUR',
                'pound', 'pounds' => 'GBP',
                default => $currency,
            };
            $entities[self::ENTITY_CURRENCY] = $currency;
        }

        // Extract date references
        $dateEntities = $this->extractDateEntities($query);
        if (! empty($dateEntities)) {
            $entities = array_merge($entities, $dateEntities);
        }

        // Extract recipient for transfer intents
        if ($intent === self::INTENT_TRANSFER_REQUEST) {
            if (preg_match('/(?:to|for)\s+([A-Za-z\s]+?)(?:\s+\$|\s+\d|$)/i', $query, $matches)) {
                $entities[self::ENTITY_RECIPIENT] = trim($matches[1]);
            }
        }

        // Extract account references
        if (preg_match('/\b(checking|savings|main|primary|business)\s*(?:account)?\b/i', $query, $matches)) {
            $entities[self::ENTITY_ACCOUNT] = strtolower($matches[1]);
        }

        // Extract category for transaction queries
        if ($intent === self::INTENT_TRANSACTION_QUERY) {
            $categories = [
                'groceries', 'dining', 'restaurants', 'transportation', 'travel',
                'entertainment', 'shopping', 'utilities', 'subscriptions', 'healthcare',
            ];

            foreach ($categories as $category) {
                if (str_contains(strtolower($query), $category)) {
                    $entities[self::ENTITY_CATEGORY] = $category;
                    break;
                }
            }
        }

        return $entities;
    }

    /**
     * Extract date-related entities from query.
     *
     * @param string $query
     * @return array<string, mixed>
     */
    private function extractDateEntities(string $query): array
    {
        $entities = [];
        $lowerQuery = strtolower($query);

        // Relative date patterns
        $relativeDates = [
            'today'        => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'yesterday'    => ['start' => now()->subDay()->startOfDay(), 'end' => now()->subDay()->endOfDay()],
            'this week'    => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'last week'    => ['start' => now()->subWeek()->startOfWeek(), 'end' => now()->subWeek()->endOfWeek()],
            'this month'   => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'last month'   => ['start' => now()->subMonth()->startOfMonth(), 'end' => now()->subMonth()->endOfMonth()],
            'last 7 days'  => ['start' => now()->subDays(7), 'end' => now()],
            'last 30 days' => ['start' => now()->subDays(30), 'end' => now()],
            'last 90 days' => ['start' => now()->subDays(90), 'end' => now()],
            'this year'    => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            'last year'    => ['start' => now()->subYear()->startOfYear(), 'end' => now()->subYear()->endOfYear()],
        ];

        foreach ($relativeDates as $pattern => $dates) {
            if (str_contains($lowerQuery, $pattern)) {
                $entities[self::ENTITY_DATE_RANGE] = [
                    'type'  => 'relative',
                    'raw'   => $pattern,
                    'start' => $dates['start']->toIso8601String(),
                    'end'   => $dates['end']->toIso8601String(),
                ];
                break;
            }
        }

        // Extract "N days/weeks/months ago" patterns
        if (preg_match('/(\d+)\s+(days?|weeks?|months?|years?)\s+ago/i', $query, $matches)) {
            $amount = (int) $matches[1];
            $unit = strtolower($matches[2]);

            $date = match (true) {
                str_starts_with($unit, 'day')   => now()->subDays($amount),
                str_starts_with($unit, 'week')  => now()->subWeeks($amount),
                str_starts_with($unit, 'month') => now()->subMonths($amount),
                str_starts_with($unit, 'year')  => now()->subYears($amount),
                default                         => now(),
            };

            $entities[self::ENTITY_DATE] = [
                'type' => 'relative_past',
                'raw'  => $matches[0],
                'date' => $date->toIso8601String(),
            ];
        }

        return $entities;
    }

    /**
     * Calculate confidence score for the extracted intent and entities.
     *
     * @param string $intent
     * @param array<string, mixed> $entities
     * @param string $query
     * @return float
     */
    private function calculateConfidence(string $intent, array $entities, string $query): float
    {
        $baseConfidence = 0.5;

        // Unknown intent has low confidence
        if ($intent === self::INTENT_UNKNOWN) {
            return 0.2;
        }

        // Add confidence based on intent detection
        $baseConfidence += 0.2;

        // Add confidence for each extracted entity
        $entityCount = count($entities);
        $baseConfidence += min($entityCount * 0.1, 0.2);

        // Add confidence for query length (not too short, not too long)
        $queryLength = strlen($query);
        if ($queryLength >= 10 && $queryLength <= 200) {
            $baseConfidence += 0.1;
        }

        return min($baseConfidence, 1.0);
    }

    /**
     * Generate a human-readable explanation of query interpretation.
     *
     * @param string $query
     * @param string $intent
     * @param array<string, mixed> $entities
     * @return string
     */
    private function generateExplanation(string $query, string $intent, array $entities): string
    {
        $intentDescriptions = [
            self::INTENT_BALANCE_QUERY     => 'checking account balance',
            self::INTENT_TRANSACTION_QUERY => 'querying transactions',
            self::INTENT_TRANSFER_REQUEST  => 'initiating a money transfer',
            self::INTENT_LOAN_INQUIRY      => 'inquiring about loan options',
            self::INTENT_INVESTMENT_QUERY  => 'reviewing investments',
            self::INTENT_COMPLIANCE_QUERY  => 'checking compliance status',
            self::INTENT_GENERAL_QUERY     => 'asking a general question',
            self::INTENT_UNKNOWN           => 'processing your request',
        ];

        $explanation = 'I understood you want to ' . ($intentDescriptions[$intent] ?? 'help you');

        // Add entity-specific details
        $details = [];

        if (isset($entities[self::ENTITY_AMOUNT])) {
            $details[] = 'amount: $' . number_format($entities[self::ENTITY_AMOUNT]['value'], 2);
        }

        if (isset($entities[self::ENTITY_CURRENCY])) {
            $details[] = 'currency: ' . $entities[self::ENTITY_CURRENCY];
        }

        if (isset($entities[self::ENTITY_DATE_RANGE])) {
            $details[] = 'time period: ' . $entities[self::ENTITY_DATE_RANGE]['raw'];
        }

        if (isset($entities[self::ENTITY_RECIPIENT])) {
            $details[] = 'recipient: ' . $entities[self::ENTITY_RECIPIENT];
        }

        if (isset($entities[self::ENTITY_ACCOUNT])) {
            $details[] = 'account: ' . $entities[self::ENTITY_ACCOUNT];
        }

        if (isset($entities[self::ENTITY_CATEGORY])) {
            $details[] = 'category: ' . $entities[self::ENTITY_CATEGORY];
        }

        if (! empty($details)) {
            $explanation .= ' with ' . implode(', ', $details);
        }

        return $explanation . '.';
    }

    /**
     * Use LLM for advanced query understanding when rule-based fails.
     *
     * @param string $query
     * @param array<string, mixed> $context
     * @return array{intent: string, entities: array<string, mixed>, confidence: float, explanation: string}
     */
    public function processQueryWithLLM(string $query, array $context = []): array
    {
        // First try rule-based processing
        $result = $this->processQuery($query, $context);

        // If confidence is low, use LLM for better understanding
        if ($result['confidence'] < 0.6 && ! $this->llmService->isDemoMode()) {
            try {
                $systemPrompt = $this->buildLLMSystemPrompt();
                $userPrompt = $this->buildLLMUserPrompt($query, $context);

                $response = $this->llmService->complete($systemPrompt, $userPrompt, [
                    'temperature' => 0.3, // Lower temperature for more deterministic results
                    'max_tokens'  => 500,
                ]);

                $llmResult = $this->parseLLMResponse($response);

                if ($llmResult !== null) {
                    return $llmResult;
                }
            } catch (Throwable $e) {
                Log::warning('LLM query processing failed, using rule-based result', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Build system prompt for LLM query understanding.
     */
    private function buildLLMSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a banking AI assistant that analyzes user queries to extract intent and entities.

Respond in JSON format with:
{
    "intent": "one of: balance_query, transaction_query, transfer_request, loan_inquiry, investment_query, compliance_query, general_query",
    "entities": {
        "amount": {"value": number, "raw": "original text"},
        "currency": "USD|EUR|GBP|GCU",
        "date_range": {"start": "ISO date", "end": "ISO date", "raw": "original text"},
        "recipient": "name or identifier",
        "account": "account type",
        "category": "spending category"
    },
    "confidence": 0.0-1.0,
    "explanation": "Brief explanation of understanding"
}

Only include entities that are explicitly mentioned. Be accurate and conservative with confidence scores.
PROMPT;
    }

    /**
     * Build user prompt for LLM query understanding.
     *
     * @param string $query
     * @param array<string, mixed> $context
     */
    private function buildLLMUserPrompt(string $query, array $context): string
    {
        $contextStr = ! empty($context) ? "\nContext: " . json_encode($context) : '';

        return "Analyze this banking query: \"{$query}\"{$contextStr}";
    }

    /**
     * Parse LLM response into structured result.
     *
     * @param LLMResponse $response
     * @return array{intent: string, entities: array<string, mixed>, confidence: float, explanation: string}|null
     */
    private function parseLLMResponse(LLMResponse $response): ?array
    {
        try {
            $content = $response->content;

            // Try to extract JSON from response
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $data = json_decode($matches[0], true);

                if (is_array($data) && isset($data['intent'])) {
                    return [
                        'intent'      => $data['intent'] ?? self::INTENT_UNKNOWN,
                        'entities'    => $data['entities'] ?? [],
                        'confidence'  => (float) ($data['confidence'] ?? 0.7),
                        'explanation' => $data['explanation'] ?? 'Processed with AI assistance.',
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::warning('Failed to parse LLM response', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
