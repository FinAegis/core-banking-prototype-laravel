<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Relayer;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\Services\EthRpcClient;
use App\Domain\Relayer\Services\GasStationService;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Relayer\ValueObjects\UserOperation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

class MobileRelayerController extends Controller
{
    public function __construct(
        private readonly GasStationService $gasStation,
        private readonly SmartAccountService $smartAccountService,
        private readonly BundlerInterface $bundler,
        private readonly ?EthRpcClient $rpcClient = null,
    ) {
    }

    /**
     * Get relayer status and gas prices.
     */
    #[OA\Get(
        path: '/api/v1/relayer/status',
        operationId: 'relayerStatus',
        summary: 'Get relayer status and gas prices',
        description: 'Returns current relayer system health, supported networks with gas prices and congestion levels.',
        tags: ['Relayer'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'healthy', type: 'boolean', example: true),
        new OA\Property(property: 'networks', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'chain_id', type: 'integer', example: 137),
        new OA\Property(property: 'gas_price_gwei', type: 'string', example: '30'),
        new OA\Property(property: 'congestion', type: 'string', example: 'low'),
        new OA\Property(property: 'native_currency', type: 'string', example: 'MATIC'),
        ])),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function status(): JsonResponse
    {
        $networks = [];
        foreach (SupportedNetwork::cases() as $network) {
            $networks[] = [
                'network'         => $network->value,
                'chain_id'        => $network->getChainId(),
                'gas_price_gwei'  => $network->getCurrentGasPrice(),
                'congestion'      => $network->getCongestionLevel(),
                'native_currency' => $network->getNativeCurrency(),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'healthy'    => true,
                'networks'   => $networks,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Estimate gas for a transaction.
     */
    #[OA\Post(
        path: '/api/v1/relayer/estimate-gas',
        operationId: 'relayerEstimateGas',
        summary: 'Estimate gas for a transaction',
        description: 'Estimates gas cost for a transaction on the specified network. Returns ERC-4337 compatible gas fields.',
        tags: ['Relayer'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['network', 'to'], properties: [
        new OA\Property(property: 'network', type: 'string', example: 'polygon', description: 'Target network'),
        new OA\Property(property: 'to', type: 'string', example: '0x1234...abcd', description: 'Destination address'),
        new OA\Property(property: 'value', type: 'string', example: '1000000', description: 'Transfer value'),
        new OA\Property(property: 'data', type: 'string', example: '0x', description: 'Call data'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Gas estimate returned',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'gas_price', type: 'string', example: '30'),
        new OA\Property(property: 'gas_limit', type: 'string', example: '200000'),
        new OA\Property(property: 'total_cost', type: 'string', example: '0.05'),
        new OA\Property(property: 'total_fee_usd', type: 'number', format: 'float', example: 0.05),
        new OA\Property(property: 'currency', type: 'string', example: 'MATIC'),
        new OA\Property(property: 'sponsored', type: 'boolean', example: true),
        new OA\Property(property: 'call_gas_limit', type: 'string', example: '200000'),
        new OA\Property(property: 'verification_gas_limit', type: 'string', example: '100000'),
        new OA\Property(property: 'pre_verification_gas', type: 'string', example: '21000'),
        new OA\Property(property: 'max_fee_per_gas', type: 'string', example: '30'),
        new OA\Property(property: 'max_priority_fee_per_gas', type: 'string', example: '2'),
        new OA\Property(property: 'paymaster_fee', type: 'string', example: '0'),
        new OA\Property(property: 'paymaster_fee_usd', type: 'number', format: 'float', example: 0.0),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Unsupported network',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'UNSUPPORTED_NETWORK'),
        new OA\Property(property: 'message', type: 'string', example: 'Network not supported.'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function estimateGas(Request $request): JsonResponse
    {
        $request->validate([
            'network' => ['required', 'string'],
            'to'      => ['required', 'string'],
            'value'   => ['string'],
            'data'    => ['string'],
        ]);

        $network = SupportedNetwork::tryFrom($request->input('network'));
        if (! $network) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UNSUPPORTED_NETWORK',
                    'message' => 'Network not supported.',
                ],
            ], 422);
        }

        $callData = $request->input('data', '0x');
        $estimate = $this->gasStation->estimateFee($callData, $network);

        $gasPrice = $network->getCurrentGasPrice();
        $gasLimit = (string) ($estimate['estimated_gas'] ?? 200000);
        $totalCost = $estimate['fee_usdc'] ?? '0';

        return response()->json([
            'success' => true,
            'data'    => [
                'network'                  => $network->value,
                'gas_price'                => $gasPrice,
                'gas_limit'                => $gasLimit,
                'total_cost'               => $totalCost,
                'total_fee_usd'            => (float) $totalCost,
                'currency'                 => $network->getNativeCurrency(),
                'sponsored'                => true,
                'call_gas_limit'           => $gasLimit,
                'verification_gas_limit'   => '100000',
                'pre_verification_gas'     => '21000',
                'max_fee_per_gas'          => $gasPrice,
                'max_priority_fee_per_gas' => '2',
                'paymaster_fee'            => '0',
                'paymaster_fee_usd'        => 0.0,
            ],
        ]);
    }

    /**
     * Build a UserOperation from parameters.
     */
    #[OA\Post(
        path: '/api/v1/relayer/build-userop',
        operationId: 'relayerBuildUserOp',
        summary: 'Build a UserOperation from parameters',
        description: 'Constructs an ERC-4337 UserOperation struct from the provided transaction parameters.',
        tags: ['Relayer'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['network', 'to'], properties: [
        new OA\Property(property: 'network', type: 'string', example: 'polygon', description: 'Target network'),
        new OA\Property(property: 'to', type: 'string', example: '0x1234...abcd', description: 'Destination address'),
        new OA\Property(property: 'value', type: 'string', example: '1000000', description: 'Transfer value'),
        new OA\Property(property: 'data', type: 'string', example: '0x', description: 'Call data'),
        new OA\Property(property: 'from', type: 'string', example: '0x0', description: 'Sender address'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'UserOperation built successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'user_op', type: 'object', properties: [
        new OA\Property(property: 'sender', type: 'string', example: '0x0'),
        new OA\Property(property: 'nonce', type: 'string', example: '0x0'),
        new OA\Property(property: 'initCode', type: 'string', example: '0x'),
        new OA\Property(property: 'callData', type: 'string', example: '0x'),
        new OA\Property(property: 'callGasLimit', type: 'string', example: '0x30D40'),
        new OA\Property(property: 'verificationGasLimit', type: 'string', example: '0x186A0'),
        new OA\Property(property: 'preVerificationGas', type: 'string', example: '0xC350'),
        new OA\Property(property: 'maxFeePerGas', type: 'string'),
        new OA\Property(property: 'maxPriorityFeePerGas', type: 'string'),
        new OA\Property(property: 'paymasterAndData', type: 'string', example: '0x'),
        new OA\Property(property: 'signature', type: 'string', example: '0x'),
        ]),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'entry_point', type: 'string', example: '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Unsupported network',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'UNSUPPORTED_NETWORK'),
        new OA\Property(property: 'message', type: 'string', example: 'Network not supported.'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function buildUserOp(Request $request): JsonResponse
    {
        $request->validate([
            'network' => ['required', 'string'],
            'to'      => ['required', 'string'],
            'value'   => ['string'],
            'data'    => ['string'],
        ]);

        $network = SupportedNetwork::tryFrom($request->input('network'));
        if (! $network) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UNSUPPORTED_NETWORK',
                    'message' => 'Network not supported.',
                ],
            ], 422);
        }

        $userOp = [
            'sender'               => $request->input('from', '0x0'),
            'nonce'                => '0x0',
            'initCode'             => '0x',
            'callData'             => $request->input('data', '0x'),
            'callGasLimit'         => '0x30D40',
            'verificationGasLimit' => '0x186A0',
            'preVerificationGas'   => '0xC350',
            'maxFeePerGas'         => '0x' . dechex(30000000000),
            'maxPriorityFeePerGas' => '0x' . dechex(1500000000),
            'paymasterAndData'     => '0x',
            'signature'            => '0x',
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'user_op'     => $userOp,
                'network'     => $network->value,
                'entry_point' => $network->getEntryPointAddress(),
            ],
        ]);
    }

    /**
     * Submit a signed UserOperation to the bundler.
     */
    #[OA\Post(
        path: '/api/v1/relayer/submit',
        operationId: 'relayerSubmitUserOp',
        summary: 'Submit a signed UserOperation',
        description: 'Submits a signed ERC-4337 UserOperation to the bundler for on-chain execution.',
        tags: ['Relayer'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['network', 'user_op', 'signature'], properties: [
        new OA\Property(property: 'network', type: 'string', example: 'polygon', description: 'Target network'),
        new OA\Property(property: 'user_op', type: 'object', description: 'The UserOperation struct'),
        new OA\Property(property: 'signature', type: 'string', example: '0xabcdef...', description: 'Signed UserOperation signature'),
        ]))
    )]
    #[OA\Response(
        response: 201,
        description: 'UserOperation submitted',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'user_op_hash', type: 'string', example: '0xabc123...'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'submitted_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Unsupported network',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'UNSUPPORTED_NETWORK'),
        new OA\Property(property: 'message', type: 'string', example: 'Network not supported.'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function submitUserOp(Request $request): JsonResponse
    {
        $request->validate([
            'network'   => ['required', 'string'],
            'user_op'   => ['required', 'array'],
            'signature' => ['required', 'string'],
        ]);

        $network = SupportedNetwork::tryFrom($request->input('network'));
        if (! $network) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UNSUPPORTED_NETWORK',
                    'message' => 'Network not supported.',
                ],
            ], 422);
        }

        $opData = $request->input('user_op');
        $signature = $request->input('signature');

        try {
            $userOp = new UserOperation(
                sender: $opData['sender'] ?? '0x0',
                nonce: (int) hexdec(ltrim($opData['nonce'] ?? '0x0', '0x')),
                initCode: $opData['initCode'] ?? '0x',
                callData: $opData['callData'] ?? '0x',
                callGasLimit: (int) hexdec(ltrim($opData['callGasLimit'] ?? '0x0', '0x')),
                verificationGasLimit: (int) hexdec(ltrim($opData['verificationGasLimit'] ?? '0x0', '0x')),
                preVerificationGas: (int) hexdec(ltrim($opData['preVerificationGas'] ?? '0x0', '0x')),
                maxFeePerGas: (int) hexdec(ltrim($opData['maxFeePerGas'] ?? '0x0', '0x')),
                maxPriorityFeePerGas: (int) hexdec(ltrim($opData['maxPriorityFeePerGas'] ?? '0x0', '0x')),
                paymasterAndData: $opData['paymasterAndData'] ?? '0x',
                signature: $signature,
            );

            $hash = $this->bundler->submitUserOperation($userOp, $network);

            return response()->json([
                'success' => true,
                'data'    => [
                    'user_op_hash' => $hash,
                    'network'      => $network->value,
                    'status'       => 'pending',
                    'submitted_at' => now()->toIso8601String(),
                ],
            ], 201);
        } catch (Throwable $e) {
            Log::error('UserOp submission failed', [
                'network' => $network->value,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'BUNDLER_ERROR',
                    'message' => 'Failed to submit UserOperation to bundler.',
                ],
            ], 502);
        }
    }

    /**
     * Get UserOperation status by hash.
     */
    #[OA\Get(
        path: '/api/v1/relayer/userop/{hash}',
        operationId: 'relayerGetUserOp',
        summary: 'Get UserOperation status by hash',
        description: 'Returns the current status and details of a previously submitted UserOperation.',
        tags: ['Relayer'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'hash', in: 'path', required: true, description: 'The UserOperation hash', schema: new OA\Schema(type: 'string', example: '0xabc123...')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'UserOperation details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'user_op_hash', type: 'string', example: '0xabc123...'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'tx_hash', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'block_number', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function getUserOp(string $hash): JsonResponse
    {
        try {
            $result = $this->bundler->getUserOperationStatus($hash);

            $status = match ($result['status'] ?? 'unknown') {
                'success', 'confirmed', 'complete' => 'confirmed',
                'reverted', 'failed'               => 'failed',
                default                            => 'pending',
            };

            return response()->json([
                'success' => true,
                'data'    => [
                    'user_op_hash' => $hash,
                    'status'       => $status,
                    'tx_hash'      => $result['tx_hash'] ?? null,
                    'block_number' => $result['receipt']['blockNumber'] ?? null,
                    'updated_at'   => now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('UserOp status query failed', [
                'hash'  => $hash,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'user_op_hash' => $hash,
                    'status'       => 'pending',
                    'tx_hash'      => null,
                    'block_number' => null,
                    'updated_at'   => now()->toIso8601String(),
                ],
            ]);
        }
    }

    /**
     * Get tokens accepted for gas payment (paymaster).
     */
    #[OA\Get(
        path: '/api/v1/relayer/supported-tokens',
        operationId: 'relayerSupportedTokens',
        summary: 'Get tokens accepted for gas payment',
        description: 'Returns the list of tokens accepted by the paymaster for sponsored gas payments.',
        tags: ['Relayer'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Supported tokens list',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'symbol', type: 'string', example: 'USDC'),
        new OA\Property(property: 'name', type: 'string', example: 'USD Coin'),
        new OA\Property(property: 'sponsored', type: 'boolean', example: true),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function supportedTokens(): JsonResponse
    {
        $tokens = [
            ['symbol' => 'USDC', 'name' => 'USD Coin', 'sponsored' => true],
            ['symbol' => 'USDT', 'name' => 'Tether USD', 'sponsored' => true],
            ['symbol' => 'WETH', 'name' => 'Wrapped Ether', 'sponsored' => false],
        ];

        return response()->json([
            'success' => true,
            'data'    => $tokens,
        ]);
    }

    /**
     * Get paymaster configuration data.
     */
    #[OA\Get(
        path: '/api/v1/relayer/paymaster-data',
        operationId: 'relayerPaymasterData',
        summary: 'Get paymaster configuration data',
        description: 'Returns paymaster addresses, entry points, sponsored tokens and gas limits per network.',
        tags: ['Relayer'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Paymaster configuration per network',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'paymaster_address', type: 'string', example: '0x1234...'),
        new OA\Property(property: 'entry_point', type: 'string', example: '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789'),
        new OA\Property(property: 'sponsored_tokens', type: 'array', example: ['USDC', 'USDT'], items: new OA\Items(type: 'string')),
        new OA\Property(property: 'max_gas_sponsored', type: 'string', example: '500000'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function paymasterData(): JsonResponse
    {
        $data = [];
        foreach (SupportedNetwork::cases() as $network) {
            $data[] = [
                'network'           => $network->value,
                'paymaster_address' => $network->getPaymasterAddress(),
                'entry_point'       => $network->getEntryPointAddress(),
                'sponsored_tokens'  => ['USDC', 'USDT'],
                'max_gas_sponsored' => '500000',
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Get per-network relayer status.
     */
    #[OA\Get(
        path: '/api/v1/relayer/networks/{network}/status',
        operationId: 'relayerNetworkStatus',
        summary: 'Get per-network relayer status',
        description: 'Returns detailed relayer status for a specific network including gas prices, block number and relayer queue depth.',
        tags: ['Relayer'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'network', in: 'path', required: true, description: 'Network identifier (e.g. polygon, ethereum, arbitrum)', schema: new OA\Schema(type: 'string', example: 'polygon')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Network relayer status',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'chainId', type: 'integer', example: 137),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'status', type: 'string', example: 'operational'),
        new OA\Property(property: 'gasPrice', type: 'object', properties: [
        new OA\Property(property: 'gwei', type: 'number', format: 'float', example: 30.0),
        new OA\Property(property: 'usdEstimate', type: 'number', format: 'float', example: 0.01),
        ]),
        new OA\Property(property: 'blockNumber', type: 'integer', example: 55000000),
        new OA\Property(property: 'relayer', type: 'object', properties: [
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'queueDepth', type: 'integer', example: 0),
        new OA\Property(property: 'avgConfirmationMs', type: 'integer', example: 2400),
        ]),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Unsupported network',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'UNSUPPORTED_NETWORK'),
        new OA\Property(property: 'message', type: 'string', example: 'Network \'unsupported\' is not supported'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function networkStatus(string $network): JsonResponse
    {
        $supported = SupportedNetwork::tryFrom($network);
        if (! $supported) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UNSUPPORTED_NETWORK',
                    'message' => "Network '{$network}' is not supported",
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'chainId'  => $supported->getChainId(),
                'network'  => $supported->value,
                'status'   => 'operational',
                'gasPrice' => [
                    'gwei'        => (float) $supported->getCurrentGasPrice(),
                    'usdEstimate' => $supported->getAverageGasCostUsd(),
                ],
                'blockNumber' => $this->getBlockNumber($supported),
                'relayer'     => [
                    'status'            => 'active',
                    'queueDepth'        => 0,
                    'avgConfirmationMs' => 2400,
                ],
                'updatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the current block number for a network.
     *
     * Queries the RPC node when available, falls back to random_int for demo mode.
     */
    private function getBlockNumber(SupportedNetwork $network): int
    {
        if ($this->rpcClient !== null) {
            try {
                return $this->rpcClient->getBlockNumber($network);
            } catch (RpcException) {
                // Fall through to demo value
            }
        }

        return random_int(50_000_000, 60_000_000);
    }
}
