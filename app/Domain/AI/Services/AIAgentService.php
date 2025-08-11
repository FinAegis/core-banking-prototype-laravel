<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Illuminate\Support\Str;

class AIAgentService
{
    /**
     * Send a chat message to the AI agent.
     */
    public function chat(
        string $message,
        string $conversationId,
        int $userId,
        array $context = [],
        array $options = []
    ): array {
        // Demo implementation - returns simulated response
        return [
            'message_id' => Str::uuid()->toString(),
            'content'    => $this->generateDemoResponse($message),
            'confidence' => 0.85,
            'tools_used' => ['AccountBalanceTool', 'TransactionHistoryTool'],
            'context'    => $context,
        ];
    }

    /**
     * Store user feedback about an AI response.
     */
    public function storeFeedback(
        string $messageId,
        int $userId,
        int $rating,
        ?string $feedback = null
    ): void {
        // Store feedback for future model improvements
        // In production, this would save to database or analytics service
    }

    /**
     * Generate a demo response based on the message.
     */
    private function generateDemoResponse(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'balance')) {
            return 'Your current account balance is $12,456.78 in your main account.';
        }

        if (str_contains($lowerMessage, 'transaction')) {
            return 'Your recent transactions include: Amazon Purchase ($156.32), Transfer to John ($500.00), and Salary Credit ($5,000.00).';
        }

        if (str_contains($lowerMessage, 'transfer')) {
            return 'I can help you transfer money. Please provide the recipient and amount you want to transfer.';
        }

        if (str_contains($lowerMessage, 'gcu') || str_contains($lowerMessage, 'exchange')) {
            return 'The current GCU exchange rate is 1 GCU = 1.00 USD. The rate is stable with minimal volatility.';
        }

        return 'I understand your query. In a production environment, I would process this using our AI models and banking tools. How else can I assist you?';
    }
}
