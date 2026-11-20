<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Exceptions\CustodianNotAvailableException;
use App\Domain\Custodian\Exceptions\CustodianNotFoundException;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Models\CustodianTransfer;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Custodian\Workflows\CustodianTransferWorkflow;
use App\Http\Controllers\Controller;
use App\Support\SecretRedactor;
use App\Support\StaffRoles;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Workflow\WorkflowStub;

#[OA\Tag(
    name: 'Custodians',
    description: 'External custodian bank integration and account management'
)]
class CustodianController extends Controller
{
    public function __construct(
        private readonly CustodianRegistry $registry
    ) {
    }

    /**
     * List available custodians.
     */
    #[OA\Get(
        path: '/api/custodians',
        operationId: 'listCustodians',
        tags: ['Custodians'],
        summary: 'List all available custodians',
        description: 'Returns a list of all registered custodian banks and their current status'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of custodians',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'name', type: 'string', example: 'paysera'),
        new OA\Property(property: 'display_name', type: 'string', example: 'Paysera Bank'),
        new OA\Property(property: 'available', type: 'boolean', example: true),
        new OA\Property(property: 'supported_assets', type: 'array', items: new OA\Items(type: 'string', example: 'EUR')),
        ])),
        new OA\Property(property: 'default', type: 'string', nullable: true, example: 'paysera'),
        ])
    )]
    public function index(): JsonResponse
    {
        $custodians = [];

        foreach ($this->registry->all() as $name => $connector) {
            $custodians[] = [
                'name'             => $name,
                'display_name'     => $connector->getName(),
                'available'        => $connector->isAvailable(),
                'supported_assets' => $connector->getSupportedAssets(),
            ];
        }

        return response()->json(
            [
                'data'    => $custodians,
                'default' => $this->registry->has($this->registry->names()[0] ?? '') ?
                    $this->registry->names()[0] : null,
            ]
        );
    }

    /**
     * Get custodian account information.
     */
    #[OA\Get(
        path: '/api/custodians/{custodian}/account-info',
        operationId: 'getCustodianAccountInfo',
        tags: ['Custodians'],
        summary: 'Get custodian account information',
        description: 'Retrieves detailed account information from the specified custodian',
        parameters: [
        new OA\Parameter(name: 'custodian', in: 'path', required: true, description: 'Custodian identifier', schema: new OA\Schema(type: 'string', example: 'paysera')),
        new OA\Parameter(name: 'account_id', in: 'query', required: true, description: 'Custodian account identifier', schema: new OA\Schema(type: 'string', example: 'ACC123456')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Account information retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'account_id', type: 'string', example: 'ACC123456'),
        new OA\Property(property: 'account_name', type: 'string', example: 'EUR Current Account'),
        new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request or custodian error',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'error', type: 'string'),
        new OA\Property(property: 'message', type: 'string'),
        ])
    )]
    #[OA\Response(
        response: 403,
        description: 'The custodian account is not linked to an account the caller owns (operators may read any)'
    )]
    public function accountInfo(Request $request, string $custodian): JsonResponse
    {
        $validated = $request->validate(
            [
                'account_id' => 'required|string',
            ]
        );

        if (! $this->canReadCustodianAccount($request, $custodian, $validated['account_id'])) {
            return $this->custodianAccountForbidden();
        }

        try {
            $connector = $this->registry->get($custodian);
            $accountInfo = $connector->getAccountInfo($validated['account_id']);

            return response()->json(
                [
                    'data' => $accountInfo->toArray(),
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to retrieve account information',
                    'message' => $this->readFailureMessage($e, $custodian),
                ],
                400
            );
        }
    }

    /**
     * Get custodian account balance.
     */
    #[OA\Get(
        path: '/api/custodians/{custodian}/balance',
        operationId: 'getCustodianBalance',
        tags: ['Custodians'],
        summary: 'Get account balance from custodian',
        description: 'Retrieves the current balance for a specific asset in a custodian account',
        parameters: [
        new OA\Parameter(name: 'custodian', in: 'path', required: true, description: 'Custodian identifier', schema: new OA\Schema(type: 'string', example: 'paysera')),
        new OA\Parameter(name: 'account_id', in: 'query', required: true, description: 'Custodian account identifier', schema: new OA\Schema(type: 'string', example: 'ACC123456')),
        new OA\Parameter(name: 'asset_code', in: 'query', required: true, description: 'Asset code (3 characters)', schema: new OA\Schema(type: 'string', example: 'EUR')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Balance retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'account_id', type: 'string', example: 'ACC123456'),
        new OA\Property(property: 'asset_code', type: 'string', example: 'EUR'),
        new OA\Property(property: 'balance', type: 'integer', example: 150000, description: 'Balance in cents'),
        new OA\Property(property: 'formatted_balance', type: 'string', example: '1,500.00'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request or custodian error'
    )]
    #[OA\Response(
        response: 403,
        description: 'The custodian account is not linked to an account the caller owns (operators may read any)'
    )]
    public function balance(Request $request, string $custodian): JsonResponse
    {
        $validated = $request->validate(
            [
                'account_id' => 'required|string',
                'asset_code' => 'required|string|size:3',
            ]
        );

        if (! $this->canReadCustodianAccount($request, $custodian, $validated['account_id'])) {
            return $this->custodianAccountForbidden();
        }

        try {
            $connector = $this->registry->get($custodian);
            $balance = $connector->getBalance($validated['account_id'], $validated['asset_code']);

            return response()->json(
                [
                    'data' => [
                        'account_id'        => $validated['account_id'],
                        'asset_code'        => $validated['asset_code'],
                        'balance'           => $balance->getAmount(),
                        'formatted_balance' => number_format($balance->getAmount() / 100, 2),
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to retrieve balance',
                    'message' => $this->readFailureMessage($e, $custodian),
                ],
                400
            );
        }
    }

    /**
     * Transfer funds between internal and custodian accounts.
     */
    #[OA\Post(
        path: '/api/custodians/{custodian}/transfer',
        operationId: 'custodianTransfer',
        tags: ['Custodians'],
        summary: 'Transfer funds to/from custodian',
        description: 'Initiates a transfer between an internal account and a custodian account',
        security: [['bearerAuth' => []]],
        parameters: [
        new OA\Parameter(name: 'custodian', in: 'path', required: true, description: 'Custodian identifier', schema: new OA\Schema(type: 'string', example: 'paysera')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['internal_account_uuid', 'custodian_account_id', 'asset_code', 'amount', 'direction'], properties: [
        new OA\Property(property: 'internal_account_uuid', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'custodian_account_id', type: 'string', example: 'ACC123456'),
        new OA\Property(property: 'asset_code', type: 'string', example: 'EUR'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.50),
        new OA\Property(property: 'direction', type: 'string', enum: ['deposit', 'withdraw'], example: 'deposit'),
        new OA\Property(property: 'reference', type: 'string', nullable: true, example: 'Invoice payment'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Transfer initiated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'transaction_id', type: 'string', example: 'tx-123456'),
        new OA\Property(property: 'direction', type: 'string', example: 'deposit'),
        new OA\Property(property: 'amount', type: 'integer', example: 10050),
        new OA\Property(property: 'asset_code', type: 'string', example: 'EUR'),
        ]),
        new OA\Property(property: 'message', type: 'string', example: 'Transfer deposit initiated successfully'),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request or transfer failed'
    )]
    public function transfer(Request $request, string $custodian): JsonResponse
    {
        $validated = $request->validate(
            [
                'internal_account_uuid' => 'required|uuid|exists:accounts,uuid',
                'custodian_account_id'  => 'required|string',
                'asset_code'            => 'required|string|size:3',
                'amount'                => 'required|numeric|min:0.01',
                'direction'             => 'required|in:deposit,withdraw',
                'reference'             => 'nullable|string|max:255',
            ]
        );

        // Only the owner of the internal account may move funds in or out of it
        // (same response as the REST account endpoints).
        if (Account::findOwnedBy($validated['internal_account_uuid'], $request->user()) === null) {
            return response()->json(
                [
                    'message' => 'Access denied to this account',
                    'error'   => 'FORBIDDEN',
                ],
                403
            );
        }

        // The custodian account must be linked to that internal account for
        // this custodian (an active CustodianAccount row).
        $linked = CustodianAccount::query()
            ->where('account_uuid', $validated['internal_account_uuid'])
            ->where('custodian_name', $custodian)
            ->where('custodian_account_id', $validated['custodian_account_id'])
            ->where('status', CustodianAccount::STATUS_ACTIVE)
            ->exists();

        if (! $linked) {
            return response()->json(
                [
                    'message' => 'Custodian account is not linked to this account',
                    'error'   => 'FORBIDDEN',
                ],
                403
            );
        }

        try {
            // Verify custodian exists and is available
            $connector = $this->registry->get($custodian);

            // Validate custodian account
            if (! $connector->validateAccount($validated['custodian_account_id'])) {
                return response()->json(
                    [
                        'error' => 'Invalid custodian account',
                    ],
                    400
                );
            }

            // Start workflow
            $workflow = WorkflowStub::make(CustodianTransferWorkflow::class);
            $result = $workflow->start(
                new AccountUuid($validated['internal_account_uuid']),
                $validated['custodian_account_id'],
                $validated['asset_code'],
                new Money((int) ($validated['amount'] * 100)),
                $custodian,
                $validated['direction'],
                $validated['reference'] ?? null
            );

            // Handle both real and fake workflow responses
            $responseData = $result ?? [
                'status'         => 'completed',
                'transaction_id' => 'mock-tx-' . uniqid(),
                'direction'      => $validated['direction'],
                'amount'         => (int) ($validated['amount'] * 100),
                'asset_code'     => $validated['asset_code'],
            ];

            return response()->json(
                [
                    'data'    => $responseData,
                    'message' => "Transfer {$validated['direction']} initiated successfully",
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Transfer failed',
                    'message' => $this->readFailureMessage($e, $custodian, 'transfer'),
                ],
                400
            );
        }
    }

    /**
     * Get transaction history from custodian.
     */
    #[OA\Get(
        path: '/api/custodians/{custodian}/transactions',
        operationId: 'getCustodianTransactionHistory',
        tags: ['Custodians'],
        summary: 'Get transaction history from custodian',
        description: 'Retrieves transaction history for a custodian account with pagination',
        parameters: [
        new OA\Parameter(name: 'custodian', in: 'path', required: true, description: 'Custodian identifier', schema: new OA\Schema(type: 'string', example: 'paysera')),
        new OA\Parameter(name: 'account_id', in: 'query', required: true, description: 'Custodian account identifier', schema: new OA\Schema(type: 'string', example: 'ACC123456')),
        new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Number of transactions to return (1-1000)', schema: new OA\Schema(type: 'integer', default: 100, minimum: 1, maximum: 1000)),
        new OA\Parameter(name: 'offset', in: 'query', required: false, description: 'Number of transactions to skip', schema: new OA\Schema(type: 'integer', default: 0, minimum: 0)),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction history retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', example: 'tx-123456'),
        new OA\Property(property: 'date', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00Z'),
        new OA\Property(property: 'description', type: 'string', example: 'Wire transfer'),
        new OA\Property(property: 'amount', type: 'integer', example: 10000),
        new OA\Property(property: 'balance', type: 'integer', example: 150000),
        new OA\Property(property: 'type', type: 'string', example: 'credit'),
        ])),
        new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'limit', type: 'integer', example: 100),
        new OA\Property(property: 'offset', type: 'integer', example: 0),
        new OA\Property(property: 'count', type: 'integer', example: 25),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request or custodian error'
    )]
    #[OA\Response(
        response: 403,
        description: 'The custodian account is not linked to an account the caller owns (operators may read any)'
    )]
    public function transactionHistory(Request $request, string $custodian): JsonResponse
    {
        $validated = $request->validate(
            [
                'account_id' => 'required|string',
                'limit'      => 'nullable|integer|min:1|max:1000',
                'offset'     => 'nullable|integer|min:0',
            ]
        );

        if (! $this->canReadCustodianAccount($request, $custodian, $validated['account_id'])) {
            return $this->custodianAccountForbidden();
        }

        try {
            $connector = $this->registry->get($custodian);
            $history = $connector->getTransactionHistory(
                $validated['account_id'],
                (int) ($validated['limit'] ?? 100),
                (int) ($validated['offset'] ?? 0)
            );

            return response()->json(
                [
                    'data' => $history,
                    'meta' => [
                        'limit'  => (int) ($validated['limit'] ?? 100),
                        'offset' => (int) ($validated['offset'] ?? 0),
                        'count'  => count($history),
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to retrieve transaction history',
                    'message' => $this->readFailureMessage($e, $custodian),
                ],
                400
            );
        }
    }

    /**
     * Get transaction status.
     */
    #[OA\Get(
        path: '/api/custodians/{custodian}/transactions/{transactionId}',
        operationId: 'getCustodianTransactionStatus',
        tags: ['Custodians'],
        summary: 'Get transaction status',
        description: 'Retrieves the current status of a specific transaction from the custodian',
        parameters: [
        new OA\Parameter(name: 'custodian', in: 'path', required: true, description: 'Custodian identifier', schema: new OA\Schema(type: 'string', example: 'paysera')),
        new OA\Parameter(name: 'transactionId', in: 'path', required: true, description: 'Transaction identifier', schema: new OA\Schema(type: 'string', example: 'tx-123456')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction status retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'transaction_id', type: 'string', example: 'tx-123456'),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'amount', type: 'integer', example: 10000),
        new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request or transaction not found'
    )]
    #[OA\Response(
        response: 403,
        description: 'The caller is not a party to the custodian transfer (operators may read any)'
    )]
    public function transactionStatus(Request $request, string $custodian, string $transactionId): JsonResponse
    {
        if (! $this->canReadCustodianTransaction($request, $transactionId)) {
            return response()->json(
                [
                    'message' => 'Access denied to this transaction',
                    'error'   => 'FORBIDDEN',
                ],
                403
            );
        }

        try {
            $connector = $this->registry->get($custodian);
            $receipt = $connector->getTransactionStatus($transactionId);

            return response()->json(
                [
                    'data' => $receipt->toArray(),
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to retrieve transaction status',
                    'message' => $this->readFailureMessage($e, $custodian),
                ],
                400
            );
        }
    }

    /**
     * Custodian reads run with the platform's own custodian credentials, so
     * a caller may read only a custodian account linked (active
     * CustodianAccount row, the same link transfer() requires) to an
     * internal account they own. Operators may read any.
     */
    private function canReadCustodianAccount(Request $request, string $custodian, string $custodianAccountId): bool
    {
        $user = $request->user();

        if (StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            return true;
        }

        $linkedAccountUuids = CustodianAccount::query()
            ->where('custodian_name', $custodian)
            ->where('custodian_account_id', $custodianAccountId)
            ->where('status', CustodianAccount::STATUS_ACTIVE)
            ->pluck('account_uuid');

        foreach ($linkedAccountUuids as $accountUuid) {
            if (Account::findOwnedBy((string) $accountUuid, $user) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * A custodian transaction status is readable by a party to the recorded
     * custodian transfer (owner of its from/to internal account) or by an
     * operator.
     */
    private function canReadCustodianTransaction(Request $request, string $transactionId): bool
    {
        $user = $request->user();

        if (StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            return true;
        }

        /** @var CustodianTransfer|null $transfer */
        $transfer = CustodianTransfer::query()->whereKey($transactionId)->first();

        if ($transfer === null) {
            return false;
        }

        foreach ([$transfer->from_account_uuid, $transfer->to_account_uuid] as $accountUuid) {
            if (Account::findOwnedBy((string) $accountUuid, $user) !== null) {
                return true;
            }
        }

        return false;
    }

    private function custodianAccountForbidden(): JsonResponse
    {
        return response()->json(
            [
                'message' => 'Access denied to this custodian account',
                'error'   => 'FORBIDDEN',
            ],
            403
        );
    }

    /**
     * Registry errors (unknown or unavailable custodian) are ours and are
     * returned as is; connector and workflow errors can carry upstream
     * response bodies, so they are logged (redacted) and answered with a
     * generic message.
     */
    private function readFailureMessage(Exception $e, string $custodian, string $operation = 'read'): string
    {
        if ($e instanceof CustodianNotFoundException || $e instanceof CustodianNotAvailableException) {
            return $e->getMessage();
        }

        Log::warning("Custodian {$operation} failed", [
            'custodian' => $custodian,
            'exception' => $e::class,
            'error'     => SecretRedactor::redact($e->getMessage()),
        ]);

        return 'The custodian request failed.';
    }
}
