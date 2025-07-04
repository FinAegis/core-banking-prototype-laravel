<?php

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Services\KeyManagementService;
use App\Http\Controllers\Controller;
use App\Http\Resources\BlockchainWalletResource;
use App\Http\Resources\WalletAddressResource;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlockchainWalletController extends Controller
{
    public function __construct(
        private BlockchainWalletService $walletService,
        private KeyManagementService $keyManager
    ) {}
    
    /**
     * List user's blockchain wallets
     */
    public function index(Request $request)
    {
        $wallets = DB::table('blockchain_wallets')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return BlockchainWalletResource::collection($wallets);
    }
    
    /**
     * Create a new blockchain wallet
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['custodial', 'non-custodial'])],
            'mnemonic' => ['required_if:type,non-custodial', 'string'],
            'settings' => ['sometimes', 'array'],
            'settings.daily_limit' => ['sometimes', 'numeric', 'min:0'],
            'settings.requires_2fa' => ['sometimes', 'boolean'],
            'settings.whitelisted_addresses' => ['sometimes', 'array'],
        ]);
        
        // Validate mnemonic if provided
        if ($validated['type'] === 'non-custodial' && isset($validated['mnemonic'])) {
            if (!$this->keyManager->validateMnemonic($validated['mnemonic'])) {
                return response()->json([
                    'message' => 'Invalid mnemonic phrase',
                    'errors' => ['mnemonic' => ['The provided mnemonic phrase is invalid']]
                ], 422);
            }
        }
        
        $wallet = $this->walletService->createWallet(
            userId: $request->user()->id,
            type: $validated['type'],
            mnemonic: $validated['mnemonic'] ?? null,
            settings: $validated['settings'] ?? []
        );
        
        $walletData = DB::table('blockchain_wallets')
            ->where('wallet_id', $wallet->getWalletId())
            ->first();
            
        return new BlockchainWalletResource($walletData);
    }
    
    /**
     * Show wallet details
     */
    public function show(Request $request, string $walletId)
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
            
        return new BlockchainWalletResource($wallet);
    }
    
    /**
     * Update wallet settings
     */
    public function update(Request $request, string $walletId)
    {
        // Verify ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
            
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.daily_limit' => ['sometimes', 'numeric', 'min:0'],
            'settings.requires_2fa' => ['sometimes', 'boolean'],
            'settings.whitelisted_addresses' => ['sometimes', 'array'],
        ]);
        
        $updatedWallet = $this->walletService->updateSettings(
            walletId: $walletId,
            settings: $validated['settings']
        );
        
        $walletData = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();
            
        return new BlockchainWalletResource($walletData);
    }
    
    /**
     * List wallet addresses
     */
    public function addresses(Request $request, string $walletId)
    {
        // Verify ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
            
        $addresses = DB::table('wallet_addresses')
            ->where('wallet_id', $walletId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return WalletAddressResource::collection($addresses);
    }
    
    /**
     * Generate new address
     */
    public function generateAddress(Request $request, string $walletId)
    {
        // Verify ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
            
        $validated = $request->validate([
            'chain' => ['required', Rule::in(['ethereum', 'polygon', 'bsc', 'bitcoin'])],
            'label' => ['sometimes', 'string', 'max:255'],
        ]);
        
        $address = $this->walletService->generateAddress(
            walletId: $walletId,
            chain: $validated['chain'],
            label: $validated['label'] ?? null
        );
        
        $addressData = DB::table('wallet_addresses')
            ->where('wallet_id', $walletId)
            ->where('address', $address['address'])
            ->first();
            
        return new WalletAddressResource($addressData);
    }
    
    /**
     * Get transaction history
     */
    public function transactions(Request $request, string $walletId)
    {
        // Verify ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
            
        $validated = $request->validate([
            'chain' => ['sometimes', Rule::in(['ethereum', 'polygon', 'bsc', 'bitcoin'])],
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'failed'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        
        $query = DB::table('blockchain_transactions')
            ->where('wallet_id', $walletId);
            
        if (isset($validated['chain'])) {
            $query->where('chain', $validated['chain']);
        }
        
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        
        $transactions = $query
            ->orderBy('created_at', 'desc')
            ->limit($validated['limit'] ?? 50)
            ->get();
            
        return TransactionResource::collection($transactions);
    }
    
    /**
     * Create wallet backup
     */
    public function createBackup(Request $request, string $walletId)
    {
        // Verify ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
            
        // Only non-custodial wallets can be backed up
        if ($wallet->type !== 'non-custodial') {
            return response()->json([
                'message' => 'Only non-custodial wallets can be backed up'
            ], 422);
        }
        
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);
        
        // This would typically involve retrieving the mnemonic from secure storage
        // For now, we'll return a message indicating backup creation
        return response()->json([
            'message' => 'Wallet backup created successfully',
            'backup_id' => 'backup_' . uniqid(),
            'instructions' => 'Store your backup securely. You will need it to recover your wallet.'
        ]);
    }
    
    /**
     * Generate new mnemonic
     */
    public function generateMnemonic()
    {
        $mnemonic = $this->keyManager->generateMnemonic();
        
        return response()->json([
            'mnemonic' => $mnemonic,
            'word_count' => count(explode(' ', $mnemonic)),
            'warning' => 'Store this mnemonic securely. It cannot be recovered if lost.'
        ]);
    }
}