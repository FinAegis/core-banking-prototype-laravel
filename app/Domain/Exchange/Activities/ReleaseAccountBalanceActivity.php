<?php

namespace App\Domain\Exchange\Activities;

use App\Models\AssetBalance;
use Illuminate\Support\Facades\DB;
use Workflow\Activity;

class ReleaseAccountBalanceActivity extends Activity
{
    public function execute(string $orderId, string $lockId): object
    {
        return DB::transaction(function () use ($orderId, $lockId) {
            $lockInfo = \Cache::get("order_lock:{$lockId}");
            
            if (!$lockInfo) {
                return (object)[
                    'success' => false,
                    'message' => 'Lock not found',
                ];
            }
            
            // Verify the lock belongs to the order
            if ($lockInfo['order_id'] !== $orderId) {
                return (object)[
                    'success' => false,
                    'message' => 'Lock does not belong to this order',
                ];
            }
            
            // Release the locked balance
            $balance = AssetBalance::where('account_id', $lockInfo['account_id'])
                ->where('asset_code', $lockInfo['currency'])
                ->lockForUpdate()
                ->first();
            
            if (!$balance) {
                return (object)[
                    'success' => false,
                    'message' => 'Balance not found',
                ];
            }
            
            // Ensure we don't go negative
            $newLockedBalance = bcsub($balance->locked_balance ?? '0', $lockInfo['amount'], 18);
            if (bccomp($newLockedBalance, '0', 18) < 0) {
                $newLockedBalance = '0';
            }
            
            $balance->locked_balance = $newLockedBalance;
            $balance->save();
            
            // Remove lock from cache
            \Cache::forget("order_lock:{$lockId}");
            
            return (object)[
                'success' => true,
                'amountReleased' => $lockInfo['amount'],
                'currency' => $lockInfo['currency'],
            ];
        });
    }
}