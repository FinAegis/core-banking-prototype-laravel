<?php

namespace App\Domain\Account\Workflows;

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\DataObjects\Account;
use Illuminate\Support\Str;
use Workflow\Activity;

class CreateAccountActivity extends Activity
{
    /**
     * @param \App\Domain\Account\DataObjects\Account $account
     *
     * @return string
     */
    public function execute( Account $account ): string
    {
        $uuid = $account->getUuid() ?: Str::uuid()->toString();
        
        $ledger = app(LedgerAggregate::class);
        
        $accountWithUuid = $account->withUuid($uuid);
        
        $ledger->retrieve( $uuid )
               ->createAccount( $accountWithUuid )
               ->persist();

        return $uuid;
    }
}
