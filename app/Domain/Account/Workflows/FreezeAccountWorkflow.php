<?php

namespace App\Domain\Account\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class FreezeAccountWorkflow extends Workflow
{
    public function execute(AccountUuid $uuid, string $reason, ?string $authorizedBy = null): Generator
    {
        return yield ActivityStub::make(
            FreezeAccountActivity::class,
            $uuid,
            $reason,
            $authorizedBy
        );
    }
}
