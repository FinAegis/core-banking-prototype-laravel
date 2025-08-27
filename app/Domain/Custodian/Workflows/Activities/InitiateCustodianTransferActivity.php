<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Workflows\Activities;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Exception;
use Workflow\Activity;

class InitiateCustodianTransferActivity extends Activity
{
    public function execute(
        string $fromAccount,
        string $toAccount,
        string $assetCode,
        Money $amount,
        string $custodianName,
        string $type, // 'incoming' or 'outgoing'
        ?string $reference = null
    ): string {
        $registry = app(CustodianRegistry::class);
        $custodian = $registry->get($custodianName);

        // Create transfer request
        $request = new TransferRequest(
            fromAccount: $fromAccount,
            toAccount: $toAccount,
            assetCode: $assetCode,
            amount: $amount,
            reference: $reference ?? 'CORE-' . time(),
            description: "Core Banking {$type} transfer",
            metadata: [
                'type'         => $type,
                'initiated_at' => now()->toISOString(),
            ]
        );

        // Initiate transfer with custodian
        $receipt = $custodian->initiateTransfer($request);

        if ($receipt->isFailed()) {
            throw new Exception('Custodian transfer failed: ' . ($receipt->metadata['error'] ?? 'Unknown error'));
        }

        return $receipt->id;
    }
}
