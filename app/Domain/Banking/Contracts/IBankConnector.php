<?php

declare(strict_types=1);

namespace App\Domain\Banking\Contracts;

use App\Domain\Banking\Models\BankAccount;
use App\Domain\Banking\Models\BankBalance;
use App\Domain\Banking\Models\BankCapabilities;
use App\Domain\Banking\Models\BankStatement;
use App\Domain\Banking\Models\BankTransaction;
use App\Domain\Banking\Models\BankTransfer;
use DateTime;
use Illuminate\Support\Collection;

interface IBankConnector
{
    /**
     * Get unique identifier for this bank connector.
     */
    public function getBankCode(): string;

    /**
     * Get human-readable bank name.
     */
    public function getBankName(): string;

    /**
     * Check if the bank connector is available and operational.
     */
    public function isAvailable(): bool;

    /**
     * Get bank capabilities and features.
     */
    public function getCapabilities(): BankCapabilities;

    /**
     * Authenticate with the bank API.
     *
     * @throws \App\Domain\Banking\Exceptions\BankAuthenticationException
     */
    public function authenticate(): void;

    /**
     * Create a new bank account.
     *
     * @param  array  $accountDetails  Account creation parameters
     *
     * @throws \App\Domain\Banking\Exceptions\BankOperationException
     */
    public function createAccount(array $accountDetails): BankAccount;

    /**
     * Get account information.
     *
     * @param  string  $accountId  External bank account ID
     *
     * @throws \App\Domain\Banking\Exceptions\AccountNotFoundException
     */
    public function getAccount(string $accountId): BankAccount;

    /**
     * Get account balance.
     *
     * @param  string  $accountId  External bank account ID
     * @param  string|null  $currency  Specific currency (null for all)
     * @return BankBalance|Collection<BankBalance>
     */
    public function getBalance(string $accountId, ?string $currency = null): BankBalance|Collection;

    /**
     * Initiate a bank transfer.
     *
     * @param  array  $transferDetails  Transfer parameters
     *
     * @throws \App\Domain\Banking\Exceptions\TransferException
     */
    public function initiateTransfer(array $transferDetails): BankTransfer;

    /**
     * Get transfer status.
     *
     * @param  string  $transferId  Bank transfer ID
     */
    public function getTransferStatus(string $transferId): BankTransfer;

    /**
     * Cancel a pending transfer.
     *
     * @param  string  $transferId  Bank transfer ID
     */
    public function cancelTransfer(string $transferId): bool;

    /**
     * Get transaction history.
     *
     * @param  string  $accountId  External bank account ID
     * @param  DateTime  $from  Start date
     * @param  DateTime  $to  End date
     * @param  int  $limit  Maximum number of transactions
     * @return Collection<BankTransaction>
     */
    public function getTransactions(string $accountId, DateTime $from, DateTime $to, int $limit = 100): Collection;

    /**
     * Get account statement.
     *
     * @param  string  $accountId  External bank account ID
     * @param  DateTime  $from  Start date
     * @param  DateTime  $to  End date
     * @param  string  $format  Statement format (PDF, CSV, JSON)
     */
    public function getStatement(string $accountId, DateTime $from, DateTime $to, string $format = 'JSON'): BankStatement;

    /**
     * Validate IBAN.
     *
     * @param  string  $iban  IBAN to validate
     */
    public function validateIBAN(string $iban): bool;

    /**
     * Get supported currencies.
     *
     * @return array<string>
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get transfer limits.
     *
     * @param  string  $accountId  External bank account ID
     * @param  string  $transferType  Type of transfer (SEPA, SWIFT, etc.)
     */
    public function getTransferLimits(string $accountId, string $transferType): array;

    /**
     * Verify webhook signature.
     *
     * @param  string  $payload  Webhook payload
     * @param  string  $signature  Webhook signature
     * @param  array  $headers  Webhook headers
     */
    public function verifyWebhookSignature(string $payload, string $signature, array $headers): bool;

    /**
     * Process webhook notification.
     *
     * @param  string  $payload  Webhook payload
     * @return array Processed webhook data
     */
    public function processWebhook(string $payload): array;
}
