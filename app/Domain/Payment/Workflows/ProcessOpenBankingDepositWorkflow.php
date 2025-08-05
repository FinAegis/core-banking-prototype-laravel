<?php

declare(strict_types=1);

namespace App\Domain\Payment\Workflows;

use App\Domain\Payment\Activities\ProcessOpenBankingDepositActivity;
use App\Domain\Payment\DataObjects\OpenBankingDeposit;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ProcessOpenBankingDepositWorkflow extends Workflow
{
    private ActivityStub $activity;

    public function __construct()
    {
        $this->activity = ActivityStub::make(ProcessOpenBankingDepositActivity::class, CarbonInterval::minutes(5));
    }

    public function execute(OpenBankingDeposit $deposit): \Generator
    {
        Log::info('Starting OpenBanking deposit workflow', [
            'reference' => $deposit->reference,
            'amount'    => $deposit->amount,
            'bank'      => $deposit->bankName,
        ]);

        try {
            // Step 1: Validate account exists
            $account = yield $this->activity->validateAccount($deposit->accountUuid);

            // Step 2: Create transaction aggregate
            yield $this->activity->createTransaction($deposit);

            // Step 3: Process with bank (in demo mode, this is instant)
            $bankReference = yield $this->activity->processBankTransfer($deposit);

            // Step 4: Complete the transaction
            yield $this->activity->completeTransaction($deposit, $bankReference);

            // Step 5: Update account balance
            yield $this->activity->updateAccountBalance($deposit);

            Log::info('OpenBanking deposit workflow completed', [
                'reference'      => $deposit->reference,
                'bank_reference' => $bankReference,
            ]);

            return $bankReference;
        } catch (\Exception $e) {
            Log::error('OpenBanking deposit workflow failed', [
                'reference' => $deposit->reference,
                'error'     => $e->getMessage(),
            ]);

            // Attempt to reverse the transaction
            yield $this->activity->reverseTransaction($deposit);

            throw $e;
        }
    }
}
