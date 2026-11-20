<?php

declare(strict_types=1);

namespace App\Domain\Account\Workflows;

use Illuminate\Support\Facades\Log;
use Workflow\Exceptions\NonRetryableException;
use Workflow\Models\StoredWorkflow;

/**
 * Re-checks ledger.direct_funding_enabled when a deposit activity runs.
 *
 * DepositAccountWorkflow and AssetDepositWorkflow started on their own credit
 * an account without a payment rail (v1/v2 deposit, BIAN, admin and MCP
 * deposits). The routes check the flag when the workflow starts, but a queued
 * or retried workflow can run much later, so the activity checks it again and
 * fails the workflow for good while the flag is off. A deposit that runs as a
 * step of another workflow (the receiving side of an internal transfer, an
 * exchange settlement, the credit side of a currency conversion after its
 * debit) is not direct funding and is not affected.
 */
final class DirectFundingGuard
{
    /**
     * @param class-string $workflowClass the direct-funding workflow the activity belongs to
     *
     * @throws NonRetryableException while direct funding is off
     */
    public static function assertAllowed(StoredWorkflow $storedWorkflow, string $workflowClass): void
    {
        if ((bool) config('ledger.direct_funding_enabled', false)) {
            return;
        }

        if ($storedWorkflow->class !== $workflowClass || $storedWorkflow->parents()->exists()) {
            return;
        }

        Log::warning('Direct ledger funding is off; deposit workflow not credited', [
            'workflow_id'    => $storedWorkflow->id,
            'workflow_class' => $workflowClass,
        ]);

        throw new NonRetryableException('Direct ledger funding is disabled (LEDGER_DIRECT_FUNDING_ENABLED); the deposit was not credited.');
    }
}
