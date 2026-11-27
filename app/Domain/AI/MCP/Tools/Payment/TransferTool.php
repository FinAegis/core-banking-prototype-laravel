<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Payment;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Asset\Models\Asset;
use App\Domain\Wallet\Workflows\WalletTransferWorkflow;
use App\Support\DecimalString;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Workflow\WorkflowStub;

class TransferTool implements MCPToolInterface
{
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
                'amount'                 => ['type' => 'string', 'description' => 'Decimal string in the precision of the asset'],
                'currency'               => ['type' => 'string'],
                'from_new_balance'       => ['type' => 'string', 'description' => 'Decimal string in the precision of the asset'],
                'to_new_balance'         => ['type' => 'string', 'description' => 'Decimal string in the precision of the asset'],
                'formatted_from_balance' => ['type' => 'string'],
                'formatted_to_balance'   => ['type' => 'string'],
                'timestamp'              => ['type' => 'string'],
                'reference'              => ['type' => 'string'],
                'status'                 => ['type' => 'string'],
                'fee'                    => ['type' => 'string', 'description' => 'No fee is charged, so this is always 0'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $fromAccountUuid = $parameters['from_account_uuid'];
            $toAccountUuid = $parameters['to_account_uuid'];
            $amount = $parameters['amount'];
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

            // Only the owner may move money out of the source account.
            // Someone else's account answers like a missing one.
            $fromAccount = Account::findOwnedBy((string) $fromAccountUuid, Auth::user());
            if (! $fromAccount) {
                return ToolExecutionResult::failure("Source account not found: {$fromAccountUuid}");
            }

            $toAccount = Account::where('uuid', $toAccountUuid)->first();
            if (! $toAccount) {
                return ToolExecutionResult::failure("Destination account not found: {$toAccountUuid}");
            }

            if ($fromAccount->frozen) {
                return ToolExecutionResult::failure('The source account is frozen.');
            }
            if ($toAccount->frozen) {
                return ToolExecutionResult::failure('The destination account is frozen.');
            }

            $asset = Asset::where('code', $currency)->first();
            if (! $asset) {
                return ToolExecutionResult::failure("Invalid currency: {$currency}");
            }

            // Exact minor units of the asset. An amount with more decimals
            // than the asset has is refused, not rounded.
            $precision = (int) $asset->precision;
            $amountInMinorUnits = DecimalString::toMinorUnitsInt($amount, $precision);
            if ($amountInMinorUnits === null) {
                return ToolExecutionResult::failure("The amount can have at most {$precision} decimal places for {$currency}.");
            }
            if ($amountInMinorUnits <= 0) {
                return ToolExecutionResult::failure('The amount must be more than zero.');
            }

            $currentBalance = $fromAccount->getBalance($currency);
            if ($currentBalance < $amountInMinorUnits) {
                return ToolExecutionResult::failure(
                    sprintf('Insufficient balance. Available: %s %s', $this->toMajorUnits($currentBalance, $precision), $currency)
                );
            }

            // The same workflow as the REST transfer endpoint, for every asset.
            WorkflowStub::make(WalletTransferWorkflow::class)->start(
                new AccountUuid((string) $fromAccountUuid),
                new AccountUuid((string) $toAccountUuid),
                $currency,
                $amountInMinorUnits,
                $reference !== null ? (string) $reference : null
            );

            $fromNewBalance = $fromAccount->fresh()?->getBalance($currency) ?? 0;
            $toNewBalance = $toAccount->fresh()?->getBalance($currency) ?? 0;

            $response = [
                'transfer_id'            => \Illuminate\Support\Str::uuid()->toString(),
                'from_account_uuid'      => $fromAccountUuid,
                'to_account_uuid'        => $toAccountUuid,
                'amount'                 => $this->toMajorUnits($amountInMinorUnits, $precision),
                'currency'               => $currency,
                'from_new_balance'       => $this->toMajorUnits($fromNewBalance, $precision),
                'to_new_balance'         => $this->toMajorUnits($toNewBalance, $precision),
                'formatted_from_balance' => $currency . ' ' . $this->toMajorUnits($fromNewBalance, $precision),
                'formatted_to_balance'   => $currency . ' ' . $this->toMajorUnits($toNewBalance, $precision),
                'timestamp'              => now()->toIso8601String(),
                'reference'              => $reference,
                'status'                 => 'completed',
                'fee'                    => '0',
            ];

            return ToolExecutionResult::success($response);
        } catch (Exception $e) {
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

    /**
     * Minor units back to a decimal string in the precision of the asset.
     */
    private function toMajorUnits(int $minor, int $precision): string
    {
        return bcdiv((string) $minor, bcpow('10', (string) $precision), $precision);
    }
}
