<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class BankStatement
{
    public function __construct(
        public readonly string $id,
        public readonly string $bankCode,
        public readonly string $accountId,
        public readonly Carbon $periodFrom,
        public readonly Carbon $periodTo,
        public readonly string $format,
        public readonly float $openingBalance,
        public readonly float $closingBalance,
        public readonly string $currency,
        public readonly Collection $transactions,
        public readonly array $summary,
        public readonly ?string $fileUrl,
        public readonly ?string $fileContent,
        public readonly Carbon $generatedAt,
        public readonly array $metadata = [],
    ) {}
    
    /**
     * Get total debits
     */
    public function getTotalDebits(): float
    {
        return $this->transactions
            ->filter(fn($tx) => $tx->isDebit())
            ->sum(fn($tx) => $tx->getAbsoluteAmount());
    }
    
    /**
     * Get total credits
     */
    public function getTotalCredits(): float
    {
        return $this->transactions
            ->filter(fn($tx) => $tx->isCredit())
            ->sum(fn($tx) => $tx->getAbsoluteAmount());
    }
    
    /**
     * Get net change
     */
    public function getNetChange(): float
    {
        return $this->closingBalance - $this->openingBalance;
    }
    
    /**
     * Get transaction count by type
     */
    public function getTransactionCountByType(): array
    {
        return $this->transactions
            ->groupBy('category')
            ->map(fn($group) => $group->count())
            ->toArray();
    }
    
    /**
     * Get average transaction amount
     */
    public function getAverageTransactionAmount(): float
    {
        if ($this->transactions->isEmpty()) {
            return 0;
        }
        
        return $this->transactions->avg(fn($tx) => $tx->getAbsoluteAmount());
    }
    
    /**
     * Check if statement is balanced
     */
    public function isBalanced(): bool
    {
        $calculatedClosing = $this->openingBalance + $this->getTotalCredits() - $this->getTotalDebits();
        return abs($calculatedClosing - $this->closingBalance) < 0.01;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'bank_code' => $this->bankCode,
            'account_id' => $this->accountId,
            'period_from' => $this->periodFrom->toIso8601String(),
            'period_to' => $this->periodTo->toIso8601String(),
            'format' => $this->format,
            'opening_balance' => $this->openingBalance,
            'closing_balance' => $this->closingBalance,
            'currency' => $this->currency,
            'transactions' => $this->transactions->map(fn($tx) => $tx->toArray())->toArray(),
            'summary' => $this->summary,
            'file_url' => $this->fileUrl,
            'generated_at' => $this->generatedAt->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        $transactions = collect($data['transactions'] ?? [])
            ->map(fn($tx) => BankTransaction::fromArray($tx));
        
        return new self(
            id: $data['id'],
            bankCode: $data['bank_code'],
            accountId: $data['account_id'],
            periodFrom: Carbon::parse($data['period_from']),
            periodTo: Carbon::parse($data['period_to']),
            format: $data['format'],
            openingBalance: (float) $data['opening_balance'],
            closingBalance: (float) $data['closing_balance'],
            currency: $data['currency'],
            transactions: $transactions,
            summary: $data['summary'] ?? [],
            fileUrl: $data['file_url'] ?? null,
            fileContent: $data['file_content'] ?? null,
            generatedAt: Carbon::parse($data['generated_at']),
            metadata: $data['metadata'] ?? [],
        );
    }
}