<?php

namespace App\Domain\Account\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class BalanceInquiryWorkflow extends Workflow
{
    public function execute(AccountUuid $uuid, ?string $requestedBy = null): Generator
    {
        return yield ActivityStub::make(
            BalanceInquiryActivity::class,
            $uuid,
            $requestedBy
        );
    }
}
