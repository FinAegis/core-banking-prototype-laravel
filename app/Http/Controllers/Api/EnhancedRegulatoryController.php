<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Regulatory\Services\EnhancedRegulatoryReportingService;
use App\Domain\Regulatory\Services\RegulatoryFilingService;
use App\Domain\Regulatory\Models\RegulatoryReport;
use App\Domain\Regulatory\Models\RegulatoryThreshold;
use App\Domain\Regulatory\Models\RegulatoryFilingRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class EnhancedRegulatoryController extends Controller
{
    public function __construct(
        private readonly EnhancedRegulatoryReportingService $reportingService,
        private readonly RegulatoryFilingService $filingService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin')->except(['show', 'index', 'download']);
    }

    /**
     * List regulatory reports with enhanced filtering
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => 'nullable|string',
            'jurisdiction' => 'nullable|string',
            'status' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'overdue_only' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:1|max:5',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:100',
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
     * Show regulatory report details
     */
    public function show(string $reportId): JsonResponse
    {
        $report = RegulatoryReport::with(['filingRecords'])->findOrFail($reportId);

        return response()->json([
            'report' => $report,
            'time_until_due' => $report->getTimeUntilDue(),
            'can_be_submitted' => $report->canBeSubmitted(),
            'filing_history' => $report->filingRecords->map(fn($filing) => [
                'filing_id' => $filing->filing_id,
                'status' => $filing->filing_status,
                'filed_at' => $filing->filed_at,
                'processing_time' => $filing->getProcessingTime(),
            ]),
        ]);
    }

    /**
     * Generate enhanced CTR report
     */
    public function generateEnhancedCTR(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        try {
            $date = Carbon::parse($request->date);
            $report = $this->reportingService->generateEnhancedCTR($date);
            
            return response()->json([
                'message' => 'Enhanced CTR generated successfully',
                'report' => $report,
                'fraud_analysis_included' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate enhanced CTR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate enhanced SAR report
     */
    public function generateEnhancedSAR(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            
            $report = $this->reportingService->generateEnhancedSAR($startDate, $endDate);
            
            return response()->json([
                'message' => 'Enhanced SAR generated successfully',
                'report' => $report,
                'requires_immediate_filing' => $report->report_data['requires_immediate_filing'] ?? false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate enhanced SAR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate AML report
     */
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
                'report' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate AML report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate OFAC report
     */
    public function generateOFACReport(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        try {
            $date = Carbon::parse($request->date);
            $report = $this->reportingService->generateOFACReport($date);
            
            return response()->json([
                'message' => 'OFAC report generated successfully',
                'report' => $report,
                'requires_immediate_action' => $report->report_data['requires_immediate_action'] ?? false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate OFAC report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate BSA report
     */
    public function generateBSAReport(Request $request): JsonResponse
    {
        $request->validate([
            'quarter' => 'required|integer|min:1|max:4',
            'year' => 'required|integer|min:2020|max:' . now()->year,
        ]);

        try {
            $quarter = Carbon::createFromDate($request->year, ($request->quarter - 1) * 3 + 1, 1);
            $report = $this->reportingService->generateBSAReport($quarter);
            
            return response()->json([
                'message' => 'BSA report generated successfully',
                'report' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate BSA report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit report to regulatory authority
     */
    public function submitReport(Request $request, string $reportId): JsonResponse
    {
        $request->validate([
            'filing_type' => 'nullable|in:initial,amendment,correction',
            'filing_method' => 'nullable|in:api,portal,email',
        ]);

        $report = RegulatoryReport::findOrFail($reportId);

        try {
            $filing = $this->filingService->submitReport($report, $request->all());
            
            return response()->json([
                'message' => 'Report submitted successfully',
                'filing' => $filing,
                'reference' => $filing->filing_reference,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to submit report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check filing status
     */
    public function checkFilingStatus(string $filingId): JsonResponse
    {
        $filing = RegulatoryFilingRecord::findOrFail($filingId);

        try {
            $status = $this->filingService->checkFilingStatus($filing);
            
            return response()->json([
                'filing' => $filing->fresh(),
                'status_check' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check filing status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry failed filing
     */
    public function retryFiling(string $filingId): JsonResponse
    {
        $filing = RegulatoryFilingRecord::findOrFail($filingId);

        try {
            $filing = $this->filingService->retryFiling($filing);
            
            return response()->json([
                'message' => 'Filing retry initiated',
                'filing' => $filing,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retry filing',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get regulatory thresholds
     */
    public function getThresholds(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'nullable|string',
            'report_type' => 'nullable|string',
            'jurisdiction' => 'nullable|string',
            'active_only' => 'nullable|boolean',
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
     * Update threshold
     */
    public function updateThreshold(Request $request, string $thresholdId): JsonResponse
    {
        $request->validate([
            'amount_threshold' => 'nullable|numeric|min:0',
            'count_threshold' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'review_priority' => 'nullable|integer|min:1|max:5',
        ]);

        $threshold = RegulatoryThreshold::findOrFail($thresholdId);
        $threshold->update($request->all());

        return response()->json([
            'message' => 'Threshold updated successfully',
            'threshold' => $threshold,
        ]);
    }

    /**
     * Get regulatory dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:week,month,quarter,year',
        ]);

        $period = $request->period ?? 'month';
        $endDate = now();
        $startDate = match($period) {
            'week' => $endDate->copy()->subWeek(),
            'month' => $endDate->copy()->subMonth(),
            'quarter' => $endDate->copy()->subQuarter(),
            'year' => $endDate->copy()->subYear(),
        };

        $dashboard = [
            'reports' => [
                'total' => RegulatoryReport::whereBetween('created_at', [$startDate, $endDate])->count(),
                'pending' => RegulatoryReport::pending()->count(),
                'overdue' => RegulatoryReport::overdue()->count(),
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
                ->map(fn($filing) => [
                    'filing_id' => $filing->filing_id,
                    'report_type' => $filing->report->report_type,
                    'status' => $filing->filing_status,
                    'filed_at' => $filing->filed_at,
                ]),
            'threshold_triggers' => RegulatoryThreshold::active()
                ->orderByDesc('trigger_count')
                ->limit(5)
                ->get()
                ->map(fn($threshold) => [
                    'code' => $threshold->threshold_code,
                    'name' => $threshold->name,
                    'trigger_count' => $threshold->trigger_count,
                    'last_triggered' => $threshold->last_triggered_at,
                ]),
        ];

        return response()->json(['dashboard' => $dashboard]);
    }

    /**
     * Download report
     */
    public function download(string $reportId)
    {
        $report = RegulatoryReport::findOrFail($reportId);

        if (!$report->file_path || !Storage::exists($report->file_path)) {
            return response()->json(['error' => 'Report file not found'], 404);
        }

        return Storage::download($report->file_path, basename($report->file_path));
    }
}