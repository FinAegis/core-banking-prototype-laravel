<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Payment;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Asset\Models\Asset;
use App\Domain\Payment\Services\TransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransferTool implements MCPToolInterface
{
    public function __construct(
        private readonly TransferService $transferService
    ) {
    }

    public function getName(): string
    {
        return 'payment.transfer';
    }

    public function getCategory(): string
    {
        return 'payment';
    }

    public function getDescription(): string
    {
        return 'Transfer funds between accounts';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'from_account_uuid' => [
                    'type'        => 'string',
                    'description' => 'The UUID of the source account',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
                'to_account_uuid' => [
                    'type'        => 'string',
                    'description' => 'The UUID of the destination account',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
                'amount' => [
                    'type'        => 'number',
                    'description' => 'The amount to transfer',
                    'minimum'     => 0.01,
                ],
                'currency' => [
                    'type'        => 'string',
                    'description' => 'The currency code (e.g., USD, EUR)',
                    'pattern'     => '^[A-Z]{3,10}$',
                ],
                'reference' => [
                    'type'        => 'string',
                    'description' => 'Optional reference for the transfer',
                    'maxLength'   => 255,
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'Optional description of the transfer',
                    'maxLength'   => 500,
                ],
            ],
            'required' => ['from_account_uuid', 'to_account_uuid', 'amount', 'currency'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'transfer_id'            => ['type' => 'string'],
                'from_account_uuid'      => ['type' => 'string'],
                'to_account_uuid'        => ['type' => 'string'],
                'amount'                 => ['type' => 'number'],
                'currency'               => ['type' => 'string'],
                'from_new_balance'       => ['type' => 'number'],
                'to_new_balance'         => ['type' => 'number'],
                'formatted_from_balance' => ['type' => 'string'],
                'formatted_to_balance'   => ['type' => 'string'],
                'timestamp'              => ['type' => 'string'],
                'reference'              => ['type' => 'string'],
                'status'                 => ['type' => 'string'],
                'fee'                    => ['type' => 'number'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $fromAccountUuid = $parameters['from_account_uuid'];
            $toAccountUuid = $parameters['to_account_uuid'];
            $amount = (float) $parameters['amount'];
            $currency = $parameters['currency'];
            $reference = $parameters['reference'] ?? null;
            $description = $parameters['description'] ?? null;

            Log::info('MCP Tool: Processing transfer', [
                'from_account'    => $fromAccountUuid,
                'to_account'      => $toAccountUuid,
                'amount'          => $amount,
                'currency'        => $currency,
                'conversation_id' => $conversationId,
            ]);

            // Validate accounts exist
            $fromAccount = Account::where('uuid', $fromAccountUuid)->first();
            if (! $fromAccount) {
                return ToolExecutionResult::failure("Source account not found: {$fromAccountUuid}");
            }

            $toAccount = Account::where('uuid', $toAccountUuid)->first();
            if (! $toAccount) {
                return ToolExecutionResult::failure("Destination account not found: {$toAccountUuid}");
            }

            // Check authorization for source account
            if (! $this->canAccessAccount($fromAccount)) {
                return ToolExecutionResult::failure('Unauthorized access to source account');
            }

            // Validate currency/asset exists
            $asset = Asset::where('code', $currency)->first();
            if (! $asset) {
                return ToolExecutionResult::failure("Invalid currency: {$currency}");
            }

            // Convert amount to smallest unit (e.g., cents)
            $amountInCents = (int) ($amount * 100);

            // Check sufficient balance
            $currentBalance = $fromAccount->getBalance($currency);
            if ($currentBalance < $amountInCents) {
                return ToolExecutionResult::failure(
                    sprintf(
                        'Insufficient balance. Available: %s %s',
                        number_format($currentBalance / 100, 2),
                        $currency
                    )
                );
            }

            // Calculate fee (example: 0.1% of transfer amount, minimum 10 cents)
            $fee = max(10, (int) ($amountInCents * 0.001));

            // Use TransferService to trigger the transfer workflow
            // This will handle event sourcing and domain events
            $this->transferService->transfer($fromAccountUuid, $toAccountUuid, [
                'amount'   => $amountInCents,
                'currency' => $currency,
            ]);

            // After workflow execution, get the new balances
            $fromNewBalance = $fromAccount->fresh()->getBalance($currency);
            $toNewBalance = $toAccount->fresh()->getBalance($currency);

            $result = [
                'transfer_id'      => \Illuminate\Support\Str::uuid()->toString(),
                'from_new_balance' => $fromNewBalance,
                'to_new_balance'   => $toNewBalance,
            ];

            $response = [
                'transfer_id'            => $result['transfer_id'],
                'from_account_uuid'      => $fromAccountUuid,
                'to_account_uuid'        => $toAccountUuid,
                'amount'                 => $amount,
                'currency'               => $currency,
                'from_new_balance'       => $result['from_new_balance'] / 100,
                'to_new_balance'         => $result['to_new_balance'] / 100,
                'formatted_from_balance' => $this->formatMoney($result['from_new_balance'], $currency),
                'formatted_to_balance'   => $this->formatMoney($result['to_new_balance'], $currency),
                'timestamp'              => now()->toIso8601String(),
                'reference'              => $reference,
                'status'                 => 'completed',
                'fee'                    => $fee / 100,
            ];

            return ToolExecutionResult::success($response);
        } catch (\Exception $e) {
            Log::error('MCP Tool error: payment.transfer', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    public function getCapabilities(): array
    {
        return [
            'write',
            'multi-asset',
            'transactional',
            'balance-check',
            'atomic',
        ];
    }

    public function isCacheable(): bool
    {
        return false; // Transfers should never be cached
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validateInput(array $parameters): bool
    {
        // UUID validation for both accounts
        foreach (['from_account_uuid', 'to_account_uuid'] as $field) {
            if (! isset($parameters[$field])) {
                return false;
            }

            $uuid = $parameters[$field];
            if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
                return false;
            }
        }

        // Prevent self-transfer
        if ($parameters['from_account_uuid'] === $parameters['to_account_uuid']) {
            return false;
        }

        // Amount validation
        if (! isset($parameters['amount']) || $parameters['amount'] <= 0) {
            return false;
        }

        // Currency validation
        if (! isset($parameters['currency'])) {
            return false;
        }

        $currency = $parameters['currency'];
        if (! preg_match('/^[A-Z]{3,10}$/', $currency)) {
            return false;
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // Check if user is authenticated
        if (! $userId && ! Auth::check()) {
            return false;
        }

        // Transfers require authentication
        return true;
    }

    private function canAccessAccount($account): bool
    {
        // Check if current user owns the account or has permission
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Check ownership via user_uuid - required for transfers
        if ($account->user_uuid === $user->uuid) {
            return true;
        }

        // Check for admin permission
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        // Check for specific transfer permission
        if (method_exists($user, 'can') && $user->can('transfer', $account)) {
            return true;
        }

        return false;
    }

    private function formatMoney(float $amount, string $currency): string
    {
        // Simple money formatting
        $decimals = in_array($currency, ['BTC', 'ETH']) ? 8 : 2;
        $formatted = number_format($amount / 100, $decimals);

        return $currency . ' ' . $formatted;
    }
}
