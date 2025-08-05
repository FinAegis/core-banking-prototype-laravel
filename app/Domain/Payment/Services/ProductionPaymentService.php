<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Domain\Payment\DataObjects\OpenBankingDeposit;
use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Workflows\ProcessBankWithdrawalWorkflow;
use App\Domain\Payment\Workflows\ProcessOpenBankingDepositWorkflow;
use App\Domain\Payment\Workflows\ProcessStripeDepositWorkflow;
use Workflow\WorkflowStub;

class ProductionPaymentService implements PaymentServiceInterface
{
    /**
     * Process a Stripe deposit.
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
        $workflow->start($deposit);

        // For now, return a mock transaction ID since workflows are async
        // In production, this would be handled via webhooks or polling
        return 'txn_' . uniqid();
    }

    /**
     * Process a bank withdrawal.
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
        $workflow->start($withdrawal);

        // Return withdrawal reference and status
        // In production, status updates would come via webhooks
        return [
            'reference' => $data['reference'],
            'status'    => 'processing',
        ];
    }

    /**
     * Process an OpenBanking deposit.
     */
    public function processOpenBankingDeposit(array $data): string
    {
        $deposit = new OpenBankingDeposit(
            accountUuid: $data['account_uuid'],
            amount: $data['amount'],
            currency: $data['currency'],
            reference: $data['reference'],
            bankName: $data['bank_name'],
            metadata: $data['metadata'] ?? []
        );

        $workflow = WorkflowStub::make(ProcessOpenBankingDepositWorkflow::class);
        $workflow->start($deposit);

        // Return OpenBanking reference
        // In production, this would be tracked through the workflow
        return 'ob_' . uniqid();
    }
}
