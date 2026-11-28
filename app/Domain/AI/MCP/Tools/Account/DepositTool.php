<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Account;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Services\AccountService;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Support\DecimalString;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Workflow\WorkflowStub;

class DepositTool implements MCPToolInterface
{
    public function __construct(
        private readonly AccountService $accountService
    ) {
    }

    public function getName(): string
    {
        return 'account.deposit';
    }

    public function getCategory(): string
    {
        return 'account';
    }

    public function getDescription(): string
    {
        return 'Deposit funds into an account';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'account_uuid' => [
                    'type'        => 'string',
                    'description' => 'The UUID of the account',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
                'amount' => [
                    'type'        => 'number',
                    'description' => 'The amount to deposit',
                    'minimum'     => 0.01,
                ],
                'currency' => [
                    'type'        => 'string',
                    'description' => 'The currency code (e.g., USD, EUR)',
                    'pattern'     => '^[A-Z]{3,10}$',
                ],
                'reference' => [
                    'type'        => 'string',
                    'description' => 'Optional reference for the deposit',
                    'maxLength'   => 255,
                ],
                'source' => [
                    'type'        => 'string',
                    'description' => 'Source of funds (e.g., bank_transfer, card, cash)',
                    'enum'        => ['bank_transfer', 'card', 'cash', 'wire', 'crypto'],
                ],
            ],
            'required' => ['account_uuid', 'amount', 'currency'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'transaction_id'    => ['type' => 'string'],
                'account_uuid'      => ['type' => 'string'],
                'amount'            => ['type' => 'string', 'description' => 'Decimal string in the precision of the asset'],
                'currency'          => ['type' => 'string'],
                'new_balance'       => ['type' => 'string', 'description' => 'Decimal string in the precision of the asset'],
                'formatted_balance' => ['type' => 'string'],
                'timestamp'         => ['type' => 'string'],
                'reference'         => ['type' => 'string'],
                'status'            => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        // A deposit creates money in the ledger, so it follows the same
        // switch as the REST deposit endpoint (off in production).
        if (! config('ledger.direct_funding_enabled')) {
            return ToolExecutionResult::failure('This operation is not available.');
        }

        try {
            $accountUuid = $parameters['account_uuid'];
            $amount = $parameters['amount'];
            $currency = $parameters['currency'];
            $reference = $parameters['reference'] ?? null;
            $source = $parameters['source'] ?? 'bank_transfer';

            Log::info('MCP Tool: Processing deposit', [
                'account_uuid'    => $accountUuid,
                'amount'          => $amount,
                'currency'        => $currency,
                'conversation_id' => $conversationId,
            ]);

            // Get account from database
            $account = Account::where('uuid', $accountUuid)->first();

            if (! $account) {
                return ToolExecutionResult::failure("Account not found: {$accountUuid}");
            }

            // Check authorization
            if (! $this->canAccessAccount($account)) {
                return ToolExecutionResult::failure('Unauthorized access to account');
            }

            // Validate currency/asset exists
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

            // The same workflows as the REST deposit endpoint.
            if ($currency === 'USD') {
                $this->accountService->deposit($accountUuid, $amountInMinorUnits);
            } else {
                WorkflowStub::make(AssetDepositWorkflow::class)
                    ->start(new AccountUuid($accountUuid), $currency, $amountInMinorUnits);
            }

            $newBalance = $account->fresh()?->getBalance($currency) ?? 0;

            $response = [
                'transaction_id'    => \Illuminate\Support\Str::uuid()->toString(),
                'account_uuid'      => $accountUuid,
                'amount'            => $this->toMajorUnits($amountInMinorUnits, $precision),
                'currency'          => $currency,
                'new_balance'       => $this->toMajorUnits($newBalance, $precision),
                'formatted_balance' => $currency . ' ' . $this->toMajorUnits($newBalance, $precision),
                'timestamp'         => now()->toIso8601String(),
                'reference'         => $reference,
                'status'            => 'completed',
            ];

            return ToolExecutionResult::success($response);
        } catch (Exception $e) {
            Log::error('MCP Tool error: account.deposit', [
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
        ];
    }

    public function isCacheable(): bool
    {
        return false; // Deposits should never be cached
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validateInput(array $parameters): bool
    {
        // UUID validation
        if (! isset($parameters['account_uuid'])) {
            return false;
        }

        $uuid = $parameters['account_uuid'];
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
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

        // Additional authorization logic can be added here
        // For deposits, we might want to check for specific permissions
        return true;
    }

    private function canAccessAccount($account): bool
    {
        // Check if current user owns the account or has permission
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Check ownership via user_uuid
        if ($account->user_uuid === $user->uuid) {
            return true;
        }

        // Check team membership if applicable
        if ($account->team_id && method_exists($user, 'belongsToTeam') && $user->belongsToTeam($account->team)) {
            return true;
        }

        // Check for admin permission
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        // Check for specific deposit permission
        if (method_exists($user, 'can') && $user->can('deposit', $account)) {
            return true;
        }

        return false;
    }

    /**
     * Minor units back to a decimal string in the precision of the asset.
     */
    private function toMajorUnits(int $minor, int $precision): string
    {
        return bcdiv((string) $minor, bcpow('10', (string) $precision), $precision);
    }
}
