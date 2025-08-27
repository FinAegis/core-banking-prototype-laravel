<?php

namespace App\Domain\Account\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class DepositAccountWorkflow extends Workflow
{
    public function execute(AccountUuid $uuid, Money $money): Generator
    {
        return yield ActivityStub::make(
            DepositAccountActivity::class,
            $uuid,
            $money
        );
    }
}
