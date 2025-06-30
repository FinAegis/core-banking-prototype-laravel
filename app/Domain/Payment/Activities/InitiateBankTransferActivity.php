<?php

namespace App\Domain\Payment\Activities;

use App\Domain\Payment\DataObjects\BankWithdrawal;
use Workflow\Activity;
use Illuminate\Support\Str;

class InitiateBankTransferActivity extends Activity
{
    public function execute(string $transactionId, BankWithdrawal $withdrawal): string
    {
        // In a real implementation, this would integrate with a banking API
        // For now, we'll simulate the transfer initiation
        
        $transferId = 'transfer_' . Str::uuid()->toString();
        
        // Log the transfer initiation
        \Log::info('Bank transfer initiated', [
            'transfer_id' => $transferId,
            'transaction_id' => $transactionId,
            'account_uuid' => $withdrawal->getAccountUuid(),
            'amount' => $withdrawal->getAmount(),
            'currency' => $withdrawal->getCurrency(),
            'bank_name' => $withdrawal->getBankName(),
            'account_number' => substr($withdrawal->getAccountNumber(), -4), // Log only last 4 digits
        ]);
        
        // In production, this would:
        // 1. Call banking partner API
        // 2. Store transfer details in database
        // 3. Set up webhook for transfer status updates
        
        return $transferId;
    }
}