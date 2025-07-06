<?php

namespace App\Domain\Lending\DataObjects;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RepaymentSchedule
{
    /**
     * @param Collection<RepaymentScheduleItem> $items
     */
    public function __construct(
        public readonly string $loanId,
        public readonly string $principal,
        public readonly float $interestRate,
        public readonly int $termMonths,
        public readonly Collection $items,
        public readonly string $monthlyPayment,
        public readonly string $totalInterest,
        public readonly string $totalAmount
    ) {}

    public static function calculate(
        string $loanId,
        string $principal,
        float $annualInterestRate,
        int $termMonths,
        Carbon $startDate
    ): self {
        $principalAmount = (float) $principal;
        $monthlyRate = $annualInterestRate / 12 / 100;
        $items = collect();
        
        // Calculate monthly payment
        if ($monthlyRate == 0) {
            $monthlyPayment = $principalAmount / $termMonths;
        } else {
            $monthlyPayment = $principalAmount * ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) / 
                             (pow(1 + $monthlyRate, $termMonths) - 1);
        }
        
        $remainingBalance = $principalAmount;
        $totalInterest = 0;
        $currentDate = $startDate->copy();
        
        for ($month = 1; $month <= $termMonths; $month++) {
            $interestPayment = $remainingBalance * $monthlyRate;
            $principalPayment = $monthlyPayment - $interestPayment;
            
            // Handle rounding for last payment
            if ($month === $termMonths) {
                $principalPayment = $remainingBalance;
                $monthlyPayment = $principalPayment + $interestPayment;
            }
            
            $remainingBalance -= $principalPayment;
            $totalInterest += $interestPayment;
            
            $items->push(new RepaymentScheduleItem(
                installmentNumber: $month,
                dueDate: $currentDate->copy(),
                principalAmount: number_format($principalPayment, 2, '.', ''),
                interestAmount: number_format($interestPayment, 2, '.', ''),
                totalAmount: number_format($monthlyPayment, 2, '.', ''),
                remainingBalance: number_format(max(0, $remainingBalance), 2, '.', '')
            ));
            
            $currentDate->addMonth();
        }
        
        return new self(
            loanId: $loanId,
            principal: $principal,
            interestRate: $annualInterestRate,
            termMonths: $termMonths,
            items: $items,
            monthlyPayment: number_format($monthlyPayment, 2, '.', ''),
            totalInterest: number_format($totalInterest, 2, '.', ''),
            totalAmount: number_format($principalAmount + $totalInterest, 2, '.', '')
        );
    }

    public function getInstallment(int $number): ?RepaymentScheduleItem
    {
        return $this->items->firstWhere('installmentNumber', $number);
    }

    public function getUpcomingInstallments(int $count = 3): Collection
    {
        return $this->items
            ->filter(fn($item) => $item->dueDate->isFuture())
            ->take($count);
    }

    public function getOverdueInstallments(): Collection
    {
        return $this->items
            ->filter(fn($item) => $item->dueDate->isPast() && !$item->isPaid);
    }

    public function toArray(): array
    {
        return [
            'loan_id' => $this->loanId,
            'principal' => $this->principal,
            'interest_rate' => $this->interestRate,
            'term_months' => $this->termMonths,
            'monthly_payment' => $this->monthlyPayment,
            'total_interest' => $this->totalInterest,
            'total_amount' => $this->totalAmount,
            'items' => $this->items->map(fn($item) => $item->toArray())->toArray()
        ];
    }
}

class RepaymentScheduleItem
{
    public function __construct(
        public readonly int $installmentNumber,
        public readonly Carbon $dueDate,
        public readonly string $principalAmount,
        public readonly string $interestAmount,
        public readonly string $totalAmount,
        public readonly string $remainingBalance,
        public bool $isPaid = false,
        public ?Carbon $paidAt = null,
        public ?string $paidAmount = null
    ) {}

    public function toArray(): array
    {
        return [
            'installment_number' => $this->installmentNumber,
            'due_date' => $this->dueDate->toDateString(),
            'principal_amount' => $this->principalAmount,
            'interest_amount' => $this->interestAmount,
            'total_amount' => $this->totalAmount,
            'remaining_balance' => $this->remainingBalance,
            'is_paid' => $this->isPaid,
            'paid_at' => $this->paidAt?->toIso8601String(),
            'paid_amount' => $this->paidAmount
        ];
    }

    public function isOverdue(): bool
    {
        return !$this->isPaid && $this->dueDate->isPast();
    }
}