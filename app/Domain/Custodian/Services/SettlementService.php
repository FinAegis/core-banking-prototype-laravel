<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Account\DataObjects\Money;
use App\Models\CustodianAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Settlement Service
 * 
 * Manages inter-bank settlement processes including:
 * - Net settlement calculation
 * - Settlement batch creation
 * - Settlement execution and reconciliation
 */
class SettlementService
{
    private CustodianRegistry $registry;
    private array $settlementConfig;
    
    /**
     * Settlement types
     */
    public const SETTLEMENT_REALTIME = 'realtime';
    public const SETTLEMENT_BATCH = 'batch';
    public const SETTLEMENT_NET = 'net';
    
    /**
     * Settlement statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    
    public function __construct(CustodianRegistry $registry)
    {
        $this->registry = $registry;
        $this->settlementConfig = config('custodians.settlement', [
            'type' => self::SETTLEMENT_NET,
            'batch_interval_minutes' => 60,
            'min_settlement_amount' => 10000, // $100.00
        ]);
    }
    
    /**
     * Process pending settlements
     */
    public function processPendingSettlements(): array
    {
        Log::info('Processing pending settlements');
        
        $settlementType = $this->settlementConfig['type'];
        
        return match ($settlementType) {
            self::SETTLEMENT_REALTIME => $this->processRealtimeSettlements(),
            self::SETTLEMENT_BATCH => $this->processBatchSettlements(),
            self::SETTLEMENT_NET => $this->processNetSettlements(),
            default => throw new \RuntimeException("Unknown settlement type: {$settlementType}"),
        };
    }
    
    /**
     * Process realtime settlements (immediate settlement)
     */
    private function processRealtimeSettlements(): array
    {
        $pendingTransfers = DB::table('custodian_transfers')
            ->where('status', 'completed')
            ->whereNull('settlement_id')
            ->where('transfer_type', '!=', 'internal')
            ->get();
        
        $results = [
            'processed' => 0,
            'failed' => 0,
            'total_amount' => 0,
        ];
        
        foreach ($pendingTransfers as $transfer) {
            try {
                $this->settleTransferImmediately($transfer);
                $results['processed']++;
                $results['total_amount'] += $transfer->amount;
            } catch (\Exception $e) {
                Log::error('Failed to settle transfer', [
                    'transfer_id' => $transfer->id,
                    'error' => $e->getMessage(),
                ]);
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Process batch settlements
     */
    private function processBatchSettlements(): array
    {
        $cutoffTime = now()->subMinutes($this->settlementConfig['batch_interval_minutes']);
        
        // Get transfers ready for batch settlement
        $transfers = DB::table('custodian_transfers')
            ->where('status', 'completed')
            ->whereNull('settlement_id')
            ->where('transfer_type', '!=', 'internal')
            ->where('completed_at', '<=', $cutoffTime)
            ->get();
        
        if ($transfers->isEmpty()) {
            return [
                'batches' => 0,
                'transfers' => 0,
                'total_amount' => 0,
            ];
        }
        
        // Group by custodian pairs and asset
        $batches = $transfers->groupBy(function ($transfer) {
            $from = DB::table('custodian_accounts')->find($transfer->from_custodian_account_id);
            $to = DB::table('custodian_accounts')->find($transfer->to_custodian_account_id);
            return "{$from->custodian_name}:{$to->custodian_name}:{$transfer->asset_code}";
        });
        
        $results = [
            'batches' => 0,
            'transfers' => 0,
            'total_amount' => 0,
        ];
        
        foreach ($batches as $key => $batchTransfers) {
            [$fromCustodian, $toCustodian, $assetCode] = explode(':', $key);
            
            $batchId = $this->createSettlementBatch(
                $fromCustodian,
                $toCustodian,
                $assetCode,
                $batchTransfers
            );
            
            if ($this->executeSettlementBatch($batchId)) {
                $results['batches']++;
                $results['transfers'] += $batchTransfers->count();
                $results['total_amount'] += $batchTransfers->sum('amount');
            }
        }
        
        return $results;
    }
    
    /**
     * Process net settlements (offset debits and credits)
     */
    private function processNetSettlements(): array
    {
        Log::info('Processing net settlements');
        
        // Calculate net positions between custodians
        $netPositions = $this->calculateNetPositions();
        
        $results = [
            'settlements' => 0,
            'total_gross' => 0,
            'total_net' => 0,
            'savings' => 0,
        ];
        
        foreach ($netPositions as $position) {
            if (abs($position->net_amount) < $this->settlementConfig['min_settlement_amount']) {
                continue;
            }
            
            try {
                $settlementId = $this->createNetSettlement($position);
                if ($this->executeNetSettlement($settlementId)) {
                    $results['settlements']++;
                    $results['total_net'] += abs($position->net_amount);
                }
                
                $results['total_gross'] += $position->gross_amount;
            } catch (\Exception $e) {
                Log::error('Failed to process net settlement', [
                    'position' => $position,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $results['savings'] = $results['total_gross'] - $results['total_net'];
        $results['savings_percentage'] = $results['total_gross'] > 0
            ? round(($results['savings'] / $results['total_gross']) * 100, 2)
            : 0;
        
        return $results;
    }
    
    /**
     * Calculate net positions between custodians
     */
    private function calculateNetPositions(): Collection
    {
        $positions = DB::table('custodian_transfers as ct')
            ->join('custodian_accounts as from_ca', 'ct.from_custodian_account_id', '=', 'from_ca.id')
            ->join('custodian_accounts as to_ca', 'ct.to_custodian_account_id', '=', 'to_ca.id')
            ->select(
                'from_ca.custodian_name as from_custodian',
                'to_ca.custodian_name as to_custodian',
                'ct.asset_code',
                DB::raw('SUM(ct.amount) as total_amount'),
                DB::raw('COUNT(*) as transfer_count')
            )
            ->where('ct.status', 'completed')
            ->whereNull('ct.settlement_id')
            ->where('ct.transfer_type', '!=', 'internal')
            ->groupBy('from_ca.custodian_name', 'to_ca.custodian_name', 'ct.asset_code')
            ->get();
        
        // Calculate net positions
        $netPositions = collect();
        
        foreach ($positions as $position) {
            // Find reverse flow
            $reverseFlow = $positions->first(function ($p) use ($position) {
                return $p->from_custodian === $position->to_custodian
                    && $p->to_custodian === $position->from_custodian
                    && $p->asset_code === $position->asset_code;
            });
            
            $reverseAmount = $reverseFlow ? $reverseFlow->total_amount : 0;
            $netAmount = $position->total_amount - $reverseAmount;
            
            // Only add if net amount is positive (avoid duplicates)
            if ($netAmount > 0) {
                $netPositions->push((object)[
                    'from_custodian' => $position->from_custodian,
                    'to_custodian' => $position->to_custodian,
                    'asset_code' => $position->asset_code,
                    'gross_amount' => $position->total_amount + $reverseAmount,
                    'net_amount' => $netAmount,
                    'transfer_count' => $position->transfer_count + ($reverseFlow ? $reverseFlow->transfer_count : 0),
                ]);
            }
        }
        
        return $netPositions;
    }
    
    /**
     * Settle a transfer immediately
     */
    private function settleTransferImmediately($transfer): void
    {
        $settlementId = $this->createSettlement([
            'type' => self::SETTLEMENT_REALTIME,
            'status' => self::STATUS_PROCESSING,
            'transfers' => [$transfer->id],
            'amount' => $transfer->amount,
            'asset_code' => $transfer->asset_code,
        ]);
        
        // Execute actual settlement between custodians
        $this->executeSettlement($settlementId);
        
        // Mark transfer as settled
        DB::table('custodian_transfers')
            ->where('id', $transfer->id)
            ->update(['settlement_id' => $settlementId]);
    }
    
    /**
     * Create a settlement batch
     */
    private function createSettlementBatch(
        string $fromCustodian,
        string $toCustodian,
        string $assetCode,
        Collection $transfers
    ): string {
        $settlementId = 'BATCH_' . uniqid();
        $totalAmount = $transfers->sum('amount');
        
        DB::table('settlements')->insert([
            'id' => $settlementId,
            'type' => self::SETTLEMENT_BATCH,
            'from_custodian' => $fromCustodian,
            'to_custodian' => $toCustodian,
            'asset_code' => $assetCode,
            'gross_amount' => $totalAmount,
            'net_amount' => $totalAmount,
            'transfer_count' => $transfers->count(),
            'status' => self::STATUS_PENDING,
            'metadata' => json_encode([
                'transfer_ids' => $transfers->pluck('id')->toArray(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Link transfers to settlement
        DB::table('custodian_transfers')
            ->whereIn('id', $transfers->pluck('id'))
            ->update(['settlement_id' => $settlementId]);
        
        return $settlementId;
    }
    
    /**
     * Create a net settlement
     */
    private function createNetSettlement($position): string
    {
        $settlementId = 'NET_' . uniqid();
        
        // Get all unsettled transfers for this position
        $transfers = DB::table('custodian_transfers as ct')
            ->join('custodian_accounts as from_ca', 'ct.from_custodian_account_id', '=', 'from_ca.id')
            ->join('custodian_accounts as to_ca', 'ct.to_custodian_account_id', '=', 'to_ca.id')
            ->select('ct.id')
            ->where('ct.status', 'completed')
            ->whereNull('ct.settlement_id')
            ->where('ct.asset_code', $position->asset_code)
            ->where(function ($query) use ($position) {
                $query->where(function ($q) use ($position) {
                    $q->where('from_ca.custodian_name', $position->from_custodian)
                      ->where('to_ca.custodian_name', $position->to_custodian);
                })->orWhere(function ($q) use ($position) {
                    $q->where('from_ca.custodian_name', $position->to_custodian)
                      ->where('to_ca.custodian_name', $position->from_custodian);
                });
            })
            ->pluck('id');
        
        DB::table('settlements')->insert([
            'id' => $settlementId,
            'type' => self::SETTLEMENT_NET,
            'from_custodian' => $position->from_custodian,
            'to_custodian' => $position->to_custodian,
            'asset_code' => $position->asset_code,
            'gross_amount' => $position->gross_amount,
            'net_amount' => $position->net_amount,
            'transfer_count' => $position->transfer_count,
            'status' => self::STATUS_PENDING,
            'metadata' => json_encode([
                'transfer_ids' => $transfers->toArray(),
                'savings' => $position->gross_amount - $position->net_amount,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Link transfers to settlement
        DB::table('custodian_transfers')
            ->whereIn('id', $transfers)
            ->update(['settlement_id' => $settlementId]);
        
        return $settlementId;
    }
    
    /**
     * Execute a settlement batch
     */
    private function executeSettlementBatch(string $settlementId): bool
    {
        return $this->executeSettlement($settlementId);
    }
    
    /**
     * Execute a net settlement
     */
    private function executeNetSettlement(string $settlementId): bool
    {
        return $this->executeSettlement($settlementId);
    }
    
    /**
     * Execute settlement between custodians
     */
    private function executeSettlement(string $settlementId): bool
    {
        $settlement = DB::table('settlements')->where('id', $settlementId)->first();
        
        if (!$settlement) {
            throw new \RuntimeException("Settlement not found: {$settlementId}");
        }
        
        try {
            // Update status to processing
            DB::table('settlements')
                ->where('id', $settlementId)
                ->update([
                    'status' => self::STATUS_PROCESSING,
                    'processed_at' => now(),
                ]);
            
            // Get custodian connectors
            $fromConnector = $this->registry->getConnector($settlement->from_custodian);
            $toConnector = $this->registry->getConnector($settlement->to_custodian);
            
            // Execute settlement transfer
            // In production, this would use specific settlement accounts and protocols
            $settlementRequest = new \App\Domain\Custodian\ValueObjects\TransferRequest(
                fromAccount: $this->getSettlementAccount($settlement->from_custodian, $settlement->asset_code),
                toAccount: $this->getSettlementAccount($settlement->to_custodian, $settlement->asset_code),
                amount: new Money($settlement->net_amount),
                assetCode: $settlement->asset_code,
                reference: $settlementId,
                description: "Settlement: {$settlement->type}",
                metadata: [
                    'settlement_type' => $settlement->type,
                    'transfer_count' => $settlement->transfer_count,
                ]
            );
            
            $receipt = $fromConnector->initiateTransfer($settlementRequest);
            
            // Update settlement status
            DB::table('settlements')
                ->where('id', $settlementId)
                ->update([
                    'status' => self::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'external_reference' => $receipt->id,
                ]);
            
            Log::info('Settlement executed successfully', [
                'settlement_id' => $settlementId,
                'amount' => $settlement->net_amount,
                'asset' => $settlement->asset_code,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Settlement execution failed', [
                'settlement_id' => $settlementId,
                'error' => $e->getMessage(),
            ]);
            
            DB::table('settlements')
                ->where('id', $settlementId)
                ->update([
                    'status' => self::STATUS_FAILED,
                    'failure_reason' => $e->getMessage(),
                    'failed_at' => now(),
                ]);
            
            return false;
        }
    }
    
    /**
     * Create settlement record
     */
    private function createSettlement(array $data): string
    {
        $settlementId = $data['type'] . '_' . uniqid();
        
        DB::table('settlements')->insert([
            'id' => $settlementId,
            'type' => $data['type'],
            'status' => $data['status'],
            'asset_code' => $data['asset_code'],
            'gross_amount' => $data['amount'],
            'net_amount' => $data['amount'],
            'transfer_count' => count($data['transfers']),
            'metadata' => json_encode([
                'transfer_ids' => $data['transfers'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $settlementId;
    }
    
    /**
     * Get settlement account for a custodian
     */
    private function getSettlementAccount(string $custodianId, string $assetCode): string
    {
        // In production, this would retrieve actual settlement account IDs
        // from configuration or database
        return "SETTLEMENT_{$custodianId}_{$assetCode}";
    }
    
    /**
     * Get settlement statistics
     */
    public function getSettlementStatistics(): array
    {
        $stats = DB::table('settlements')
            ->selectRaw('
                COUNT(*) as total_settlements,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(gross_amount) as total_gross,
                SUM(net_amount) as total_net,
                SUM(transfer_count) as total_transfers,
                AVG(CASE WHEN status = "completed" 
                    THEN TIMESTAMPDIFF(SECOND, created_at, completed_at)
                    ELSE NULL END) as avg_settlement_seconds
            ')
            ->first();
        
        $byType = DB::table('settlements')
            ->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(net_amount) as amount'))
            ->groupBy('type')
            ->get();
        
        return [
            'total' => (int) ($stats->total_settlements ?? 0),
            'completed' => (int) ($stats->completed ?? 0),
            'failed' => (int) ($stats->failed ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
            'total_gross_amount' => (int) ($stats->total_gross ?? 0),
            'total_net_amount' => (int) ($stats->total_net ?? 0),
            'total_savings' => (int) (($stats->total_gross ?? 0) - ($stats->total_net ?? 0)),
            'savings_percentage' => $stats->total_gross > 0
                ? round(((float) ($stats->total_gross - $stats->total_net) / (float) $stats->total_gross) * 100, 2)
                : 0,
            'total_transfers_settled' => (int) ($stats->total_transfers ?? 0),
            'avg_settlement_seconds' => round((float) ($stats->avg_settlement_seconds ?? 0), 2),
            'by_type' => $byType->keyBy('type')->map(function ($item) {
                return [
                    'count' => (int) $item->count,
                    'amount' => (int) $item->amount,
                ];
            })->toArray(),
        ];
    }
}