<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Workflows\Activities;

use App\Domain\Custodian\Services\CustodianRegistry;
use Exception;
use Workflow\Activity;

class VerifyCustodianTransferActivity extends Activity
{
    private const MAX_RETRIES = 30;

    private const RETRY_DELAY = 2; // seconds

    public function execute(string $transactionId, string $custodianName): bool
    {
        $registry = app(CustodianRegistry::class);
        $custodian = $registry->get($custodianName);

        $retries = 0;

        while ($retries < self::MAX_RETRIES) {
            $receipt = $custodian->getTransactionStatus($transactionId);

            if ($receipt->isCompleted()) {
                return true;
            }

            if ($receipt->isFailed()) {
                throw new Exception(
                    "Custodian transfer {$transactionId} failed: " .
                    ($receipt->metadata['error'] ?? 'Unknown error')
                );
            }

            // Transaction is still pending, wait and retry
            sleep(self::RETRY_DELAY);
            $retries++;
        }

        throw new Exception(
            "Custodian transfer {$transactionId} timed out after " .
            (self::MAX_RETRIES * self::RETRY_DELAY) . ' seconds'
        );
    }
}
