<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Custodian\Events\AccountBalanceUpdated;
use App\Domain\Custodian\Events\TransactionStatusUpdated;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Models\CustodianWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class WebhookProcessorService
{
    public function __construct(
        private readonly CustodianAccountService $accountService,
        private readonly CustodianRegistry $custodianRegistry
    ) {
    }

    /**
     * Process a webhook based on its type and custodian.
     */
    public function process(CustodianWebhook $webhook): void
    {
        $handler = match ($webhook->custodian_name) {
            'paysera'   => $this->processPayseraWebhook(...),
            'santander' => $this->processSantanderWebhook(...),
            'mock'      => $this->processMockWebhook(...),
            default     => throw new InvalidArgumentException("Unknown custodian: {$webhook->custodian_name}"),
        };

        $handler($webhook);
    }

    /**
     * Process Paysera webhook events.
     */
    private function processPayseraWebhook(CustodianWebhook $webhook): void
    {
        $payload = $webhook->payload;

        match ($webhook->event_type) {
            'payment.completed'       => $this->handlePaymentCompleted($webhook, $payload),
            'payment.failed'          => $this->handlePaymentFailed($webhook, $payload),
            'account.balance_changed' => $this->handleBalanceChanged($webhook, $payload),
            'account.status_changed'  => $this->handleAccountStatusChanged($webhook, $payload),
            default                   => $webhook->markAsIgnored("Unknown event type: {$webhook->event_type}"),
        };
    }

    /**
     * Process Santander webhook events.
     */
    private function processSantanderWebhook(CustodianWebhook $webhook): void
    {
        $payload = $webhook->payload;

        match ($webhook->event_type) {
            'transfer.completed' => $this->handlePaymentCompleted($webhook, $payload),
            'transfer.rejected'  => $this->handlePaymentFailed($webhook, $payload),
            'account.updated'    => $this->handleAccountUpdated($webhook, $payload),
            default              => $webhook->markAsIgnored("Unknown event type: {$webhook->event_type}"),
        };
    }

    /**
     * Process Mock Bank webhook events.
     */
    private function processMockWebhook(CustodianWebhook $webhook): void
    {
        $payload = $webhook->payload;

        match ($webhook->event_type) {
            'transaction.completed' => $this->handlePaymentCompleted($webhook, $payload),
            'transaction.failed'    => $this->handlePaymentFailed($webhook, $payload),
            'balance.updated'       => $this->handleBalanceChanged($webhook, $payload),
            default                 => $webhook->markAsIgnored("Unknown event type: {$webhook->event_type}"),
        };
    }

    /**
     * Handle payment completed event.
     */
    private function handlePaymentCompleted(CustodianWebhook $webhook, array $payload): void
    {
        DB::transaction(
            function () use ($webhook, $payload) {
                $transactionId = $this->extractTransactionId($webhook->custodian_name, $payload);

                // Update webhook with transaction reference
                $webhook->update(['transaction_id' => $transactionId]);

                // Emit event for other parts of the system
                event(
                    new TransactionStatusUpdated(
                        custodian: $webhook->custodian_name,
                        transactionId: $transactionId,
                        status: 'completed',
                        metadata: $payload
                    )
                );

                Log::info(
                    'Payment completed webhook processed',
                    [
                        'custodian'      => $webhook->custodian_name,
                        'transaction_id' => $transactionId,
                    ]
                );
            }
        );
    }

    /**
     * Handle payment failed event.
     */
    private function handlePaymentFailed(CustodianWebhook $webhook, array $payload): void
    {
        DB::transaction(
            function () use ($webhook, $payload) {
                $transactionId = $this->extractTransactionId($webhook->custodian_name, $payload);
                $reason = $payload['reason'] ?? $payload['error'] ?? 'Unknown reason';

                // Update webhook with transaction reference
                $webhook->update(['transaction_id' => $transactionId]);

                // Emit event for other parts of the system
                event(
                    new TransactionStatusUpdated(
                        custodian: $webhook->custodian_name,
                        transactionId: $transactionId,
                        status: 'failed',
                        metadata: array_merge($payload, ['failure_reason' => $reason])
                    )
                );

                Log::warning(
                    'Payment failed webhook processed',
                    [
                        'custodian'      => $webhook->custodian_name,
                        'transaction_id' => $transactionId,
                        'reason'         => $reason,
                    ]
                );
            }
        );
    }

    /**
     * Handle balance changed event.
     */
    private function handleBalanceChanged(CustodianWebhook $webhook, array $payload): void
    {
        DB::transaction(
            function () use ($webhook, $payload) {
                $accountId = $this->extractAccountId($webhook->custodian_name, $payload);
                $balances = $this->extractBalances($webhook->custodian_name, $payload);

                // Find the custodian account
                $custodianAccount = CustodianAccount::where('custodian_name', $webhook->custodian_name)
                    ->where('custodian_account_id', $accountId)
                    ->first();

                if (! $custodianAccount) {
                    throw new RuntimeException("Custodian account not found: {$accountId}");
                }

                // Update webhook with account reference
                $webhook->update(['custodian_account_id' => $custodianAccount->uuid]);

                // Emit event for balance update
                event(
                    new AccountBalanceUpdated(
                        custodianAccount: $custodianAccount,
                        balances: $balances,
                        timestamp: $payload['timestamp'] ?? now()->toISOString()
                    )
                );

                Log::info(
                    'Balance changed webhook processed',
                    [
                        'custodian'  => $webhook->custodian_name,
                        'account_id' => $accountId,
                        'balances'   => $balances,
                    ]
                );
            }
        );
    }

    /**
     * Handle account status changed event.
     */
    private function handleAccountStatusChanged(CustodianWebhook $webhook, array $payload): void
    {
        DB::transaction(
            function () use ($webhook, $payload) {
                $accountId = $this->extractAccountId($webhook->custodian_name, $payload);
                $newStatus = $payload['status'] ?? $payload['new_status'] ?? 'unknown';

                // Find and update the custodian account
                $custodianAccount = CustodianAccount::where('custodian_name', $webhook->custodian_name)
                    ->where('custodian_account_id', $accountId)
                    ->first();

                if (! $custodianAccount) {
                    throw new RuntimeException("Custodian account not found: {$accountId}");
                }

                // Update webhook with account reference
                $webhook->update(['custodian_account_id' => $custodianAccount->uuid]);

                // Sync account status
                $this->accountService->syncAccountStatus($custodianAccount);

                Log::info(
                    'Account status changed webhook processed',
                    [
                        'custodian'  => $webhook->custodian_name,
                        'account_id' => $accountId,
                        'new_status' => $newStatus,
                    ]
                );
            }
        );
    }

    /**
     * Handle generic account updated event.
     */
    private function handleAccountUpdated(CustodianWebhook $webhook, array $payload): void
    {
        // This could trigger balance check or status sync
        if (isset($payload['balance_changed']) && $payload['balance_changed']) {
            $this->handleBalanceChanged($webhook, $payload);
        } elseif (isset($payload['status_changed']) && $payload['status_changed']) {
            $this->handleAccountStatusChanged($webhook, $payload);
        } else {
            $webhook->markAsIgnored('No actionable changes in account update');
        }
    }

    /**
     * Extract transaction ID based on custodian format.
     */
    private function extractTransactionId(string $custodianName, array $payload): string
    {
        return match ($custodianName) {
            'paysera'   => $payload['payment_id'] ?? throw new RuntimeException('Missing payment_id'),
            'santander' => $payload['transfer_id'] ?? throw new RuntimeException('Missing transfer_id'),
            'mock'      => $payload['transaction_id'] ?? throw new RuntimeException('Missing transaction_id'),
            default     => throw new InvalidArgumentException("Unknown custodian: {$custodianName}"),
        };
    }

    /**
     * Extract account ID based on custodian format.
     */
    private function extractAccountId(string $custodianName, array $payload): string
    {
        return match ($custodianName) {
            'paysera'   => $payload['account_id'] ?? throw new RuntimeException('Missing account_id'),
            'santander' => $payload['account_number'] ?? throw new RuntimeException('Missing account_number'),
            'mock'      => $payload['account'] ?? throw new RuntimeException('Missing account'),
            default     => throw new InvalidArgumentException("Unknown custodian: {$custodianName}"),
        };
    }

    /**
     * Extract balances based on custodian format.
     */
    private function extractBalances(string $custodianName, array $payload): array
    {
        return match ($custodianName) {
            'paysera'   => $this->parsePayseraBalances($payload['balances'] ?? []),
            'santander' => $this->parseSantanderBalances($payload['balances'] ?? []),
            'mock'      => $payload['balances'] ?? [],
            default     => [],
        };
    }

    /**
     * Parse Paysera balance format.
     */
    private function parsePayseraBalances(array $balances): array
    {
        $result = [];
        foreach ($balances as $balance) {
            $result[$balance['currency']] = (int) $balance['amount'];
        }

        return $result;
    }

    /**
     * Parse Santander balance format.
     */
    private function parseSantanderBalances(array $balances): array
    {
        $result = [];
        foreach ($balances as $currency => $amount) {
            $result[$currency] = (int) ($amount * 100); // Convert to cents
        }

        return $result;
    }
}
