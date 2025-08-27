<?php

declare(strict_types=1);

namespace App\Domain\Payment\Workflows;

use App\Domain\Payment\Activities\ProcessOpenBankingDepositActivity;
use App\Domain\Payment\DataObjects\OpenBankingDeposit;
use Exception;
use Generator;
use Illuminate\Support\Facades\Log;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ProcessOpenBankingDepositWorkflow extends Workflow
{
    public function execute(OpenBankingDeposit $deposit): Generator
    {
        Log::info('Starting OpenBanking deposit workflow', [
            'reference' => $deposit->reference,
            'amount'    => $deposit->amount,
            'bank'      => $deposit->bankName,
        ]);

        try {
            // Step 1: Validate account exists
            $account = yield ActivityStub::make(ProcessOpenBankingDepositActivity::class)
                ->validateAccount($deposit->accountUuid);

            // Step 2: Create transaction aggregate
            yield ActivityStub::make(ProcessOpenBankingDepositActivity::class)
                ->createTransaction($deposit);

            // Step 3: Process with bank (in demo mode, this is instant)
            $bankReference = yield ActivityStub::make(ProcessOpenBankingDepositActivity::class)
                ->processBankTransfer($deposit);

            // Step 4: Complete the transaction
            yield ActivityStub::make(ProcessOpenBankingDepositActivity::class)
                ->completeTransaction($deposit, $bankReference);

            // Step 5: Update account balance
            yield ActivityStub::make(ProcessOpenBankingDepositActivity::class)
                ->updateAccountBalance($deposit);

            Log::info('OpenBanking deposit workflow completed', [
                'reference'      => $deposit->reference,
                'bank_reference' => $bankReference,
            ]);

            return $bankReference;
        } catch (Exception $e) {
            Log::error('OpenBanking deposit workflow failed', [
                'reference' => $deposit->reference,
                'error'     => $e->getMessage(),
            ]);

            // Attempt to reverse the transaction
            yield ActivityStub::make(ProcessOpenBankingDepositActivity::class)
                ->reverseTransaction($deposit);

            throw $e;
        }
    }
}
