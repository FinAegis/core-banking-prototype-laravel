<?php

namespace App\Domain\Exchange\Activities;

use App\Domain\Account\Models\AccountBalance;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Support\Facades\DB;
use Workflow\Activity;

class ReleaseLiquidityActivity extends Activity
{
    public function execute($lock): array
    {
        return DB::transaction(
            function () use ($lock) {
                // Find and remove lock record
                $lockRecord = \DB::table('balance_locks')
                    ->where('id', $lock['lock_id'])
                    ->first();

                if (! $lockRecord) {
                    throw new DomainException('Lock record not found');
                }

                // Update balance
                $balance = AccountBalance::where('account_id', $lock['account_id'])
                    ->where('currency_code', $lock['currency'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $amount = BigDecimal::of($lock['amount']);

                $balance->locked_balance = BigDecimal::of($balance->locked_balance)
                    ->minus($amount)
                    ->__toString();
                $balance->available_balance = BigDecimal::of($balance->available_balance)
                    ->plus($amount)
                    ->__toString();
                $balance->save();

                // Remove lock record
                \DB::table('balance_locks')
                    ->where('id', $lock['lock_id'])
                    ->delete();

                return [
                'released'   => true,
                'account_id' => $lock['account_id'],
                'currency'   => $lock['currency'],
                'amount'     => $lock['amount'],
                ];
            }
        );
    }
}
