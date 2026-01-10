<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Payment;

use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Account\Models\Transfer;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentStatusTool implements MCPToolInterface
{
    public function getName(): string
    {
        return 'payment.status';
    }

    public function getCategory(): string
    {
        return 'payment';
    }

    public function getDescription(): string
    {
        return 'Check the status of a payment transaction or transfer';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'transaction_id' => [
                    'type'        => 'string',
                    'description' => 'Transaction UUID or reference number',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$|^[A-Z0-9-]+$',
                ],
                'type' => [
                    'type'        => 'string',
                    'description' => 'Type of payment to check',
                    'enum'        => ['transaction', 'transfer', 'both'],
                    'default'     => 'both',
                ],
                'include_details' => [
                    'type'        => 'boolean',
                    'description' => 'Include full transaction details',
                    'default'     => true,
                ],
                'include_related' => [
                    'type'        => 'boolean',
                    'description' => 'Include related transactions (e.g., for grouped transfers)',
                    'default'     => false,
                ],
                'user_uuid' => [
                    'type'        => 'string',
                    'description' => 'UUID of the user (optional, defaults to current user)',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
            ],
            'required' => ['transaction_id'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'transaction_id'       => ['type' => 'string'],
                'status'               => ['type' => 'string'],
                'type'                 => ['type' => 'string'],
                'subtype'              => ['type' => 'string'],
                'amount'               => ['type' => 'number'],
                'currency'             => ['type' => 'string'],
                'from_account'         => ['type' => 'string'],
                'to_account'           => ['type' => 'string'],
                'description'          => ['type' => 'string'],
                'reference'            => ['type' => 'string'],
                'external_reference'   => ['type' => 'string'],
                'created_at'           => ['type' => 'string'],
                'updated_at'           => ['type' => 'string'],
                'completed_at'         => ['type' => 'string'],
                'cancelled_at'         => ['type' => 'string'],
                'cancelled_by'         => ['type' => 'string'],
                'failure_reason'       => ['type' => 'string'],
                'retry_count'          => ['type' => 'integer'],
                'metadata'             => ['type' => 'object'],
                'related_transactions' => ['type' => 'array'],
                'status_history'       => ['type' => 'array'],
                'estimated_completion' => ['type' => 'string'],
                'next_steps'           => ['type' => 'array'],
                'message'              => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $transactionId = $parameters['transaction_id'];
            $type = $parameters['type'] ?? 'both';
            $includeDetails = $parameters['include_details'] ?? true;
            $includeRelated = $parameters['include_related'] ?? false;

            Log::info('MCP Tool: Checking payment status', [
                'transaction_id'  => $transactionId,
                'type'            => $type,
                'conversation_id' => $conversationId,
            ]);

            // Get the user
            $user = $this->getUser($parameters['user_uuid'] ?? null);

            if (! $user) {
                return ToolExecutionResult::failure('User not found or not authenticated');
            }

            // Try to find the transaction/transfer
            $result = null;

            if ($type === 'transaction' || $type === 'both') {
                $result = $this->findTransaction($transactionId, $user);
            }

            if (! $result && ($type === 'transfer' || $type === 'both')) {
                $result = $this->findTransfer($transactionId, $user);
            }

            if (! $result) {
                // Return success with not_found status instead of failure
                return ToolExecutionResult::success([
                    'status'         => 'not_found',
                    'message'        => 'Transaction not found',
                    'transaction_id' => $transactionId,
                ]);
            }

            // Build response based on the result type
            $response = $this->buildResponse($result, $includeDetails, $includeRelated);

            return ToolExecutionResult::success($response);
        } catch (Exception $e) {
            Log::error('MCP Tool error: payment.status', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function getUser(?string $userUuid): ?User
    {
        if ($userUuid) {
            // First try to find by UUID
            $user = User::where('uuid', $userUuid)->first();
            if ($user) {
                return $user;
            }

            // If it's numeric, try to find by ID
            if (is_numeric($userUuid)) {
                $user = User::find((int) $userUuid);
                if ($user) {
                    return $user;
                }
            }
        }

        return Auth::user();
    }

    private function findTransaction(string $transactionId, User $user): ?TransactionProjection
    {
        // First try to find by UUID
        /** @var TransactionProjection|null $transaction */
        $transaction = TransactionProjection::query()
            ->where('uuid', $transactionId)
            ->whereHas('account', function ($query) use ($user) {
                /** @phpstan-ignore-next-line */
                $query->where('user_uuid', $user->uuid);
            })
            ->first();

        if ($transaction) {
            return $transaction;
        }

        // Try to find by reference
        /** @var TransactionProjection|null $transaction */
        $transaction = TransactionProjection::query()
            ->where('reference', $transactionId)
            ->whereHas('account', function ($query) use ($user) {
                /** @phpstan-ignore-next-line */
                $query->where('user_uuid', $user->uuid);
            })
            ->first();

        if ($transaction) {
            return $transaction;
        }

        // Try to find by external reference
        /** @var TransactionProjection|null $transaction */
        $transaction = TransactionProjection::query()
            ->where('external_reference', $transactionId)
            ->whereHas('account', function ($query) use ($user) {
                /** @phpstan-ignore-next-line */
                $query->where('user_uuid', $user->uuid);
            })
            ->first();

        return $transaction;
    }

    private function findTransfer(string $transactionId, User $user): ?Transfer
    {
        // Transfers use event sourcing, so we check the stored events
        // First try by aggregate_uuid (transfer UUID)
        $transfer = Transfer::where('aggregate_uuid', $transactionId)
            ->orderBy('aggregate_version', 'desc')
            ->first();

        if ($transfer && $this->userOwnsTransfer($transfer, $user)) {
            return $transfer;
        }

        // Try to find in metadata using whereJsonContains
        $transfer = Transfer::whereJsonContains('meta_data', ['reference' => $transactionId])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($transfer && $this->userOwnsTransfer($transfer, $user)) {
            return $transfer;
        }

        return null;
    }

    private function userOwnsTransfer(Transfer $transfer, User $user): bool
    {
        // Check if the transfer involves user's accounts
        $eventData = $transfer->event_properties;

        // Check from_account_uuid
        if (isset($eventData['from_account_uuid'])) {
            /** @var \App\Domain\Account\Models\Account|null $account */
            $account = \App\Domain\Account\Models\Account::query()
                ->where('uuid', $eventData['from_account_uuid'])
                ->where('user_uuid', $user->uuid)
                ->first();
            if ($account) {
                return true;
            }
        }

        // Check to_account_uuid
        if (isset($eventData['to_account_uuid'])) {
            /** @var \App\Domain\Account\Models\Account|null $account */
            $account = \App\Domain\Account\Models\Account::query()
                ->where('uuid', $eventData['to_account_uuid'])
                ->where('user_uuid', $user->uuid)
                ->first();
            if ($account) {
                return true;
            }
        }

        return false;
    }

    private function buildResponse($result, bool $includeDetails, bool $includeRelated): array
    {
        if ($result instanceof TransactionProjection) {
            return $this->buildTransactionResponse($result, $includeDetails, $includeRelated);
        }

        if ($result instanceof Transfer) {
            return $this->buildTransferResponse($result, $includeDetails, $includeRelated);
        }

        return [];
    }

    private function buildTransactionResponse(
        TransactionProjection $transaction,
        bool $includeDetails,
        bool $includeRelated
    ): array {
        $response = [
            'transaction_id' => $transaction->uuid,
            'status'         => $transaction->status,
            'type'           => $transaction->type,
            'amount'         => $transaction->amount,
            'currency'       => $transaction->asset_code ?? 'USD',
            'created_at'     => $transaction->created_at->toIso8601String(),
            'message'        => $this->getStatusMessage($transaction->status, $transaction->type),
        ];

        if ($includeDetails) {
            $response = array_merge($response, [
                'subtype'            => $transaction->subtype,
                'description'        => $transaction->description,
                'reference'          => $transaction->reference,
                'external_reference' => $transaction->external_reference,
                'from_account'       => $transaction->account_uuid,
                'to_account'         => $transaction->related_account_uuid,
                'updated_at'         => $transaction->updated_at?->toIso8601String() ?? $transaction->created_at?->toIso8601String(),
                'metadata'           => $transaction->metadata,
            ]);

            // Add cancellation info if cancelled
            if ($transaction->cancelled_at) {
                $response['cancelled_at'] = is_string($transaction->cancelled_at)
                    ? $transaction->cancelled_at
                    : $transaction->cancelled_at->toIso8601String();
                $response['cancelled_by'] = $transaction->cancelled_by;
            }

            // Add retry info if retried
            if ($transaction->retried_at) {
                $response['retried_at'] = is_string($transaction->retried_at)
                    ? $transaction->retried_at
                    : $transaction->retried_at->toIso8601String();
                $response['retry_transaction_id'] = $transaction->retry_transaction_id;
            }
        }

        if ($includeRelated && $transaction->transaction_group_uuid) {
            $relatedTransactions = TransactionProjection::where('transaction_group_uuid', $transaction->transaction_group_uuid)
                ->where('uuid', '!=', $transaction->uuid)
                ->get()
                ->map(function ($tx) {
                    return [
                        'transaction_id' => $tx->uuid,
                        'type'           => $tx->type,
                        'amount'         => $tx->amount,
                        'status'         => $tx->status,
                    ];
                })
                ->toArray();

            $response['related_transactions'] = $relatedTransactions;
        }

        // Add next steps based on status
        $response['next_steps'] = $this->getNextSteps($transaction->status, $transaction->type);

        return $response;
    }

    private function buildTransferResponse(Transfer $transfer, bool $includeDetails, bool $includeRelated): array
    {
        $eventData = $transfer->event_properties;
        $metadata = $transfer->meta_data;

        // Determine status from event type
        $status = $this->getTransferStatus($transfer->event_class);

        $response = [
            'transaction_id' => $transfer->aggregate_uuid,
            'status'         => $status,
            'type'           => 'transfer',
            'amount'         => $eventData['amount'] ?? 0,
            'currency'       => $eventData['currency'] ?? 'USD',
            'created_at'     => is_string($transfer->created_at)
                ? $transfer->created_at
                : $transfer->created_at->toIso8601String(),
            'message' => $this->getStatusMessage($status, 'transfer'),
        ];

        if ($includeDetails) {
            $response = array_merge($response, [
                'from_account'      => $eventData['from_account_uuid'] ?? null,
                'to_account'        => $eventData['to_account_uuid'] ?? null,
                'description'       => $eventData['description'] ?? $metadata['description'] ?? null,
                'reference'         => $metadata['reference'] ?? null,
                'event_version'     => $transfer->event_version,
                'aggregate_version' => $transfer->aggregate_version,
                'metadata'          => $metadata,
            ]);
        }

        if ($includeRelated) {
            // Get all events for this transfer
            $allEvents = Transfer::where('aggregate_uuid', $transfer->aggregate_uuid)
                ->orderBy('aggregate_version', 'asc')
                ->get()
                ->map(function ($event) {
                    return [
                        'event'      => class_basename($event->event_class),
                        'version'    => $event->aggregate_version,
                        'created_at' => is_string($event->created_at)
                            ? $event->created_at
                            : $event->created_at->toIso8601String(),
                    ];
                })
                ->toArray();

            $response['status_history'] = $allEvents;
        }

        // Add next steps based on status
        $response['next_steps'] = $this->getNextSteps($status, 'transfer');

        return $response;
    }

    private function getTransferStatus(string $eventClass): string
    {
        // Map event classes to statuses
        $eventName = class_basename($eventClass);

        return match ($eventName) {
            'TransferStarted', 'TransferInitiated' => 'pending',
            'TransferCompleted', 'TransferSucceeded', 'MoneyTransferred' => 'completed',
            'TransferFailed', 'TransferFailedEvent' => 'failed',
            'TransferCancelled' => 'cancelled',
            'TransferReversed'  => 'reversed',
            'TransferPending'   => 'processing',
            default             => 'completed', // Default to completed for unknown events
        };
    }

    private function getStatusMessage(string $status, string $type): string
    {
        return match ($status) {
            'pending'    => ucfirst($type) . ' is pending and will be processed soon',
            'processing' => ucfirst($type) . ' is currently being processed',
            'completed'  => ucfirst($type) . ' has been completed successfully',
            'failed'     => ucfirst($type) . ' has failed and may need to be retried',
            'cancelled'  => ucfirst($type) . ' has been cancelled',
            'reversed'   => ucfirst($type) . ' has been reversed',
            'hold'       => ucfirst($type) . ' is on hold pending review',
            default      => ucfirst($type) . ' status: ' . $status,
        };
    }

    private function getNextSteps(string $status, string $type): array
    {
        return match ($status) {
            'pending'    => ['Wait for processing', 'Check status again in a few minutes'],
            'processing' => ['Transaction is being processed', 'No action required'],
            'completed'  => ['Transaction complete', 'No further action needed'],
            'failed'     => ['Review failure reason', 'Retry transaction if needed', 'Contact support if issue persists'],
            'cancelled'  => ['Transaction cancelled', 'Create new transaction if needed'],
            'reversed'   => ['Transaction has been reversed', 'Funds returned to source account'],
            'hold'       => ['Under review', 'May require additional verification', 'Contact support for details'],
            default      => ['Check transaction details', 'Contact support if needed'],
        };
    }

    public function getCapabilities(): array
    {
        return [
            'read',
            'payment-tracking',
            'status-monitoring',
            'real-time',
        ];
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtl(): int
    {
        return 60; // Cache for 1 minute
    }

    public function validateInput(array $parameters): bool
    {
        // Transaction ID is required
        if (! isset($parameters['transaction_id']) || empty($parameters['transaction_id'])) {
            return false;
        }

        // Validate type if provided
        if (isset($parameters['type'])) {
            if (! in_array($parameters['type'], ['transaction', 'transfer', 'both'])) {
                return false;
            }
        }

        // Validate UUID if provided
        if (isset($parameters['user_uuid'])) {
            $uuid = $parameters['user_uuid'];
            if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
                return false;
            }
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // Payment status check requires authentication
        if (! $userId && ! Auth::check()) {
            return false;
        }

        return true;
    }
}
