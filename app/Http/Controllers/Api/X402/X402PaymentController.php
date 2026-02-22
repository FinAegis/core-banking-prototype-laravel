<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\X402;

use App\Domain\X402\Enums\SettlementStatus;
use App\Domain\X402\Models\X402Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * X402 Payment History Controller.
 *
 * Provides read access to x402 payment records and statistics.
 *
 * @OA\Tag(
 *     name="X402 Payments",
 *     description="X402 payment history and analytics"
 * )
 */
class X402PaymentController extends Controller
{
    /**
     * List x402 payments.
     *
     * @OA\Get(
     *     path="/api/v1/x402/payments",
     *     summary="List x402 payment records",
     *     tags={"X402 Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending", "verified", "settled", "failed", "expired"})),
     *     @OA\Parameter(name="network", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="payer_address", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=20, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated payment records"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = X402Payment::query()
            ->where('team_id', $request->user()?->currentTeam?->id);

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

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

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
     *
     * @OA\Get(
     *     path="/api/v1/x402/payments/{id}",
     *     summary="Get x402 payment details",
     *     tags={"X402 Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Payment details"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $payment = X402Payment::where('team_id', $request->user()?->currentTeam?->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $payment->toApiResponse(),
        ]);
    }

    /**
     * Get payment statistics.
     *
     * @OA\Get(
     *     path="/api/v1/x402/payments/stats",
     *     summary="Get x402 payment statistics",
     *     tags={"X402 Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="string", enum={"day", "week", "month"}, default="day")),
     *     @OA\Response(
     *         response=200,
     *         description="Payment statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_payments", type="integer"),
     *             @OA\Property(property="total_settled", type="integer"),
     *             @OA\Property(property="total_failed", type="integer"),
     *             @OA\Property(property="total_volume_atomic", type="string"),
     *             @OA\Property(property="total_volume_usd", type="string"),
     *             @OA\Property(property="unique_payers", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'day');
        if (! in_array($period, ['day', 'week', 'month'], true)) {
            return response()->json([
                'errors' => ['period' => ['Must be one of: day, week, month.']],
            ], 422);
        }

        $since = match ($period) {
            'week'  => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay(),
        };

        $query = X402Payment::where('team_id', $request->user()?->currentTeam?->id)
            ->where('created_at', '>=', $since);

        $totalAtomic = (string) ((clone $query)->where('status', 'settled')->sum('amount') ?: 0);

        return response()->json([
            'data' => [
                'period'              => $period,
                'total_payments'      => (clone $query)->count(),
                'total_settled'       => (clone $query)->where('status', 'settled')->count(),
                'total_failed'        => (clone $query)->where('status', 'failed')->count(),
                'total_volume_atomic' => $totalAtomic,
                'total_volume_usd'    => bcdiv($totalAtomic, '1000000', 6),
                'unique_payers'       => (clone $query)->whereNotNull('payer_address')->distinct()->count('payer_address'),
            ],
        ]);
    }
}
