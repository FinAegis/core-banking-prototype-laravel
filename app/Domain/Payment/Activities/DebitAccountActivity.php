<?php

namespace App\Domain\Payment\Activities;

use App\Domain\Account\Models\Account;
use Exception;
use Workflow\Activity;

class DebitAccountActivity extends Activity
{
    public function execute(string $accountUuid, int $amount, string $currency): void
    {
        /** @var Account $account */
        /** @var Account|null $account */
        $account = Account::where('uuid', $accountUuid)->firstOrFail();

        // Since Account uses event sourcing, we should trigger a debit event
        // For now, we'll use a simple update but this should be refactored
        // to use proper event sourcing commands
        $balance = $account->balances()
            ->where('asset_code', $currency)
            ->firstOrFail();

        if ($balance->balance < $amount) {
            throw new Exception('Insufficient balance');
        }

        $balance->balance -= $amount;
        $balance->save();
    }
}
