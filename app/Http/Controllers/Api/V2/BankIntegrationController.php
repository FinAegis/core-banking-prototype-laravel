<?php

namespace App\Http\Controllers\Api\V2;

use App\Domain\Banking\Contracts\IBankIntegrationService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BankIntegrationController extends Controller
{
    private IBankIntegrationService $bankService;

    public function __construct(IBankIntegrationService $bankService)
    {
        $this->bankService = $bankService;
    }

    /**
     * Get available banks.
     *
     * @OA\Get(
     *     path="/api/v2/banks/available",
     *     operationId="bankGetAvailableBanks",
     *     summary="Get available banks",
     *     description="Returns list of available bank connectors with their capabilities, supported currencies, and features.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="code", type="string", example="revolut"),
     *                     @OA\Property(property="name", type="string", example="Revolut"),
     *                     @OA\Property(property="available", type="boolean", example=true),
     *                     @OA\Property(property="supported_currencies", type="array", @OA\Items(type="string", example="EUR")),
     *                     @OA\Property(property="supported_transfer_types", type="array", @OA\Items(type="string", example="SEPA")),
     *                     @OA\Property(property="features", type="array", @OA\Items(type="string", example="instant_transfers")),
     *                     @OA\Property(property="supports_instant_transfers", type="boolean", example=true),
     *                     @OA\Property(property="supports_multi_currency", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getAvailableBanks(): JsonResponse
    {
        try {
            $banks = $this->bankService->getAvailableConnectors()
                ->map(
                    function ($connector, $code) {
                        $capabilities = $connector->getCapabilities();

                        return [
                            'code'                       => $code,
                            'name'                       => $connector->getBankName(),
                            'available'                  => $connector->isAvailable(),
                            'supported_currencies'       => $capabilities->supportedCurrencies,
                            'supported_transfer_types'   => $capabilities->supportedTransferTypes,
                            'features'                   => $capabilities->features,
                            'supports_instant_transfers' => $capabilities->supportsInstantTransfers,
                            'supports_multi_currency'    => $capabilities->supportsMultiCurrency,
                        ];
                    }
                );

            return response()->json(
                [
                    'data' => $banks->values(),
                ]
            );
        } catch (Exception $e) {
            Log::error('Failed to get available banks', ['error' => $e->getMessage()]);

            return response()->json(
                [
                    'error' => 'Failed to retrieve available banks',
                ],
                500
            );
        }
    }

    /**
     * Get user's bank connections.
     *
     * @OA\Get(
     *     path="/api/v2/banks/connections",
     *     operationId="bankGetUserConnections",
     *     summary="Get user bank connections",
     *     description="Returns list of the authenticated user's bank connections with status and sync information.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="string", example="conn_abc123"),
     *                     @OA\Property(property="bank_code", type="string", example="revolut"),
     *                     @OA\Property(property="status", type="string", example="active"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(property="needs_renewal", type="boolean", example=false),
     *                     @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="read_accounts")),
     *                     @OA\Property(property="last_sync_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getUserConnections(Request $request): JsonResponse
    {
        try {
            $connections = $this->bankService->getUserBankConnections($request->user())
                ->map(
                    function ($connection) {
                        return [
                            'id'            => $connection->id,
                            'bank_code'     => $connection->bankCode,
                            'status'        => $connection->status,
                            'active'        => $connection->isActive(),
                            'needs_renewal' => $connection->needsRenewal(),
                            'permissions'   => $connection->permissions,
                            'last_sync_at'  => $connection->lastSyncAt?->toIso8601String(),
                            'expires_at'    => $connection->expiresAt?->toIso8601String(),
                            'created_at'    => $connection->createdAt->toIso8601String(),
                        ];
                    }
                );

            return response()->json(
                [
                    'data' => $connections,
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to get user bank connections',
                [
                    'user_id' => $request->user()->uuid,
                    'error'   => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to retrieve bank connections',
                ],
                500
            );
        }
    }

    /**
     * Connect to a bank.
     *
     * @OA\Post(
     *     path="/api/v2/banks/connect",
     *     operationId="bankConnectBank",
     *     summary="Connect to a bank",
     *     description="Establishes a new connection between the authenticated user and a bank using provided credentials.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bank_code", "credentials"},
     *             @OA\Property(property="bank_code", type="string", example="revolut"),
     *             @OA\Property(property="credentials", type="object",
     *                 @OA\Property(property="api_key", type="string", example="sk_live_xxx"),
     *                 @OA\Property(property="api_secret", type="string", example="secret_xxx")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bank connected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="conn_abc123"),
     *                 @OA\Property(property="bank_code", type="string", example="revolut"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="message", type="string", example="Successfully connected to bank")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or connection failure")
     * )
     */
    public function connectBank(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'bank_code'   => 'required|string',
                'credentials' => 'required|array',
            ]
        );

        try {
            $connection = $this->bankService->connectUserToBank(
                $request->user(),
                $validated['bank_code'],
                $validated['credentials']
            );

            return response()->json(
                [
                    'data' => [
                        'id'         => $connection->id,
                        'bank_code'  => $connection->bankCode,
                        'status'     => $connection->status,
                        'expires_at' => $connection->expiresAt?->toIso8601String(),
                        'message'    => 'Successfully connected to bank',
                    ],
                ],
                201
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to connect to bank',
                [
                    'user_id'   => $request->user()->uuid,
                    'bank_code' => $validated['bank_code'],
                    'error'     => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to connect to bank: ' . $e->getMessage(),
                ],
                422
            );
        }
    }

    /**
     * Disconnect from a bank.
     *
     * @OA\Delete(
     *     path="/api/v2/banks/disconnect/{bankCode}",
     *     operationId="bankDisconnectBank",
     *     summary="Disconnect from a bank",
     *     description="Removes the authenticated user's connection to the specified bank.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="bankCode",
     *         in="path",
     *         required=true,
     *         description="Bank connector code",
     *         @OA\Schema(type="string", example="revolut")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully disconnected",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully disconnected from bank")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bank connection not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function disconnectBank(Request $request, string $bankCode): JsonResponse
    {
        try {
            $success = $this->bankService->disconnectUserFromBank(
                $request->user(),
                $bankCode
            );

            if ($success) {
                return response()->json(
                    [
                        'message' => 'Successfully disconnected from bank',
                    ]
                );
            } else {
                return response()->json(
                    [
                        'error' => 'Bank connection not found',
                    ],
                    404
                );
            }
        } catch (Exception $e) {
            Log::error(
                'Failed to disconnect from bank',
                [
                    'user_id'   => $request->user()->uuid,
                    'bank_code' => $bankCode,
                    'error'     => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to disconnect from bank',
                ],
                500
            );
        }
    }

    /**
     * Get user's bank accounts.
     *
     * @OA\Get(
     *     path="/api/v2/banks/accounts",
     *     operationId="bankGetBankAccounts",
     *     summary="Get user bank accounts",
     *     description="Returns list of the authenticated user's bank accounts, optionally filtered by bank code.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="bank_code",
     *         in="query",
     *         required=false,
     *         description="Filter accounts by bank code",
     *         @OA\Schema(type="string", example="revolut")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="string", example="acct_abc123"),
     *                     @OA\Property(property="bank_code", type="string", example="revolut"),
     *                     @OA\Property(property="account_number", type="string", example="***1234"),
     *                     @OA\Property(property="iban", type="string", example="GB82***5678"),
     *                     @OA\Property(property="currency", type="string", example="EUR"),
     *                     @OA\Property(property="account_type", type="string", example="checking"),
     *                     @OA\Property(property="status", type="string", example="active"),
     *                     @OA\Property(property="label", type="string", example="Main Account"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getBankAccounts(Request $request): JsonResponse
    {
        $bankCode = $request->query('bank_code');

        try {
            $accounts = $this->bankService->getUserBankAccounts($request->user(), $bankCode)
                ->map(
                    function ($account) {
                        return [
                            'id'             => $account->id,
                            'bank_code'      => $account->bankCode,
                            'account_number' => '***' . substr($account->accountNumber, -4),
                            'iban'           => substr($account->iban, 0, 4) . '***' . substr($account->iban, -4),
                            'currency'       => $account->currency,
                            'account_type'   => $account->accountType,
                            'status'         => $account->status,
                            'label'          => $account->getLabel(),
                            'created_at'     => $account->createdAt->toIso8601String(),
                        ];
                    }
                );

            return response()->json(
                [
                    'data' => $accounts,
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to get bank accounts',
                [
                    'user_id'   => $request->user()->uuid,
                    'bank_code' => $bankCode,
                    'error'     => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to retrieve bank accounts',
                ],
                500
            );
        }
    }

    /**
     * Sync bank accounts.
     *
     * @OA\Post(
     *     path="/api/v2/banks/accounts/sync/{bankCode}",
     *     operationId="bankSyncAccounts",
     *     summary="Sync bank accounts",
     *     description="Triggers a synchronization of the authenticated user's accounts for the specified bank.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="bankCode",
     *         in="path",
     *         required=true,
     *         description="Bank connector code",
     *         @OA\Schema(type="string", example="revolut")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Accounts synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bank accounts synced successfully"),
     *             @OA\Property(property="accounts_synced", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function syncAccounts(Request $request, string $bankCode): JsonResponse
    {
        try {
            $accounts = $this->bankService->syncBankAccounts($request->user(), $bankCode);

            return response()->json(
                [
                    'message'         => 'Bank accounts synced successfully',
                    'accounts_synced' => $accounts->count(),
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to sync bank accounts',
                [
                    'user_id'   => $request->user()->uuid,
                    'bank_code' => $bankCode,
                    'error'     => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to sync bank accounts',
                ],
                500
            );
        }
    }

    /**
     * Get aggregated balance.
     *
     * @OA\Get(
     *     path="/api/v2/banks/balance/aggregate",
     *     operationId="bankGetAggregatedBalance",
     *     summary="Get aggregated balance",
     *     description="Returns the aggregated balance across all connected bank accounts for the specified currency.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="currency",
     *         in="query",
     *         required=true,
     *         description="Three-letter ISO currency code",
     *         @OA\Schema(type="string", minLength=3, maxLength=3, example="EUR")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="currency", type="string", example="EUR"),
     *                 @OA\Property(property="balance", type="integer", example=150000),
     *                 @OA\Property(property="formatted", type="string", example="1,500.00")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getAggregatedBalance(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'currency' => 'required|string|size:3',
            ]
        );

        try {
            $balance = $this->bankService->getAggregatedBalance(
                $request->user(),
                strtoupper($validated['currency'])
            );

            return response()->json(
                [
                    'data' => [
                        'currency'  => $validated['currency'],
                        'balance'   => $balance,
                        'formatted' => number_format($balance / 100, 2),
                    ],
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to get aggregated balance',
                [
                    'user_id'  => $request->user()->uuid,
                    'currency' => $validated['currency'],
                    'error'    => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to retrieve aggregated balance',
                ],
                500
            );
        }
    }

    /**
     * Initiate inter-bank transfer.
     *
     * @OA\Post(
     *     path="/api/v2/banks/transfer",
     *     operationId="bankInitiateTransfer",
     *     summary="Initiate inter-bank transfer",
     *     description="Initiates a transfer between bank accounts, potentially across different banks. Amount is specified in the major currency unit (e.g., euros) and converted to minor units internally.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_bank_code", "from_account_id", "to_bank_code", "to_account_id", "amount", "currency"},
     *             @OA\Property(property="from_bank_code", type="string", example="revolut"),
     *             @OA\Property(property="from_account_id", type="string", example="acct_src_123"),
     *             @OA\Property(property="to_bank_code", type="string", example="wise"),
     *             @OA\Property(property="to_account_id", type="string", example="acct_dst_456"),
     *             @OA\Property(property="amount", type="number", format="float", example=100.50),
     *             @OA\Property(property="currency", type="string", minLength=3, maxLength=3, example="EUR"),
     *             @OA\Property(property="reference", type="string", nullable=true, maxLength=140, example="Invoice #1234"),
     *             @OA\Property(property="description", type="string", nullable=true, maxLength=500, example="Payment for services")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transfer initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="txn_abc123"),
     *                 @OA\Property(property="type", type="string", example="inter_bank"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="amount", type="integer", example=10050),
     *                 @OA\Property(property="currency", type="string", example="EUR"),
     *                 @OA\Property(property="reference", type="string", example="Invoice #1234"),
     *                 @OA\Property(property="total_amount", type="integer", example=10150),
     *                 @OA\Property(property="fees", type="integer", example=100),
     *                 @OA\Property(property="estimated_arrival", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or transfer failure")
     * )
     */
    public function initiateTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'from_bank_code'  => 'required|string',
                'from_account_id' => 'required|string',
                'to_bank_code'    => 'required|string',
                'to_account_id'   => 'required|string',
                'amount'          => 'required|numeric|min:0.01',
                'currency'        => 'required|string|size:3',
                'reference'       => 'nullable|string|max:140',
                'description'     => 'nullable|string|max:500',
            ]
        );

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
                    'reference'   => $validated['reference'] ?? null,
                    'description' => $validated['description'] ?? null,
                ]
            );

            return response()->json(
                [
                    'data' => [
                        'id'                => $transfer->id,
                        'type'              => $transfer->type,
                        'status'            => $transfer->status,
                        'amount'            => $transfer->amount,
                        'currency'          => $transfer->currency,
                        'reference'         => $transfer->reference,
                        'total_amount'      => $transfer->getTotalAmount(),
                        'fees'              => $transfer->fees,
                        'estimated_arrival' => $transfer->getEstimatedArrival()?->toIso8601String(),
                        'created_at'        => $transfer->createdAt->toIso8601String(),
                    ],
                ],
                201
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to initiate transfer',
                [
                    'user_id'       => $request->user()->uuid,
                    'transfer_data' => $validated,
                    'error'         => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to initiate transfer: ' . $e->getMessage(),
                ],
                422
            );
        }
    }

    /**
     * Get bank health status.
     *
     * @OA\Get(
     *     path="/api/v2/banks/health/{bankCode}",
     *     operationId="bankGetBankHealth",
     *     summary="Get bank health status",
     *     description="Returns the current health and availability status for the specified bank connector.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="bankCode",
     *         in="path",
     *         required=true,
     *         description="Bank connector code",
     *         @OA\Schema(type="string", example="revolut")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="status", type="string", example="healthy"),
     *                 @OA\Property(property="latency_ms", type="integer", example=120),
     *                 @OA\Property(property="last_checked_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getBankHealth(string $bankCode): JsonResponse
    {
        try {
            $health = $this->bankService->checkBankHealth($bankCode);

            return response()->json(
                [
                    'data' => $health,
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to get bank health',
                [
                    'bank_code' => $bankCode,
                    'error'     => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to retrieve bank health status',
                ],
                500
            );
        }
    }

    /**
     * Get recommended banks for user.
     *
     * @OA\Get(
     *     path="/api/v2/banks/recommendations",
     *     operationId="bankGetRecommendedBanks",
     *     summary="Get recommended banks",
     *     description="Returns a list of recommended banks for the authenticated user based on optional currency, feature, and country filters.",
     *     tags={"Banking V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="currencies[]",
     *         in="query",
     *         required=false,
     *         description="Filter by supported currencies (ISO 4217, 3-letter codes)",
     *         @OA\Schema(type="array", @OA\Items(type="string", example="EUR"))
     *     ),
     *     @OA\Parameter(
     *         name="features[]",
     *         in="query",
     *         required=false,
     *         description="Filter by required features",
     *         @OA\Schema(type="array", @OA\Items(type="string", example="instant_transfers"))
     *     ),
     *     @OA\Parameter(
     *         name="countries[]",
     *         in="query",
     *         required=false,
     *         description="Filter by supported countries (ISO 3166-1 alpha-2, 2-letter codes)",
     *         @OA\Schema(type="array", @OA\Items(type="string", example="DE"))
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getRecommendedBanks(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'currencies'   => 'nullable|array',
                'currencies.*' => 'string|size:3',
                'features'     => 'nullable|array',
                'features.*'   => 'string',
                'countries'    => 'nullable|array',
                'countries.*'  => 'string|size:2',
            ]
        );

        try {
            $routingService = app(\App\Domain\Banking\Services\BankRoutingService::class);
            $recommendations = $routingService->getRecommendedBanks(
                $request->user(),
                $validated
            );

            return response()->json(
                [
                    'data' => $recommendations,
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to get bank recommendations',
                [
                    'user_id'      => $request->user()->uuid,
                    'requirements' => $validated,
                    'error'        => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to retrieve bank recommendations',
                ],
                500
            );
        }
    }
}
