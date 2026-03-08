<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Turnover;
use App\Domain\Account\Services\Cache\AccountCacheService;
use App\Domain\Account\Services\Cache\TurnoverCacheService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BalanceController extends Controller
{
    public function __construct(
        private readonly AccountCacheService $accountCache,
        private readonly TurnoverCacheService $turnoverCache
    ) {
    }

        #[OA\Get(
            path: '/api/accounts/{uuid}/balance',
            operationId: 'getAccountBalance',
            tags: ['Balance'],
            summary: 'Get account balance',
            description: 'Retrieves the current balance and turnover information for an account',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'Account UUID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Balance information retrieved',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/Balance'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function show(string $uuid): JsonResponse
    {
        // Get account from cache
        $account = $this->accountCache->get($uuid);

        if (! $account) {
            abort(404, 'Account not found');
        }

        $accountUuid = new AccountUuid($uuid);

        // Get cached balance
        $balance = $this->accountCache->getBalance($uuid) ?? $account->balance;

        // Get cached turnover
        $turnover = $this->turnoverCache->getLatest($uuid);

        return response()->json(
            [
                'data' => [
                    'account_uuid' => $uuid,
                    'balance'      => $balance,
                    'frozen'       => $account->frozen ?? false,
                    'last_updated' => $account->updated_at,
                    'turnover'     => $turnover ? [
                        'debit'        => number_format((float) $turnover->debit, 2, '.', ''),
                        'credit'       => number_format((float) $turnover->credit, 2, '.', ''),
                        'period_start' => $turnover->created_at,
                        'period_end'   => $turnover->updated_at,
                    ] : null,
                ],
            ]
        );
    }

        #[OA\Get(
            path: '/api/accounts/{uuid}/balance/summary',
            operationId: 'getAccountBalanceSummary',
            tags: ['Balance'],
            summary: 'Get account balance summary',
            description: 'Retrieves detailed balance statistics including 12-month turnover data',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'Account UUID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Balance summary retrieved',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'current_balance', type: 'integer', example: 50000),
        new OA\Property(property: 'frozen', type: 'boolean', example: false),
        new OA\Property(property: 'statistics', type: 'object', properties: [
        new OA\Property(property: 'total_debit_12_months', type: 'integer', example: 120000),
        new OA\Property(property: 'total_credit_12_months', type: 'integer', example: 170000),
        new OA\Property(property: 'average_monthly_debit', type: 'number', example: 10000),
        new OA\Property(property: 'average_monthly_credit', type: 'number', example: 14166.67),
        new OA\Property(property: 'months_analyzed', type: 'integer', example: 12),
        ]),
        new OA\Property(property: 'monthly_turnovers', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'month', type: 'integer', example: 1),
        new OA\Property(property: 'year', type: 'integer', example: 2024),
        new OA\Property(property: 'debit', type: 'integer', example: 10000),
        new OA\Property(property: 'credit', type: 'integer', example: 15000),
        ])),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function summary(string $uuid): JsonResponse
    {
        // Get account from cache
        $account = $this->accountCache->get($uuid);

        if (! $account) {
            abort(404, 'Account not found');
        }

        // Get cached turnovers
        $turnovers = $this->turnoverCache->getLastMonths($uuid, 12);

        // Get cached statistics
        $statistics = $this->turnoverCache->getStatistics($uuid);

        return response()->json(
            [
                'data' => [
                    'account_uuid'    => $uuid,
                    'current_balance' => $account->balance,
                    'frozen'          => $account->frozen ?? false,
                    'statistics'      => [
                        'total_debit_12_months'  => $statistics['total_debit'],
                        'total_credit_12_months' => $statistics['total_credit'],
                        'average_monthly_debit'  => (int) $statistics['average_monthly_debit'],
                        'average_monthly_credit' => (int) $statistics['average_monthly_credit'],
                        'months_analyzed'        => $statistics['months_analyzed'],
                    ],
                    'monthly_turnovers' => $turnovers->map(
                        function ($turnover) {
                            return [
                                'month'  => $turnover->created_at->format('Y-m'),
                                'debit'  => $turnover->debit,
                                'credit' => $turnover->credit,
                                'net'    => $turnover->credit - $turnover->debit,
                            ];
                        }
                    ),
                ],
            ]
        );
    }
}
