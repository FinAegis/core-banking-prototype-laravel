<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Connectors;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;

class MockBankConnector extends BaseCustodianConnector
{
    private array $mockBalances = [];

    private array $mockTransactions = [];

    private array $mockAccounts = [];

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->initializeMockData();
    }

    private function initializeMockData(): void
    {
        // Initialize with some mock accounts
        $this->mockAccounts = [
            'mock-account-1' => [
                'id'         => 'mock-account-1',
                'name'       => 'Mock Business Account',
                'status'     => 'active',
                'type'       => 'business',
                'created_at' => Carbon::now()->subMonths(6),
            ],
            'mock-account-2' => [
                'id'         => 'mock-account-2',
                'name'       => 'Mock Personal Account',
                'status'     => 'active',
                'type'       => 'personal',
                'created_at' => Carbon::now()->subMonths(3),
            ],
        ];

        // Initialize with some mock balances
        $this->mockBalances = [
            'mock-account-1' => [
                'USD' => 1000000, // $10,000.00
                'EUR' => 500000,  // €5,000.00
                'BTC' => 10000000, // 0.1 BTC
            ],
            'mock-account-2' => [
                'USD' => 50000,   // $500.00
                'EUR' => 20000,   // €200.00
            ],
        ];
    }

    protected function getHealthCheckEndpoint(): string
    {
        return '/health';
    }

    public function isAvailable(): bool
    {
        // Mock bank is always available
        return true;
    }

    public function getBalance(string $accountId, string $assetCode): Money
    {
        $this->logRequest('GET', "/accounts/{$accountId}/balance/{$assetCode}");

        $balance = $this->mockBalances[$accountId][$assetCode] ?? 0;

        return new Money($balance);
    }

    public function getAccountInfo(string $accountId): AccountInfo
    {
        $this->logRequest('GET', "/accounts/{$accountId}");

        if (! isset($this->mockAccounts[$accountId])) {
            throw new Exception("Account {$accountId} not found");
        }

        $account = $this->mockAccounts[$accountId];
        $balances = $this->mockBalances[$accountId] ?? [];

        return new AccountInfo(
            accountId: $account['id'],
            name: $account['name'],
            status: $account['status'],
            balances: $balances,
            currency: 'USD',
            type: $account['type'],
            createdAt: $account['created_at'],
            metadata: [
                'mock'      => true,
                'connector' => 'MockBankConnector',
            ]
        );
    }

    public function initiateTransfer(TransferRequest $request): TransactionReceipt
    {
        $this->logRequest('POST', '/transfers', $request->toArray());

        // Simulate processing time
        usleep(100000); // 0.1 seconds

        // Create mock transaction
        $transactionId = 'mock-tx-' . Str::uuid()->toString();

        // Check if source account has sufficient balance
        $sourceBalance = $this->mockBalances[$request->fromAccount][$request->assetCode] ?? 0;

        if ($sourceBalance < $request->amount->getAmount()) {
            $receipt = new TransactionReceipt(
                id: $transactionId,
                status: 'failed',
                fromAccount: $request->fromAccount,
                toAccount: $request->toAccount,
                assetCode: $request->assetCode,
                amount: $request->amount->getAmount(),
                reference: $request->reference,
                createdAt: Carbon::now(),
                completedAt: Carbon::now(),
                metadata: [
                    'error' => 'Insufficient balance',
                    'mock'  => true,
                ]
            );
        } else {
            // Update mock balances
            $this->mockBalances[$request->fromAccount][$request->assetCode] -= $request->amount->getAmount();

            if (! isset($this->mockBalances[$request->toAccount])) {
                $this->mockBalances[$request->toAccount] = [];
            }

            if (! isset($this->mockBalances[$request->toAccount][$request->assetCode])) {
                $this->mockBalances[$request->toAccount][$request->assetCode] = 0;
            }

            $this->mockBalances[$request->toAccount][$request->assetCode] += $request->amount->getAmount();

            $receipt = new TransactionReceipt(
                id: $transactionId,
                status: 'completed',
                fromAccount: $request->fromAccount,
                toAccount: $request->toAccount,
                assetCode: $request->assetCode,
                amount: $request->amount->getAmount(),
                fee: 100, // Mock fee of $1.00
                reference: $request->reference,
                createdAt: Carbon::now(),
                completedAt: Carbon::now(),
                metadata: [
                    'mock'        => true,
                    'description' => $request->description,
                ]
            );
        }

        $this->mockTransactions[$transactionId] = $receipt;

        return $receipt;
    }

    public function getTransactionStatus(string $transactionId): TransactionReceipt
    {
        $this->logRequest('GET', "/transactions/{$transactionId}");

        if (! isset($this->mockTransactions[$transactionId])) {
            throw new Exception("Transaction {$transactionId} not found");
        }

        return $this->mockTransactions[$transactionId];
    }

    public function cancelTransaction(string $transactionId): bool
    {
        $this->logRequest('DELETE', "/transactions/{$transactionId}");

        if (! isset($this->mockTransactions[$transactionId])) {
            return false;
        }

        $transaction = $this->mockTransactions[$transactionId];

        if ($transaction->isPending()) {
            // Update transaction status
            $this->mockTransactions[$transactionId] = new TransactionReceipt(
                id: $transaction->id,
                status: 'cancelled',
                fromAccount: $transaction->fromAccount,
                toAccount: $transaction->toAccount,
                assetCode: $transaction->assetCode,
                amount: $transaction->amount,
                reference: $transaction->reference,
                createdAt: $transaction->createdAt,
                completedAt: Carbon::now(),
                metadata: array_merge($transaction->metadata, ['cancelled_at' => Carbon::now()->toISOString()])
            );

            return true;
        }

        return false;
    }

    public function getSupportedAssets(): array
    {
        return ['USD', 'EUR', 'GBP', 'BTC', 'ETH'];
    }

    public function validateAccount(string $accountId): bool
    {
        return isset($this->mockAccounts[$accountId]) &&
               $this->mockAccounts[$accountId]['status'] === 'active';
    }

    public function getTransactionHistory(string $accountId, ?int $limit = 100, ?int $offset = 0): array
    {
        $this->logRequest(
            'GET',
            "/accounts/{$accountId}/transactions",
            [
                'limit'  => $limit,
                'offset' => $offset,
            ]
        );

        // Return mock transaction history
        $history = [];

        foreach ($this->mockTransactions as $transaction) {
            if ($transaction->fromAccount === $accountId || $transaction->toAccount === $accountId) {
                $history[] = $transaction->toArray();
            }
        }

        // Apply pagination
        return array_slice($history, $offset, $limit);
    }
}
