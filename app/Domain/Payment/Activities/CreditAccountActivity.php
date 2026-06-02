<?php

declare(strict_types=1);

namespace App\Domain\Payment\Activities;

use App\Domain\Account\Services\AccountCreditService;
use Workflow\Activity;

class CreditAccountActivity extends Activity
{
    public function execute(string $accountUuid, int $amount, string $currency): void
    {
        app(AccountCreditService::class)->credit($accountUuid, $amount, $currency);
    }
}
