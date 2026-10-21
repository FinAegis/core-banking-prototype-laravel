<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\X402;

use App\Domain\X402\Enums\SettlementStatus;
use App\Domain\X402\Models\X402Payment;
use App\Http\Controllers\Controller;
use App\Support\MachinePaymentScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * X402 Payment History Controller.
 *
 * Provides read access to x402 payment records and statistics: the records
 * of the caller's current team (none without a team), or every record for
 * platform administrators (StaffRoles::ADMINS).
 */
#[OA\Tag(
    name: 'X402 Payments',
    description: 'X402 payment history and analytics'
)]
class X402PaymentController extends Controller
{
    /**
     * List x402 payments.
     */
    #[OA\Get(
        path: '/api/v1/x402/payments',
        summary: 'List x402 payment records',
        description: 'Payments recorded for the caller\'s current team (none without a team), or every payment for platform administrators (admin, super_admin).',
        tags: ['X402 Payments'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'verified', 'settled', 'failed', 'expired'])),
        new OA\Parameter(name: 'network', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'payer_address', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Items per page (alias: limit)', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Paginated payment records'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function index(Request $request): JsonResponse
    {
        $query = MachinePaymentScope::teamRecords(X402Payment::query(), $request->user());

        if ($request->filled('status')) {
            $status = SettlementStatus::tryFrom($request->input('status'));
            if ($status === null) {
                return response()->json([
                    'errors' => ['status' => ['Invalid status. Must be one of: pending, verified, settled, failed, expired.']],
                ], 422);
            }
            $query->where('status', $status->value);
        }

        if ($request->filled('network')) {
            $query->where('network', $request->input('network'));
        }

        if ($request->filled('payer_address')) {
            $query->where('payer_address', $request->input('payer_address'));
        }

        $perPage = min(max((int) $request->input('per_page', $request->input('limit', 20)), 1), 100);

        $payments = $query->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($payments->items())->map(fn ($p) => $p->toApiResponse()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
                'per_page'     => $payments->perPage(),
                'total'        => $payments->total(),
            ],
        ]);
    }

    /**
     * Get a specific payment.
     */
    #[OA\Get(
        path: '/api/v1/x402/payments/{id}',
        summary: 'Get x402 payment details',
        description: 'A payment recorded for the caller\'s current team, or any payment for platform administrators (admin, super_admin); 404 otherwise.',
        tags: ['X402 Payments'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment details'
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $payment = MachinePaymentScope::teamRecords(X402Payment::query(), $request->user())
            ->findOrFail($id);

        return response()->json([
            'data' => $payment->toApiResponse(),
        ]);
    }

    /**
     * Get payment statistics.
     */
    #[OA\Get(
        path: '/api/v1/x402/payments/stats',
        summary: 'Get x402 payment statistics',
        description: 'Statistics over the payments recorded for the caller\'s current team (zero without a team), or over every payment for platform administrators (admin, super_admin).',
        tags: ['X402 Payments'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'period', in: 'query', required: false, description: 'Time period (aliases: 24h=day, 7d=week, 30d=month)', schema: new OA\Schema(type: 'string', enum: ['day', 'week', 'month', '24h', '7d', '30d'], default: 'day')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment statistics',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'total_payments', type: 'integer'),
        new OA\Property(property: 'total_settled', type: 'integer'),
        new OA\Property(property: 'total_failed', type: 'integer'),
        new OA\Property(property: 'total_volume_atomic', type: 'string'),
        new OA\Property(property: 'total_volume_usd', type: 'string'),
        new OA\Property(property: 'unique_payers', type: 'integer'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function stats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'day');

        // Normalize common aliases
        $period = match ($period) {
            '24h', '1d' => 'day',
            '7d'        => 'week',
            '30d'       => 'month',
            default     => $period,
        };

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            return response()->json([
                'errors' => ['period' => ['Must be one of: day, week, month (or aliases: 24h, 1d, 7d, 30d).']],
            ], 422);
        }

        $since = match ($period) {
            'week'  => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay(),
        };

        /** @var object{total_payments: int, total_settled: int, total_failed: int, total_volume_atomic: string, unique_payers: int} $stats */
        $stats = MachinePaymentScope::teamRecords(X402Payment::query(), $request->user())
            ->toBase()
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as total_payments')
            ->selectRaw("SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) as total_settled")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as total_failed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'settled' THEN CAST(amount AS DECIMAL(20,0)) ELSE 0 END), 0) as total_volume_atomic")
            ->selectRaw('COUNT(DISTINCT payer_address) as unique_payers')
            ->first();

        $totalAtomic = (string) ($stats->total_volume_atomic ?? 0);

        return response()->json([
            'data' => [
                'period'              => $period,
                'total_payments'      => (int) $stats->total_payments,
                'total_settled'       => (int) $stats->total_settled,
                'total_failed'        => (int) $stats->total_failed,
                'total_volume_atomic' => $totalAtomic,
                'total_volume_usd'    => bcdiv($totalAtomic, '1000000', 6),
                'unique_payers'       => (int) $stats->unique_payers,
            ],
        ]);
    }
}
