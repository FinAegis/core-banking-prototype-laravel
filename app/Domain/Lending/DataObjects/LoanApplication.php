<?php

namespace App\Domain\Lending\DataObjects;

use App\Domain\Lending\Enums\EmploymentStatus;
use App\Domain\Lending\Enums\LoanPurpose;
use Illuminate\Support\Carbon;

class LoanApplication
{
    public function __construct(
        public readonly string $applicationId,
        public readonly string $borrowerAccountId,
        public readonly string $amount,
        public readonly int $termMonths,
        public readonly LoanPurpose $purpose,
        public readonly ?string $purposeDescription,
        public readonly EmploymentStatus $employmentStatus,
        public readonly string $monthlyIncome,
        public readonly string $monthlyExpenses,
        public readonly ?array $collateral,
        public readonly array $documents,
        public readonly ?Carbon $submittedAt = null,
        public readonly array $metadata = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            applicationId: $data['application_id'] ?? \Illuminate\Support\Str::uuid()->toString(),
            borrowerAccountId: $data['borrower_account_id'],
            amount: $data['amount'],
            termMonths: (int) $data['term_months'],
            purpose: LoanPurpose::from($data['purpose']),
            purposeDescription: $data['purpose_description'] ?? null,
            employmentStatus: EmploymentStatus::from($data['employment_status']),
            monthlyIncome: $data['monthly_income'],
            monthlyExpenses: $data['monthly_expenses'],
            collateral: $data['collateral'] ?? null,
            documents: $data['documents'] ?? [],
            submittedAt: isset($data['submitted_at']) ? Carbon::parse($data['submitted_at']) : now(),
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'application_id' => $this->applicationId,
            'borrower_account_id' => $this->borrowerAccountId,
            'amount' => $this->amount,
            'term_months' => $this->termMonths,
            'purpose' => $this->purpose->value,
            'purpose_description' => $this->purposeDescription,
            'employment_status' => $this->employmentStatus->value,
            'monthly_income' => $this->monthlyIncome,
            'monthly_expenses' => $this->monthlyExpenses,
            'collateral' => $this->collateral,
            'documents' => $this->documents,
            'submitted_at' => $this->submittedAt?->toIso8601String(),
            'metadata' => $this->metadata
        ];
    }

    public function getDebtToIncomeRatio(): float
    {
        $monthlyIncome = (float) $this->monthlyIncome;
        $monthlyExpenses = (float) $this->monthlyExpenses;
        
        if ($monthlyIncome <= 0) {
            return 1.0; // Maximum ratio if no income
        }
        
        return $monthlyExpenses / $monthlyIncome;
    }

    public function getMonthlyPaymentEstimate(float $interestRate): string
    {
        $principal = (float) $this->amount;
        $monthlyRate = $interestRate / 12 / 100;
        $numPayments = $this->termMonths;
        
        if ($monthlyRate == 0) {
            return number_format($principal / $numPayments, 2, '.', '');
        }
        
        $monthlyPayment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $numPayments)) / 
                         (pow(1 + $monthlyRate, $numPayments) - 1);
        
        return number_format($monthlyPayment, 2, '.', '');
    }

    public function isCollateralized(): bool
    {
        return !empty($this->collateral);
    }
}