<?php

namespace App\Domain\Lending\ValueObjects;

use InvalidArgumentException;

class RepaymentSchedule
{
    public function __construct(
        private readonly array $payments
    ) {
        if (empty($payments)) {
            throw new InvalidArgumentException('Repayment schedule cannot be empty');
        }
    }

    public function getPayment(int $paymentNumber): array
    {
        foreach ($this->payments as $payment) {
            if ($payment['payment_number'] === $paymentNumber) {
                return $payment;
            }
        }

        throw new InvalidArgumentException("Payment number {$paymentNumber} not found in schedule");
    }

    public function getPayments(): array
    {
        return $this->payments;
    }

    public function getTotalPayments(): int
    {
        return count($this->payments);
    }

    public function getTotalPrincipal(): string
    {
        $total = '0';
        foreach ($this->payments as $payment) {
            $total = bcadd($total, $payment['principal'], 2);
        }

        return $total;
    }

    public function getTotalInterest(): string
    {
        $total = '0';
        foreach ($this->payments as $payment) {
            $total = bcadd($total, $payment['interest'], 2);
        }

        return $total;
    }

    public function getNextDuePayment(?int $afterPaymentNumber = null): ?array
    {
        foreach ($this->payments as $payment) {
            if ($afterPaymentNumber === null || $payment['payment_number'] > $afterPaymentNumber) {
                return $payment;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return $this->payments;
    }
}
