<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Demo Payment Service that simulates payment processing without external APIs.
 * Bypasses external payment processors while maintaining the full platform workflow.
 */
class DemoPaymentService implements PaymentServiceInterface
{
    /**
     * Process a Stripe deposit in demo mode.
     * Simulates instant payment confirmation without calling Stripe APIs.
     */
    public function processStripeDeposit(array $data): string
    {
        Log::info('Processing demo Stripe deposit', $data);

        // Generate a demo payment intent ID
        $demoIntentId = 'demo_pi_' . uniqid();

        // Process the deposit through the transaction aggregate
        $this->processDeposit(
            accountUuid: $data['account_uuid'],
            amount: $data['amount'],
            currency: $data['currency'],
            reference: $data['reference'],
            paymentProcessor: 'stripe_demo',
            externalReference: $demoIntentId,
            metadata: array_merge($data['metadata'] ?? [], [
                'demo_mode'           => true,
                'payment_method'      => $data['payment_method'] ?? 'demo_card',
                'payment_method_type' => $data['payment_method_type'] ?? 'card',
            ])
        );

        return $demoIntentId;
    }

    /**
     * Process a bank withdrawal in demo mode.
     * Simulates instant bank transfer without calling banking APIs.
     */
    public function processBankWithdrawal(array $data): array
    {
        Log::info('Processing demo bank withdrawal', $data);

        // Generate a demo withdrawal reference
        $withdrawalReference = 'demo_wd_' . uniqid();

        // Process the withdrawal through the transaction aggregate
        $this->processWithdrawal(
            accountUuid: $data['account_uuid'],
            amount: $data['amount'],
            currency: $data['currency'],
            reference: $data['reference'],
            paymentProcessor: 'bank_demo',
            externalReference: $withdrawalReference,
            metadata: array_merge($data['metadata'] ?? [], [
                'demo_mode'           => true,
                'bank_name'           => $data['bank_name'],
                'account_number'      => substr($data['account_number'], -4),
                'account_holder_name' => $data['account_holder_name'],
            ])
        );

        return [
            'reference' => $withdrawalReference,
            'status'    => 'completed',
        ];
    }

    /**
     * Process an OpenBanking deposit in demo mode.
     * Simulates instant bank authorization and transfer without OAuth flow.
     */
    public function processOpenBankingDeposit(array $data): string
    {
        Log::info('Processing demo OpenBanking deposit', $data);

        // Generate a demo OpenBanking reference
        $obReference = 'demo_ob_' . uniqid();

        // Process the deposit through the transaction aggregate
        $this->processDeposit(
            accountUuid: $data['account_uuid'],
            amount: $data['amount'],
            currency: $data['currency'],
            reference: $data['reference'],
            paymentProcessor: 'openbanking_demo',
            externalReference: $obReference,
            metadata: array_merge($data['metadata'] ?? [], [
                'demo_mode'            => true,
                'bank_name'            => $data['bank_name'],
                'authorization_method' => 'instant_demo',
            ])
        );

        return $obReference;
    }

    /**
     * Process a deposit through the transaction aggregate.
     */
    private function processDeposit(
        string $accountUuid,
        int $amount,
        string $currency,
        string $reference,
        string $paymentProcessor,
        string $externalReference,
        array $metadata
    ): void {
        $account = Account::where('uuid', $accountUuid)->firstOrFail();

        // Create transaction directly for demo mode
        TransactionProjection::create([
            'uuid'               => $reference,
            'account_uuid'       => $accountUuid,
            'amount'             => $amount,
            'asset_code'         => $currency,
            'type'               => 'deposit',
            'status'             => 'completed',
            'reference'          => $reference,
            'external_reference' => $externalReference,
            'description'        => "Demo deposit via {$paymentProcessor}",
            'metadata'           => $metadata,
            'hash'               => hash('sha3-512', $reference . $accountUuid . time()),
        ]);

        // Update account balance
        $account->balance += $amount;
        $account->save();

        Log::info('Demo deposit completed', [
            'account_uuid' => $accountUuid,
            'amount'       => $amount,
            'currency'     => $currency,
            'reference'    => $reference,
            'processor'    => $paymentProcessor,
        ]);
    }

    /**
     * Process a withdrawal through the transaction aggregate.
     */
    private function processWithdrawal(
        string $accountUuid,
        int $amount,
        string $currency,
        string $reference,
        string $paymentProcessor,
        string $externalReference,
        array $metadata
    ): void {
        $account = Account::where('uuid', $accountUuid)->firstOrFail();

        // Create transaction directly for demo mode
        TransactionProjection::create([
            'uuid'               => $reference,
            'account_uuid'       => $accountUuid,
            'amount'             => -$amount, // Negative for withdrawal
            'asset_code'         => $currency,
            'type'               => 'withdrawal',
            'status'             => 'completed',
            'reference'          => $reference,
            'external_reference' => $externalReference,
            'description'        => "Demo withdrawal via {$paymentProcessor}",
            'metadata'           => $metadata,
            'hash'               => hash('sha3-512', $reference . $accountUuid . time()),
        ]);

        // Update account balance
        $account->balance -= $amount;
        $account->save();

        Log::info('Demo withdrawal completed', [
            'account_uuid' => $accountUuid,
            'amount'       => $amount,
            'currency'     => $currency,
            'reference'    => $reference,
            'processor'    => $paymentProcessor,
        ]);
    }
}
