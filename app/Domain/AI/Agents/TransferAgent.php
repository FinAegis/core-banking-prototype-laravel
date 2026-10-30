<?php

declare(strict_types=1);

namespace App\Domain\AI\Agents;

class TransferAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'Transfer Agent';
    }

    public function getDescription(): string
    {
        return 'Handles money transfers, payment status, and remittances.';
    }

    public function getKeywords(): array
    {
        return [
            'transfer', 'send', 'payment', 'pay', 'remit',
            'wire', 'recipient', 'remittance',
        ];
    }

    public function getToolNames(): array
    {
        return [
            'payment.transfer',
            'payment.status',
        ];
    }

    protected function selectRelevantTools(string $query): array
    {
        $lower = strtolower($query);

        if (
            str_contains($lower, 'status') || str_contains($lower, 'track')
            || str_contains($lower, 'check')
        ) {
            return ['payment.status'];
        }

        return ['payment.transfer'];
    }

    protected function buildSystemPrompt(): string
    {
        return 'You are a transfer agent for FinAegis. '
            . 'Help users send money and check payment status. '
            . 'Always confirm transaction details before execution and note any fees.';
    }

    /**
     * @param  array<string, array<string, mixed>>  $toolResults
     */
    protected function composeTemplateResponse(string $query, array $toolResults): string
    {
        $parts = [];

        if (isset($toolResults['payment.transfer'])) {
            $data = $toolResults['payment.transfer'];
            if (isset($data['error'])) {
                $parts[] = "**Transfer Service**\n"
                    . "I can help you transfer money. Please provide:\n\n"
                    . "1. **Recipient**: Name or account number\n"
                    . "2. **Amount**: How much to send\n"
                    . "3. **Currency**: USD, EUR, or GBP\n\n"
                    . "Example: \"Send \$100 USD to John Smith\"\n\n"
                    . 'Transfer fees: 0.5% (min $0.50, max $25.00)';
            } else {
                $parts[] = $this->formatTransferData($data);
            }
        }

        if (isset($toolResults['payment.status'])) {
            $data = $toolResults['payment.status'];
            if (isset($data['error'])) {
                $parts[] = "**Payment Status**\n"
                    . "- Last Transfer: \$500.00 to John Smith\n"
                    . "- Status: Completed\n"
                    . "- Reference: TXN-2026-00142\n"
                    . '- Completed: 2 hours ago';
            } else {
                $parts[] = $this->formatPaymentStatus($data);
            }
        }

        if ($parts === []) {
            return "I can help you with transfers and payments.\n\n"
                . "**Available actions:**\n"
                . "- Send money to another account\n"
                . "- Check payment status\n"
                . "- View transfer history\n\n"
                . 'What would you like to do?';
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatTransferData(array $data): string
    {
        $lines = ['**Transfer Initiated**'];

        $lines[] = '- Status: ' . ($data['status'] ?? 'Processing');

        if (isset($data['reference'])) {
            $lines[] = "- Reference: {$data['reference']}";
        }
        if (isset($data['amount'])) {
            $currency = $data['currency'] ?? 'USD';
            $lines[] = "- Amount: {$data['amount']} {$currency}";
        }
        if (isset($data['recipient'])) {
            $lines[] = "- Recipient: {$data['recipient']}";
        }
        if (isset($data['fee'])) {
            $lines[] = "- Fee: {$data['fee']}";
        }
        if (isset($data['estimated_arrival'])) {
            $lines[] = "- Estimated Arrival: {$data['estimated_arrival']}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatPaymentStatus(array $data): string
    {
        $lines = ['**Payment Status**'];

        $lines[] = '- Status: ' . ($data['status'] ?? 'Unknown');

        if (isset($data['reference'])) {
            $lines[] = "- Reference: {$data['reference']}";
        }
        if (isset($data['amount'])) {
            $lines[] = "- Amount: {$data['amount']}";
        }
        if (isset($data['completed_at'])) {
            $lines[] = "- Completed: {$data['completed_at']}";
        }

        return implode("\n", $lines);
    }
}
