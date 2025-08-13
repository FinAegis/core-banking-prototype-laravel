<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities;

use Workflow\Activity;

class IntentRecognitionActivity extends Activity
{
    /**
     * @param array<string, mixed> $context
     * @return array{type: string, confidence: float, entities: array<string, mixed>}
     */
    public function recognize(string $query, array $context = []): array
    {
        // This is a placeholder implementation for testing
        // In production, this would integrate with an LLM or NLP service

        // Simulate intent recognition based on keywords
        $intent = match (true) {
            str_contains(strtolower($query), 'balance')  => 'balance_inquiry',
            str_contains(strtolower($query), 'transfer') => 'money_transfer',
            str_contains(strtolower($query), 'payment')  => 'payment_request',
            str_contains(strtolower($query), 'loan')     => 'loan_application',
            str_contains(strtolower($query), 'trade')    => 'trading_request',
            default                                      => 'general_inquiry'
        };

        return [
            'type'       => $intent,
            'confidence' => 0.95,
            'entities'   => array_merge([
                'query'     => $query,
                'timestamp' => now()->toIso8601String(),
            ], $context),
        ];
    }
}
