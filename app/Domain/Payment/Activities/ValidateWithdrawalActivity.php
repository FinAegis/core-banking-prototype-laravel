<?php

namespace App\Domain\Payment\Activities;

use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Models\Account;
use Workflow\Activity;

class ValidateWithdrawalActivity extends Activity
{
    public function execute(BankWithdrawal $withdrawal): array
    {
        $account = Account::where('uuid', $withdrawal->getAccountUuid())->first();
        
        if (!$account) {
            return [
                'valid' => false,
                'message' => 'Account not found',
            ];
        }
        
        // Check balance
        $balance = $account->balances()
            ->where('asset_code', $withdrawal->getCurrency())
            ->first();
            
        if (!$balance || $balance->balance < $withdrawal->getAmount()) {
            return [
                'valid' => false,
                'message' => 'Insufficient balance',
            ];
        }
        
        // Check minimum withdrawal amount (e.g., $10)
        if ($withdrawal->getAmount() < 1000) { // 1000 cents = $10
            return [
                'valid' => false,
                'message' => 'Minimum withdrawal amount is $10',
            ];
        }
        
        // Check maximum withdrawal amount (e.g., $10,000 per transaction)
        if ($withdrawal->getAmount() > 1000000) { // 1000000 cents = $10,000
            return [
                'valid' => false,
                'message' => 'Maximum withdrawal amount is $10,000 per transaction',
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Withdrawal validated successfully',
        ];
    }
}