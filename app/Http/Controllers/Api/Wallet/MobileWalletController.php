<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wallet;

use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Domain\MobilePayment\Services\ActivityFeedService;
use App\Domain\MobilePayment\Services\PaymentIntentService;
use App\Domain\MobilePayment\Services\TransactionDetailService;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Relayer\Services\WalletBalanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MobileWalletController extends Controller
{
    public function __construct(
        private readonly WalletBalanceService $balanceService,
        private readonly SmartAccountService $smartAccountService,
        private readonly ActivityFeedService $activityFeedService,
        private readonly TransactionDetailService $transactionDetailService,
        private readonly PaymentIntentService $paymentIntentService,
    ) {
    }

    /**
     * Get supported token list with network and decimals info.
     *
     * @OA\Get(
     *     path="/api/v1/wallet/tokens",
     *     operationId="walletTokens",
     *     summary="Get supported token list",
     *     description="Returns the list of supported tokens with network availability and decimals info.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="symbol", type="string", example="USDC"),
     *                     @OA\Property(property="name", type="string", example="USD Coin"),
     *                     @OA\Property(property="decimals", type="integer", example=6),
     *                     @OA\Property(property="networks", type="array", @OA\Items(type="string"), example={"polygon", "base", "arbitrum"}),
     *                     @OA\Property(property="icon", type="string", example="usdc")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function tokens(): JsonResponse
    {
        $tokens = [
            [
                'symbol'   => 'USDC',
                'name'     => 'USD Coin',
                'decimals' => 6,
                'networks' => ['polygon', 'base', 'arbitrum', 'optimism', 'ethereum'],
                'icon'     => 'usdc',
            ],
            [
                'symbol'   => 'USDT',
                'name'     => 'Tether USD',
                'decimals' => 6,
                'networks' => ['polygon', 'arbitrum', 'optimism', 'ethereum'],
                'icon'     => 'usdt',
            ],
            [
                'symbol'   => 'WETH',
                'name'     => 'Wrapped Ether',
                'decimals' => 18,
                'networks' => ['polygon', 'base', 'arbitrum', 'optimism'],
                'icon'     => 'weth',
            ],
            [
                'symbol'   => 'WBTC',
                'name'     => 'Wrapped Bitcoin',
                'decimals' => 8,
                'networks' => ['polygon', 'arbitrum', 'ethereum'],
                'icon'     => 'wbtc',
            ],
        ];

        return response()->json([
            'success' => true,
            'data'    => $tokens,
        ]);
    }

    /**
     * Get ERC-20 balances across user's smart accounts.
     *
     * @OA\Get(
     *     path="/api/v1/wallet/balances",
     *     operationId="walletBalances",
     *     summary="Get ERC-20 balances",
     *     description="Returns ERC-20 token balances across all of the authenticated user's smart accounts.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Balances per token and network",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="token", type="string", example="USDC"),
     *                     @OA\Property(property="network", type="string", example="polygon"),
     *                     @OA\Property(property="address", type="string", example="0x1234...abcd"),
     *                     @OA\Property(property="balance", type="string", example="1000.50"),
     *                     @OA\Property(property="error", type="string", nullable=true, example=null, description="Present only if balance query failed")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function balances(Request $request): JsonResponse
    {
        $user = $request->user();
        $accounts = $this->smartAccountService->getUserAccounts($user);

        $balances = [];
        $supportedTokens = ['USDC', 'USDT', 'WETH', 'WBTC'];

        foreach ($accounts as $account) {
            $networkStr = $account->network ?? 'polygon';
            $network = SupportedNetwork::tryFrom($networkStr);
            if (! $network) {
                continue;
            }
            foreach ($supportedTokens as $token) {
                if (! $this->balanceService->isTokenSupported($token, $network)) {
                    continue;
                }
                try {
                    $balance = $this->balanceService->getBalance(
                        $account->account_address,
                        $token,
                        $network,
                    );
                    $balances[] = [
                        'token'   => $token,
                        'network' => $networkStr,
                        'address' => $account->account_address,
                        'balance' => $balance,
                    ];
                } catch (Throwable) {
                    $balances[] = [
                        'token'   => $token,
                        'network' => $networkStr,
                        'address' => $account->account_address,
                        'balance' => '0',
                        'error'   => 'Balance query failed',
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $balances,
        ]);
    }

    /**
     * Get aggregated wallet state (balances + addresses + sync info).
     *
     * @OA\Get(
     *     path="/api/v1/wallet/state",
     *     operationId="walletState",
     *     summary="Get aggregated wallet state",
     *     description="Returns aggregated wallet state including addresses, supported networks and sync information.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Aggregated wallet state",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="addresses",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="address", type="string", example="0x1234...abcd"),
     *                         @OA\Property(property="network", type="string", example="polygon"),
     *                         @OA\Property(property="deployed", type="boolean", example=true)
     *                     )
     *                 ),
     *                 @OA\Property(property="networks", type="array", @OA\Items(type="string"), example={"polygon", "ethereum", "arbitrum"}),
     *                 @OA\Property(property="synced_at", type="string", format="date-time"),
     *                 @OA\Property(property="account_count", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function state(Request $request): JsonResponse
    {
        $user = $request->user();
        $accounts = $this->smartAccountService->getUserAccounts($user);

        $addresses = [];
        foreach ($accounts as $account) {
            $addresses[] = [
                'address'  => $account->account_address,
                'network'  => $account->network ?? 'polygon',
                'deployed' => $account->is_deployed ?? false,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'addresses'     => $addresses,
                'networks'      => $this->smartAccountService->getSupportedNetworks(),
                'synced_at'     => now()->toIso8601String(),
                'account_count' => count($accounts),
            ],
        ]);
    }

    /**
     * List user's addresses per network.
     *
     * @OA\Get(
     *     path="/api/v1/wallet/addresses",
     *     operationId="walletAddresses",
     *     summary="List user's addresses per network",
     *     description="Returns all smart account addresses for the authenticated user across supported networks.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User addresses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="address", type="string", example="0x1234...abcd"),
     *                     @OA\Property(property="network", type="string", example="polygon"),
     *                     @OA\Property(property="deployed", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function addresses(Request $request): JsonResponse
    {
        $user = $request->user();
        $accounts = $this->smartAccountService->getUserAccounts($user);

        $addresses = [];
        foreach ($accounts as $account) {
            $addresses[] = [
                'address'    => $account->account_address,
                'network'    => $account->network ?? 'polygon',
                'type'       => 'smart_account',
                'deployed'   => $account->is_deployed ?? false,
                'created_at' => $account->created_at?->toIso8601String(),
            ];
        }

        // If no smart accounts exist yet, return deterministic placeholder addresses
        // so the mobile Receive screen can display a QR code before onboarding completes.
        if (empty($addresses)) {
            $supportedNetworks = $this->smartAccountService->getSupportedNetworks();
            $seed = hash('sha256', "wallet:{$user->id}:" . config('app.key'));

            foreach ($supportedNetworks as $network) {
                $addresses[] = [
                    'address'    => '0x' . substr(hash('sha256', "{$seed}:{$network}"), 0, 40),
                    'network'    => $network,
                    'type'       => 'pending',
                    'deployed'   => false,
                    'created_at' => null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $addresses,
        ]);
    }

    /**
     * Cursor-based transaction list from activity feed.
     *
     * @OA\Get(
     *     path="/api/v1/wallet/transactions",
     *     operationId="walletTransactions",
     *     summary="List transactions with cursor-based pagination",
     *     description="Returns a paginated list of transactions from the user's activity feed using cursor-based pagination.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="cursor",
     *         in="query",
     *         required=false,
     *         description="Pagination cursor for the next page",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of items per page (max 100, default 20)",
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction feed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Activity feed with items and pagination cursor")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();

        $feed = $this->activityFeedService->getFeed(
            userId: $user->id,
            cursor: $request->query('cursor'),
            limit: min((int) $request->query('limit', '20'), 100),
        );

        return response()->json([
            'success' => true,
            'data'    => $feed,
        ]);
    }

    /**
     * Get transaction detail.
     *
     * @OA\Get(
     *     path="/api/v1/wallet/transactions/{id}",
     *     operationId="walletTransactionDetail",
     *     summary="Get transaction detail",
     *     description="Returns detailed information for a specific transaction by ID.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transaction identifier",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Transaction detail object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="TRANSACTION_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Transaction not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function transactionDetail(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $detail = $this->transactionDetailService->getDetails($id, $user->id);

        if (! $detail) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'TRANSACTION_NOT_FOUND',
                    'message' => 'Transaction not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $detail,
        ]);
    }

    /**
     * Create and auto-submit a payment intent (send transaction).
     *
     * @OA\Post(
     *     path="/api/v1/wallet/transactions/send",
     *     operationId="walletSend",
     *     summary="Send a transaction",
     *     description="Creates a payment intent and auto-submits it to send tokens to a recipient address.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"to", "token", "amount", "network"},
     *             @OA\Property(property="to", type="string", example="0x1234...abcd", description="Recipient address"),
     *             @OA\Property(property="token", type="string", enum={"USDC", "USDT", "WETH", "WBTC"}, example="USDC", description="Token symbol"),
     *             @OA\Property(property="amount", type="string", example="100.00", description="Amount to send"),
     *             @OA\Property(property="network", type="string", example="polygon", description="Target network")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transaction submitted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Payment intent result")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Send failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="SEND_FAILED"),
     *                 @OA\Property(property="message", type="string", example="Insufficient balance.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'to'      => ['required', 'string'],
            'token'   => ['required', 'string', 'in:USDC,USDT,WETH,WBTC'],
            'amount'  => ['required', 'string'],
            'network' => ['required', 'string'],
        ]);

        $user = $request->user();

        try {
            $intent = $this->paymentIntentService->create(
                userId: $user->id,
                data: [
                    'recipient_address' => $request->input('to'),
                    'token'             => $request->input('token'),
                    'amount'            => $request->input('amount'),
                    'network'           => $request->input('network'),
                    'type'              => 'send',
                ],
            );

            $intentId = $intent->public_id;

            // Auto-submit the intent
            $result = $this->paymentIntentService->submit($intentId, $user->id, 'wallet');

            return response()->json([
                'success' => true,
                'data'    => $result->toApiResponse(),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'SEND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get recent recipient addresses from send history.
     *
     * @OA\Get(
     *     path="/api/v1/wallet/recent-recipients",
     *     operationId="walletRecentRecipients",
     *     summary="Get recent send recipients",
     *     description="Returns unique recipient addresses from recent send transactions, ordered by most recent.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of recipients to return (max 50, default 10)",
     *         @OA\Schema(type="integer", default=10, maximum=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recent recipients list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="address", type="string", example="0x1234...abcd"),
     *                 @OA\Property(property="network", type="string", example="polygon"),
     *                 @OA\Property(property="token", type="string", example="USDC"),
     *                 @OA\Property(property="last_sent_at", type="string", format="date-time")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function recentRecipients(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $limit = min((int) $request->query('limit', '10'), 50);

        $recipients = PaymentIntent::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereNotNull('metadata->recipient_address')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (PaymentIntent $intent) => [
                'address'      => $intent->metadata['recipient_address'] ?? '',
                'network'      => $intent->network,
                'token'        => $intent->asset,
                'last_sent_at' => $intent->created_at->toIso8601String(),
            ])
            ->filter(fn (array $r) => $r['address'] !== '')
            ->unique('address')
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $recipients,
        ]);
    }
}
