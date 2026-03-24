<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MachinePay;

use App\Domain\MachinePay\Models\MppPayment;
use App\Domain\MachinePay\Models\MppSpendingLimit;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Machine Payments')]
class MppPaymentController extends Controller
{
    #[OA\Get(
        path: '/api/v1/mpp/payments',
        operationId: 'mppPayments',
        summary: 'List MPP payment history',
        description: 'Returns paginated payment history with optional rail and status filters.',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
        parameters: [
            new OA\Parameter(name: 'rail', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filter by rail (stripe, tempo, lightning, card, x402)'),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filter by status (pending, settled, failed)'),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment history',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'data', type: 'object'),
            ],
        ),
    )]
    public function index(Request $request): JsonResponse
    {
        $payments = MppPayment::query()
            ->when($request->filled('rail'), fn ($q) => $q->where('rail', $request->input('rail')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $payments,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/mpp/payments/stats',
        operationId: 'mppPaymentStats',
        summary: 'Payment statistics',
        description: 'Returns aggregate statistics: total payments, settled/failed counts, total volume, and breakdown by rail.',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment stats',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'total_payments', type: 'integer'),
                    new OA\Property(property: 'settled', type: 'integer'),
                    new OA\Property(property: 'failed', type: 'integer'),
                    new OA\Property(property: 'total_settled_cents', type: 'integer'),
                    new OA\Property(property: 'by_rail', type: 'object'),
                ]),
            ],
        ),
    )]
    public function stats(): JsonResponse
    {
        $total = MppPayment::count();
        $settled = MppPayment::where('status', 'settled')->count();
        $failed = MppPayment::where('status', 'failed')->count();
        $totalAmount = MppPayment::where('status', 'settled')->sum('amount_cents');

        $byRail = MppPayment::where('status', 'settled')
            ->selectRaw('rail, COUNT(*) as count, SUM(amount_cents) as total_cents')
            ->groupBy('rail')
            ->get()
            ->keyBy('rail')
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_payments'      => $total,
                'settled'             => $settled,
                'failed'              => $failed,
                'total_settled_cents' => (int) $totalAmount,
                'by_rail'             => $byRail,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/mpp/spending-limits',
        operationId: 'mppSpendingLimits',
        summary: 'List agent spending limits',
        description: 'Returns all configured agent spending limits for MPP payments.',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
    )]
    #[OA\Response(response: 200, description: 'Spending limits')]
    public function spendingLimits(): JsonResponse
    {
        $limits = MppSpendingLimit::orderBy('agent_id')->get();

        return response()->json([
            'success' => true,
            'data'    => $limits,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/mpp/spending-limits',
        operationId: 'mppSetSpendingLimit',
        summary: 'Set or update a spending limit',
        description: 'Creates or updates daily and per-transaction spending limits for an agent.',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['agent_id', 'daily_limit', 'per_tx_limit'],
            properties: [
                new OA\Property(property: 'agent_id', type: 'string'),
                new OA\Property(property: 'daily_limit', type: 'integer', example: 5000000),
                new OA\Property(property: 'per_tx_limit', type: 'integer', example: 100000),
                new OA\Property(property: 'auto_pay', type: 'boolean', example: true),
            ],
        ),
    )]
    #[OA\Response(response: 201, description: 'Spending limit set')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function setSpendingLimit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id'     => ['required', 'string', 'max:255'],
            'daily_limit'  => ['required', 'integer', 'min:0'],
            'per_tx_limit' => ['required', 'integer', 'min:0'],
            'auto_pay'     => ['sometimes', 'boolean'],
        ]);

        $limit = MppSpendingLimit::updateOrCreate(
            ['agent_id' => $validated['agent_id']],
            $validated,
        );

        return response()->json([
            'success' => true,
            'data'    => $limit,
        ], 201);
    }

    #[OA\Delete(
        path: '/api/v1/mpp/spending-limits/{agentId}',
        operationId: 'mppDeleteSpendingLimit',
        summary: 'Delete a spending limit',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
        parameters: [
            new OA\Parameter(name: 'agentId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(response: 200, description: 'Spending limit deleted')]
    public function deleteSpendingLimit(string $agentId): JsonResponse
    {
        MppSpendingLimit::where('agent_id', $agentId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Spending limit deleted.',
        ]);
    }
}
