<?php

namespace App\Http\Controllers;

use App\Domain\Wallet\Contracts\WalletConnectorInterface;
use App\Domain\Wallet\Contracts\KeyManagementServiceInterface;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Models\BlockchainAddress;
use App\Models\BlockchainTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlockchainWalletController extends Controller
{
    public function __construct(
        private WalletConnectorInterface $walletConnector,
        private KeyManagementServiceInterface $keyManagementService
    ) {}

    /**
     * Display blockchain wallet dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get user's blockchain addresses
        $addresses = BlockchainAddress::where('user_uuid', $user->uuid)
            ->with(['transactions' => function($query) {
                $query->latest()->limit(5);
            }])
            ->get();
        
        // Get blockchain balances
        $balances = $this->getBlockchainBalances($addresses);
        
        // Get recent transactions
        $recentTransactions = BlockchainTransaction::whereIn('address_uuid', $addresses->pluck('uuid'))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Get supported chains
        $supportedChains = $this->getSupportedChains();
        
        return view('wallet.blockchain.index', compact('addresses', 'balances', 'recentTransactions', 'supportedChains'));
    }

    /**
     * Show form to generate new blockchain address
     */
    public function createAddress()
    {
        $supportedChains = $this->getSupportedChains();
        
        return view('wallet.blockchain.create-address', compact('supportedChains'));
    }

    /**
     * Generate new blockchain address
     */
    public function generateAddress(Request $request)
    {
        $validated = $request->validate([
            'chain' => 'required|in:ethereum,bitcoin,polygon,bsc',
            'label' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);
        
        try {
            // Generate new address
            $addressData = $this->walletConnector->generateAddress(
                Auth::user()->uuid,
                $validated['chain']
            );
            
            // Store address in database
            $address = BlockchainAddress::create([
                'uuid' => Str::uuid()->toString(),
                'user_uuid' => Auth::user()->uuid,
                'chain' => $validated['chain'],
                'address' => $addressData['address'],
                'public_key' => $addressData['public_key'],
                'derivation_path' => $addressData['derivation_path'],
                'label' => $validated['label'],
                'is_active' => true,
                'metadata' => [
                    'created_via' => 'web',
                    'ip_address' => $request->ip(),
                ],
            ]);
            
            return redirect()
                ->route('wallet.blockchain.show', $address->uuid)
                ->with('success', 'Blockchain address generated successfully');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to generate address: ' . $e->getMessage()]);
        }
    }

    /**
     * Show blockchain address details
     */
    public function showAddress($addressId)
    {
        $address = BlockchainAddress::where('uuid', $addressId)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();
        
        // Get balance
        $balance = $this->walletConnector->getBalance($address->address, $address->chain);
        
        // Get transactions
        $transactions = $address->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Get transaction statistics
        $statistics = $this->getAddressStatistics($address);
        
        // Get supported chains
        $supportedChains = $this->getSupportedChains();
        
        return view('wallet.blockchain.address', compact('address', 'balance', 'transactions', 'statistics', 'supportedChains'));
    }

    /**
     * Show send cryptocurrency form
     */
    public function sendForm($addressId)
    {
        $address = BlockchainAddress::where('uuid', $addressId)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();
        
        // Get current balance
        $balance = $this->walletConnector->getBalance($address->address, $address->chain);
        
        // Get network fees
        $networkFees = $this->getNetworkFees($address->chain);
        
        // Get supported chains
        $supportedChains = $this->getSupportedChains();
        
        return view('wallet.blockchain.send', compact('address', 'balance', 'networkFees', 'supportedChains'));
    }

    /**
     * Send cryptocurrency
     */
    public function send(Request $request, $addressId)
    {
        $address = BlockchainAddress::where('uuid', $addressId)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();
        
        $validated = $request->validate([
            'recipient_address' => 'required|string',
            'amount' => 'required|numeric|min:0.00000001',
            'fee_level' => 'required|in:slow,medium,fast',
            'password' => 'required|string',
            'memo' => 'nullable|string|max:255',
        ]);
        
        try {
            // Validate recipient address
            if (!$this->walletConnector->validateAddress($validated['recipient_address'], $address->chain)) {
                return back()->withErrors(['recipient_address' => 'Invalid recipient address']);
            }
            
            // Check balance
            $balance = $this->walletConnector->getBalance($address->address, $address->chain);
            $networkFees = $this->getNetworkFees($address->chain);
            $fee = $networkFees[$validated['fee_level']]['amount'];
            
            if ($balance['available'] < $validated['amount'] + $fee) {
                return back()->withErrors(['amount' => 'Insufficient balance (including network fee)']);
            }
            
            // Create and send transaction
            $txHash = $this->walletConnector->sendTransaction(
                $address->address,
                $validated['recipient_address'],
                $validated['amount'],
                $address->chain,
                [
                    'fee' => $fee,
                    'memo' => $validated['memo'],
                ]
            );
            
            // Record transaction
            BlockchainTransaction::create([
                'uuid' => Str::uuid()->toString(),
                'address_uuid' => $address->uuid,
                'tx_hash' => $txHash,
                'type' => 'send',
                'amount' => $validated['amount'],
                'fee' => $fee,
                'from_address' => $address->address,
                'to_address' => $validated['recipient_address'],
                'chain' => $address->chain,
                'status' => 'pending',
                'metadata' => [
                    'memo' => $validated['memo'],
                    'fee_level' => $validated['fee_level'],
                ],
            ]);
            
            return redirect()
                ->route('wallet.blockchain.show', $address->uuid)
                ->with('success', 'Transaction sent successfully. Transaction hash: ' . $txHash);
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to send transaction: ' . $e->getMessage()]);
        }
    }

    /**
     * Show transaction details
     */
    public function showTransaction($transactionId)
    {
        $transaction = BlockchainTransaction::where('uuid', $transactionId)
            ->whereHas('address', function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->firstOrFail();
        
        // Get transaction status from blockchain
        $blockchainData = $this->walletConnector->getTransactionStatus(
            $transaction->tx_hash,
            $transaction->chain
        );
        
        // Get supported chains
        $supportedChains = $this->getSupportedChains();
        
        return view('wallet.blockchain.transaction', compact('transaction', 'blockchainData', 'supportedChains'));
    }

    /**
     * Export wallet backup
     */
    public function exportBackup(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string',
            'include_private_keys' => 'boolean',
        ]);
        
        try {
            $user = Auth::user();
            $addresses = BlockchainAddress::where('user_uuid', $user->uuid)->get();
            
            $backup = $this->keyManagementService->generateBackup($user->uuid);
            
            return response()->json([
                'backup_id' => $backup['backup_id'],
                'encrypted_data' => $backup['encrypted_data'],
                'checksum' => $backup['checksum'],
                'created_at' => now()->toIso8601String(),
                'addresses_count' => $addresses->count(),
            ])->header('Content-Disposition', 'attachment; filename="wallet-backup-' . now()->format('Y-m-d') . '.json"');
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to export backup: ' . $e->getMessage()]);
        }
    }

    /**
     * Get blockchain balances for addresses
     */
    private function getBlockchainBalances($addresses)
    {
        $balances = [];
        
        foreach ($addresses as $address) {
            try {
                $balance = $this->walletConnector->getBalance($address->address, $address->chain);
                $balances[$address->uuid] = $balance;
            } catch (\Exception $e) {
                $balances[$address->uuid] = [
                    'balance' => 0,
                    'available' => 0,
                    'pending' => 0,
                    'error' => true,
                ];
            }
        }
        
        return $balances;
    }

    /**
     * Get supported blockchain networks
     */
    private function getSupportedChains()
    {
        return [
            'ethereum' => [
                'name' => 'Ethereum',
                'symbol' => 'ETH',
                'decimals' => 18,
                'explorer' => 'https://etherscan.io',
                'icon' => 'eth-icon',
            ],
            'bitcoin' => [
                'name' => 'Bitcoin',
                'symbol' => 'BTC',
                'decimals' => 8,
                'explorer' => 'https://blockstream.info',
                'icon' => 'btc-icon',
            ],
            'polygon' => [
                'name' => 'Polygon',
                'symbol' => 'MATIC',
                'decimals' => 18,
                'explorer' => 'https://polygonscan.com',
                'icon' => 'matic-icon',
            ],
            'bsc' => [
                'name' => 'BNB Smart Chain',
                'symbol' => 'BNB',
                'decimals' => 18,
                'explorer' => 'https://bscscan.com',
                'icon' => 'bnb-icon',
            ],
        ];
    }

    /**
     * Get network fees for chain
     */
    private function getNetworkFees($chain)
    {
        // Mock network fees - in production, fetch from blockchain
        $fees = [
            'ethereum' => [
                'slow' => ['time' => '10 min', 'amount' => 0.001],
                'medium' => ['time' => '3 min', 'amount' => 0.002],
                'fast' => ['time' => '30 sec', 'amount' => 0.003],
            ],
            'bitcoin' => [
                'slow' => ['time' => '60 min', 'amount' => 0.00001],
                'medium' => ['time' => '30 min', 'amount' => 0.00002],
                'fast' => ['time' => '10 min', 'amount' => 0.00005],
            ],
            'polygon' => [
                'slow' => ['time' => '30 sec', 'amount' => 0.001],
                'medium' => ['time' => '15 sec', 'amount' => 0.002],
                'fast' => ['time' => '5 sec', 'amount' => 0.005],
            ],
            'bsc' => [
                'slow' => ['time' => '15 sec', 'amount' => 0.0001],
                'medium' => ['time' => '6 sec', 'amount' => 0.0002],
                'fast' => ['time' => '3 sec', 'amount' => 0.0005],
            ],
        ];
        
        return $fees[$chain] ?? $fees['ethereum'];
    }

    /**
     * Get address statistics
     */
    private function getAddressStatistics($address)
    {
        $transactions = $address->transactions;
        
        return [
            'total_transactions' => $transactions->count(),
            'total_sent' => $transactions->where('type', 'send')->sum('amount'),
            'total_received' => $transactions->where('type', 'receive')->sum('amount'),
            'total_fees' => $transactions->sum('fee'),
            'first_transaction' => $transactions->min('created_at'),
            'last_transaction' => $transactions->max('created_at'),
        ];
    }
}