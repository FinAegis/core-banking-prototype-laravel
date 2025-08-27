<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CustodianAccountService
{
    public function __construct(
        private readonly CustodianRegistry $custodianRegistry
    ) {
    }

    /**
     * Link an internal account to a custodian account.
     */
    public function linkAccount(
        Account $account,
        string $custodianName,
        string $custodianAccountId,
        array $metadata = [],
        bool $isPrimary = false
    ): CustodianAccount {
        // Verify the custodian exists and is available
        $custodian = $this->custodianRegistry->get($custodianName);

        // Validate the custodian account exists
        if (! $custodian->validateAccount($custodianAccountId)) {
            throw new InvalidArgumentException("Invalid custodian account: {$custodianAccountId}");
        }

        // Get account info from custodian
        $accountInfo = $custodian->getAccountInfo($custodianAccountId);

        return DB::transaction(
            function () use ($account, $custodianName, $custodianAccountId, $accountInfo, $metadata, $isPrimary) {
                $custodianAccount = CustodianAccount::create(
                    [
                        'account_uuid'           => $account->uuid,
                        'custodian_name'         => $custodianName,
                        'custodian_account_id'   => $custodianAccountId,
                        'custodian_account_name' => $accountInfo->name,
                        'status'                 => $accountInfo->status,
                        'metadata'               => array_merge($accountInfo->metadata, $metadata),
                    ]
                );

                if ($isPrimary) {
                    $custodianAccount->setAsPrimary();
                }

                Log::info(
                    'Linked custodian account',
                    [
                        'account_uuid'      => $account->uuid,
                        'custodian'         => $custodianName,
                        'custodian_account' => $custodianAccountId,
                    ]
                );

                return $custodianAccount;
            }
        );
    }

    /**
     * Unlink a custodian account.
     */
    public function unlinkAccount(CustodianAccount $custodianAccount): void
    {
        DB::transaction(
            function () use ($custodianAccount) {
                // If this was primary, make another one primary
                if ($custodianAccount->is_primary) {
                    $nextPrimary = CustodianAccount::where('account_uuid', $custodianAccount->account_uuid)
                        ->where('id', '!=', $custodianAccount->id)
                        ->where('status', 'active')
                        ->first();

                    if ($nextPrimary) {
                        $nextPrimary->setAsPrimary();
                    }
                }

                $custodianAccount->delete();

                Log::info(
                    'Unlinked custodian account',
                    [
                        'custodian_account_id' => $custodianAccount->id,
                        'account_uuid'         => $custodianAccount->account_uuid,
                    ]
                );
            }
        );
    }

    /**
     * Get balance from custodian for a specific asset.
     */
    public function getBalance(CustodianAccount $custodianAccount, string $assetCode): Money
    {
        $custodian = $this->custodianRegistry->get($custodianAccount->custodian_name);

        return $custodian->getBalance($custodianAccount->custodian_account_id, $assetCode);
    }

    /**
     * Get all balances from custodian.
     */
    public function getAllBalances(CustodianAccount $custodianAccount): array
    {
        $custodian = $this->custodianRegistry->get($custodianAccount->custodian_name);
        $accountInfo = $custodian->getAccountInfo($custodianAccount->custodian_account_id);

        return $accountInfo->balances;
    }

    /**
     * Initiate a transfer between custodian accounts.
     */
    public function initiateTransfer(
        CustodianAccount $fromAccount,
        CustodianAccount $toAccount,
        Money $amount,
        string $assetCode,
        string $reference,
        ?string $description = null
    ): string {
        // Ensure both accounts use the same custodian
        if ($fromAccount->custodian_name !== $toAccount->custodian_name) {
            throw new InvalidArgumentException('Cross-custodian transfers are not supported yet');
        }

        $custodian = $this->custodianRegistry->get($fromAccount->custodian_name);

        $transferRequest = new TransferRequest(
            fromAccount: $fromAccount->custodian_account_id,
            toAccount: $toAccount->custodian_account_id,
            amount: $amount,
            assetCode: $assetCode,
            reference: $reference,
            description: $description
        );

        $receipt = $custodian->initiateTransfer($transferRequest);

        Log::info(
            'Initiated custodian transfer',
            [
                'transaction_id' => $receipt->id,
                'from_account'   => $fromAccount->custodian_account_id,
                'to_account'     => $toAccount->custodian_account_id,
                'amount'         => $amount->getAmount(),
                'asset'          => $assetCode,
            ]
        );

        return $receipt->id;
    }

    /**
     * Get transaction status from custodian.
     */
    public function getTransactionStatus(string $custodianName, string $transactionId): array
    {
        $custodian = $this->custodianRegistry->get($custodianName);
        $receipt = $custodian->getTransactionStatus($transactionId);

        return $receipt->toArray();
    }

    /**
     * Sync account status with custodian.
     */
    public function syncAccountStatus(CustodianAccount $custodianAccount): void
    {
        $custodian = $this->custodianRegistry->get($custodianAccount->custodian_name);

        try {
            $accountInfo = $custodian->getAccountInfo($custodianAccount->custodian_account_id);

            $custodianAccount->update(
                [
                    'status'                 => $accountInfo->status,
                    'custodian_account_name' => $accountInfo->name,
                    'metadata'               => array_merge($custodianAccount->metadata ?? [], $accountInfo->metadata),
                ]
            );

            Log::info(
                'Synced custodian account status',
                [
                    'custodian_account_id' => $custodianAccount->id,
                    'new_status'           => $accountInfo->status,
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to sync custodian account status',
                [
                    'custodian_account_id' => $custodianAccount->id,
                    'error'                => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Get transaction history from custodian.
     */
    public function getTransactionHistory(
        CustodianAccount $custodianAccount,
        ?int $limit = 100,
        ?int $offset = 0
    ): array {
        $custodian = $this->custodianRegistry->get($custodianAccount->custodian_name);

        return $custodian->getTransactionHistory(
            $custodianAccount->custodian_account_id,
            $limit,
            $offset
        );
    }
}
