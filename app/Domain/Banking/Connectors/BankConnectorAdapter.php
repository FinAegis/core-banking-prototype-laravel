<?php

declare(strict_types=1);

namespace App\Domain\Banking\Connectors;

use App\Domain\Banking\Contracts\IBankConnector;
use App\Domain\Banking\Models\BankAccount;
use App\Domain\Banking\Models\BankBalance;
use App\Domain\Banking\Models\BankCapabilities;
use App\Domain\Banking\Models\BankStatement;
use App\Domain\Banking\Models\BankTransaction;
use App\Domain\Banking\Models\BankTransfer;
use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Adapter to use existing Custodian connectors as Bank connectors.
 */
class BankConnectorAdapter implements IBankConnector
{
    private ICustodianConnector $custodianConnector;

    private string $bankCode;

    private string $bankName;

    public function __construct(
        ICustodianConnector $custodianConnector,
        string $bankCode,
        string $bankName
    ) {
        $this->custodianConnector = $custodianConnector;
        $this->bankCode = $bankCode;
        $this->bankName = $bankName;
    }

    public function getBankCode(): string
    {
        return $this->bankCode;
    }

    public function getBankName(): string
    {
        return $this->bankName;
    }

    public function isAvailable(): bool
    {
        return $this->custodianConnector->isAvailable();
    }

    public function getCapabilities(): BankCapabilities
    {
        // Map custodian capabilities to bank capabilities
        $supportedAssets = $this->custodianConnector->getSupportedAssets();

        return new BankCapabilities(
            supportedCurrencies: $supportedAssets,
            supportedTransferTypes: ['INTERNAL', 'SEPA', 'SWIFT'],
            features: ['multi_currency', 'instant_transfers', 'api_access'],
            limits: [
                'SEPA'  => ['EUR' => ['min' => 100, 'max' => 10000000, 'daily' => 50000000]],
                'SWIFT' => ['USD' => ['min' => 100, 'max' => 100000000, 'daily' => 500000000]],
            ],
            fees: [
                'transfer' => ['EUR' => ['fixed' => 100, 'percentage' => 0.1]],
            ],
            supportsInstantTransfers: true,
            supportsScheduledTransfers: false,
            supportsBulkTransfers: false,
            supportsDirectDebits: false,
            supportsStandingOrders: false,
            supportsVirtualAccounts: true,
            supportsMultiCurrency: true,
            supportsWebhooks: true,
            supportsStatements: true,
            supportsCardIssuance: false,
            maxAccountsPerUser: 10,
            requiredDocuments: ['id', 'proof_of_address'],
            availableCountries: ['LT', 'DE', 'ES', 'GB', 'FR']
        );
    }

    public function authenticate(): void
    {
        // Custodian connectors handle auth internally
        if (! $this->custodianConnector->isAvailable()) {
            throw new \App\Domain\Banking\Exceptions\BankAuthenticationException(
                "Failed to authenticate with {$this->bankName}"
            );
        }
    }

    public function createAccount(array $accountDetails): BankAccount
    {
        // This would need to be implemented based on specific bank APIs
        // For now, throw an exception as custodian connectors don't support account creation
        throw new \App\Domain\Banking\Exceptions\BankOperationException(
            "Account creation not supported by {$this->bankName} connector"
        );
    }

    public function getAccount(string $accountId): BankAccount
    {
        $accountInfo = $this->custodianConnector->getAccountInfo($accountId);

        return new BankAccount(
            id: $accountInfo->accountId,
            bankCode: $this->bankCode,
            accountNumber: $accountInfo->accountNumber ?? $accountInfo->accountId,
            iban: $accountInfo->iban ?? '',
            swift: $accountInfo->swift ?? '',
            currency: $accountInfo->currency,
            accountType: $accountInfo->type ?? 'current',
            status: $accountInfo->status,
            holderName: $accountInfo->holderName ?? null,
            holderAddress: null,
            metadata: $accountInfo->metadata ?? [],
            createdAt: $accountInfo->createdAt ?? Carbon::now(),
            updatedAt: Carbon::now()
        );
    }

    public function getBalance(string $accountId, ?string $currency = null): BankBalance|Collection
    {
        if ($currency !== null) {
            $balance = $this->custodianConnector->getBalance($accountId, $currency);

            return new BankBalance(
                accountId: $accountId,
                currency: $currency,
                available: $balance->amount,
                current: $balance->amount,
                pending: 0,
                reserved: 0,
                asOf: Carbon::now()
            );
        }

        // Get all supported currencies
        $balances = collect();
        foreach ($this->custodianConnector->getSupportedAssets() as $asset) {
            try {
                $balance = $this->custodianConnector->getBalance($accountId, $asset);
                $balances->push(
                    new BankBalance(
                        accountId: $accountId,
                        currency: $asset,
                        available: $balance->amount,
                        current: $balance->amount,
                        pending: 0,
                        reserved: 0,
                        asOf: Carbon::now()
                    )
                );
            } catch (Exception $e) {
                // Skip if balance not available for this currency
                continue;
            }
        }

        return $balances;
    }

    public function initiateTransfer(array $transferDetails): BankTransfer
    {
        $request = new TransferRequest(
            fromAccountId: $transferDetails['from_account_id'],
            toAccountId: $transferDetails['to_account_id'],
            amount: (int) ($transferDetails['amount'] * 100), // Convert to cents
            currency: $transferDetails['currency'],
            reference: $transferDetails['reference'] ?? Str::random(16),
            description: $transferDetails['description'] ?? null
        );

        $receipt = $this->custodianConnector->initiateTransfer($request);

        return new BankTransfer(
            id: $receipt->transactionId,
            bankCode: $this->bankCode,
            type: $transferDetails['type'] ?? 'INTERNAL',
            status: $receipt->status,
            fromAccountId: $transferDetails['from_account_id'],
            toAccountId: $transferDetails['to_account_id'],
            toBankCode: $transferDetails['to_bank_code'] ?? $this->bankCode,
            amount: $transferDetails['amount'],
            currency: $transferDetails['currency'],
            reference: $receipt->reference,
            description: $transferDetails['description'] ?? null,
            fees: [],
            exchangeRate: [],
            createdAt: $receipt->createdAt,
            updatedAt: Carbon::now(),
            executedAt: $receipt->status === 'completed' ? Carbon::now() : null,
            failedAt: null,
            failureReason: null,
            metadata: $receipt->metadata ?? []
        );
    }

    public function getTransferStatus(string $transferId): BankTransfer
    {
        $receipt = $this->custodianConnector->getTransactionStatus($transferId);

        return new BankTransfer(
            id: $receipt->transactionId,
            bankCode: $this->bankCode,
            type: 'INTERNAL',
            status: $receipt->status,
            fromAccountId: $receipt->fromAccountId ?? '',
            toAccountId: $receipt->toAccountId ?? '',
            toBankCode: $this->bankCode,
            amount: $receipt->amount / 100, // Convert from cents
            currency: $receipt->currency,
            reference: $receipt->reference,
            description: null,
            fees: $receipt->fees ?? [],
            exchangeRate: [],
            createdAt: $receipt->createdAt,
            updatedAt: $receipt->updatedAt ?? Carbon::now(),
            executedAt: $receipt->executedAt ?? null,
            failedAt: $receipt->failedAt ?? null,
            failureReason: $receipt->failureReason ?? null,
            metadata: $receipt->metadata ?? []
        );
    }

    public function cancelTransfer(string $transferId): bool
    {
        return $this->custodianConnector->cancelTransaction($transferId);
    }

    public function getTransactions(string $accountId, DateTime $from, DateTime $to, int $limit = 100): Collection
    {
        $history = $this->custodianConnector->getTransactionHistory($accountId, $limit);

        return collect($history)->map(
            function ($tx) {
                return new BankTransaction(
                    id: $tx['id'] ?? Str::uuid()->toString(),
                    bankCode: $this->bankCode,
                    accountId: $tx['account_id'] ?? '',
                    type: $tx['amount'] < 0 ? 'debit' : 'credit',
                    category: $tx['type'] ?? 'transfer',
                    amount: $tx['amount'] ?? 0,
                    currency: $tx['currency'] ?? 'EUR',
                    balanceAfter: $tx['balance_after'] ?? 0,
                    reference: $tx['reference'] ?? null,
                    description: $tx['description'] ?? null,
                    counterpartyName: $tx['counterparty_name'] ?? null,
                    counterpartyAccount: $tx['counterparty_account'] ?? null,
                    counterpartyBank: $tx['counterparty_bank'] ?? null,
                    transactionDate: Carbon::parse($tx['transaction_date'] ?? now()),
                    valueDate: Carbon::parse($tx['value_date'] ?? now()),
                    bookingDate: Carbon::parse($tx['booking_date'] ?? now()),
                    status: $tx['status'] ?? 'completed',
                    metadata: $tx['metadata'] ?? []
                );
            }
        )->filter(
            function ($tx) use ($from, $to) {
                return $tx->transactionDate >= Carbon::instance($from) &&
                   $tx->transactionDate <= Carbon::instance($to);
            }
        );
    }

    public function getStatement(string $accountId, DateTime $from, DateTime $to, string $format = 'JSON'): BankStatement
    {
        /** @var array|null $firstTx */
        $firstTx = null;
        $transactions = $this->getTransactions($accountId, $from, $to, 1000);

        // Calculate opening and closing balances
        /** @var \Illuminate\Database\Eloquent\Model|null $$firstTx */
        $$firstTx = $transactions->first();
        $lastTx = $transactions->last();

        $openingBalance = $firstTx ? ($firstTx->balanceAfter - $firstTx->amount) : 0;
        $closingBalance = $lastTx ? $lastTx->balanceAfter : $openingBalance;

        return new BankStatement(
            id: Str::uuid()->toString(),
            bankCode: $this->bankCode,
            accountId: $accountId,
            periodFrom: Carbon::instance($from),
            periodTo: Carbon::instance($to),
            format: $format,
            openingBalance: $openingBalance,
            closingBalance: $closingBalance,
            currency: 'EUR', // Would need to determine from account
            transactions: $transactions,
            summary: [
                'total_debits'       => $transactions->filter(fn ($tx) => $tx->isDebit())->count(),
                'total_credits'      => $transactions->filter(fn ($tx) => $tx->isCredit())->count(),
                'total_transactions' => $transactions->count(),
            ],
            fileUrl: null,
            fileContent: null,
            generatedAt: Carbon::now()
        );
    }

    public function validateIBAN(string $iban): bool
    {
        // Use base implementation
        return (new BaseBankConnector([]))->validateIBAN($iban);
    }

    public function getSupportedCurrencies(): array
    {
        return $this->custodianConnector->getSupportedAssets();
    }

    public function getTransferLimits(string $accountId, string $transferType): array
    {
        // Return default limits based on transfer type
        return match ($transferType) {
            'SEPA'     => ['min' => 100, 'max' => 10000000, 'daily' => 50000000],
            'SWIFT'    => ['min' => 100, 'max' => 100000000, 'daily' => 500000000],
            'INTERNAL' => ['min' => 1, 'max' => PHP_INT_MAX, 'daily' => PHP_INT_MAX],
            default    => ['min' => 100, 'max' => 1000000, 'daily' => 5000000],
        };
    }

    public function verifyWebhookSignature(string $payload, string $signature, array $headers): bool
    {
        // This would need to be implemented based on specific bank webhook verification
        return true;
    }

    public function processWebhook(string $payload): array
    {
        // This would need to be implemented based on specific bank webhook format
        return json_decode($payload, true) ?? [];
    }
}
