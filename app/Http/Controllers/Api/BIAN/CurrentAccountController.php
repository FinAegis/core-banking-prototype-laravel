<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\BIAN;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Services\AccountService;
use App\Domain\Account\Workflows\CreateAccountWorkflow;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\FreezeAccountWorkflow;
use App\Domain\Account\Workflows\UnfreezeAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Http\Controllers\Controller;
use App\Support\StaffRoles;
use DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Workflow\WorkflowStub;

/**
 * BIAN-compliant Current Account Service Domain Controller.
 *
 * Service Domain: Current Account
 * Functional Pattern: Fulfill
 * Asset Type: Current Account Fulfillment Arrangement
 */
#[OA\Tag(
    name: 'BIAN',
    description: 'BIAN-compliant banking service operations'
)]
class CurrentAccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService
    ) {
    }

    /**
     * Initiate a new current account fulfillment arrangement.
     *
     * BIAN Operation: Initiate
     * HTTP Method: POST
     * Path: /current-account/{cr-reference-id}/initiate
     */
    #[OA\Post(
        path: '/api/bian/current-account/initiate',
        operationId: 'initiateCurrentAccount',
        tags: ['BIAN'],
        summary: 'Initiate new current account',
        description: 'Creates a new current account fulfillment arrangement following BIAN standards',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['customerReference', 'accountName', 'accountType'], properties: [
        new OA\Property(property: 'customerReference', type: 'string', format: 'uuid', description: 'Customer UUID reference'),
        new OA\Property(property: 'accountName', type: 'string', maxLength: 255, description: 'Account name'),
        new OA\Property(property: 'accountType', type: 'string', enum: ['current', 'checking'], description: 'Account type'),
        new OA\Property(property: 'initialDeposit', type: 'integer', minimum: 0, maximum: 0, description: 'Deprecated: only 0 is accepted. Accounts open empty; fund them through a supported deposit method.'),
        new OA\Property(property: 'currency', type: 'string', pattern: '^[A-Z]{3}$', default: 'USD', description: 'Currency code'),
        ]))
    )]
    #[OA\Response(
        response: 201,
        description: 'Account created successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'currentAccountFulfillmentArrangement', type: 'object', properties: [
        new OA\Property(property: 'crReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'customerReference', type: 'string', format: 'uuid'),
        new OA\Property(property: 'accountName', type: 'string'),
        new OA\Property(property: 'accountType', type: 'string'),
        new OA\Property(property: 'accountStatus', type: 'string', example: 'active'),
        new OA\Property(property: 'accountBalance', type: 'object', properties: [
        new OA\Property(property: 'amount', type: 'integer'),
        new OA\Property(property: 'currency', type: 'string'),
        ]),
        new OA\Property(property: 'dateType', type: 'object', properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date-time'),
        new OA\Property(property: 'dateTypeName', type: 'string', example: 'AccountOpeningDate'),
        ]),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'customerReference' => 'required|uuid',
                'accountName'       => 'required|string|max:255',
                'accountType'       => 'required|in:current,checking',
                'initialDeposit'    => 'sometimes|integer|min:0',
                'currency'          => 'sometimes|string|size:3',
            ]
        );

        // Accounts are always opened for the authenticated customer.
        if (! hash_equals((string) $request->user()?->uuid, (string) $validated['customerReference'])) {
            return $this->forbidden();
        }

        // Opening balances are not accepted on this public endpoint: an
        // account opens empty and is funded through a supported deposit
        // method. 0/absent stays valid for backward compatibility.
        if ((int) ($validated['initialDeposit'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'initialDeposit' => 'An initial deposit is not accepted. Open the account and fund it through a supported deposit method.',
            ]);
        }

        // Generate Control Record Reference ID
        $crReferenceId = Str::uuid()->toString();

        // Create the Account data object with the UUID
        $accountData = new \App\Domain\Account\DataObjects\Account(
            uuid: $crReferenceId,
            name: $validated['accountName'],
            userUuid: $validated['customerReference']
        );

        $workflow = WorkflowStub::make(CreateAccountWorkflow::class);
        $workflow->start($accountData);

        // Create the account record for immediate response
        $account = Account::create(
            [
                'uuid'      => $crReferenceId,
                'user_uuid' => $validated['customerReference'],
                'name'      => $validated['accountName'],
                'balance'   => 0,
            ]
        );

        return response()->json(
            [
                'currentAccountFulfillmentArrangement' => [
                    'crReferenceId'     => $crReferenceId,
                    'customerReference' => $validated['customerReference'],
                    'accountName'       => $validated['accountName'],
                    'accountType'       => $validated['accountType'] ?? 'current',
                    'accountStatus'     => 'active',
                    'accountBalance'    => [
                        'amount'   => 0,
                        'currency' => $validated['currency'] ?? 'USD',
                    ],
                    'dateType' => [
                        'date'         => now()->toIso8601String(),
                        'dateTypeName' => 'AccountOpeningDate',
                    ],
                ],
            ],
            201
        );
    }

    /**
     * Retrieve current account fulfillment arrangement.
     *
     * BIAN Operation: Retrieve
     * HTTP Method: GET
     * Path: /current-account/{cr-reference-id}
     */
    #[OA\Get(
        path: '/api/bian/current-account/{crReferenceId}',
        operationId: 'retrieveCurrentAccount',
        tags: ['BIAN'],
        summary: 'Retrieve current account details',
        description: 'Retrieves the details of a current account fulfillment arrangement',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'crReferenceId', in: 'path', required: true, description: 'Control Record Reference ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Account details retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'currentAccountFulfillmentArrangement', type: 'object', properties: [
        new OA\Property(property: 'crReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'customerReference', type: 'string', format: 'uuid'),
        new OA\Property(property: 'accountName', type: 'string'),
        new OA\Property(property: 'accountType', type: 'string'),
        new OA\Property(property: 'accountStatus', type: 'string'),
        new OA\Property(property: 'accountBalance', type: 'object', properties: [
        new OA\Property(property: 'amount', type: 'integer'),
        new OA\Property(property: 'currency', type: 'string'),
        ]),
        new OA\Property(property: 'dateType', type: 'object', properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date-time'),
        new OA\Property(property: 'dateTypeName', type: 'string'),
        ]),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function retrieve(string $crReferenceId): JsonResponse
    {
        $account = $this->findOwnedAccount($crReferenceId);

        return response()->json(
            [
                'currentAccountFulfillmentArrangement' => [
                    'crReferenceId'     => $account->uuid,
                    'customerReference' => $account->user_uuid,
                    'accountName'       => $account->name,
                    'accountType'       => 'current',
                    'accountStatus'     => 'active',
                    'accountBalance'    => [
                        'amount'   => $account->balance,
                        'currency' => 'USD',
                    ],
                    'dateType' => [
                        'date'         => $account->created_at->toIso8601String(),
                        'dateTypeName' => 'AccountOpeningDate',
                    ],
                ],
            ]
        );
    }

    /**
     * Update current account fulfillment arrangement.
     *
     * BIAN Operation: Update
     * HTTP Method: PUT
     * Path: /current-account/{cr-reference-id}
     */
    #[OA\Put(
        path: '/api/bian/current-account/{crReferenceId}',
        operationId: 'updateCurrentAccount',
        tags: ['BIAN'],
        summary: 'Update current account',
        description: 'Updates the properties of a current account fulfillment arrangement',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'crReferenceId', in: 'path', required: true, description: 'Control Record Reference ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'accountName', type: 'string', maxLength: 255),
        new OA\Property(property: 'accountStatus', type: 'string', enum: ['active', 'dormant', 'closed']),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Account updated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'currentAccountFulfillmentArrangement', type: 'object', properties: [
        new OA\Property(property: 'crReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'customerReference', type: 'string', format: 'uuid'),
        new OA\Property(property: 'accountName', type: 'string'),
        new OA\Property(property: 'accountType', type: 'string'),
        new OA\Property(property: 'accountStatus', type: 'string'),
        new OA\Property(property: 'updateResult', type: 'string', example: 'successful'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function update(Request $request, string $crReferenceId): JsonResponse
    {
        $account = $this->findOwnedAccount($crReferenceId);

        $validated = $request->validate(
            [
                'accountName'   => 'sometimes|string|max:255',
                'accountStatus' => 'sometimes|in:active,dormant,closed',
            ]
        );

        if (isset($validated['accountName'])) {
            $account->update(['name' => $validated['accountName']]);
        }

        return response()->json(
            [
                'currentAccountFulfillmentArrangement' => [
                    'crReferenceId'     => $account->uuid,
                    'customerReference' => $account->user_uuid,
                    'accountName'       => $account->name,
                    'accountType'       => 'current',
                    'accountStatus'     => $validated['accountStatus'] ?? 'active',
                    'updateResult'      => 'successful',
                ],
            ]
        );
    }

    /**
     * Control current account fulfillment arrangement (freeze/unfreeze).
     *
     * BIAN Operation: Control
     * HTTP Method: PUT
     * Path: /current-account/{cr-reference-id}/control
     */
    #[OA\Put(
        path: '/api/bian/current-account/{crReferenceId}/control',
        operationId: 'controlCurrentAccount',
        tags: ['BIAN'],
        summary: 'Control account status',
        description: 'Controls the status of a current account (freeze, unfreeze, suspend, reactivate)',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'crReferenceId', in: 'path', required: true, description: 'Control Record Reference ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['controlAction', 'controlReason'], properties: [
        new OA\Property(property: 'controlAction', type: 'string', enum: ['freeze', 'unfreeze', 'suspend', 'reactivate']),
        new OA\Property(property: 'controlReason', type: 'string', maxLength: 500),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Control action executed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'currentAccountFulfillmentControlRecord', type: 'object', properties: [
        new OA\Property(property: 'crReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'controlAction', type: 'string'),
        new OA\Property(property: 'controlReason', type: 'string'),
        new OA\Property(property: 'controlStatus', type: 'string'),
        new OA\Property(property: 'controlDateTime', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    #[OA\Response(
        response: 403,
        description: 'Not the account owner, or an unfreeze/reactivate of a freeze placed by staff'
    )]
    public function control(Request $request, string $crReferenceId): JsonResponse
    {
        $account = $this->findOwnedAccount($crReferenceId);

        $validated = $request->validate(
            [
                'controlAction' => 'required|in:freeze,unfreeze,suspend,reactivate',
                'controlReason' => 'required|string|max:500',
            ]
        );

        $accountUuid = new AccountUuid($crReferenceId);
        $user = Auth::user();

        // The owner may lift only a freeze they placed themselves.
        if (in_array($validated['controlAction'], ['unfreeze', 'reactivate'], true) && ! $account->mayBeUnfrozenBy($user)) {
            return $this->forbidden();
        }

        switch ($validated['controlAction']) {
            case 'freeze':
            case 'suspend':
                $account->recordFreeze(byStaff: StaffRoles::userHasAny($user, StaffRoles::OPERATORS));
                $workflow = WorkflowStub::make(FreezeAccountWorkflow::class);
                $workflow->start($accountUuid, $validated['controlReason'], auth()->user()->name ?? 'System');
                $status = 'frozen';
                break;
            case 'unfreeze':
            case 'reactivate':
                $workflow = WorkflowStub::make(UnfreezeAccountWorkflow::class);
                $workflow->start($accountUuid, $validated['controlReason'], auth()->user()->name ?? 'System');
                $status = 'active';
                break;
        }

        return response()->json(
            [
                'currentAccountFulfillmentControlRecord' => [
                    'crReferenceId'   => $crReferenceId,
                    'controlAction'   => $validated['controlAction'],
                    'controlReason'   => $validated['controlReason'],
                    'controlStatus'   => $status ?? 'unknown',
                    'controlDateTime' => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Execute payment from current account (withdrawal).
     *
     * BIAN Operation: Execute
     * Behavior Qualifier: Payment
     * HTTP Method: POST
     * Path: /current-account/{cr-reference-id}/payment/{bq-reference-id}/execute
     */
    #[OA\Post(
        path: '/api/bian/current-account/{crReferenceId}/payment/execute',
        operationId: 'executePaymentFromAccount',
        tags: ['BIAN'],
        summary: 'Execute payment/withdrawal',
        description: 'Executes a payment or withdrawal from the current account',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'crReferenceId', in: 'path', required: true, description: 'Control Record Reference ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['paymentAmount', 'paymentType'], properties: [
        new OA\Property(property: 'paymentAmount', type: 'integer', minimum: 1, description: 'Amount in cents'),
        new OA\Property(property: 'paymentType', type: 'string', enum: ['withdrawal', 'payment', 'transfer']),
        new OA\Property(property: 'paymentDescription', type: 'string', maxLength: 500),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment executed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'paymentExecutionRecord', type: 'object', properties: [
        new OA\Property(property: 'crReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'bqReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'executionStatus', type: 'string', example: 'completed'),
        new OA\Property(property: 'paymentAmount', type: 'integer'),
        new OA\Property(property: 'paymentType', type: 'string'),
        new OA\Property(property: 'paymentDescription', type: 'string', nullable: true),
        new OA\Property(property: 'accountBalance', type: 'integer'),
        new OA\Property(property: 'executionDateTime', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Insufficient funds',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'paymentExecutionRecord', type: 'object', properties: [
        new OA\Property(property: 'executionStatus', type: 'string', example: 'rejected'),
        new OA\Property(property: 'executionReason', type: 'string', example: 'Insufficient funds'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function executePayment(Request $request, string $crReferenceId): JsonResponse
    {
        $account = $this->findOwnedAccount($crReferenceId);

        $validated = $request->validate(
            [
                'paymentAmount'      => 'required|integer|min:1',
                'paymentType'        => 'required|in:withdrawal,payment,transfer',
                'paymentDescription' => 'sometimes|string|max:500',
            ]
        );

        if ($account->balance < $validated['paymentAmount']) {
            return response()->json(
                [
                    'paymentExecutionRecord' => [
                        'crReferenceId'   => $crReferenceId,
                        'bqReferenceId'   => Str::uuid()->toString(),
                        'executionStatus' => 'rejected',
                        'executionReason' => 'Insufficient funds',
                        'accountBalance'  => $account->balance,
                        'requestedAmount' => $validated['paymentAmount'],
                    ],
                ],
                422
            );
        }

        $accountUuid = new AccountUuid($crReferenceId);
        $money = new Money($validated['paymentAmount']);

        $workflow = WorkflowStub::make(WithdrawAccountWorkflow::class);
        $workflow->start($accountUuid, $money);

        $account->refresh();

        return response()->json(
            [
                'paymentExecutionRecord' => [
                    'crReferenceId'      => $crReferenceId,
                    'bqReferenceId'      => Str::uuid()->toString(),
                    'executionStatus'    => 'completed',
                    'paymentAmount'      => $validated['paymentAmount'],
                    'paymentType'        => $validated['paymentType'],
                    'paymentDescription' => $validated['paymentDescription'] ?? null,
                    'accountBalance'     => $account->balance,
                    'executionDateTime'  => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Execute deposit to current account.
     *
     * BIAN Operation: Execute
     * Behavior Qualifier: Deposit
     * HTTP Method: POST
     * Path: /current-account/{cr-reference-id}/deposit/{bq-reference-id}/execute
     */
    #[OA\Post(
        path: '/api/bian/current-account/{crReferenceId}/deposit/execute',
        operationId: 'executeDepositToAccount',
        tags: ['BIAN'],
        summary: 'Execute deposit',
        description: 'Executes a deposit to the current account',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'crReferenceId', in: 'path', required: true, description: 'Control Record Reference ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['depositAmount', 'depositType'], properties: [
        new OA\Property(property: 'depositAmount', type: 'integer', minimum: 1, description: 'Amount in cents'),
        new OA\Property(property: 'depositType', type: 'string', enum: ['cash', 'check', 'transfer', 'direct']),
        new OA\Property(property: 'depositDescription', type: 'string', maxLength: 500),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Deposit executed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'depositExecutionRecord', type: 'object', properties: [
        new OA\Property(property: 'crReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'bqReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'executionStatus', type: 'string', example: 'completed'),
        new OA\Property(property: 'depositAmount', type: 'integer'),
        new OA\Property(property: 'depositType', type: 'string'),
        new OA\Property(property: 'depositDescription', type: 'string', nullable: true),
        new OA\Property(property: 'accountBalance', type: 'integer'),
        new OA\Property(property: 'executionDateTime', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function executeDeposit(Request $request, string $crReferenceId): JsonResponse
    {
        $account = $this->findOwnedAccount($crReferenceId);

        $validated = $request->validate(
            [
                'depositAmount'      => 'required|integer|min:1',
                'depositType'        => 'required|in:cash,check,transfer,direct',
                'depositDescription' => 'sometimes|string|max:500',
            ]
        );

        $accountUuid = new AccountUuid($crReferenceId);
        $money = new Money($validated['depositAmount']);

        $workflow = WorkflowStub::make(DepositAccountWorkflow::class);
        $workflow->start($accountUuid, $money);

        $account->refresh();

        return response()->json(
            [
                'depositExecutionRecord' => [
                    'crReferenceId'      => $crReferenceId,
                    'bqReferenceId'      => Str::uuid()->toString(),
                    'executionStatus'    => 'completed',
                    'depositAmount'      => $validated['depositAmount'],
                    'depositType'        => $validated['depositType'],
                    'depositDescription' => $validated['depositDescription'] ?? null,
                    'accountBalance'     => $account->balance,
                    'executionDateTime'  => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Retrieve account balance.
     *
     * BIAN Operation: Retrieve
     * Behavior Qualifier: AccountBalance
     * HTTP Method: GET
     * Path: /current-account/{cr-reference-id}/account-balance/{bq-reference-id}/retrieve
     */
    #[OA\Get(
        path: '/api/bian/current-account/{crReferenceId}/account-balance/retrieve',
        operationId: 'retrieveAccountBalance',
        tags: ['BIAN'],
        summary: 'Retrieve account balance',
        description: 'Retrieves the current balance of the account',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'crReferenceId', in: 'path', required: true, description: 'Control Record Reference ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Balance retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'accountBalanceRecord', type: 'object', properties: [
        new OA\Property(property: 'crReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'bqReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'balanceAmount', type: 'integer'),
        new OA\Property(property: 'balanceCurrency', type: 'string', example: 'USD'),
        new OA\Property(property: 'balanceType', type: 'string', example: 'available'),
        new OA\Property(property: 'balanceDateTime', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function retrieveAccountBalance(string $crReferenceId): JsonResponse
    {
        $account = $this->findOwnedAccount($crReferenceId);

        return response()->json(
            [
                'accountBalanceRecord' => [
                    'crReferenceId'   => $crReferenceId,
                    'bqReferenceId'   => Str::uuid()->toString(),
                    'balanceAmount'   => $account->balance,
                    'balanceCurrency' => 'USD',
                    'balanceType'     => 'available',
                    'balanceDateTime' => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Retrieve transaction report.
     *
     * BIAN Operation: Retrieve
     * Behavior Qualifier: TransactionReport
     * HTTP Method: GET
     * Path: /current-account/{cr-reference-id}/transaction-report/{bq-reference-id}/retrieve
     */
    #[OA\Get(
        path: '/api/bian/current-account/{crReferenceId}/transaction-report/retrieve',
        operationId: 'retrieveTransactionReport',
        tags: ['BIAN'],
        summary: 'Retrieve transaction report',
        description: 'Retrieves a report of account transactions for a specified period',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'crReferenceId', in: 'path', required: true, description: 'Control Record Reference ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'fromDate', in: 'query', required: false, description: 'Start date for transaction report', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'toDate', in: 'query', required: false, description: 'End date for transaction report', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'transactionType', in: 'query', required: false, description: 'Filter by transaction type', schema: new OA\Schema(type: 'string', enum: ['all', 'credit', 'debit'])),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction report retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'transactionReportRecord', type: 'object', properties: [
        new OA\Property(property: 'crReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'bqReferenceId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'reportPeriod', type: 'object', properties: [
        new OA\Property(property: 'fromDate', type: 'string', format: 'date'),
        new OA\Property(property: 'toDate', type: 'string', format: 'date'),
        ]),
        new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'transactionReference', type: 'string'),
        new OA\Property(property: 'transactionType', type: 'string', enum: ['credit', 'debit']),
        new OA\Property(property: 'transactionAmount', type: 'integer'),
        new OA\Property(property: 'transactionDateTime', type: 'string', format: 'date-time'),
        new OA\Property(property: 'transactionDescription', type: 'string'),
        ])),
        new OA\Property(property: 'transactionCount', type: 'integer'),
        new OA\Property(property: 'reportDateTime', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function retrieveTransactionReport(Request $request, string $crReferenceId): JsonResponse
    {
        $account = $this->findOwnedAccount($crReferenceId);

        $validated = $request->validate(
            [
                'fromDate'        => 'sometimes|date',
                'toDate'          => 'sometimes|date|after_or_equal:fromDate',
                'transactionType' => 'sometimes|in:all,credit,debit',
            ]
        );

        // Query stored events for transaction history
        $query = DB::table('stored_events')
            ->where('aggregate_uuid', $crReferenceId)
            ->whereIn(
                'event_class',
                [
                    'App\Domain\Account\Events\MoneyAdded',
                    'App\Domain\Account\Events\MoneySubtracted',
                ]
            );

        if (isset($validated['fromDate'])) {
            $query->where('created_at', '>=', $validated['fromDate']);
        }

        if (isset($validated['toDate'])) {
            $query->where('created_at', '<=', $validated['toDate']);
        }

        $events = $query->orderBy('created_at', 'desc')->get();

        $transactions = $events->map(
            function ($event) {
                $properties = json_decode($event->event_properties, true);
                $eventClass = class_basename($event->event_class);

                return [
                    'transactionReference'   => $event->aggregate_uuid,
                    'transactionType'        => $eventClass === 'MoneyAdded' ? 'credit' : 'debit',
                    'transactionAmount'      => $properties['money']['amount'] ?? 0,
                    'transactionDateTime'    => $event->created_at,
                    'transactionDescription' => $eventClass === 'MoneyAdded' ? 'Deposit' : 'Withdrawal',
                ];
            }
        );

        if (isset($validated['transactionType']) && $validated['transactionType'] !== 'all') {
            $transactions = $transactions->filter(
                function ($transaction) use ($validated) {
                    return $transaction['transactionType'] === $validated['transactionType'];
                }
            );
        }

        return response()->json(
            [
                'transactionReportRecord' => [
                    'crReferenceId' => $crReferenceId,
                    'bqReferenceId' => Str::uuid()->toString(),
                    'reportPeriod'  => [
                        'fromDate' => $validated['fromDate'] ?? $account->created_at->toDateString(),
                        'toDate'   => $validated['toDate'] ?? now()->toDateString(),
                    ],
                    'transactions'     => $transactions->values(),
                    'transactionCount' => $transactions->count(),
                    'reportDateTime'   => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Load an account the authenticated user owns: 404 when it does not
     * exist, 403 when it belongs to someone else (same responses as the
     * REST account endpoints).
     */
    private function findOwnedAccount(string $crReferenceId): Account
    {
        $account = Account::where('uuid', $crReferenceId)->firstOrFail();

        if (! $account->isOwnedBy(Auth::user())) {
            throw new HttpResponseException($this->forbidden());
        }

        return $account;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(
            [
                'message' => 'Access denied to this account',
                'error'   => 'FORBIDDEN',
            ],
            403
        );
    }
}
