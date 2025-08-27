<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Account;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Services\AccountService;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AccountBalanceTool implements MCPToolInterface
{
    public function __construct(
        /** @phpstan-ignore-next-line */
        private readonly AccountService $accountService
    ) {
    }

    public function getName(): string
    {
        return 'account.balance';
    }

    public function getCategory(): string
    {
        return 'account';
    }

    public function getDescription(): string
    {
        return 'Get the current balance of an account, optionally filtered by asset';
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
                'asset_code' => [
                    'type'        => 'string',
                    'description' => 'Optional asset code (e.g., USD, EUR, BTC)',
                    'pattern'     => '^[A-Z]{3,10}$',
                ],
            ],
            'required' => ['account_uuid'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'account_uuid' => ['type' => 'string'],
                'balances'     => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'asset_code'   => ['type' => 'string'],
                            'balance'      => ['type' => 'number'],
                            'formatted'    => ['type' => 'string'],
                            'last_updated' => ['type' => 'string'],
                        ],
                    ],
                ],
                'total_value_usd' => ['type' => 'number'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $accountUuid = $parameters['account_uuid'];
            $assetCode = $parameters['asset_code'] ?? null;

            Log::info('MCP Tool: Getting account balance', [
                'account_uuid'    => $accountUuid,
                'asset_code'      => $assetCode,
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

            // Get balances
            $balances = [];

            if ($assetCode) {
                // Get specific asset balance
                $balance = $account->getBalance($assetCode);
                $balances[] = [
                    'asset_code'   => $assetCode,
                    'balance'      => $balance,
                    'formatted'    => $this->formatMoney($balance, $assetCode),
                    'last_updated' => now()->toIso8601String(),
                ];
            } else {
                // Get all asset balances
                foreach ($account->balances as $assetBalance) {
                    $balances[] = [
                        'asset_code'   => $assetBalance->asset_code,
                        'balance'      => $assetBalance->balance,
                        'formatted'    => $this->formatMoney($assetBalance->balance, $assetBalance->asset_code),
                        'last_updated' => $assetBalance->updated_at->toIso8601String(),
                    ];
                }
            }

            // Calculate total value in USD
            $totalValueUsd = $this->calculateTotalValueInUsd($balances);

            $result = [
                'account_uuid'    => $accountUuid,
                'account_name'    => $account->name,
                'balances'        => $balances,
                'total_value_usd' => $totalValueUsd,
                'formatted_total' => $this->formatMoney($totalValueUsd, 'USD'),
            ];

            return ToolExecutionResult::success($result);
        } catch (Exception $e) {
            Log::error('MCP Tool error: account.balance', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    public function getCapabilities(): array
    {
        return [
            'read',
            'multi-asset',
            'real-time',
        ];
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtl(): int
    {
        return 30; // Cache for 30 seconds
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

        // Asset code validation if provided
        if (isset($parameters['asset_code'])) {
            $assetCode = $parameters['asset_code'];
            if (! preg_match('/^[A-Z]{3,10}$/', $assetCode)) {
                return false;
            }
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

        return false;
    }

    private function calculateTotalValueInUsd(array $balances): float
    {
        $total = 0.0;

        foreach ($balances as $balance) {
            // Get exchange rate to USD
            $rate = $this->getExchangeRateToUsd($balance['asset_code']);
            $total += $balance['balance'] * $rate;
        }

        return round($total, 2);
    }

    private function getExchangeRateToUsd(string $assetCode): float
    {
        if ($assetCode === 'USD') {
            return 1.0;
        }

        // Get from exchange rate service
        try {
            $exchangeRate = app(\App\Domain\Asset\Services\ExchangeRateService::class);

            $rate = $exchangeRate->getRate($assetCode, 'USD');
            if (is_numeric($rate)) {
                return (float) $rate;
            }

            return 1.0;
        } catch (Exception $e) {
            Log::warning("Could not get exchange rate for {$assetCode}", [
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    private function formatMoney(float $amount, string $currency): string
    {
        // Simple money formatting
        $decimals = in_array($currency, ['BTC', 'ETH']) ? 8 : 2;
        $formatted = number_format($amount / pow(10, $decimals), $decimals);

        return $currency . ' ' . $formatted;
    }
}
