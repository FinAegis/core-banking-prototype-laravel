<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Multi-Custodian Transfer Service.
 *
 * Handles transfers across multiple custodians with intelligent routing,
 * load balancing, and failure recovery.
 */
class MultiCustodianTransferService
{
    private CustodianRegistry $registry;

    private array $routingStrategy;

    /**
     * Transfer routing strategies.
     */
    public const ROUTE_SAME_CUSTODIAN = 'same_custodian';

    public const ROUTE_FASTEST = 'fastest';

    public const ROUTE_CHEAPEST = 'cheapest';

    public const ROUTE_BALANCED = 'balanced';

    public function __construct(CustodianRegistry $registry)
    {
        $this->registry = $registry;
        $this->routingStrategy = config(
            'custodians.routing_strategy',
            [
                'primary'  => self::ROUTE_SAME_CUSTODIAN,
                'fallback' => self::ROUTE_FASTEST,
            ]
        );
    }

    /**
     * Execute a multi-bank transfer with optimal routing.
     */
    public function transfer(
        Account $fromAccount,
        Account $toAccount,
        Money $amount,
        string $assetCode,
        ?string $reference = null,
        ?string $description = null
    ): TransactionReceipt {
        Log::info(
            'Initiating multi-custodian transfer',
            [
                'from_account' => $fromAccount->uuid,
                'to_account'   => $toAccount->uuid,
                'amount'       => $amount->getAmount(),
                'asset'        => $assetCode,
            ]
        );

        // Find optimal route
        $route = $this->findOptimalRoute($fromAccount, $toAccount, $amount, $assetCode);

        if (! $route) {
            throw new RuntimeException('No valid transfer route found');
        }

        // Execute transfer based on route type
        return match ($route['type']) {
            'internal' => $this->executeInternalTransfer($route, $amount, $assetCode, $reference, $description),
            'external' => $this->executeExternalTransfer($route, $amount, $assetCode, $reference, $description),
            'bridge'   => $this->executeBridgeTransfer($route, $amount, $assetCode, $reference, $description),
            default    => throw new RuntimeException("Unknown route type: {$route['type']}"),
        };
    }

    /**
     * Find the optimal transfer route between accounts.
     */
    private function findOptimalRoute(
        Account $fromAccount,
        Account $toAccount,
        Money $amount,
        string $assetCode
    ): ?array {
        // Get custodian accounts for both parties
        $fromCustodians = $fromAccount->custodianAccounts()
            ->active()
            ->with('account')
            ->get();

        $toCustodians = $toAccount->custodianAccounts()
            ->active()
            ->with('account')
            ->get();

        if ($fromCustodians->isEmpty() || $toCustodians->isEmpty()) {
            Log::error(
                'No active custodian accounts found',
                [
                    'from_count' => $fromCustodians->count(),
                    'to_count'   => $toCustodians->count(),
                ]
            );

            return null;
        }

        // Check for same custodian (internal transfer)
        $sameCustodian = $this->findSameCustodianRoute($fromCustodians, $toCustodians);
        if ($sameCustodian && $this->routingStrategy['primary'] === self::ROUTE_SAME_CUSTODIAN) {
            return [
                'type'      => 'internal',
                'custodian' => $sameCustodian['custodian_name'],
                'from'      => $sameCustodian['from'],
                'to'        => $sameCustodian['to'],
            ];
        }

        // Check for direct external transfer capability
        $directRoute = $this->findDirectExternalRoute($fromCustodians, $toCustodians, $assetCode);
        if ($directRoute) {
            return [
                'type'           => 'external',
                'from_custodian' => $directRoute['from_custodian'],
                'to_custodian'   => $directRoute['to_custodian'],
                'from'           => $directRoute['from'],
                'to'             => $directRoute['to'],
            ];
        }

        // Find bridge route through intermediate custodian
        $bridgeRoute = $this->findBridgeRoute($fromCustodians, $toCustodians, $assetCode);
        if ($bridgeRoute) {
            return [
                'type'  => 'bridge',
                'route' => $bridgeRoute,
            ];
        }

        return null;
    }

    /**
     * Find accounts on the same custodian for internal transfer.
     */
    private function findSameCustodianRoute($fromCustodians, $toCustodians): ?array
    {
        foreach ($fromCustodians as $from) {
            $to = $toCustodians->firstWhere('custodian_name', $from->custodian_name);
            if ($to) {
                return [
                    'custodian_name' => $from->custodian_name,
                    'from'           => $from,
                    'to'             => $to,
                ];
            }
        }

        return null;
    }

    /**
     * Find direct external transfer route between custodians.
     */
    private function findDirectExternalRoute($fromCustodians, $toCustodians, string $assetCode): ?array
    {
        foreach ($fromCustodians as $from) {
            foreach ($toCustodians as $to) {
                if ($from->custodian_name === $to->custodian_name) {
                    continue;
                }

                // Check if custodian supports external transfers to destination
                $connector = $this->registry->getConnector($from->custodian_name);
                if ($this->canTransferTo($connector, $to->custodian_name, $assetCode)) {
                    return [
                        'from_custodian' => $from->custodian_name,
                        'to_custodian'   => $to->custodian_name,
                        'from'           => $from,
                        'to'             => $to,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Find bridge route through intermediate custodian.
     */
    private function findBridgeRoute($fromCustodians, $toCustodians, string $assetCode): ?array
    {
        // Get all available custodians
        $availableCustodians = $this->registry->listCustodians();

        foreach ($availableCustodians as $bridgeCustodian) {
            // Check if bridge can receive from source and send to destination
            $canReceive = false;
            $canSend = false;
            $fromAccount = null;
            $toAccount = null;

            foreach ($fromCustodians as $from) {
                $connector = $this->registry->getConnector($from->custodian_name);
                if ($this->canTransferTo($connector, $bridgeCustodian['id'], $assetCode)) {
                    $canReceive = true;
                    $fromAccount = $from;
                    break;
                }
            }

            if (! $canReceive) {
                continue;
            }

            foreach ($toCustodians as $to) {
                $connector = $this->registry->getConnector($bridgeCustodian['id']);
                if ($this->canTransferTo($connector, $to->custodian_name, $assetCode)) {
                    $canSend = true;
                    $toAccount = $to;
                    break;
                }
            }

            if ($canSend) {
                return [
                    'from'   => $fromAccount,
                    'bridge' => $bridgeCustodian['id'],
                    'to'     => $toAccount,
                ];
            }
        }

        return null;
    }

    /**
     * Check if a custodian can transfer to another custodian.
     */
    private function canTransferTo(ICustodianConnector $connector, string $toCustodian, string $assetCode): bool
    {
        try {
            // Check if connector supports external transfers
            $info = $connector->getInfo();
            if (! $info['features']['external_transfers'] ?? false) {
                return false;
            }

            // Check supported destination custodians
            $supportedDestinations = $info['supported_destinations'] ?? [];
            if (! in_array($toCustodian, $supportedDestinations) && ! in_array('*', $supportedDestinations)) {
                return false;
            }

            // Check if asset is supported
            $supportedAssets = $info['supported_assets'] ?? [];
            if (! in_array($assetCode, $supportedAssets) && ! in_array('*', $supportedAssets)) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::warning(
                'Failed to check transfer capability',
                [
                    'from'  => $connector->getName(),
                    'to'    => $toCustodian,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Execute internal transfer within same custodian.
     */
    private function executeInternalTransfer(
        array $route,
        Money $amount,
        string $assetCode,
        ?string $reference,
        ?string $description
    ): TransactionReceipt {
        Log::info(
            'Executing internal transfer',
            [
                'custodian' => $route['custodian'],
                'from'      => $route['from']->custodian_account_id,
                'to'        => $route['to']->custodian_account_id,
            ]
        );

        $connector = $this->registry->getConnector($route['custodian']);

        $request = new TransferRequest(
            fromAccount: $route['from']->custodian_account_id,
            toAccount: $route['to']->custodian_account_id,
            amount: $amount,
            assetCode: $assetCode,
            reference: $reference,
            description: $description
        );

        $receipt = $connector->initiateTransfer($request);

        // Record transfer in database
        $this->recordTransfer($route['from'], $route['to'], $receipt, 'internal');

        return $receipt;
    }

    /**
     * Execute external transfer between custodians.
     */
    private function executeExternalTransfer(
        array $route,
        Money $amount,
        string $assetCode,
        ?string $reference,
        ?string $description
    ): TransactionReceipt {
        Log::info(
            'Executing external transfer',
            [
                'from_custodian' => $route['from_custodian'],
                'to_custodian'   => $route['to_custodian'],
                'from'           => $route['from']->custodian_account_id,
                'to'             => $route['to']->custodian_account_id,
            ]
        );

        $connector = $this->registry->getConnector($route['from_custodian']);

        // Create external transfer request with destination custodian info
        $request = new TransferRequest(
            fromAccount: $route['from']->custodian_account_id,
            toAccount: $route['to']->custodian_account_id,
            amount: $amount,
            assetCode: $assetCode,
            reference: $reference,
            description: $description,
            metadata: [
                'destination_custodian' => $route['to_custodian'],
                'transfer_type'         => 'external',
            ]
        );

        $receipt = $connector->initiateTransfer($request);

        // Record transfer in database
        $this->recordTransfer($route['from'], $route['to'], $receipt, 'external');

        return $receipt;
    }

    /**
     * Execute bridge transfer through intermediate custodian.
     */
    private function executeBridgeTransfer(
        array $route,
        Money $amount,
        string $assetCode,
        ?string $reference,
        ?string $description
    ): TransactionReceipt {
        Log::info(
            'Executing bridge transfer',
            [
                'from'   => $route['route']['from']->custodian_account_id,
                'bridge' => $route['route']['bridge'],
                'to'     => $route['route']['to']->custodian_account_id,
            ]
        );

        DB::beginTransaction();

        try {
            // Step 1: Transfer from source to bridge
            $firstLegConnector = $this->registry->getConnector($route['route']['from']->custodian_name);

            $firstLegRequest = new TransferRequest(
                fromAccount: $route['route']['from']->custodian_account_id,
                toAccount: $this->getBridgeAccountId($route['route']['bridge'], $assetCode),
                amount: $amount,
                assetCode: $assetCode,
                reference: $reference . '_LEG1',
                description: 'Bridge transfer leg 1: ' . $description,
                metadata: [
                    'bridge_transfer'   => true,
                    'final_destination' => $route['route']['to']->custodian_account_id,
                ]
            );

            $firstLegReceipt = $firstLegConnector->initiateTransfer($firstLegRequest);

            // Wait for first leg to complete
            $this->waitForTransferCompletion($firstLegConnector, $firstLegReceipt->id);

            // Step 2: Transfer from bridge to destination
            $secondLegConnector = $this->registry->getConnector($route['route']['bridge']);

            $secondLegRequest = new TransferRequest(
                fromAccount: $this->getBridgeAccountId($route['route']['bridge'], $assetCode),
                toAccount: $route['route']['to']->custodian_account_id,
                amount: $amount,
                assetCode: $assetCode,
                reference: $reference . '_LEG2',
                description: 'Bridge transfer leg 2: ' . $description,
                metadata: [
                    'bridge_transfer' => true,
                    'original_source' => $route['route']['from']->custodian_account_id,
                ]
            );

            $secondLegReceipt = $secondLegConnector->initiateTransfer($secondLegRequest);

            DB::commit();

            // Create composite receipt
            $bridgeReceipt = new TransactionReceipt(
                id: 'BRIDGE_' . $firstLegReceipt->id . '_' . $secondLegReceipt->id,
                status: 'pending',
                amount: $amount->getAmount(),
                assetCode: $assetCode,
                reference: $reference,
                createdAt: now(),
                metadata: [
                    'type' => 'bridge',
                    'leg1' => $firstLegReceipt->toArray(),
                    'leg2' => $secondLegReceipt->toArray(),
                ]
            );

            // Record transfer in database
            $this->recordTransfer($route['route']['from'], $route['route']['to'], $bridgeReceipt, 'bridge');

            return $bridgeReceipt;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error(
                'Bridge transfer failed',
                [
                    'error' => $e->getMessage(),
                    'route' => $route,
                ]
            );

            throw new RuntimeException('Bridge transfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Wait for transfer completion with timeout.
     */
    private function waitForTransferCompletion(
        ICustodianConnector $connector,
        string $transferId,
        int $maxWaitSeconds = 30
    ): void {
        $startTime = time();

        while (time() - $startTime < $maxWaitSeconds) {
            $status = $connector->getTransactionStatus($transferId);

            if ($status->isCompleted()) {
                return;
            }

            if ($status->isFailed()) {
                throw new RuntimeException("Transfer {$transferId} failed: " . $status->failureReason);
            }

            // Wait before checking again
            sleep(1);
        }

        throw new RuntimeException("Transfer {$transferId} timed out after {$maxWaitSeconds} seconds");
    }

    /**
     * Get bridge account ID for a custodian.
     */
    private function getBridgeAccountId(string $custodianId, string $assetCode): string
    {
        // In production, this would retrieve the actual bridge account ID
        // For now, return a placeholder
        return "BRIDGE_{$custodianId}_{$assetCode}";
    }

    /**
     * Record transfer in database for audit trail.
     */
    private function recordTransfer(
        CustodianAccount $from,
        CustodianAccount $to,
        TransactionReceipt $receipt,
        string $type
    ): void {
        DB::table('custodian_transfers')->insert(
            [
                'id'                        => $receipt->id,
                'from_account_uuid'         => $from->account_uuid,
                'to_account_uuid'           => $to->account_uuid,
                'from_custodian_account_id' => $from->id,
                'to_custodian_account_id'   => $to->id,
                'amount'                    => $receipt->amount,
                'asset_code'                => $receipt->assetCode,
                'transfer_type'             => $type,
                'status'                    => $receipt->status,
                'reference'                 => $receipt->reference,
                'metadata'                  => json_encode($receipt->metadata),
                'created_at'                => now(),
                'updated_at'                => now(),
            ]
        );
    }

    /**
     * Get transfer statistics.
     */
    public function getTransferStatistics(): array
    {
        // Use database-agnostic approach for better compatibility
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite-compatible query
            $stats = DB::table('custodian_transfers')
                ->selectRaw(
                    '
                    COUNT(*) as total_transfers,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN transfer_type = "internal" THEN 1 ELSE 0 END) as internal,
                    SUM(CASE WHEN transfer_type = "external" THEN 1 ELSE 0 END) as external,
                    SUM(CASE WHEN transfer_type = "bridge" THEN 1 ELSE 0 END) as bridge,
                    AVG(CASE WHEN status = "completed" AND completed_at IS NOT NULL 
                        THEN (JULIANDAY(completed_at) - JULIANDAY(created_at)) * 86400
                        ELSE NULL END) as avg_completion_seconds
                '
                )
                ->first();
        } else {
            // MySQL/MariaDB query
            $stats = DB::table('custodian_transfers')
                ->selectRaw(
                    '
                    COUNT(*) as total_transfers,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN transfer_type = "internal" THEN 1 ELSE 0 END) as internal,
                    SUM(CASE WHEN transfer_type = "external" THEN 1 ELSE 0 END) as external,
                    SUM(CASE WHEN transfer_type = "bridge" THEN 1 ELSE 0 END) as bridge,
                    AVG(CASE WHEN status = "completed" AND completed_at IS NOT NULL 
                        THEN TIMESTAMPDIFF(SECOND, created_at, completed_at)
                        ELSE NULL END) as avg_completion_seconds
                '
                )
                ->first();
        }

        return [
            'total'     => (int) ($stats->total_transfers ?? 0),
            'completed' => (int) ($stats->completed ?? 0),
            'failed'    => (int) ($stats->failed ?? 0),
            'pending'   => (int) ($stats->pending ?? 0),
            'by_type'   => [
                'internal' => (int) ($stats->internal ?? 0),
                'external' => (int) ($stats->external ?? 0),
                'bridge'   => (int) ($stats->bridge ?? 0),
            ],
            'avg_completion_seconds' => round((float) ($stats->avg_completion_seconds ?? 0), 2),
            'success_rate'           => $stats->total_transfers > 0
                ? round(((int) $stats->completed / (int) $stats->total_transfers) * 100, 2)
                : 0,
        ];
    }
}
