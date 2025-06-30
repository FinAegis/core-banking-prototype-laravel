<?php

namespace App\Domain\Payment\Services;

use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Domain\Payment\Workflows\ProcessStripeDepositWorkflow;
use App\Domain\Payment\Workflows\ProcessBankWithdrawalWorkflow;
use Workflow\WorkflowStub;

class PaymentService
{
    /**
     * Process a Stripe deposit
     */
    public function processStripeDeposit(array $data): string
    {
        $deposit = new StripeDeposit(
            accountUuid: $data['account_uuid'],
            amount: $data['amount'],
            currency: $data['currency'],
            reference: $data['reference'],
            externalReference: $data['external_reference'],
            paymentMethod: $data['payment_method'],
            paymentMethodType: $data['payment_method_type'],
            metadata: $data['metadata'] ?? []
        );

        $workflow = WorkflowStub::make(ProcessStripeDepositWorkflow::class);
        return $workflow->start($deposit);
    }

    /**
     * Process a bank withdrawal
     */
    public function processBankWithdrawal(array $data): array
    {
        $withdrawal = new BankWithdrawal(
            accountUuid: $data['account_uuid'],
            amount: $data['amount'],
            currency: $data['currency'],
            reference: $data['reference'],
            bankName: $data['bank_name'],
            accountNumber: $data['account_number'],
            accountHolderName: $data['account_holder_name'],
            routingNumber: $data['routing_number'] ?? null,
            iban: $data['iban'] ?? null,
            swift: $data['swift'] ?? null,
            metadata: $data['metadata'] ?? []
        );

        $workflow = WorkflowStub::make(ProcessBankWithdrawalWorkflow::class);
        return $workflow->start($withdrawal);
    }
}