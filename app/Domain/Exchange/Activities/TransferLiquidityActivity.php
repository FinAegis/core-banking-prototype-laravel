<?php

namespace App\Domain\Exchange\Activities;

use App\Domain\Account\Aggregates\AccountAggregate;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Workflow\Activity;

class TransferLiquidityActivity extends Activity
{
    public function execute($input): array
    {
        return DB::transaction(function () use ($input) {
            $transactionId = Str::uuid()->toString();
            
            if (isset($input['from_account_id'])) {
                // Transfer from account to pool
                $this->transferFromAccount(
                    $input['from_account_id'],
                    $input['to_pool_id'],
                    $input['base_currency'],
                    $input['base_amount'],
                    $transactionId . '-base'
                );

                $this->transferFromAccount(
                    $input['from_account_id'],
                    $input['to_pool_id'],
                    $input['quote_currency'],
                    $input['quote_amount'],
                    $transactionId . '-quote'
                );
            } else {
                // Transfer from pool to account
                $this->transferFromPool(
                    $input['from_pool_id'],
                    $input['to_account_id'],
                    $input['base_currency'],
                    $input['base_amount'],
                    $transactionId . '-base'
                );

                $this->transferFromPool(
                    $input['from_pool_id'],
                    $input['to_account_id'],
                    $input['quote_currency'],
                    $input['quote_amount'],
                    $transactionId . '-quote'
                );
            }

            return [
                'transaction_id' => $transactionId,
                'base_transferred' => $input['base_amount'],
                'quote_transferred' => $input['quote_amount'],
            ];
        });
    }

    private function transferFromAccount(
        string $accountId,
        string $poolId,
        string $currency,
        string $amount,
        string $transactionId
    ): void {
        // Get pool's account
        $pool = PoolProjection::where('pool_id', $poolId)->firstOrFail();
        $poolAccountId = $pool->account_id;

        // Execute transfer using account aggregate
        AccountAggregate::retrieve($accountId)
            ->transfer(
                toAccountId: $poolAccountId,
                currency: $currency,
                amount: $amount,
                description: "Liquidity provision to pool {$poolId}",
                metadata: [
                    'type' => 'liquidity_addition',
                    'pool_id' => $poolId,
                    'transaction_id' => $transactionId,
                ]
            )
            ->persist();
    }

    private function transferFromPool(
        string $poolId,
        string $accountId,
        string $currency,
        string $amount,
        string $transactionId
    ): void {
        // Get pool's account
        $pool = PoolProjection::where('pool_id', $poolId)->firstOrFail();
        $poolAccountId = $pool->account_id;

        // Execute transfer using account aggregate
        AccountAggregate::retrieve($poolAccountId)
            ->transfer(
                toAccountId: $accountId,
                currency: $currency,
                amount: $amount,
                description: "Liquidity removal from pool {$poolId}",
                metadata: [
                    'type' => 'liquidity_removal',
                    'pool_id' => $poolId,
                    'transaction_id' => $transactionId,
                ]
            )
            ->persist();
    }
}