<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Models\PartnerInvoice;
use App\Domain\FinancialInstitution\Services\PartnerBillingService;
use App\Domain\VisaCli\Contracts\VisaCliPaymentGatewayInterface;
use App\Domain\VisaCli\Exceptions\VisaCliPaymentException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PartnerBillingController extends Controller
{
    public function __construct(
        private readonly PartnerBillingService $billingService,
        private readonly VisaCliPaymentGatewayInterface $paymentGateway,
    ) {
    }

    /**
     * List partner invoices.
     *
     * GET /api/partner/v1/billing/invoices
     */
    #[OA\Get(
        path: '/api/partner/v1/billing/invoices',
        operationId: 'partnerListInvoices',
        summary: 'List partner invoices',
        description: 'Returns up to 50 most recent invoices for the authenticated partner, ordered by creation date descending.',
        tags: ['Partner BaaS'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'List of invoices',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'invoice_number', type: 'string', example: 'INV-2025-0001'),
        new OA\Property(property: 'amount', type: 'number', example: 299.00),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', type: 'string', example: 'paid'),
        new OA\Property(property: 'period_start', type: 'string', format: 'date'),
        new OA\Property(property: 'period_end', type: 'string', format: 'date'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'UNAUTHORIZED'),
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
        ]),
        ])
    )]
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
     */
    #[OA\Get(
        path: '/api/partner/v1/billing/invoices/{id}',
        operationId: 'partnerGetInvoice',
        summary: 'Get a specific invoice',
        description: 'Retrieves the details of a single invoice by its ID for the authenticated partner.',
        tags: ['Partner BaaS'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Invoice ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Invoice details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'invoice_number', type: 'string', example: 'INV-2025-0001'),
        new OA\Property(property: 'amount', type: 'number', example: 299.00),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', type: 'string', example: 'paid'),
        new OA\Property(property: 'period_start', type: 'string', format: 'date'),
        new OA\Property(property: 'period_end', type: 'string', format: 'date'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'UNAUTHORIZED'),
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Invoice not found',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Invoice not found'),
        ])
    )]
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
     */
    #[OA\Get(
        path: '/api/partner/v1/billing/outstanding',
        operationId: 'partnerGetOutstandingBalance',
        summary: 'Get outstanding balance',
        description: 'Returns the total outstanding (unpaid) balance for the authenticated partner in USD.',
        tags: ['Partner BaaS'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Outstanding balance',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'outstanding_balance_usd', type: 'number', example: 599.00),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'UNAUTHORIZED'),
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
        ]),
        ])
    )]
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
     */
    #[OA\Get(
        path: '/api/partner/v1/billing/breakdown',
        operationId: 'partnerGetBillingBreakdown',
        summary: 'Get current period billing breakdown preview',
        description: 'Returns a detailed billing breakdown for the current month-to-date period, including base fees, overage charges, and per-endpoint costs.',
        tags: ['Partner BaaS'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Billing breakdown',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', description: 'Detailed billing breakdown with line items and totals'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'UNAUTHORIZED'),
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
        ]),
        ])
    )]
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

    /**
     * Pay an invoice via Visa CLI.
     *
     * POST /api/partner/v1/billing/invoices/{id}/pay
     */
    #[OA\Post(
        path: '/api/partner/v1/billing/invoices/{id}/pay',
        operationId: 'partnerPayInvoice',
        summary: 'Pay an invoice via Visa CLI',
        description: 'Collects payment for a pending or overdue invoice using the Visa CLI payment gateway.',
        tags: ['Partner BaaS'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Invoice ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
            new OA\Property(property: 'card_id', type: 'string', description: 'Optional enrolled card ID', example: null),
            ])
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment successful',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'payment_reference', type: 'string'),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'amount_cents', type: 'integer', example: 29900),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Invoice not found',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Invoice not found'),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Invoice cannot be paid or payment failed',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Invoice cannot be paid'),
        ])
    )]
    public function payInvoice(Request $request, int $id): JsonResponse
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

        if (! $invoice->canBePaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice cannot be paid (status: ' . $invoice->status . ')',
            ], 422);
        }

        try {
            $cardId = $request->input('card_id');
            $result = $this->paymentGateway->collectPayment(
                $invoice,
                is_string($cardId) ? $cardId : null,
            );

            return response()->json([
                'success' => true,
                'data'    => $result->toArray(),
            ]);
        } catch (VisaCliPaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function getPartner(Request $request): FinancialInstitutionPartner
    {
        return $request->attributes->get('partner');
    }
}
