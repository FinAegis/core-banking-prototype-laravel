<?php

namespace App\Domain\Account\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Payment\Workflows\TransferWorkflow;
use Generator;
use Throwable;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class BulkTransferWorkflow extends Workflow
{
    /**
     * Execute bulk transfers with compensation handling.
     *
     * @param  array  $transfers  - array of ['to' => AccountUuid, 'amount' => Money]
     *
     * @throws Throwable
     */
    public function execute(AccountUuid $from, array $transfers): Generator
    {
        $completedTransfers = [];

        try {
            foreach ($transfers as $transfer) {
                $transferResult = yield ChildWorkflowStub::make(
                    TransferWorkflow::class,
                    $from,
                    $transfer['to'],
                    $transfer['amount']
                );

                $completedTransfers[] = $transfer;

                // Add compensation for each completed transfer
                $this->addCompensation(
                    function () use ($from, $transfer) {
                        return ChildWorkflowStub::make(
                            TransferWorkflow::class,
                            $transfer['to'],
                            $from,
                            $transfer['amount']
                        );
                    }
                );
            }

            return $completedTransfers;
        } catch (Throwable $th) {
            // Compensate all completed transfers
            yield from $this->compensate();
            throw $th;
        }
    }
}
