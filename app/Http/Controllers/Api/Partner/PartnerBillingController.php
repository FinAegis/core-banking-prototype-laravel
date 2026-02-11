<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Models\PartnerInvoice;
use App\Domain\FinancialInstitution\Services\PartnerBillingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerBillingController extends Controller
{
    public function __construct(
        private readonly PartnerBillingService $billingService,
    ) {
    }

    /**
     * List partner invoices.
     *
     * GET /api/partner/v1/billing/invoices
     *
     * @OA\Get(
     *     path="/api/partner/v1/billing/invoices",
     *     operationId="partnerListInvoices",
     *     summary="List partner invoices",
     *     description="Returns up to 50 most recent invoices for the authenticated partner, ordered by creation date descending.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of invoices",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="invoice_number", type="string", example="INV-2025-0001"),
     *                 @OA\Property(property="amount", type="number", example=299.00),
     *                 @OA\Property(property="currency", type="string", example="USD"),
     *                 @OA\Property(property="status", type="string", example="paid"),
     *                 @OA\Property(property="period_start", type="string", format="date"),
     *                 @OA\Property(property="period_end", type="string", format="date"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     )
     * )
     */
    public function invoices(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request);

        $invoices = PartnerInvoice::where('partner_id', $partner->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $invoices,
        ]);
    }

    /**
     * Get a specific invoice.
     *
     * GET /api/partner/v1/billing/invoices/{id}
     *
     * @OA\Get(
     *     path="/api/partner/v1/billing/invoices/{id}",
     *     operationId="partnerGetInvoice",
     *     summary="Get a specific invoice",
     *     description="Retrieves the details of a single invoice by its ID for the authenticated partner.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="invoice_number", type="string", example="INV-2025-0001"),
     *                 @OA\Property(property="amount", type="number", example=299.00),
     *                 @OA\Property(property="currency", type="string", example="USD"),
     *                 @OA\Property(property="status", type="string", example="paid"),
     *                 @OA\Property(property="period_start", type="string", format="date"),
     *                 @OA\Property(property="period_end", type="string", format="date"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     )
     * )
     */
    public function invoice(Request $request, int $id): JsonResponse
    {
        $partner = $this->getPartner($request);

        $invoice = PartnerInvoice::where('partner_id', $partner->id)
            ->where('id', $id)
            ->first();

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $invoice,
        ]);
    }

    /**
     * Get outstanding balance.
     *
     * GET /api/partner/v1/billing/outstanding
     *
     * @OA\Get(
     *     path="/api/partner/v1/billing/outstanding",
     *     operationId="partnerGetOutstandingBalance",
     *     summary="Get outstanding balance",
     *     description="Returns the total outstanding (unpaid) balance for the authenticated partner in USD.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Outstanding balance",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="outstanding_balance_usd", type="number", example=599.00),
     *                 @OA\Property(property="currency", type="string", example="USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     )
     * )
     */
    public function outstanding(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request);
        $balance = $this->billingService->getOutstandingBalance($partner);

        return response()->json([
            'success' => true,
            'data'    => [
                'outstanding_balance_usd' => $balance,
                'currency'                => 'USD',
            ],
        ]);
    }

    /**
     * Get current period billing breakdown preview.
     *
     * GET /api/partner/v1/billing/breakdown
     *
     * @OA\Get(
     *     path="/api/partner/v1/billing/breakdown",
     *     operationId="partnerGetBillingBreakdown",
     *     summary="Get current period billing breakdown preview",
     *     description="Returns a detailed billing breakdown for the current month-to-date period, including base fees, overage charges, and per-endpoint costs.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Billing breakdown",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Detailed billing breakdown with line items and totals")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     )
     * )
     */
    public function breakdown(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request);

        $periodStart = now()->startOfMonth();
        $periodEnd = now();

        $breakdown = $this->billingService->calculateBillingBreakdown($partner, $periodStart, $periodEnd);

        return response()->json([
            'success' => true,
            'data'    => $breakdown,
        ]);
    }

    private function getPartner(Request $request): FinancialInstitutionPartner
    {
        return $request->attributes->get('partner');
    }
}
