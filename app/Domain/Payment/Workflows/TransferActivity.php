<?php

declare(strict_types=1);

namespace App\Domain\Payment\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Exceptions\NotEnoughFunds;
use App\Domain\Account\Workflows\DepositAccountActivity;
use App\Domain\Account\Workflows\WithdrawAccountActivity;
use App\Domain\Payment\Services\TransferService;
use Exception;
use Generator;
use Workflow\Activity;
use Workflow\ActivityStub;

class TransferActivity extends Activity
{
    public function __construct(
        private readonly TransferService $transferService
    ) {
    }

    /**
     * Execute a transfer between two accounts.
     *
     * @throws NotEnoughFunds
     * @throws Exception
     */
    public function execute(AccountUuid $from, AccountUuid $to, Money $money): Generator
    {
        // Validate the transfer can be performed
        $this->transferService->validateTransfer($from, $to, $money);

        // Perform the withdrawal from source account
        yield ActivityStub::make(WithdrawAccountActivity::class, $from, $money);

        // Perform the deposit to destination account
        yield ActivityStub::make(DepositAccountActivity::class, $to, $money);

        // Record the transfer
        $this->transferService->recordTransfer($from, $to, $money);
    }
}
