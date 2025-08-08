<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Account;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Services\AccountService;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Asset\Models\Asset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
                'amount'            => ['type' => 'number'],
                'currency'          => ['type' => 'string'],
                'new_balance'       => ['type' => 'number'],
                'formatted_balance' => ['type' => 'string'],
                'timestamp'         => ['type' => 'string'],
                'reference'         => ['type' => 'string'],
                'status'            => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $accountUuid = $parameters['account_uuid'];
            $amount = (float) $parameters['amount'];
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

            // Convert amount to smallest unit (e.g., cents)
            $amountInCents = (int) ($amount * 100);

            // Use AccountService to trigger the deposit workflow
            // This will handle event sourcing and domain events
            $this->accountService->deposit($accountUuid, [
                'amount'   => $amountInCents,
                'currency' => $currency,
            ]);

            // After workflow execution, get the new balance
            $newBalance = $account->fresh()->getBalance($currency);

            $result = [
                'transaction_id' => \Illuminate\Support\Str::uuid()->toString(),
                'new_balance'    => $newBalance,
            ];

            $response = [
                'transaction_id'    => $result['transaction_id'],
                'account_uuid'      => $accountUuid,
                'amount'            => $amount,
                'currency'          => $currency,
                'new_balance'       => $result['new_balance'] / 100, // Convert back to major units
                'formatted_balance' => $this->formatMoney($result['new_balance'], $currency),
                'timestamp'         => now()->toIso8601String(),
                'reference'         => $reference,
                'status'            => 'completed',
            ];

            return ToolExecutionResult::success($response);
        } catch (\Exception $e) {
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

    private function formatMoney(float $amount, string $currency): string
    {
        // Simple money formatting
        $decimals = in_array($currency, ['BTC', 'ETH']) ? 8 : 2;
        $formatted = number_format($amount / 100, $decimals);

        return $currency . ' ' . $formatted;
    }
}
