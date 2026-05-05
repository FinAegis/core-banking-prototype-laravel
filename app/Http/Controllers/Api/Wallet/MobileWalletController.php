<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wallet;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\MobilePayment\Enums\PaymentIntentStatus;
use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Domain\MobilePayment\Services\ActivityFeedService;
use App\Domain\MobilePayment\Services\TransactionDetailService;
use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Wallet\Constants\SolanaCacheKeys;
use App\Domain\Wallet\Constants\SolanaTokens;
use App\Domain\Wallet\Exceptions\IdempotencyConflictException;
use App\Domain\Wallet\Exceptions\InvalidAddressException;
use App\Domain\Wallet\Exceptions\InvalidAmountException;
use App\Domain\Wallet\Exceptions\InvalidAssetException;
use App\Domain\Wallet\Exceptions\InvalidSendStateException;
use App\Domain\Wallet\Exceptions\InvalidSignatureException;
use App\Domain\Wallet\Exceptions\NetworkDisabledException;
use App\Domain\Wallet\Factories\BlockchainConnectorFactory;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\PrivyAddressRegistrar;
use App\Domain\Wallet\Services\Send\EvmUserOpPreparer;
use App\Domain\Wallet\Services\Send\EvmUserOpSubmitter;
use App\Domain\Wallet\Services\Send\SolanaSendPreparer;
use App\Domain\Wallet\Services\Send\SolanaSendSubmitter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Throwable;

class MobileWalletController extends Controller
{
    public function __construct(
        private readonly WalletBalanceProviderInterface $balanceService,
        private readonly SmartAccountService $smartAccountService,
        private readonly ActivityFeedService $activityFeedService,
        private readonly TransactionDetailService $transactionDetailService,
        private readonly PrivyAddressRegistrar $privyAddressRegistrar,
        private readonly SolanaSendPreparer $solanaSendPreparer,
        private readonly SolanaSendSubmitter $solanaSendSubmitter,
        private readonly EvmUserOpPreparer $evmUserOpPreparer,
        private readonly EvmUserOpSubmitter $evmUserOpSubmitter,
    ) {
    }

    /**
     * Get supported token list with network and decimals info.
     */
    #[OA\Get(
        path: '/api/v1/wallet/tokens',
        operationId: 'walletTokens',
        summary: 'Get supported token list',
        description: 'Returns the list of supported tokens with network availability and decimals info.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Token list',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'symbol', type: 'string', example: 'USDC'),
        new OA\Property(property: 'name', type: 'string', example: 'USD Coin'),
        new OA\Property(property: 'decimals', type: 'integer', example: 6),
        new OA\Property(property: 'networks', type: 'array', example: ['polygon', 'base', 'arbitrum'], items: new OA\Items(type: 'string')),
        new OA\Property(property: 'icon', type: 'string', example: 'usdc'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function tokens(Request $request): JsonResponse
    {
        /** @var array<string, array{name: string, decimals: int, icon: string, networks: array<string, string>}> $registry */
        $registry = config('supported_tokens', []);
        $chainFilter = $request->query('chain_id');

        $tokens = [];
        foreach ($registry as $symbol => $meta) {
            $networks = $meta['networks'] ?? [];

            if ($chainFilter !== null) {
                $networks = array_filter(
                    $networks,
                    fn (string $network) => $network === $chainFilter,
                    ARRAY_FILTER_USE_KEY,
                );
                if (empty($networks)) {
                    continue;
                }
            }

            $tokens[] = [
                'symbol'    => $symbol,
                'name'      => $meta['name'],
                'decimals'  => $meta['decimals'],
                'networks'  => array_keys($networks),
                'icon'      => $meta['icon'],
                'addresses' => $networks,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $tokens,
        ]);
    }

    /**
     * Get token balances across user's smart accounts and Solana wallet.
     */
    #[OA\Get(
        path: '/api/v1/wallet/balances',
        operationId: 'walletBalances',
        summary: 'Get token balances (EVM + Solana)',
        description: 'Returns token balances across all of the authenticated user\'s EVM smart accounts and Solana wallet (SPL tokens + native SOL).',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Balances per token and network',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'token', type: 'string', example: 'USDC'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'address', type: 'string', example: '0x1234...abcd'),
        new OA\Property(property: 'balance', type: 'string', example: '1000.50'),
        new OA\Property(property: 'usd_value', type: 'number', format: 'float', example: 1000.50, description: 'USD equivalent of the balance'),
        new OA\Property(property: 'error', type: 'string', nullable: true, example: null, description: 'Present only if balance query failed'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function balances(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $accounts = $this->smartAccountService->getUserAccounts($user);

        $balances = [];
        $supportedTokens = ['USDC', 'USDT', 'WETH', 'WBTC'];
        $stablecoins = ['USDC', 'USDT'];

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
                    $usdValue = in_array($token, $stablecoins, true)
                        ? (float) $balance
                        : 0.0;
                    $balances[] = [
                        'token'             => $token,
                        'network'           => $networkStr,
                        'address'           => $account->account_address,
                        'balance'           => $balance,
                        'balance_formatted' => $this->formatBalance($balance, $token),
                        'usd_value'         => $usdValue,
                        'change_24h'        => 0.0,
                    ];
                } catch (Throwable) {
                    $balances[] = [
                        'token'             => $token,
                        'network'           => $networkStr,
                        'address'           => $account->account_address,
                        'balance'           => '0',
                        'balance_formatted' => '0.00',
                        'usd_value'         => 0.0,
                        'change_24h'        => 0.0,
                        'error'             => 'Balance query failed',
                    ];
                }
            }
        }

        // Query Solana token balances
        $solanaAddress = BlockchainAddress::where('user_uuid', $user->uuid)
            ->where('chain', 'solana')
            ->where('is_active', true)
            ->first();

        if ($solanaAddress) {
            $solanaBalances = $this->fetchSolanaBalances($solanaAddress);
            foreach ($solanaBalances as $bal) {
                $balances[] = array_merge($bal, [
                    'address'           => $solanaAddress->address,
                    'balance_formatted' => $bal['balance'],
                    'change_24h'        => 0.0,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $balances,
        ]);
    }

    /**
     * Get aggregated wallet state (balances + addresses + sync info).
     */
    #[OA\Get(
        path: '/api/v1/wallet/state',
        operationId: 'walletState',
        summary: 'Get aggregated wallet state',
        description: 'Returns aggregated wallet state including addresses, balances, supported networks and sync information.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Aggregated wallet state',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'addresses', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'address', type: 'string', example: '0x1234...abcd'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'deployed', type: 'boolean', example: true),
        ])),
        new OA\Property(property: 'balances', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'token', type: 'string', example: 'USDC'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'balance', type: 'string', example: '1000.50'),
        new OA\Property(property: 'usd_value', type: 'number', format: 'float', example: 1000.50),
        ])),
        new OA\Property(property: 'total_usd_value', type: 'number', format: 'float', example: 2500.75),
        new OA\Property(property: 'shielded_balance', type: 'number', format: 'float', example: 0.0),
        new OA\Property(property: 'networks', type: 'array', example: ['polygon', 'ethereum', 'arbitrum'], items: new OA\Items(type: 'string')),
        new OA\Property(property: 'synced_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'account_count', type: 'integer', example: 2),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function state(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $accounts = $this->smartAccountService->getUserAccounts($user);

        $addresses = [];
        $balances = [];
        $totalUsdValue = 0.0;
        $supportedTokens = ['USDC', 'USDT', 'WETH', 'WBTC'];
        $stablecoins = ['USDC', 'USDT'];

        foreach ($accounts as $account) {
            $networkStr = $account->network ?? 'polygon';
            $addresses[] = [
                'address'  => $account->account_address,
                'network'  => $networkStr,
                'deployed' => $account->is_deployed ?? false,
            ];

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
                    $usdValue = in_array($token, $stablecoins, true)
                        ? (float) $balance
                        : 0.0;
                    $totalUsdValue += $usdValue;
                    $balances[] = [
                        'token'     => $token,
                        'network'   => $networkStr,
                        'balance'   => $balance,
                        'usd_value' => $usdValue,
                    ];
                } catch (Throwable) {
                    $balances[] = [
                        'token'     => $token,
                        'network'   => $networkStr,
                        'balance'   => '0',
                        'usd_value' => 0.0,
                    ];
                }
            }
        }

        // Query Solana address and balances
        $solanaAddress = BlockchainAddress::where('user_uuid', $user->uuid)
            ->where('chain', 'solana')
            ->where('is_active', true)
            ->first();

        if ($solanaAddress) {
            $addresses[] = ['address' => $solanaAddress->address, 'network' => 'solana', 'deployed' => true];
            $solanaBalances = $this->fetchSolanaBalances($solanaAddress);
            foreach ($solanaBalances as $bal) {
                $totalUsdValue += $bal['usd_value'];
                $balances[] = $bal;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'addresses'        => $addresses,
                'balances'         => $balances,
                'total_usd_value'  => $totalUsdValue,
                'shielded_balance' => 0.0,
                'networks'         => $this->smartAccountService->getSupportedNetworks(),
                'synced_at'        => now()->toIso8601String(),
                'account_count'    => count($accounts),
            ],
        ]);
    }

    /**
     * List user's addresses per network.
     */
    #[OA\Get(
        path: '/api/v1/wallet/addresses',
        operationId: 'walletAddresses',
        summary: 'List user\'s addresses per network',
        description: 'Returns all smart account addresses for the authenticated user across supported networks.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'User addresses',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'address', type: 'string', example: '0x1234...abcd'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'deployed', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function addresses(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $records = BlockchainAddress::where('user_uuid', $user->uuid)
            ->where('is_active', true)
            ->orderBy('chain')
            ->get();

        $addresses = $records->map(function (BlockchainAddress $record): array {
            return [
                'address'    => $record->address,
                'network'    => $record->chain,
                'type'       => $record->chain === 'solana' ? 'keypair' : 'smart_account',
                'deployed'   => true, // Privy-managed; deployment is handled at first-tx time
                'created_at' => $record->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data'    => $addresses,
        ]);
    }

    /**
     * Register Privy-derived addresses for the authenticated user.
     *
     * Mobile derives addresses on-device via Privy (passkey-controlled smart
     * account on EVM, embedded ed25519 on Solana) and POSTs them here so the
     * backend can index balances, fire Helius webhook sync, etc. The backend
     * stores public addresses only — keys never leave the device.
     */
    #[OA\Post(
        path: '/api/v1/wallet/addresses',
        operationId: 'walletRegisterAddresses',
        summary: 'Register Privy-derived addresses',
        description: 'Stores the user\'s on-device-derived EVM smart-account address (same across all 4 EVM chains) plus their Solana ed25519 pubkey. Idempotent — re-posting the same payload is a no-op.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['evm', 'solana'], properties: [
        new OA\Property(property: 'evm', type: 'object', required: ['address'], properties: [
        new OA\Property(property: 'address', type: 'string', example: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'),
        new OA\Property(property: 'ownerPasskeyCredentialId', type: 'string', nullable: true, example: 'cred_abc123'),
        ]),
        new OA\Property(property: 'solana', type: 'object', required: ['address'], properties: [
        new OA\Property(property: 'address', type: 'string', example: 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'),
        ]),
        ]))
    )]
    #[OA\Response(response: 201, description: 'Addresses registered')]
    #[OA\Response(response: 422, description: 'Invalid address or address already owned by a different user')]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function registerAddresses(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'evm.address'                  => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'evm.ownerPasskeyCredentialId' => ['nullable', 'string', 'max:512'],
            'solana.address'               => ['required', 'string', 'min:32', 'max:44'],
        ]);

        try {
            $records = $this->privyAddressRegistrar->register(
                user: $user,
                evm: [
                    'address'                     => (string) $validated['evm']['address'],
                    'owner_passkey_credential_id' => $validated['evm']['ownerPasskeyCredentialId'] ?? null,
                ],
                solana: ['address' => (string) $validated['solana']['address']],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_ADDRESS', 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'addresses' => collect($records)->map(fn (BlockchainAddress $r): array => [
                    'address' => $r->address,
                    'network' => $r->chain,
                ])->values()->all(),
            ],
        ], 201);
    }

    /**
     * Cursor-based transaction list from activity feed.
     */
    #[OA\Get(
        path: '/api/v1/wallet/transactions',
        operationId: 'walletTransactions',
        summary: 'List transactions with cursor-based pagination',
        description: 'Returns a paginated list of transactions from the user\'s activity feed using cursor-based pagination.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'cursor', in: 'query', required: false, description: 'Pagination cursor for the next page', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Number of items per page (max 100, default 20)', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction feed',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', description: 'Activity feed with items and pagination cursor'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

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
     */
    #[OA\Get(
        path: '/api/v1/wallet/transactions/{id}',
        operationId: 'walletTransactionDetail',
        summary: 'Get transaction detail',
        description: 'Returns detailed information for a specific transaction by ID.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Transaction identifier', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction detail',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', description: 'Transaction detail object'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Transaction not found',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'TRANSACTION_NOT_FOUND'),
        new OA\Property(property: 'message', type: 'string', example: 'Transaction not found.'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function transactionDetail(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
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
     * Step 1 of a non-custodial send: build the unsigned payload for the
     * device to sign.
     *
     * Mobile must read `Idempotency-Key` from a previous attempt to replay.
     * Same key + same body → same `intentId` + same payload-to-sign.
     * Same key + different body → 409 IDEMPOTENCY_CONFLICT.
     */
    #[OA\Post(
        path: '/api/v1/wallet/transactions/prepare',
        operationId: 'walletTransactionPrepare',
        summary: 'Prepare an unsigned send payload',
        description: 'Builds the unsigned Solana legacy-tx message bytes (ed25519) or ERC-4337 v0.6 UserOp hash (with Pimlico paymaster sponsorship) for the device to sign via Privy. Persists a `pending` wallet_send_record. Mobile signs and POSTs the signature to /transactions/submit.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['to', 'token', 'amount', 'network', 'quoteId'], properties: [
        new OA\Property(property: 'to', type: 'string', example: '0x1234...abcd or base58 Solana pubkey'),
        new OA\Property(property: 'token', type: 'string', enum: ['USDC', 'USDT'], example: 'USDC'),
        new OA\Property(property: 'amount', type: 'string', example: '1.50', description: 'Decimal major units. Send "1" or "1.5"; not "1000000".'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon', description: 'solana | polygon | base | arbitrum | ethereum'),
        new OA\Property(property: 'quoteId', type: 'string', example: 'q_abc123'),
        ]))
    )]
    #[OA\Response(response: 201, description: 'Unsigned payload ready')]
    #[OA\Response(response: 409, description: 'Idempotency conflict')]
    #[OA\Response(response: 422, description: 'Validation / address / asset / amount / network error')]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function prepareTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to'      => ['required', 'string', 'min:26', 'max:128'],
            'token'   => ['required', 'string', 'in:USDC,USDT'],
            'amount'  => ['required', 'string'],
            'network' => ['required', 'string', 'in:solana,polygon,base,arbitrum,ethereum'],
            'quoteId' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Not authenticated.']], 401);
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($idempotencyKey) ? $idempotencyKey : null;

        $networkKey = strtolower((string) $validated['network']);
        $assetSymbol = strtoupper((string) $validated['token']);
        $recipient = (string) $validated['to'];
        $amount = (string) $validated['amount'];
        $quoteId = (string) $validated['quoteId'];

        $sender = $this->resolveSenderAddress($user, $networkKey);
        if ($sender === null) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NO_SENDER_ADDRESS', 'message' => 'No registered address for this network. POST /api/v1/wallet/addresses first.'],
            ], 422);
        }

        try {
            if ($networkKey === 'solana') {
                $result = $this->solanaSendPreparer->prepare(
                    user: $user,
                    senderAddressBase58: $sender,
                    recipientAddressBase58: $recipient,
                    assetSymbol: $assetSymbol,
                    amountMajor: $amount,
                    idempotencyKey: $idempotencyKey,
                    quoteId: $quoteId,
                );
            } else {
                $result = $this->evmUserOpPreparer->prepare(
                    user: $user,
                    senderSmartAccountAddress: $sender,
                    recipientAddress: $recipient,
                    assetSymbol: $assetSymbol,
                    networkKey: $networkKey,
                    amountMajor: $amount,
                    idempotencyKey: $idempotencyKey,
                    quoteId: $quoteId,
                );
            }
        } catch (IdempotencyConflictException $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'IDEMPOTENCY_CONFLICT', 'message' => $e->getMessage()],
            ], 409);
        } catch (NetworkDisabledException | InvalidAssetException | InvalidAddressException | InvalidAmountException $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => $this->errorCodeFor($e), 'message' => $e->getMessage()],
            ], 422);
        } catch (Throwable $e) {
            Log::warning('wallet.prepare failed', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => ['code' => 'PREPARE_FAILED', 'message' => 'Could not prepare the transaction.'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'intentId' => $result['record']->public_id,
                'payload'  => $result['payload'],
                'record'   => $result['record']->toApiResponse(),
            ],
        ], 201);
    }

    /**
     * Step 2 of a non-custodial send: attach the device-produced signature
     * and broadcast.
     */
    #[OA\Post(
        path: '/api/v1/wallet/transactions/submit',
        operationId: 'walletTransactionSubmit',
        summary: 'Submit a signed payload',
        description: 'Attaches a Privy-produced signature (ed25519 for Solana, smart-wallet signature blob for EVM) to the matching prepared record and broadcasts via Helius / Pimlico.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['intentId', 'signature'], properties: [
        new OA\Property(property: 'intentId', type: 'string', example: 'pi_send_abc123'),
        new OA\Property(property: 'signature', type: 'string', example: '0x... or base64 ed25519', description: 'For Solana: 64-byte ed25519 signature, base64-encoded. For EVM: 0x-prefixed hex signature blob from Privy smart wallet.'),
        ]))
    )]
    #[OA\Response(response: 200, description: 'Submitted (or already submitted)')]
    #[OA\Response(response: 404, description: 'Intent not found for this user')]
    #[OA\Response(response: 422, description: 'Bad signature / state')]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function submitTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'intentId'  => ['required', 'string', 'max:64'],
            'signature' => ['required', 'string', 'min:1', 'max:8192'],
        ]);

        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Not authenticated.']], 401);
        }

        $record = WalletSendRecord::query()
            ->where('user_id', $user->id)
            ->where('public_id', (string) $validated['intentId'])
            ->first();

        if (! $record instanceof WalletSendRecord) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INTENT_NOT_FOUND', 'message' => 'No prepared intent matches this id.'],
            ], 404);
        }

        try {
            if ($record->network === 'solana') {
                $record = $this->solanaSendSubmitter->submit($record, (string) $validated['signature']);
            } else {
                $record = $this->evmUserOpSubmitter->submit($record, (string) $validated['signature']);
            }
        } catch (InvalidSignatureException | InvalidSendStateException $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => $this->errorCodeFor($e), 'message' => $e->getMessage()],
            ], 422);
        } catch (Throwable $e) {
            Log::warning('wallet.submit failed', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => ['code' => 'SUBMIT_FAILED', 'message' => 'Could not submit the transaction.'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $record->toApiResponse(),
        ]);
    }

    /**
     * Look up the user's registered Privy address for the given network.
     *
     * EVM smart accounts share an address across all four EVM chains (one
     * row per chain in `blockchain_addresses` with the same `address` value).
     */
    private function resolveSenderAddress(\App\Models\User $user, string $networkKey): ?string
    {
        $row = BlockchainAddress::where('user_uuid', $user->uuid)
            ->where('chain', $networkKey)
            ->where('is_active', true)
            ->first();

        return $row?->address;
    }

    /**
     * Map a Wallet domain exception to the canonical error code surfaced in
     * API responses. Falls back to the class short name uppercased.
     */
    private function errorCodeFor(Throwable $e): string
    {
        return match (true) {
            $e instanceof IdempotencyConflictException => 'IDEMPOTENCY_CONFLICT',
            $e instanceof NetworkDisabledException     => 'NETWORK_DISABLED',
            $e instanceof InvalidAssetException        => 'INVALID_ASSET',
            $e instanceof InvalidAddressException      => 'INVALID_ADDRESS',
            $e instanceof InvalidAmountException       => 'INVALID_AMOUNT',
            $e instanceof InvalidSignatureException    => 'INVALID_SIGNATURE',
            $e instanceof InvalidSendStateException    => 'INVALID_SEND_STATE',
            default                                    => 'WALLET_SEND_ERROR',
        };
    }

    /**
     * Get recent recipient addresses from send history.
     */
    #[OA\Get(
        path: '/api/v1/wallet/recent-recipients',
        operationId: 'walletRecentRecipients',
        summary: 'Get recent send recipients',
        description: 'Returns unique recipient addresses from recent send transactions, ordered by most recent.',
        tags: ['Mobile Wallet'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Number of recipients to return (max 50, default 10)', schema: new OA\Schema(type: 'integer', default: 10, maximum: 50)),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Recent recipients list',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'address', type: 'string', example: '0x1234...abcd'),
        new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Alice Johnson'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'token', type: 'string', example: 'USDC'),
        new OA\Property(property: 'last_sent_at', type: 'string', format: 'date-time'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function recentRecipients(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $limit = min((int) $request->query('limit', '10'), 50);

        $recipients = PaymentIntent::where('user_id', $user->id)
            ->whereIn('status', [PaymentIntentStatus::CONFIRMED, PaymentIntentStatus::PENDING])
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

        // Resolve recipient names from blockchain_addresses → users
        $addresses = $recipients->pluck('address')->all();
        $nameMap = BlockchainAddress::whereIn('address', $addresses)
            ->with('user:uuid,name')
            ->get()
            ->keyBy('address')
            ->map(fn (BlockchainAddress $ba) => $ba->user?->name);

        $recipients = $recipients->map(fn (array $r) => array_merge(
            ['address' => $r['address'], 'name' => $nameMap[$r['address']] ?? null],
            ['network' => $r['network'], 'token' => $r['token'], 'last_sent_at' => $r['last_sent_at']],
        ));

        return response()->json([
            'success' => true,
            'data'    => $recipients,
        ]);
    }

    /**
     * Fetch Solana SPL token and native SOL balances for a blockchain address.
     *
     * @return array<int, array{token: string, network: string, balance: string, usd_value: float}>
     */
    private function fetchSolanaBalances(BlockchainAddress $solanaAddress): array
    {
        $balances = [];

        try {
            $cacheKey = SolanaCacheKeys::balances($solanaAddress->address);
            $solanaTokens = Cache::remember($cacheKey, (int) config('relayer.balance_checking.cache_ttl_seconds', 120), function () use ($solanaAddress): array {
                try {
                    $connector = BlockchainConnectorFactory::create('solana');

                    return $connector->getTokenBalances($solanaAddress->address);
                } catch (Throwable) {
                    return [];
                }
            });

            $knownMints = SolanaTokens::KNOWN_MINTS;

            foreach ($solanaTokens as $token) {
                $mint = $token['contract'] ?? '';
                $info = $knownMints[$mint] ?? null;
                if ($info === null) {
                    continue;
                }

                $rawBalance = $token['balance'] ?? '0';
                $decimals = $info['decimals'];
                $formatted = bcdiv(
                    is_numeric($rawBalance) ? (string) $rawBalance : '0',
                    bcpow('10', (string) $decimals),
                    $decimals,
                );

                $balances[] = [
                    'token'     => $info['symbol'],
                    'network'   => 'solana',
                    'balance'   => $formatted,
                    'usd_value' => (float) $formatted, // Stablecoins pegged 1:1
                ];
            }

            // Also get native SOL balance
            $connector = BlockchainConnectorFactory::create('solana');
            $solBalance = Cache::remember(SolanaCacheKeys::balance($solanaAddress->address), (int) config('relayer.balance_checking.cache_ttl_seconds', 120), function () use ($connector, $solanaAddress): string {
                return $connector->getBalance($solanaAddress->address)->balance;
            });

            $solFormatted = bcdiv(
                is_numeric($solBalance) ? (string) $solBalance : '0',
                '1000000000',
                9,
            );
            if (bccomp($solFormatted, '0', 9) > 0) {
                $balances[] = [
                    'token'     => 'SOL',
                    'network'   => 'solana',
                    'balance'   => $solFormatted,
                    'usd_value' => 0.0, // SOL price not tracked
                ];
            }
        } catch (Throwable) {
            // Solana balances are best-effort
        }

        return $balances;
    }

    /**
     * Format a raw balance string for display.
     */
    private function formatBalance(string $balance, string $token): string
    {
        $decimals = in_array($token, ['USDC', 'USDT'], true) ? 2 : 6;

        return number_format((float) $balance, $decimals, '.', ',');
    }
}
