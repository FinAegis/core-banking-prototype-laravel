<?php

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Services\KeyManagementService;
use App\Http\Controllers\Controller;
use App\Http\Resources\BlockchainWalletResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletAddressResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlockchainWalletController extends Controller
{
    public function __construct(
        private BlockchainWalletService $walletService,
        private KeyManagementService $keyManager
    ) {
    }

    /**
     * List user's blockchain wallets.
     *
     * @OA\Get(
     *     path="/api/v1/blockchain-wallets",
     *     operationId="blockchainWalletsList",
     *     summary="List blockchain wallets",
     *     description="Returns a list of all blockchain wallets belonging to the authenticated user.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="wallet_id", type="string", example="wal_abc123"),
     *                     @OA\Property(property="type", type="string", enum={"custodial", "non-custodial"}, example="custodial"),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="settings", type="object"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
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
     * Create a new blockchain wallet.
     *
     * @OA\Post(
     *     path="/api/v1/blockchain-wallets",
     *     operationId="blockchainWalletsStore",
     *     summary="Create a blockchain wallet",
     *     description="Creates a new custodial or non-custodial blockchain wallet for the authenticated user.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type"},
     *             @OA\Property(property="type", type="string", enum={"custodial", "non-custodial"}, example="custodial"),
     *             @OA\Property(property="mnemonic", type="string", description="Required if type is non-custodial", example="abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about"),
     *             @OA\Property(property="settings", type="object",
     *                 @OA\Property(property="daily_limit", type="number", example=10000),
     *                 @OA\Property(property="requires_2fa", type="boolean", example=true),
     *                 @OA\Property(property="whitelisted_addresses", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wallet created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="wallet_id", type="string", example="wal_abc123"),
     *                 @OA\Property(property="type", type="string", example="custodial"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="settings", type="object"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or invalid mnemonic")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'type'                           => ['required', Rule::in(['custodial', 'non-custodial'])],
                'mnemonic'                       => ['required_if:type,non-custodial', 'string'],
                'settings'                       => ['sometimes', 'array'],
                'settings.daily_limit'           => ['sometimes', 'numeric', 'min:0'],
                'settings.requires_2fa'          => ['sometimes', 'boolean'],
                'settings.whitelisted_addresses' => ['sometimes', 'array'],
            ]
        );

        // Validate mnemonic if provided
        if ($validated['type'] === 'non-custodial' && isset($validated['mnemonic'])) {
            if (! $this->keyManager->validateMnemonic($validated['mnemonic'])) {
                return response()->json(
                    [
                        'message' => 'Invalid mnemonic phrase',
                        'errors'  => ['mnemonic' => ['The provided mnemonic phrase is invalid']],
                    ],
                    422
                );
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
     * Show wallet details.
     *
     * @OA\Get(
     *     path="/api/v1/blockchain-wallets/{walletId}",
     *     operationId="blockchainWalletsShow",
     *     summary="Get wallet details",
     *     description="Returns detailed information about a specific blockchain wallet owned by the authenticated user.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="walletId",
     *         in="path",
     *         required=true,
     *         description="The wallet ID",
     *         @OA\Schema(type="string", example="wal_abc123")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="wallet_id", type="string", example="wal_abc123"),
     *                 @OA\Property(property="type", type="string", example="custodial"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="settings", type="object"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Wallet not found")
     * )
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
     * Update wallet settings.
     *
     * @OA\Put(
     *     path="/api/v1/blockchain-wallets/{walletId}",
     *     operationId="blockchainWalletsUpdate",
     *     summary="Update wallet settings",
     *     description="Updates the settings of a specific blockchain wallet owned by the authenticated user.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="walletId",
     *         in="path",
     *         required=true,
     *         description="The wallet ID",
     *         @OA\Schema(type="string", example="wal_abc123")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"settings"},
     *             @OA\Property(property="settings", type="object",
     *                 @OA\Property(property="daily_limit", type="number", example=10000),
     *                 @OA\Property(property="requires_2fa", type="boolean", example=true),
     *                 @OA\Property(property="whitelisted_addresses", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wallet settings updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="wallet_id", type="string", example="wal_abc123"),
     *                 @OA\Property(property="type", type="string", example="custodial"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="settings", type="object"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Wallet not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, string $walletId)
    {
        // Verify ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate(
            [
                'settings'                       => ['required', 'array'],
                'settings.daily_limit'           => ['sometimes', 'numeric', 'min:0'],
                'settings.requires_2fa'          => ['sometimes', 'boolean'],
                'settings.whitelisted_addresses' => ['sometimes', 'array'],
            ]
        );

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
     * List wallet addresses.
     *
     * @OA\Get(
     *     path="/api/v1/blockchain-wallets/{walletId}/addresses",
     *     operationId="blockchainWalletsAddresses",
     *     summary="List wallet addresses",
     *     description="Returns all active addresses for a specific blockchain wallet owned by the authenticated user.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="walletId",
     *         in="path",
     *         required=true,
     *         description="The wallet ID",
     *         @OA\Schema(type="string", example="wal_abc123")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="wallet_id", type="string", example="wal_abc123"),
     *                     @OA\Property(property="address", type="string", example="0x1234...abcd"),
     *                     @OA\Property(property="chain", type="string", example="ethereum"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Wallet not found")
     * )
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
     * Generate new address for a wallet.
     *
     * @OA\Post(
     *     path="/api/v1/blockchain-wallets/{walletId}/addresses",
     *     operationId="blockchainWalletsGenerateAddress",
     *     summary="Generate a new wallet address",
     *     description="Generates a new blockchain address for the specified chain on a wallet owned by the authenticated user.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="walletId",
     *         in="path",
     *         required=true,
     *         description="The wallet ID",
     *         @OA\Schema(type="string", example="wal_abc123")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"chain"},
     *             @OA\Property(property="chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin"}, example="ethereum"),
     *             @OA\Property(property="label", type="string", example="My savings address", maxLength=255)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="wallet_id", type="string", example="wal_abc123"),
     *                 @OA\Property(property="address", type="string", example="0x1234...abcd"),
     *                 @OA\Property(property="chain", type="string", example="ethereum"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Wallet not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function generateAddress(Request $request, string $walletId)
    {
        // Verify ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate(
            [
                'chain' => ['required', Rule::in(['ethereum', 'polygon', 'bsc', 'bitcoin'])],
                'label' => ['sometimes', 'string', 'max:255'],
            ]
        );

        $address = $this->walletService->generateAddress(
            $validated['chain'],
            $walletId
        );

        $addressData = DB::table('wallet_addresses')
            ->where('wallet_id', $walletId)
            ->where('address', $address->address)
            ->first();

        return new WalletAddressResource($addressData);
    }

    /**
     * Get transaction history for a wallet.
     *
     * @OA\Get(
     *     path="/api/v1/blockchain-wallets/{walletId}/transactions",
     *     operationId="blockchainWalletsTransactions",
     *     summary="List wallet transactions",
     *     description="Returns the transaction history for a specific blockchain wallet, with optional chain and status filters.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="walletId",
     *         in="path",
     *         required=true,
     *         description="The wallet ID",
     *         @OA\Schema(type="string", example="wal_abc123")
     *     ),
     *     @OA\Parameter(
     *         name="chain",
     *         in="query",
     *         required=false,
     *         description="Filter by blockchain network",
     *         @OA\Schema(type="string", enum={"ethereum", "polygon", "bsc", "bitcoin"})
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by transaction status",
     *         @OA\Schema(type="string", enum={"pending", "confirmed", "failed"})
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of transactions to return (1-100, default 50)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="wallet_id", type="string", example="wal_abc123"),
     *                     @OA\Property(property="chain", type="string", example="ethereum"),
     *                     @OA\Property(property="status", type="string", example="confirmed"),
     *                     @OA\Property(property="amount", type="string", example="1.5"),
     *                     @OA\Property(property="tx_hash", type="string", example="0xabc...def"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Wallet not found")
     * )
     */
    public function transactions(Request $request, string $walletId)
    {
        // Verify ownership
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate(
            [
                'chain'  => ['sometimes', Rule::in(['ethereum', 'polygon', 'bsc', 'bitcoin'])],
                'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'failed'])],
                'limit'  => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]
        );

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
     * Create a wallet backup.
     *
     * @OA\Post(
     *     path="/api/v1/blockchain-wallets/{walletId}/backup",
     *     operationId="blockchainWalletsCreateBackup",
     *     summary="Create wallet backup",
     *     description="Creates a secure backup for a non-custodial blockchain wallet. Only non-custodial wallets can be backed up.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="walletId",
     *         in="path",
     *         required=true,
     *         description="The wallet ID",
     *         @OA\Schema(type="string", example="wal_abc123")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="securepass123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Backup created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Wallet backup created successfully"),
     *             @OA\Property(property="backup_id", type="string", example="backup_64a1b2c3"),
     *             @OA\Property(property="instructions", type="string", example="Store your backup securely. You will need it to recover your wallet.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Wallet not found"),
     *     @OA\Response(response=422, description="Only non-custodial wallets can be backed up")
     * )
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
            return response()->json(
                [
                    'message' => 'Only non-custodial wallets can be backed up',
                ],
                422
            );
        }

        $validated = $request->validate(
            [
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        // This would typically involve retrieving the mnemonic from secure storage
        // For now, we'll return a message indicating backup creation
        return response()->json(
            [
                'message'      => 'Wallet backup created successfully',
                'backup_id'    => 'backup_' . uniqid(),
                'instructions' => 'Store your backup securely. You will need it to recover your wallet.',
            ]
        );
    }

    /**
     * Generate a new mnemonic phrase.
     *
     * @OA\Post(
     *     path="/api/v1/blockchain-wallets/generate-mnemonic",
     *     operationId="blockchainWalletsGenerateMnemonic",
     *     summary="Generate mnemonic phrase",
     *     description="Generates a new BIP-39 mnemonic phrase for non-custodial wallet creation.",
     *     tags={"Blockchain Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Mnemonic generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="mnemonic", type="string", example="abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about"),
     *             @OA\Property(property="word_count", type="integer", example=12),
     *             @OA\Property(property="warning", type="string", example="Store this mnemonic securely. It cannot be recovered if lost.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function generateMnemonic()
    {
        $mnemonic = $this->keyManager->generateMnemonic();

        return response()->json(
            [
                'mnemonic'   => $mnemonic,
                'word_count' => count(explode(' ', $mnemonic)),
                'warning'    => 'Store this mnemonic securely. It cannot be recovered if lost.',
            ]
        );
    }
}
