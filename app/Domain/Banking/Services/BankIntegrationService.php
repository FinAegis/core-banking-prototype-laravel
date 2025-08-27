<?php

declare(strict_types=1);

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Contracts\IBankConnector;
use App\Domain\Banking\Contracts\IBankIntegrationService;
use App\Domain\Banking\Exceptions\BankConnectionException;
use App\Domain\Banking\Exceptions\BankNotFoundException;
use App\Domain\Banking\Models\BankAccount;
use App\Domain\Banking\Models\BankAccountModel;
use App\Domain\Banking\Models\BankConnection;
use App\Domain\Banking\Models\BankConnectionModel;
use App\Domain\Banking\Models\BankTransfer;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BankIntegrationService implements IBankIntegrationService
{
    /**
     * @var array<string, IBankConnector>
     */
    private array $connectors = [];

    private BankHealthMonitor $healthMonitor;

    private BankRoutingService $routingService;

    public function __construct(
        BankHealthMonitor $healthMonitor,
        BankRoutingService $routingService
    ) {
        $this->healthMonitor = $healthMonitor;
        $this->routingService = $routingService;
    }

    /**
     * {@inheritDoc}
     */
    public function registerConnector(string $bankCode, IBankConnector $connector): void
    {
        $this->connectors[$bankCode] = $connector;

        // Register with health monitor
        $this->healthMonitor->registerBank($bankCode, $connector);
    }

    /**
     * {@inheritDoc}
     */
    public function getConnector(string $bankCode): IBankConnector
    {
        if (! isset($this->connectors[$bankCode])) {
            throw new BankNotFoundException("Bank connector not found: {$bankCode}");
        }

        return $this->connectors[$bankCode];
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableConnectors(): Collection
    {
        return collect($this->connectors)
            ->filter(fn ($connector) => $connector->isAvailable());
    }

    /**
     * {@inheritDoc}
     */
    public function connectUserToBank(User $user, string $bankCode, array $credentials): BankConnection
    {
        $connector = $this->getConnector($bankCode);

        DB::beginTransaction();
        try {
            // Validate credentials with bank
            $connector->authenticate();

            // Store encrypted connection
            $connectionModel = BankConnectionModel::create(
                [
                    'id'           => Str::uuid()->toString(),
                    'user_uuid'    => $user->uuid,
                    'bank_code'    => $bankCode,
                    'status'       => 'active',
                    'credentials'  => encrypt($credentials),
                    'permissions'  => ['accounts', 'transactions', 'transfers'],
                    'last_sync_at' => null,
                    'expires_at'   => now()->addMonths(3),
                    'metadata'     => [
                        'bank_name'    => $connector->getBankName(),
                        'connected_at' => now()->toIso8601String(),
                    ],
                ]
            );

            // Initial account sync
            $this->syncBankAccounts($user, $bankCode);

            DB::commit();

            return BankConnection::fromArray($connectionModel->toArray());
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'Failed to connect user to bank',
                [
                    'user_id'   => $user->uuid,
                    'bank_code' => $bankCode,
                    'error'     => $e->getMessage(),
                ]
            );
            throw new BankConnectionException("Failed to connect to {$bankCode}: " . $e->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function disconnectUserFromBank(User $user, string $bankCode): bool
    {
        return DB::transaction(
            function () use ($user, $bankCode) {
                // Deactivate connection
                $updated = BankConnectionModel::where('user_uuid', $user->uuid)
                    ->where('bank_code', $bankCode)
                    ->where('status', 'active')
                    ->update(
                        [
                            'status'     => 'disconnected',
                            'updated_at' => now(),
                        ]
                    );

                // Deactivate associated accounts
                BankAccountModel::where('user_uuid', $user->uuid)
                    ->where('bank_code', $bankCode)
                    ->where('status', 'active')
                    ->update(
                        [
                            'status'     => 'disconnected',
                            'updated_at' => now(),
                        ]
                    );

                return $updated > 0;
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getUserBankConnections(User $user): Collection
    {
        return BankConnectionModel::where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->get()
            ->map(fn ($model) => BankConnection::fromArray($model->toArray()));
    }

    /**
     * {@inheritDoc}
     */
    public function createBankAccount(User $user, string $bankCode, array $accountDetails): BankAccount
    {
        $connector = $this->getConnector($bankCode);

        // Verify user has active connection
        $connection = BankConnectionModel::where('user_uuid', $user->uuid)
            ->where('bank_code', $bankCode)
            ->where('status', 'active')
            ->firstOrFail();

        DB::beginTransaction();
        try {
            // Create account with bank
            $bankAccount = $connector->createAccount($accountDetails);

            // Store in database
            $accountModel = BankAccountModel::create(
                [
                    'id'             => Str::uuid()->toString(),
                    'user_uuid'      => $user->uuid,
                    'bank_code'      => $bankCode,
                    'external_id'    => $bankAccount->id,
                    'account_number' => encrypt($bankAccount->accountNumber),
                    'iban'           => encrypt($bankAccount->iban),
                    'swift'          => $bankAccount->swift,
                    'currency'       => $bankAccount->currency,
                    'account_type'   => $bankAccount->accountType,
                    'status'         => 'active',
                    'metadata'       => $bankAccount->metadata,
                ]
            );

            DB::commit();

            return $bankAccount;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUserBankAccounts(User $user, ?string $bankCode = null): Collection
    {
        $query = BankAccountModel::where('user_uuid', $user->uuid)
            ->where('status', 'active');

        if ($bankCode) {
            $query->where('bank_code', $bankCode);
        }

        return $query->get()->map(
            function ($model) {
                return new BankAccount(
                    id: $model->external_id,
                    bankCode: $model->bank_code,
                    accountNumber: decrypt($model->account_number),
                    iban: decrypt($model->iban),
                    swift: $model->swift,
                    currency: $model->currency,
                    accountType: $model->account_type,
                    status: $model->status,
                    holderName: $model->metadata['holder_name'] ?? null,
                    holderAddress: $model->metadata['holder_address'] ?? null,
                    metadata: $model->metadata,
                    createdAt: $model->created_at,
                    updatedAt: $model->updated_at,
                    closedAt: null
                );
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function syncBankAccounts(User $user, string $bankCode): Collection
    {
        $connector = $this->getConnector($bankCode);
        $connection = BankConnectionModel::where('user_uuid', $user->uuid)
            ->where('bank_code', $bankCode)
            ->where('status', 'active')
            ->firstOrFail();

        // Get credentials
        $credentials = decrypt($connection->credentials);
        $connector->authenticate();

        // Fetch accounts from bank
        // This would need to be implemented based on specific bank API
        $accounts = collect();

        DB::transaction(
            function () use ($accounts, $user, $bankCode) {
                foreach ($accounts as $account) {
                    BankAccountModel::updateOrCreate(
                        [
                            'user_uuid'   => $user->uuid,
                            'bank_code'   => $bankCode,
                            'external_id' => $account->id,
                        ],
                        [
                            'account_number' => encrypt($account->accountNumber),
                            'iban'           => encrypt($account->iban),
                            'swift'          => $account->swift,
                            'currency'       => $account->currency,
                            'account_type'   => $account->accountType,
                            'status'         => $account->status,
                            'metadata'       => $account->metadata,
                            'updated_at'     => now(),
                        ]
                    );
                }

                // Update last sync time
                $connection->update(['last_sync_at' => now()]);
            }
        );

        return $accounts;
    }

    /**
     * {@inheritDoc}
     */
    public function initiateInterBankTransfer(
        User $user,
        string $fromBankCode,
        string $fromAccountId,
        string $toBankCode,
        string $toAccountId,
        float $amount,
        string $currency,
        array $metadata = []
    ): BankTransfer {
        // Get connectors
        $fromConnector = $this->getConnector($fromBankCode);
        $toConnector = $this->getConnector($toBankCode);

        // Verify accounts belong to user
        $fromAccount = BankAccountModel::where('user_uuid', $user->uuid)
            ->where('bank_code', $fromBankCode)
            ->where('external_id', $fromAccountId)
            ->where('status', 'active')
            ->firstOrFail();

        DB::beginTransaction();
        try {
            // Check balance
            $balance = $fromConnector->getBalance($fromAccountId, $currency);
            if (! $balance->hasSufficientFunds($amount)) {
                throw new Exception('Insufficient funds');
            }

            // Determine transfer type
            $transferType = $this->routingService->determineTransferType(
                $fromBankCode,
                $toBankCode,
                $currency,
                $amount
            );

            // Initiate transfer
            $transfer = $fromConnector->initiateTransfer(
                [
                    'from_account_id' => $fromAccountId,
                    'to_account_id'   => $toAccountId,
                    'to_bank_code'    => $toBankCode,
                    'amount'          => $amount,
                    'currency'        => $currency,
                    'type'            => $transferType,
                    'reference'       => $metadata['reference'] ?? Str::random(16),
                    'description'     => $metadata['description'] ?? null,
                ]
            );

            // Store transfer record
            DB::table('bank_transfers')->insert(
                [
                    'id'              => $transfer->id,
                    'user_uuid'       => $user->uuid,
                    'from_bank_code'  => $fromBankCode,
                    'from_account_id' => $fromAccountId,
                    'to_bank_code'    => $toBankCode,
                    'to_account_id'   => $toAccountId,
                    'amount'          => $amount,
                    'currency'        => $currency,
                    'type'            => $transferType,
                    'status'          => $transfer->status,
                    'metadata'        => json_encode($transfer->metadata),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );

            DB::commit();

            return $transfer;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getOptimalBank(User $user, string $currency, float $amount, string $transferType): string
    {
        return $this->routingService->getOptimalBank(
            $user,
            $currency,
            $amount,
            $transferType,
            $this->getUserBankConnections($user)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function checkBankHealth(string $bankCode): array
    {
        return $this->healthMonitor->checkHealth($bankCode);
    }

    /**
     * {@inheritDoc}
     */
    public function getAggregatedBalance(User $user, string $currency): float
    {
        $cacheKey = "user_balance:{$user->uuid}:{$currency}";

        return Cache::remember(
            $cacheKey,
            300,
            function () use ($user, $currency) {
                $totalBalance = 0;

                $connections = $this->getUserBankConnections($user);

                foreach ($connections as $connection) {
                    if (! $connection->isActive()) {
                        continue;
                    }

                    try {
                        $connector = $this->getConnector($connection->bankCode);
                        $accounts = $this->getUserBankAccounts($user, $connection->bankCode);

                        foreach ($accounts as $account) {
                            if ($account->supportsCurrency($currency)) {
                                $balance = $connector->getBalance($account->id, $currency);
                                $totalBalance += $balance->available;
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning(
                            'Failed to get balance from bank',
                            [
                                'bank_code' => $connection->bankCode,
                                'error'     => $e->getMessage(),
                            ]
                        );
                    }
                }

                return $totalBalance;
            }
        );
    }
}
