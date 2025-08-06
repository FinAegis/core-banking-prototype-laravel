<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Connectors;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Illuminate\Support\Facades\Log;

/**
 * Demo bank connector for bypassing external bank APIs while demonstrating functionality.
 */
class DemoBankConnector extends BaseCustodianConnector
{
    public function getName(): string
    {
        return 'demo_bank';
    }

    public function isAvailable(): bool
    {
        // Always available in demo environment or sandbox mode
        return app()->environment('demo') || config('demo.sandbox.enabled');
    }

    public function getSupportedAssets(): array
    {
        // Support all major currencies in demo
        return ['USD', 'EUR', 'GBP', 'CHF', 'JPY', 'CAD', 'AUD'];
    }

    public function validateAccount(string $accountId): bool
    {
        // Accept any account format in demo mode
        Log::info('Demo bank account validation', ['account_id' => $accountId]);

        return ! empty($accountId);
    }

    public function getAccountInfo(string $accountId): AccountInfo
    {
        Log::info('Demo bank account info request', ['account_id' => $accountId]);

        // Return realistic account info without calling external API
        return new AccountInfo(
            accountId: $accountId,
            name: 'Demo Account',
            status: 'active',
            balances: [],
            currency: 'USD',
            type: 'checking',
            createdAt: now(),
            metadata: [
                'demo_mode'  => true,
                'created_at' => now()->toIso8601String(),
            ]
        );
    }

    public function getBalance(string $accountId, string $assetCode): Money
    {
        Log::info('Demo bank balance request', [
            'account_id' => $accountId,
            'asset_code' => $assetCode,
        ]);

        // Return a realistic balance for demo purposes
        // In a real demo, this could be stored in database
        $balances = session()->get('demo_bank_balances', []);
        $balance = $balances[$accountId][$assetCode] ?? 1000000; // Default $10,000

        return new Money($balance);
    }

    public function initiateTransfer(TransferRequest $request): TransactionReceipt
    {
        Log::info('Demo bank transfer initiated', [
            'from'     => $request->fromAccount,
            'to'       => $request->toAccount,
            'amount'   => $request->amount->getAmount(),
            'currency' => $request->assetCode,
        ]);

        // Simulate instant transfer in demo mode
        $transactionId = 'demo_txn_' . uniqid();

        // Update demo balances in session
        $balances = session()->get('demo_bank_balances', []);

        // Deduct from sender
        $currentFromBalance = $balances[$request->fromAccount][$request->assetCode] ?? 1000000;
        $balances[$request->fromAccount][$request->assetCode] =
            $currentFromBalance - $request->amount->getAmount();

        // Add to receiver
        $currentToBalance = $balances[$request->toAccount][$request->assetCode] ?? 0;
        $balances[$request->toAccount][$request->assetCode] =
            $currentToBalance + $request->amount->getAmount();

        session()->put('demo_bank_balances', $balances);

        return new TransactionReceipt(
            id: $transactionId,
            status: 'completed', // Instant in demo
            fromAccount: $request->fromAccount,
            toAccount: $request->toAccount,
            assetCode: $request->assetCode,
            amount: $request->amount->getAmount(),
            fee: 0,
            reference: $request->reference,
            createdAt: now(),
            completedAt: now(),
            metadata: [
                'demo_mode'          => true,
                'instant_transfer'   => true,
                'processing_time_ms' => rand(100, 500), // Simulate realistic timing
                'description'        => $request->description,
            ]
        );
    }

    public function getTransactionStatus(string $transactionId): TransactionReceipt
    {
        Log::info('Demo bank transaction status request', ['transaction_id' => $transactionId]);

        // All demo transactions are instantly completed
        return new TransactionReceipt(
            id: $transactionId,
            status: 'completed',
            fromAccount: 'demo_account',
            toAccount: 'demo_account',
            assetCode: 'USD',
            amount: 0, // Would be stored in real implementation
            fee: 0,
            reference: 'DEMO',
            createdAt: now(),
            completedAt: now(),
            metadata: [
                'demo_mode'   => true,
                'description' => 'Demo transaction',
            ]
        );
    }

    public function cancelTransaction(string $transactionId): bool
    {
        Log::info('Demo bank transaction cancellation', ['transaction_id' => $transactionId]);

        // In demo mode, we can't cancel completed transactions
        return false;
    }

    public function getTransactionHistory(string $accountId, ?int $limit = 10, ?int $offset = 0): array
    {
        Log::info('Demo bank transaction history request', [
            'account_id' => $accountId,
            'limit'      => $limit,
            'offset'     => $offset,
        ]);

        // Return sample transaction history
        $transactions = [];
        for ($i = 0; $i < min($limit, 5); $i++) {
            $transactions[] = [
                'id'          => 'demo_txn_' . uniqid(),
                'type'        => $i % 2 === 0 ? 'credit' : 'debit',
                'amount'      => rand(1000, 50000), // $10 to $500
                'currency'    => 'USD',
                'status'      => 'completed',
                'description' => 'Demo transaction #' . ($offset + $i + 1),
                'date'        => now()->subDays($i)->toIso8601String(),
            ];
        }

        return $transactions;
    }

    protected function getHealthCheckEndpoint(): string
    {
        return '/health';
    }
}
