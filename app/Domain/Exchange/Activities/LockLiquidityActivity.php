<?php

namespace App\Domain\Exchange\Activities;

use App\Domain\Account\Models\AccountBalance;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Support\Facades\DB;
use Str;
use Workflow\Activity;

class LockLiquidityActivity extends Activity
{
    public function execute($input): array
    {
        return DB::transaction(
            function () use ($input) {
                $balance = AccountBalance::where('account_id', $input['account_id'])
                    ->where('currency_code', $input['currency'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $amount = BigDecimal::of($input['amount']);
                $available = BigDecimal::of($balance->available_balance);

                if ($available->isLessThan($amount)) {
                    throw new DomainException('Insufficient available balance');
                }

                // Update balance
                $balance->available_balance = $available->minus($amount)->__toString();
                $balance->locked_balance = BigDecimal::of($balance->locked_balance)
                    ->plus($amount)
                    ->__toString();
                $balance->save();

                // Create lock record for compensation
                $lockId = Str::uuid()->toString();

                \DB::table('balance_locks')->insert(
                    [
                        'id'            => $lockId,
                        'account_id'    => $input['account_id'],
                        'currency_code' => $input['currency'],
                        'amount'        => $input['amount'],
                        'reason'        => 'liquidity_pool',
                        'reference_id'  => $input['pool_id'],
                        'created_at'    => now(),
                    ]
                );

                return [
                    'lock_id'    => $lockId,
                    'account_id' => $input['account_id'],
                    'currency'   => $input['currency'],
                    'amount'     => $input['amount'],
                ];
            }
        );
    }
}
