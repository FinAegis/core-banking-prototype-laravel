<?php

namespace App\Http\Controllers\Api;

use App\Domain\Regulatory\Models\RegulatoryFilingRecord;
use App\Domain\Regulatory\Models\RegulatoryReport;
use App\Domain\Regulatory\Models\RegulatoryThreshold;
use App\Domain\Regulatory\Services\EnhancedRegulatoryReportingService;
use App\Domain\Regulatory\Services\RegulatoryFilingService;
use App\Http\Controllers\Controller;
use App\Support\StaffRoles;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\Permission\Middleware\RoleMiddleware;

class EnhancedRegulatoryController extends Controller
{
    public function __construct(
        private readonly EnhancedRegulatoryReportingService $reportingService,
        private readonly RegulatoryFilingService $filingService
    ) {
        $this->middleware('auth:sanctum');
        // Compliance staff only (the regulatory reports cover every user).
        $this->middleware(RoleMiddleware::using(StaffRoles::COMPLIANCE));
    }

    /**
     * List regulatory reports with enhanced filtering.
     */
    #[OA\Get(
        path: '/api/compliance/regulatory-reports',
        operationId: 'regulatoryIndex',
        summary: 'List regulatory reports with enhanced filtering',
        description: 'Returns a paginated list of regulatory reports with support for filtering by type, jurisdiction, status, date range, overdue flag, and priority.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'report_type', in: 'query', required: false, description: 'Filter by report type', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'jurisdiction', in: 'query', required: false, description: 'Filter by jurisdiction', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Filter by report status', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'date_from', in: 'query', required: false, description: 'Start of reporting period', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'date_to', in: 'query', required: false, description: 'End of reporting period', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'overdue_only', in: 'query', required: false, description: 'Show only overdue reports', schema: new OA\Schema(type: 'boolean')),
        new OA\Parameter(name: 'priority', in: 'query', required: false, description: 'Minimum priority level', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 5)),
        new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Results per page', schema: new OA\Schema(type: 'integer', minimum: 10, maximum: 100)),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of regulatory reports',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 5),
        new OA\Property(property: 'per_page', type: 'integer', example: 20),
        new OA\Property(property: 'total', type: 'integer', example: 100),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'report_type'  => 'nullable|string',
            'jurisdiction' => 'nullable|string',
            'status'       => 'nullable|string',
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'overdue_only' => 'nullable|boolean',
            'priority'     => 'nullable|integer|min:1|max:5',
            'page'         => 'nullable|integer|min:1',
            'per_page'     => 'nullable|integer|min:10|max:100',
        ]);

        $query = RegulatoryReport::query()->with(['latestFiling']);

        if ($request->report_type) {
            $query->byType($request->report_type);
        }

        if ($request->jurisdiction) {
            $query->byJurisdiction($request->jurisdiction);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->date_from) {
            $query->where('reporting_period_start', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('reporting_period_end', '<=', $request->date_to);
        }

        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        if ($request->priority) {
            $query->where('priority', '>=', $request->priority);
        }

        $reports = $query->orderByDesc('priority')
            ->orderBy('due_date')
            ->paginate($request->per_page ?? 20);

        return response()->json($reports);
    }

    /**
     * Show regulatory report details.
     */
    #[OA\Get(
        path: '/api/compliance/regulatory-reports/{reportId}',
        operationId: 'regulatoryShow',
        summary: 'Show regulatory report details',
        description: 'Returns detailed information for a specific regulatory report, including time until due, submission eligibility, and full filing history.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'reportId', in: 'path', required: true, description: 'Regulatory report ID', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Report details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'report', type: 'object'),
        new OA\Property(property: 'time_until_due', type: 'string', example: '3 days', nullable: true),
        new OA\Property(property: 'can_be_submitted', type: 'boolean', example: true),
        new OA\Property(property: 'filing_history', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'filing_id', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'filed_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'processing_time', type: 'string', nullable: true),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 404,
        description: 'Report not found'
    )]
    public function show(string $reportId): JsonResponse
    {
        $report = RegulatoryReport::with(['filingRecords'])->findOrFail($reportId);

        return response()->json([
            'report'           => $report,
            'time_until_due'   => $report->getTimeUntilDue(),
            'can_be_submitted' => $report->canBeSubmitted(),
            'filing_history'   => $report->filingRecords->map(fn ($filing) => [
                'filing_id'       => $filing->filing_id,
                'status'          => $filing->filing_status,
                'filed_at'        => $filing->filed_at,
                'processing_time' => $filing->getProcessingTime(),
            ]),
        ]);
    }

    /**
     * Generate enhanced CTR report.
     */
    #[OA\Post(
        path: '/api/compliance/regulatory-reports/generate/ctr',
        operationId: 'regulatoryGenerateEnhancedCTR',
        summary: 'Generate enhanced Currency Transaction Report',
        description: 'Generates an enhanced CTR (Currency Transaction Report) for the specified date, including fraud analysis indicators.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['date'], properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-01-15', description: 'Report date (must be today or earlier)'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'CTR generated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Enhanced CTR generated successfully'),
        new OA\Property(property: 'report', type: 'object'),
        new OA\Property(property: 'fraud_analysis_included', type: 'boolean', example: true),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Report generation failed'
    )]
    public function generateEnhancedCTR(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        try {
            $date = Carbon::parse($request->date);
            $report = $this->reportingService->generateEnhancedCTR($date);

            return response()->json([
                'message'                 => 'Enhanced CTR generated successfully',
                'report'                  => $report,
                'fraud_analysis_included' => true,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to generate enhanced CTR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate enhanced SAR report.
     */
    #[OA\Post(
        path: '/api/compliance/regulatory-reports/generate/sar',
        operationId: 'regulatoryGenerateEnhancedSAR',
        summary: 'Generate enhanced Suspicious Activity Report',
        description: 'Generates an enhanced SAR (Suspicious Activity Report) for the specified date range. Flags reports that require immediate filing.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['start_date', 'end_date'], properties: [
        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-01-01', description: 'Start date (must be today or earlier)'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-01-31', description: 'End date (must be on or after start_date and today or earlier)'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'SAR generated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Enhanced SAR generated successfully'),
        new OA\Property(property: 'report', type: 'object'),
        new OA\Property(property: 'requires_immediate_filing', type: 'boolean', example: false),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Report generation failed'
    )]
    public function generateEnhancedSAR(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date|before_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date|before_or_equal:today',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $report = $this->reportingService->generateEnhancedSAR($startDate, $endDate);

            return response()->json([
                'message'                   => 'Enhanced SAR generated successfully',
                'report'                    => $report,
                'requires_immediate_filing' => $report->report_data['requires_immediate_filing'] ?? false,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to generate enhanced SAR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate AML report.
     */
    #[OA\Post(
        path: '/api/compliance/regulatory-reports/generate/aml',
        operationId: 'regulatoryGenerateAMLReport',
        summary: 'Generate AML compliance report',
        description: 'Generates an Anti-Money Laundering (AML) compliance report for the specified month.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['month'], properties: [
        new OA\Property(property: 'month', type: 'string', example: '2025-01', description: 'Report month in Y-m format (must be current month or earlier)'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'AML report generated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'AML report generated successfully'),
        new OA\Property(property: 'report', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Report generation failed'
    )]
    public function generateAMLReport(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m|before_or_equal:' . now()->format('Y-m'),
        ]);

        try {
            $month = Carbon::createFromFormat('Y-m', $request->month);
            $report = $this->reportingService->generateAMLReport($month);

            return response()->json([
                'message' => 'AML report generated successfully',
                'report'  => $report,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to generate AML report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate OFAC report.
     */
    #[OA\Post(
        path: '/api/compliance/regulatory-reports/generate/ofac',
        operationId: 'regulatoryGenerateOFACReport',
        summary: 'Generate OFAC sanctions screening report',
        description: 'Generates an OFAC (Office of Foreign Assets Control) sanctions screening report for the specified date. Flags reports that require immediate action.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['date'], properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-01-15', description: 'Report date (must be today or earlier)'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'OFAC report generated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'OFAC report generated successfully'),
        new OA\Property(property: 'report', type: 'object'),
        new OA\Property(property: 'requires_immediate_action', type: 'boolean', example: false),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Report generation failed'
    )]
    public function generateOFACReport(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        try {
            $date = Carbon::parse($request->date);
            $report = $this->reportingService->generateOFACReport($date);

            return response()->json([
                'message'                   => 'OFAC report generated successfully',
                'report'                    => $report,
                'requires_immediate_action' => $report->report_data['requires_immediate_action'] ?? false,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to generate OFAC report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate BSA report.
     */
    #[OA\Post(
        path: '/api/compliance/regulatory-reports/generate/bsa',
        operationId: 'regulatoryGenerateBSAReport',
        summary: 'Generate BSA compliance report',
        description: 'Generates a Bank Secrecy Act (BSA) compliance report for the specified quarter and year.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['quarter', 'year'], properties: [
        new OA\Property(property: 'quarter', type: 'integer', minimum: 1, maximum: 4, example: 1, description: 'Fiscal quarter (1-4)'),
        new OA\Property(property: 'year', type: 'integer', minimum: 2020, example: 2025, description: 'Report year'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'BSA report generated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'BSA report generated successfully'),
        new OA\Property(property: 'report', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Report generation failed'
    )]
    public function generateBSAReport(Request $request): JsonResponse
    {
        $request->validate([
            'quarter' => 'required|integer|min:1|max:4',
            'year'    => 'required|integer|min:2020|max:' . now()->year,
        ]);

        try {
            $quarter = Carbon::createFromDate($request->year, ($request->quarter - 1) * 3 + 1, 1);
            $report = $this->reportingService->generateBSAReport($quarter);

            return response()->json([
                'message' => 'BSA report generated successfully',
                'report'  => $report,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to generate BSA report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit report to regulatory authority.
     */
    #[OA\Post(
        path: '/api/compliance/regulatory-reports/{reportId}/submit',
        operationId: 'regulatorySubmitReport',
        summary: 'Record a regulatory report filing (simulated)',
        description: 'Records a filing of type initial, amendment or correction for the report. No regulator is contacted. The api method needs an endpoint in the regulatory.api_endpoints config, which this codebase does not define, so it fails. The portal and email methods are simulated. They send nothing and return a generated reference, and the portal method fails at random about one time in five.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'reportId', in: 'path', required: true, description: 'Regulatory report ID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'filing_type', type: 'string', enum: ['initial', 'amendment', 'correction'], example: 'initial', description: 'Type of filing'),
        new OA\Property(property: 'filing_method', type: 'string', enum: ['api', 'portal', 'email'], example: 'api', description: 'Filing submission method'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Report submitted successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Report submitted successfully'),
        new OA\Property(property: 'filing', type: 'object'),
        new OA\Property(property: 'reference', type: 'string', example: 'FIL-2025-00123'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Report not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Submission failed'
    )]
    public function submitReport(Request $request, string $reportId): JsonResponse
    {
        $request->validate([
            'filing_type'   => 'nullable|in:initial,amendment,correction',
            'filing_method' => 'nullable|in:api,portal,email',
        ]);

        $report = RegulatoryReport::findOrFail($reportId);

        try {
            $filing = $this->filingService->submitReport($report, $request->all());

            return response()->json([
                'message'   => 'Report submitted successfully',
                'filing'    => $filing,
                'reference' => $filing->filing_reference,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to submit report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check filing status.
     */
    #[OA\Get(
        path: '/api/compliance/regulatory-reports/filings/{filingId}/status',
        operationId: 'regulatoryCheckFilingStatus',
        summary: 'Check the status of a regulatory filing (simulated)',
        description: 'Returns the latest filing record and its status. No regulator is contacted. For a portal filing the status is chosen at random from pending, accepted and rejected, and an accepted or rejected status is saved to the filing. An email filing has no status check.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'filingId', in: 'path', required: true, description: 'Filing record ID', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Filing status retrieved',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'filing', type: 'object'),
        new OA\Property(property: 'status_check', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Filing not found'
    )]
    #[OA\Response(
        response: 500,
        description: 'Status check failed'
    )]
    public function checkFilingStatus(string $filingId): JsonResponse
    {
        $filing = RegulatoryFilingRecord::findOrFail($filingId);

        try {
            $status = $this->filingService->checkFilingStatus($filing);

            return response()->json([
                'filing'       => $filing->fresh(),
                'status_check' => $status,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to check filing status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry failed filing.
     */
    #[OA\Post(
        path: '/api/compliance/regulatory-reports/filings/{filingId}/retry',
        operationId: 'regulatoryRetryFiling',
        summary: 'Retry a failed regulatory filing (simulated)',
        description: 'Retries a failed filing with the same simulated methods as the submit endpoint. No regulator is contacted.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'filingId', in: 'path', required: true, description: 'Filing record ID', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Filing retry initiated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Filing retry initiated'),
        new OA\Property(property: 'filing', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Filing not found'
    )]
    #[OA\Response(
        response: 500,
        description: 'Retry failed'
    )]
    public function retryFiling(string $filingId): JsonResponse
    {
        $filing = RegulatoryFilingRecord::findOrFail($filingId);

        try {
            $filing = $this->filingService->retryFiling($filing);

            return response()->json([
                'message' => 'Filing retry initiated',
                'filing'  => $filing,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to retry filing',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get regulatory thresholds.
     */
    #[OA\Get(
        path: '/api/compliance/regulatory-reports/thresholds',
        operationId: 'regulatoryGetThresholds',
        summary: 'Get regulatory thresholds',
        description: 'Returns a paginated list of regulatory thresholds, with optional filtering by category, report type, jurisdiction, and active status.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'category', in: 'query', required: false, description: 'Filter by threshold category', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'report_type', in: 'query', required: false, description: 'Filter by report type', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'jurisdiction', in: 'query', required: false, description: 'Filter by jurisdiction', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'active_only', in: 'query', required: false, description: 'Show only active thresholds (default true)', schema: new OA\Schema(type: 'boolean')),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Results per page', schema: new OA\Schema(type: 'integer', minimum: 10, maximum: 100)),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of regulatory thresholds',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
        new OA\Property(property: 'per_page', type: 'integer', example: 20),
        new OA\Property(property: 'total', type: 'integer', example: 50),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function getThresholds(Request $request): JsonResponse
    {
        $request->validate([
            'category'     => 'nullable|string',
            'report_type'  => 'nullable|string',
            'jurisdiction' => 'nullable|string',
            'active_only'  => 'nullable|boolean',
        ]);

        $query = RegulatoryThreshold::query();

        if ($request->category) {
            $query->byCategory($request->category);
        }

        if ($request->report_type) {
            $query->byReportType($request->report_type);
        }

        if ($request->jurisdiction) {
            $query->byJurisdiction($request->jurisdiction);
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $thresholds = $query->orderBy('review_priority', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($thresholds);
    }

    /**
     * Update threshold.
     */
    #[OA\Put(
        path: '/api/compliance/regulatory-reports/thresholds/{thresholdId}',
        operationId: 'regulatoryUpdateThreshold',
        summary: 'Update a regulatory threshold',
        description: 'Updates configuration for a specific regulatory threshold, including amount, count, active status, and review priority.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'thresholdId', in: 'path', required: true, description: 'Threshold ID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'amount_threshold', type: 'number', format: 'float', example: 10000.00, description: 'Amount threshold'),
        new OA\Property(property: 'count_threshold', type: 'integer', minimum: 0, example: 5, description: 'Transaction count threshold'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true, description: 'Whether the threshold is active'),
        new OA\Property(property: 'review_priority', type: 'integer', minimum: 1, maximum: 5, example: 3, description: 'Review priority level'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Threshold updated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Threshold updated successfully'),
        new OA\Property(property: 'threshold', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Threshold not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function updateThreshold(Request $request, string $thresholdId): JsonResponse
    {
        $request->validate([
            'amount_threshold' => 'nullable|numeric|min:0',
            'count_threshold'  => 'nullable|integer|min:0',
            'is_active'        => 'nullable|boolean',
            'review_priority'  => 'nullable|integer|min:1|max:5',
        ]);

        $threshold = RegulatoryThreshold::findOrFail($thresholdId);
        $threshold->update($request->all());

        return response()->json([
            'message'   => 'Threshold updated successfully',
            'threshold' => $threshold,
        ]);
    }

    /**
     * Get regulatory dashboard.
     */
    #[OA\Get(
        path: '/api/compliance/regulatory-reports/dashboard',
        operationId: 'regulatoryDashboard',
        summary: 'Get the regulatory compliance dashboard',
        description: 'Returns an aggregated dashboard view of regulatory reports, including totals, pending/overdue counts, breakdowns by type and jurisdiction, upcoming deadlines, recent filings, and top threshold triggers.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'period', in: 'query', required: false, description: 'Dashboard time period (default: month)', schema: new OA\Schema(type: 'string', enum: ['week', 'month', 'quarter', 'year'])),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Dashboard data',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'dashboard', type: 'object', properties: [
        new OA\Property(property: 'reports', type: 'object', properties: [
        new OA\Property(property: 'total', type: 'integer', example: 42),
        new OA\Property(property: 'pending', type: 'integer', example: 5),
        new OA\Property(property: 'overdue', type: 'integer', example: 2),
        new OA\Property(property: 'submitted', type: 'integer', example: 35),
        ]),
        new OA\Property(property: 'by_type', type: 'object'),
        new OA\Property(property: 'by_jurisdiction', type: 'object'),
        new OA\Property(property: 'upcoming_due', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'recent_filings', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'filing_id', type: 'string'),
        new OA\Property(property: 'report_type', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'filed_at', type: 'string', format: 'date-time'),
        ])),
        new OA\Property(property: 'threshold_triggers', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'trigger_count', type: 'integer'),
        new OA\Property(property: 'last_triggered', type: 'string', format: 'date-time', nullable: true),
        ])),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:week,month,quarter,year',
        ]);

        $period = $request->period ?? 'month';
        $endDate = now();
        $startDate = match ($period) {
            'week'    => $endDate->copy()->subWeek(),
            'month'   => $endDate->copy()->subMonth(),
            'quarter' => $endDate->copy()->subQuarter(),
            'year'    => $endDate->copy()->subYear(),
        };

        $dashboard = [
            'reports' => [
                'total'     => RegulatoryReport::whereBetween('created_at', [$startDate, $endDate])->count(),
                'pending'   => RegulatoryReport::pending()->count(),
                'overdue'   => RegulatoryReport::overdue()->count(),
                'submitted' => RegulatoryReport::where('status', RegulatoryReport::STATUS_SUBMITTED)
                    ->whereBetween('submitted_at', [$startDate, $endDate])
                    ->count(),
            ],
            'by_type' => RegulatoryReport::whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('report_type')
                ->selectRaw('report_type, COUNT(*) as count')
                ->pluck('count', 'report_type'),
            'by_jurisdiction' => RegulatoryReport::whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('jurisdiction')
                ->selectRaw('jurisdiction, COUNT(*) as count')
                ->pluck('count', 'jurisdiction'),
            'upcoming_due' => RegulatoryReport::dueSoon(7)
                ->select('report_id', 'report_type', 'due_date', 'priority')
                ->orderBy('due_date')
                ->limit(10)
                ->get(),
            'recent_filings' => RegulatoryFilingRecord::with('report')
                ->orderByDesc('filed_at')
                ->limit(10)
                ->get()
                ->map(fn ($filing) => [
                    'filing_id'   => $filing->filing_id,
                    'report_type' => $filing->report->report_type,
                    'status'      => $filing->filing_status,
                    'filed_at'    => $filing->filed_at,
                ]),
            'threshold_triggers' => RegulatoryThreshold::active()
                ->orderByDesc('trigger_count')
                ->limit(5)
                ->get()
                ->map(fn ($threshold) => [
                    'code'           => $threshold->threshold_code,
                    'name'           => $threshold->name,
                    'trigger_count'  => $threshold->trigger_count,
                    'last_triggered' => $threshold->last_triggered_at,
                ]),
        ];

        return response()->json(['dashboard' => $dashboard]);
    }

    /**
     * Download report.
     */
    #[OA\Get(
        path: '/api/compliance/regulatory-reports/{reportId}/download',
        operationId: 'regulatoryDownload',
        summary: 'Download a regulatory report file',
        description: 'Downloads the generated file for a specific regulatory report. Returns a 404 if the file has not been generated or is no longer available.',
        tags: ['Compliance'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'reportId', in: 'path', required: true, description: 'Regulatory report ID', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'File download',
        content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 404,
        description: 'Report or file not found'
    )]
    public function download(string $reportId)
    {
        $report = RegulatoryReport::findOrFail($reportId);

        if (! $report->file_path || ! Storage::exists($report->file_path)) {
            return response()->json(['error' => 'Report file not found'], 404);
        }

        return Storage::download($report->file_path, basename($report->file_path));
    }
}
