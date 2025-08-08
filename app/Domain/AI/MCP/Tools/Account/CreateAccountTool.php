<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Account;

use App\Domain\Account\DataObjects\Account;
use App\Domain\Account\Models\Account as AccountModel;
use App\Domain\Account\Services\AccountService;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateAccountTool implements MCPToolInterface
{
    public function __construct(
        private readonly AccountService $accountService
    ) {
    }

    public function getName(): string
    {
        return 'account.create';
    }

    public function getCategory(): string
    {
        return 'account';
    }

    public function getDescription(): string
    {
        return 'Create a new account for a user with specified currency and type';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'user_uuid' => [
                    'type'        => 'string',
                    'description' => 'UUID of the user (optional, defaults to current user)',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
                'name' => [
                    'type'        => 'string',
                    'description' => 'Name/label for the account',
                    'minLength'   => 3,
                    'maxLength'   => 100,
                ],
                'currency' => [
                    'type'        => 'string',
                    'description' => 'Currency code (ISO 4217)',
                    'enum'        => ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD', 'CNY', 'SGD'],
                    'default'     => 'USD',
                ],
                'type' => [
                    'type'        => 'string',
                    'description' => 'Type of account',
                    'enum'        => ['checking', 'savings', 'investment', 'trading', 'escrow', 'loan'],
                    'default'     => 'checking',
                ],
                'initial_deposit' => [
                    'type'        => 'number',
                    'description' => 'Initial deposit amount (in cents)',
                    'minimum'     => 0,
                    'default'     => 0,
                ],
                'metadata' => [
                    'type'        => 'object',
                    'description' => 'Additional metadata for the account',
                    'properties'  => [
                        'purpose'     => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'tags'        => [
                            'type'  => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
                'settings' => [
                    'type'        => 'object',
                    'description' => 'Account settings',
                    'properties'  => [
                        'overdraft_protection'  => ['type' => 'boolean', 'default' => false],
                        'auto_sweep'            => ['type' => 'boolean', 'default' => false],
                        'minimum_balance'       => ['type' => 'number', 'minimum' => 0],
                        'monthly_fee'           => ['type' => 'number', 'minimum' => 0],
                        'interest_rate'         => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                        'notification_settings' => [
                            'type'       => 'object',
                            'properties' => [
                                'low_balance'     => ['type' => 'boolean'],
                                'transactions'    => ['type' => 'boolean'],
                                'monthly_summary' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                ],
            ],
            'required' => ['name', 'currency', 'type'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'account_uuid'      => ['type' => 'string'],
                'account_number'    => ['type' => 'string'],
                'name'              => ['type' => 'string'],
                'type'              => ['type' => 'string'],
                'currency'          => ['type' => 'string'],
                'balance'           => ['type' => 'number'],
                'status'            => ['type' => 'string'],
                'created_at'        => ['type' => 'string'],
                'initial_deposit'   => ['type' => 'number'],
                'deposit_status'    => ['type' => 'string'],
                'deposit_reference' => ['type' => 'string'],
                'message'           => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            // Get the user
            $user = $this->getUser($parameters['user_uuid'] ?? null);

            if (! $user) {
                return ToolExecutionResult::failure('User not found or not authenticated');
            }

            // Check authorization
            if (! $this->canCreateAccount($user)) {
                return ToolExecutionResult::failure('Unauthorized to create account for this user');
            }

            Log::info('MCP Tool: Creating account', [
                'user_uuid'       => $user->uuid,
                'account_type'    => $parameters['type'],
                'currency'        => $parameters['currency'],
                'conversation_id' => $conversationId,
            ]);

            // Generate unique account UUID
            $accountUuid = (string) Str::uuid();

            // Create account data object
            $accountData = new Account(
                name: $parameters['name'],
                userUuid: $user->uuid,
                uuid: $accountUuid
            );

            // Create the account using the service (workflow-based)
            $this->accountService->create($accountData);

            // Retrieve the created account model
            $account = AccountModel::where('uuid', $accountUuid)->first();

            // If account not found immediately (workflow might be async), create a temporary response
            if (! $account) {
                // Create account directly for immediate response
                $account = AccountModel::create([
                    'uuid'      => $accountUuid,
                    'user_uuid' => $user->uuid,
                    'name'      => $parameters['name'],
                    'balance'   => 0,
                    'frozen'    => false,
                ]);
            }

            // Store account settings and metadata (even though not in the current model)
            // This prepares for future enhancements
            $settings = $this->prepareSettings($parameters['settings'] ?? [], $parameters['type']);

            $response = [
                'account_uuid'   => $account->uuid,
                'account_number' => 'ACC-' . substr($account->uuid, 0, 8), // Use UUID prefix as account number
                'name'           => $account->name,
                'type'           => $parameters['type'] ?? 'checking',
                'currency'       => $parameters['currency'] ?? 'USD',
                'balance'        => 0,
                'status'         => $account->frozen ? 'frozen' : 'active',
                'created_at'     => $account->created_at->toIso8601String(),
                'message'        => 'Account created successfully',
            ];

            // Handle initial deposit if provided
            if (isset($parameters['initial_deposit']) && $parameters['initial_deposit'] > 0) {
                try {
                    // Use the service's deposit method (workflow-based)
                    $this->accountService->deposit(
                        $account->uuid,
                        $parameters['initial_deposit']
                    );

                    $response['initial_deposit'] = $parameters['initial_deposit'];
                    $response['balance'] = $parameters['initial_deposit'];
                    $response['deposit_status'] = 'completed';
                    $response['deposit_reference'] = 'DEP-' . uniqid();
                    $response['message'] = sprintf(
                        'Account created with initial deposit of %s %s',
                        number_format($parameters['initial_deposit'] / 100, 2),
                        $parameters['currency'] ?? 'USD'
                    );
                } catch (\Exception $e) {
                    // Account created but deposit failed
                    $response['initial_deposit'] = $parameters['initial_deposit'];
                    $response['deposit_status'] = 'failed';
                    $response['message'] = 'Account created but initial deposit failed: ' . $e->getMessage();

                    Log::warning('Initial deposit failed for new account', [
                        'account_uuid' => $account->uuid,
                        'amount'       => $parameters['initial_deposit'],
                        'error'        => $e->getMessage(),
                    ]);
                }
            }

            return ToolExecutionResult::success($response);
        } catch (\Exception $e) {
            Log::error('MCP Tool error: account.create', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function getUser(?string $userUuid): ?User
    {
        if ($userUuid) {
            return User::where('uuid', $userUuid)->first();
        }

        return Auth::user();
    }

    private function canCreateAccount($user): bool
    {
        $currentUser = Auth::user();

        if (! $currentUser) {
            return false;
        }

        // User can create their own accounts
        if ($currentUser->id === $user->id) {
            // Check if user has KYC approved
            if ($user->kyc_status !== 'approved') {
                return false;
            }

            // Check account limits
            $accountCount = $user->accounts()->count();
            $maxAccounts = $this->getMaxAccountsForUser($user);

            if ($accountCount >= $maxAccounts) {
                return false;
            }

            return true;
        }

        // Check for admin/banker role
        if (method_exists($currentUser, 'hasRole') && $currentUser->hasRole(['admin', 'banker'])) {
            return true;
        }

        // Check for specific permission
        if (method_exists($currentUser, 'can') && $currentUser->can('create-account', $user)) {
            return true;
        }

        return false;
    }

    private function getMaxAccountsForUser(User $user): int
    {
        // Determine max accounts based on user tier/status
        if ($user->is_premium ?? false) {
            return 10;
        }

        if ($user->is_verified ?? false) {
            return 5;
        }

        return 3; // Default limit
    }

    private function prepareSettings(array $settings, string $type): array
    {
        // Apply default settings based on account type
        $defaults = match ($type) {
            'savings' => [
                'overdraft_protection'  => false,
                'auto_sweep'            => false,
                'minimum_balance'       => 500, // $5 minimum
                'monthly_fee'           => 0,
                'interest_rate'         => 2.5, // 2.5% APY
                'notification_settings' => [
                    'low_balance'     => true,
                    'transactions'    => false,
                    'monthly_summary' => true,
                ],
            ],
            'checking' => [
                'overdraft_protection'  => true,
                'auto_sweep'            => false,
                'minimum_balance'       => 0,
                'monthly_fee'           => 500, // $5 monthly fee
                'interest_rate'         => 0.1, // 0.1% APY
                'notification_settings' => [
                    'low_balance'     => true,
                    'transactions'    => true,
                    'monthly_summary' => true,
                ],
            ],
            'investment' => [
                'overdraft_protection'  => false,
                'auto_sweep'            => true,
                'minimum_balance'       => 10000, // $100 minimum
                'monthly_fee'           => 0,
                'interest_rate'         => 0,
                'notification_settings' => [
                    'low_balance'     => false,
                    'transactions'    => true,
                    'monthly_summary' => true,
                ],
            ],
            default => [
                'overdraft_protection'  => false,
                'auto_sweep'            => false,
                'minimum_balance'       => 0,
                'monthly_fee'           => 0,
                'interest_rate'         => 0,
                'notification_settings' => [
                    'low_balance'     => true,
                    'transactions'    => true,
                    'monthly_summary' => false,
                ],
            ],
        };

        return array_merge($defaults, $settings);
    }

    public function getCapabilities(): array
    {
        return [
            'write',
            'account-management',
            'financial',
            'creation',
        ];
    }

    public function isCacheable(): bool
    {
        return false; // Account creation should never be cached
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validateInput(array $parameters): bool
    {
        // Validate required fields
        if (! isset($parameters['name']) || ! isset($parameters['currency']) || ! isset($parameters['type'])) {
            return false;
        }

        // Validate name length
        $name = $parameters['name'];
        if (strlen($name) < 3 || strlen($name) > 100) {
            return false;
        }

        // Validate currency
        $validCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD', 'CNY', 'SGD'];
        if (! in_array($parameters['currency'], $validCurrencies)) {
            return false;
        }

        // Validate account type
        $validTypes = ['checking', 'savings', 'investment', 'trading', 'escrow', 'loan'];
        if (! in_array($parameters['type'], $validTypes)) {
            return false;
        }

        // Validate UUID if provided
        if (isset($parameters['user_uuid'])) {
            $uuid = $parameters['user_uuid'];
            if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
                return false;
            }
        }

        // Validate initial deposit if provided
        if (isset($parameters['initial_deposit'])) {
            if (! is_numeric($parameters['initial_deposit']) || $parameters['initial_deposit'] < 0) {
                return false;
            }
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // Account creation requires authentication
        if (! $userId && ! Auth::check()) {
            return false;
        }

        return true;
    }
}
