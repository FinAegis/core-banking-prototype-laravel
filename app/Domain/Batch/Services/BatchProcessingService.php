<?php

namespace App\Domain\Batch\Services;

use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Models\BatchItem;
use App\Models\Account;
use App\Domain\Transfer\Services\TransferService;
use App\Domain\Exchange\Services\ExchangeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchProcessingService
{
    protected TransferService $transferService;
    protected ExchangeService $exchangeService;
    
    public function __construct(
        TransferService $transferService,
        ExchangeService $exchangeService
    ) {
        $this->transferService = $transferService;
        $this->exchangeService = $exchangeService;
    }
    
    /**
     * Process a batch job
     */
    public function processBatch(BatchJob $batchJob): void
    {
        // Update status
        $batchJob->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
        
        // Process each item
        $items = $batchJob->items()->where('status', 'pending')->get();
        
        foreach ($items as $item) {
            $this->processItem($item);
            
            // Update progress
            $batchJob->increment('processed_items');
        }
        
        // Update final status
        $this->updateBatchStatus($batchJob);
    }
    
    /**
     * Process a single batch item
     */
    protected function processItem(BatchItem $item): void
    {
        try {
            DB::beginTransaction();
            
            switch ($item->type) {
                case 'transfer':
                    $result = $this->processTransfer($item);
                    break;
                    
                case 'payment':
                    $result = $this->processPayment($item);
                    break;
                    
                case 'conversion':
                    $result = $this->processConversion($item);
                    break;
                    
                default:
                    throw new \Exception("Unknown batch item type: {$item->type}");
            }
            
            // Update item status
            $item->update([
                'status' => 'completed',
                'result' => $result,
                'processed_at' => now(),
            ]);
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Update item with error
            $item->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
            
            // Increment failed items
            $item->batchJob->increment('failed_items');
            
            Log::error('Batch item processing failed', [
                'batch_job_id' => $item->batch_job_id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Process a transfer item
     */
    protected function processTransfer(BatchItem $item): array
    {
        $data = $item->data;
        
        // Validate accounts exist
        $fromAccount = Account::where('uuid', $data['from_account'])->first();
        $toAccount = Account::where('uuid', $data['to_account'])->first();
        
        if (!$fromAccount || !$toAccount) {
            throw new \Exception('Invalid account specified');
        }
        
        // Check balance
        $balance = $fromAccount->getBalance($data['currency']);
        $amount = (int)($data['amount'] * 100); // Convert to cents
        
        if ($balance < $amount) {
            throw new \Exception('Insufficient balance');
        }
        
        // Execute transfer using wallet service or direct event
        // For now, we'll create a transaction projection
        $transactionUuid = \Illuminate\Support\Str::uuid();
        
        \App\Models\TransactionProjection::create([
            'uuid' => $transactionUuid,
            'account_uuid' => $fromAccount->uuid,
            'type' => 'transfer_out',
            'status' => 'completed',
            'amount' => -$amount,
            'currency' => $data['currency'],
            'description' => $data['description'] ?? 'Batch transfer',
            'related_transaction_uuid' => $transactionUuid,
        ]);
        
        \App\Models\TransactionProjection::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'account_uuid' => $toAccount->uuid,
            'type' => 'transfer_in',
            'status' => 'completed',
            'amount' => $amount,
            'currency' => $data['currency'],
            'description' => $data['description'] ?? 'Batch transfer',
            'related_transaction_uuid' => $transactionUuid,
        ]);
        
        return [
            'transfer_id' => $transactionUuid,
            'status' => 'completed',
        ];
    }
    
    /**
     * Process a payment item
     */
    protected function processPayment(BatchItem $item): array
    {
        // Similar to transfer but with different business logic
        // For now, treat it the same as transfer
        return $this->processTransfer($item);
    }
    
    /**
     * Process a conversion item
     */
    protected function processConversion(BatchItem $item): array
    {
        $data = $item->data;
        
        // Get account
        $account = Account::where('uuid', $data['from_account'])->first();
        
        if (!$account) {
            throw new \Exception('Invalid account specified');
        }
        
        // Check balance for from currency
        $balance = $account->getBalance($data['from_currency']);
        $amount = (int)($data['amount'] * 100); // Convert to cents
        
        if ($balance < $amount) {
            throw new \Exception('Insufficient balance');
        }
        
        // Simple conversion logic - in production this would use actual exchange rates
        $rates = [
            'USD' => ['EUR' => 0.92, 'GBP' => 0.79, 'PHP' => 56.25],
            'EUR' => ['USD' => 1.09, 'GBP' => 0.86, 'PHP' => 61.20],
            'GBP' => ['USD' => 1.27, 'EUR' => 1.16, 'PHP' => 71.15],
            'PHP' => ['USD' => 0.018, 'EUR' => 0.016, 'GBP' => 0.014],
        ];
        
        $rate = $rates[$data['from_currency']][$data['to_currency']] ?? 1;
        $convertedAmount = (int)($amount * $rate);
        
        // Create transaction projections
        $conversionUuid = \Illuminate\Support\Str::uuid();
        
        // Debit from currency
        \App\Models\TransactionProjection::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'account_uuid' => $account->uuid,
            'type' => 'conversion_out',
            'status' => 'completed',
            'amount' => -$amount,
            'currency' => $data['from_currency'],
            'description' => "Convert {$data['from_currency']} to {$data['to_currency']}",
            'related_transaction_uuid' => $conversionUuid,
        ]);
        
        // Credit to currency
        \App\Models\TransactionProjection::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'account_uuid' => $account->uuid,
            'type' => 'conversion_in',
            'status' => 'completed',
            'amount' => $convertedAmount,
            'currency' => $data['to_currency'],
            'description' => "Convert {$data['from_currency']} to {$data['to_currency']}",
            'related_transaction_uuid' => $conversionUuid,
        ]);
        
        return [
            'conversion_id' => $conversionUuid,
            'converted_amount' => $convertedAmount,
            'rate' => $rate,
        ];
    }
    
    /**
     * Update batch job status based on processed items
     */
    protected function updateBatchStatus(BatchJob $batchJob): void
    {
        $batchJob->refresh();
        
        if ($batchJob->processed_items === $batchJob->total_items) {
            // All items processed
            if ($batchJob->failed_items > 0) {
                $status = 'completed_with_errors';
            } else {
                $status = 'completed';
            }
        } elseif ($batchJob->failed_items === $batchJob->total_items) {
            // All items failed
            $status = 'failed';
        } else {
            // Partially processed
            $status = 'partial';
        }
        
        $batchJob->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }
}