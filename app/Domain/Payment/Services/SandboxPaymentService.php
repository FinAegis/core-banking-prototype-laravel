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
use Illuminate\Support\Facades\Log;
use Workflow\WorkflowStub;

/**
 * Sandbox Payment Service that uses real external APIs in test/sandbox mode.
 * Connects to payment processors' sandbox environments for realistic testing.
 */
class SandboxPaymentService implements PaymentServiceInterface
{
    /**
     * Process a Stripe deposit using Stripe's test mode.
     * Uses real Stripe API with test keys for sandbox environment.
     */
    public function processStripeDeposit(array $data): string
    {
        Log::info('Processing sandbox Stripe deposit', array_merge($data, [
            'environment' => 'sandbox',
        ]));

        // Add sandbox-specific metadata
        $sandboxData = $data;
        $sandboxData['metadata'] = array_merge($data['metadata'] ?? [], [
            'environment' => 'sandbox',
            'test_mode'   => true,
        ]);

        // Use the production workflow with sandbox Stripe keys
        $deposit = new StripeDeposit(
            accountUuid: $sandboxData['account_uuid'],
            amount: $sandboxData['amount'],
            currency: $sandboxData['currency'],
            reference: $sandboxData['reference'],
            externalReference: $sandboxData['external_reference'],
            paymentMethod: $sandboxData['payment_method'],
            paymentMethodType: $sandboxData['payment_method_type'],
            metadata: $sandboxData['metadata']
        );

        $workflow = WorkflowStub::make(ProcessStripeDepositWorkflow::class);
        $workflow->start($deposit);

        // Return sandbox transaction ID
        return 'sandbox_txn_' . uniqid();
    }

    /**
     * Process a bank withdrawal using banking sandbox APIs.
     * Connects to bank sandbox environments for testing.
     */
    public function processBankWithdrawal(array $data): array
    {
        Log::info('Processing sandbox bank withdrawal', array_merge($data, [
            'environment' => 'sandbox',
        ]));

        // Add sandbox-specific metadata
        $sandboxData = $data;
        $sandboxData['metadata'] = array_merge($data['metadata'] ?? [], [
            'environment' => 'sandbox',
            'test_mode'   => true,
        ]);

        // Use the production workflow with sandbox bank credentials
        $withdrawal = new BankWithdrawal(
            accountUuid: $sandboxData['account_uuid'],
            amount: $sandboxData['amount'],
            currency: $sandboxData['currency'],
            reference: $sandboxData['reference'],
            bankName: $sandboxData['bank_name'],
            accountNumber: $sandboxData['account_number'],
            accountHolderName: $sandboxData['account_holder_name'],
            routingNumber: $sandboxData['routing_number'] ?? null,
            iban: $sandboxData['iban'] ?? null,
            swift: $sandboxData['swift'] ?? null,
            metadata: $sandboxData['metadata']
        );

        $workflow = WorkflowStub::make(ProcessBankWithdrawalWorkflow::class);
        $workflow->start($withdrawal);

        // Return sandbox withdrawal status
        return [
            'reference' => $sandboxData['reference'],
            'status'    => 'processing',
        ];
    }

    /**
     * Process an OpenBanking deposit using bank sandbox APIs.
     * Uses real OAuth flow with sandbox credentials.
     */
    public function processOpenBankingDeposit(array $data): string
    {
        Log::info('Processing sandbox OpenBanking deposit', array_merge($data, [
            'environment' => 'sandbox',
        ]));

        // Add sandbox-specific metadata
        $sandboxData = $data;
        $sandboxData['metadata'] = array_merge($data['metadata'] ?? [], [
            'environment' => 'sandbox',
            'test_mode'   => true,
        ]);

        // Use the production workflow with sandbox OpenBanking credentials
        $deposit = new OpenBankingDeposit(
            accountUuid: $sandboxData['account_uuid'],
            amount: $sandboxData['amount'],
            currency: $sandboxData['currency'],
            reference: $sandboxData['reference'],
            bankName: $sandboxData['bank_name'],
            metadata: $sandboxData['metadata']
        );

        $workflow = WorkflowStub::make(ProcessOpenBankingDepositWorkflow::class);
        $workflow->start($deposit);

        // Return sandbox OpenBanking reference
        return 'sandbox_ob_' . uniqid();
    }
}
