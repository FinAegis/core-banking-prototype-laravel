<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Domain\Banking\Contracts\IBankIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BankIntegrationController extends Controller
{
    private IBankIntegrationService $bankService;
    
    public function __construct(IBankIntegrationService $bankService)
    {
        $this->bankService = $bankService;
    }
    
    /**
     * Get available banks
     */
    public function getAvailableBanks(): JsonResponse
    {
        try {
            $banks = $this->bankService->getAvailableConnectors()
                ->map(function ($connector, $code) {
                    $capabilities = $connector->getCapabilities();
                    
                    return [
                        'code' => $code,
                        'name' => $connector->getBankName(),
                        'available' => $connector->isAvailable(),
                        'supported_currencies' => $capabilities->supportedCurrencies,
                        'supported_transfer_types' => $capabilities->supportedTransferTypes,
                        'features' => $capabilities->features,
                        'supports_instant_transfers' => $capabilities->supportsInstantTransfers,
                        'supports_multi_currency' => $capabilities->supportsMultiCurrency,
                    ];
                });
            
            return response()->json([
                'data' => $banks->values()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get available banks', ['error' => $e->getMessage()]);
            
            return response()->json([
                'error' => 'Failed to retrieve available banks'
            ], 500);
        }
    }
    
    /**
     * Get user's bank connections
     */
    public function getUserConnections(Request $request): JsonResponse
    {
        try {
            $connections = $this->bankService->getUserBankConnections($request->user())
                ->map(function ($connection) {
                    return [
                        'id' => $connection->id,
                        'bank_code' => $connection->bankCode,
                        'status' => $connection->status,
                        'active' => $connection->isActive(),
                        'needs_renewal' => $connection->needsRenewal(),
                        'permissions' => $connection->permissions,
                        'last_sync_at' => $connection->lastSyncAt?->toIso8601String(),
                        'expires_at' => $connection->expiresAt?->toIso8601String(),
                        'created_at' => $connection->createdAt->toIso8601String(),
                    ];
                });
            
            return response()->json([
                'data' => $connections
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get user bank connections', [
                'user_id' => $request->user()->uuid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve bank connections'
            ], 500);
        }
    }
    
    /**
     * Connect to a bank
     */
    public function connectBank(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_code' => 'required|string',
            'credentials' => 'required|array',
        ]);
        
        try {
            $connection = $this->bankService->connectUserToBank(
                $request->user(),
                $validated['bank_code'],
                $validated['credentials']
            );
            
            return response()->json([
                'data' => [
                    'id' => $connection->id,
                    'bank_code' => $connection->bankCode,
                    'status' => $connection->status,
                    'expires_at' => $connection->expiresAt?->toIso8601String(),
                    'message' => 'Successfully connected to bank',
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to connect to bank', [
                'user_id' => $request->user()->uuid,
                'bank_code' => $validated['bank_code'],
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to connect to bank: ' . $e->getMessage()
            ], 422);
        }
    }
    
    /**
     * Disconnect from a bank
     */
    public function disconnectBank(Request $request, string $bankCode): JsonResponse
    {
        try {
            $success = $this->bankService->disconnectUserFromBank(
                $request->user(),
                $bankCode
            );
            
            if ($success) {
                return response()->json([
                    'message' => 'Successfully disconnected from bank'
                ]);
            } else {
                return response()->json([
                    'error' => 'Bank connection not found'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Failed to disconnect from bank', [
                'user_id' => $request->user()->uuid,
                'bank_code' => $bankCode,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to disconnect from bank'
            ], 500);
        }
    }
    
    /**
     * Get user's bank accounts
     */
    public function getBankAccounts(Request $request): JsonResponse
    {
        $bankCode = $request->query('bank_code');
        
        try {
            $accounts = $this->bankService->getUserBankAccounts($request->user(), $bankCode)
                ->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'bank_code' => $account->bankCode,
                        'account_number' => '***' . substr($account->accountNumber, -4),
                        'iban' => substr($account->iban, 0, 4) . '***' . substr($account->iban, -4),
                        'currency' => $account->currency,
                        'account_type' => $account->accountType,
                        'status' => $account->status,
                        'label' => $account->getLabel(),
                        'created_at' => $account->createdAt->toIso8601String(),
                    ];
                });
            
            return response()->json([
                'data' => $accounts
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get bank accounts', [
                'user_id' => $request->user()->uuid,
                'bank_code' => $bankCode,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve bank accounts'
            ], 500);
        }
    }
    
    /**
     * Sync bank accounts
     */
    public function syncAccounts(Request $request, string $bankCode): JsonResponse
    {
        try {
            $accounts = $this->bankService->syncBankAccounts($request->user(), $bankCode);
            
            return response()->json([
                'message' => 'Bank accounts synced successfully',
                'accounts_synced' => $accounts->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync bank accounts', [
                'user_id' => $request->user()->uuid,
                'bank_code' => $bankCode,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to sync bank accounts'
            ], 500);
        }
    }
    
    /**
     * Get aggregated balance
     */
    public function getAggregatedBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => 'required|string|size:3',
        ]);
        
        try {
            $balance = $this->bankService->getAggregatedBalance(
                $request->user(),
                strtoupper($validated['currency'])
            );
            
            return response()->json([
                'data' => [
                    'currency' => $validated['currency'],
                    'balance' => $balance,
                    'formatted' => number_format($balance / 100, 2),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get aggregated balance', [
                'user_id' => $request->user()->uuid,
                'currency' => $validated['currency'],
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve aggregated balance'
            ], 500);
        }
    }
    
    /**
     * Initiate inter-bank transfer
     */
    public function initiateTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_bank_code' => 'required|string',
            'from_account_id' => 'required|string',
            'to_bank_code' => 'required|string',
            'to_account_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'reference' => 'nullable|string|max:140',
            'description' => 'nullable|string|max:500',
        ]);
        
        try {
            $transfer = $this->bankService->initiateInterBankTransfer(
                $request->user(),
                $validated['from_bank_code'],
                $validated['from_account_id'],
                $validated['to_bank_code'],
                $validated['to_account_id'],
                $validated['amount'] * 100, // Convert to cents
                strtoupper($validated['currency']),
                [
                    'reference' => $validated['reference'] ?? null,
                    'description' => $validated['description'] ?? null,
                ]
            );
            
            return response()->json([
                'data' => [
                    'id' => $transfer->id,
                    'type' => $transfer->type,
                    'status' => $transfer->status,
                    'amount' => $transfer->amount,
                    'currency' => $transfer->currency,
                    'reference' => $transfer->reference,
                    'total_amount' => $transfer->getTotalAmount(),
                    'fees' => $transfer->fees,
                    'estimated_arrival' => $transfer->getEstimatedArrival()?->toIso8601String(),
                    'created_at' => $transfer->createdAt->toIso8601String(),
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to initiate transfer', [
                'user_id' => $request->user()->uuid,
                'transfer_data' => $validated,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to initiate transfer: ' . $e->getMessage()
            ], 422);
        }
    }
    
    /**
     * Get bank health status
     */
    public function getBankHealth(string $bankCode): JsonResponse
    {
        try {
            $health = $this->bankService->checkBankHealth($bankCode);
            
            return response()->json([
                'data' => $health
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get bank health', [
                'bank_code' => $bankCode,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve bank health status'
            ], 500);
        }
    }
    
    /**
     * Get recommended banks for user
     */
    public function getRecommendedBanks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currencies' => 'nullable|array',
            'currencies.*' => 'string|size:3',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'countries' => 'nullable|array',
            'countries.*' => 'string|size:2',
        ]);
        
        try {
            $routingService = app(\App\Domain\Banking\Services\BankRoutingService::class);
            $recommendations = $routingService->getRecommendedBanks(
                $request->user(),
                $validated
            );
            
            return response()->json([
                'data' => $recommendations
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get bank recommendations', [
                'user_id' => $request->user()->uuid,
                'requirements' => $validated,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve bank recommendations'
            ], 500);
        }
    }
}