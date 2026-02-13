<?php

namespace App\Http\Controllers;

use App\Domain\Regulatory\Models\RegulatoryReport;
use App\Domain\Regulatory\Models\RegulatoryThreshold;
use App\Domain\Regulatory\Services\ReportGenerator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Regulatory Reports",
 *     description="Regulatory report generation and submission"
 * )
 */
class RegulatoryReportsController extends Controller
{
    protected ReportGenerator $reportGenerator;

    public function __construct(ReportGenerator $reportGenerator)
    {
        $this->reportGenerator = $reportGenerator;
    }

    /**
     * @OA\Get(
     *     path="/regulatory-reports",
     *     operationId="regulatoryReportsIndex",
     *     tags={"Regulatory Reports"},
     *     summary="List regulatory reports",
     *     description="Returns the regulatory reports dashboard",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index()
    {
        $this->authorize('generate_regulatory_reports');

        $reports = RegulatoryReport::with('generatedBy')
            ->latest()
            ->paginate(20);

        // Get statistics
        $stats = [
            'total_reports'      => RegulatoryReport::count(),
            'pending_submission' => RegulatoryReport::where('status', 'pending_submission')->count(),
            'submitted'          => RegulatoryReport::where('status', 'submitted')->count(),
            'this_month'         => RegulatoryReport::whereMonth('created_at', now()->month)->count(),
        ];

        // Get active thresholds
        $thresholds = RegulatoryThreshold::active()
            ->get()
            ->groupBy('report_type');

        return view('regulatory.reports.index', compact('reports', 'stats', 'thresholds'));
    }

    /**
     * @OA\Get(
     *     path="/regulatory-reports/create",
     *     operationId="regulatoryReportsCreate",
     *     tags={"Regulatory Reports"},
     *     summary="Show create report form",
     *     description="Shows the form to create a regulatory report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function create()
    {
        $this->authorize('generate_regulatory_reports');

        $reportTypes = [
            'ctr'                => 'Currency Transaction Report (CTR)',
            'sar'                => 'Suspicious Activity Report (SAR)',
            'monthly_compliance' => 'Monthly Compliance Report',
            'quarterly_risk'     => 'Quarterly Risk Assessment',
            'annual_aml'         => 'Annual AML Report',
        ];

        return view('regulatory.reports.create', compact('reportTypes'));
    }

    /**
     * @OA\Post(
     *     path="/regulatory-reports",
     *     operationId="regulatoryReportsStore",
     *     tags={"Regulatory Reports"},
     *     summary="Create regulatory report",
     *     description="Creates a new regulatory report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request)
    {
        $this->authorize('generate_regulatory_reports');

        $request->validate(
            [
                'report_type'  => 'required|in:ctr,sar,monthly_compliance,quarterly_risk,annual_aml',
                'start_date'   => 'required|date',
                'end_date'     => 'required|date|after_or_equal:start_date',
                'jurisdiction' => 'required|string',
            ]
        );

        try {
            $data = [
                'report_type'     => $request->report_type,
                'start_date'      => $request->start_date,
                'end_date'        => $request->end_date,
                'jurisdiction'    => $request->jurisdiction,
                'include_details' => $request->boolean('include_details'),
            ];

            // Call the appropriate method based on report type
            switch ($request->report_type) {
                case 'ctr':
                    $report = $this->reportGenerator->generateCTRReport($data);
                    break;
                case 'sar':
                    $report = $this->reportGenerator->generateSARReport($data);
                    break;
                default:
                    // For other report types, use a generic approach
                    $report = $this->reportGenerator->generateCTRReport($data);
                    break;
            }

            return redirect()->route('regulatory.reports.show', $report)
                ->with('success', 'Report generated successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/regulatory-reports/{id}",
     *     operationId="regulatoryReportsShow",
     *     tags={"Regulatory Reports"},
     *     summary="Show report details",
     *     description="Returns details of a regulatory report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(RegulatoryReport $report)
    {
        $this->authorize('generate_regulatory_reports');

        return view('regulatory.reports.show', compact('report'));
    }

    /**
     * @OA\Get(
     *     path="/regulatory-reports/{id}/download",
     *     operationId="regulatoryReportsDownload",
     *     tags={"Regulatory Reports"},
     *     summary="Download report",
     *     description="Downloads a regulatory report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function download(RegulatoryReport $report)
    {
        $this->authorize('generate_regulatory_reports');

        if (! $report->file_path || ! Storage::exists($report->file_path)) {
            return back()->with('error', 'Report file not found.');
        }

        return Storage::download($report->file_path, $report->getFileName());
    }

    /**
     * @OA\Post(
     *     path="/regulatory-reports/{id}/submit",
     *     operationId="regulatoryReportsSubmit",
     *     tags={"Regulatory Reports"},
     *     summary="Submit report to authority",
     *     description="Submits a regulatory report to the authority",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function submit(RegulatoryReport $report)
    {
        $this->authorize('generate_regulatory_reports');

        if ($report->status !== 'pending_submission') {
            return back()->with('error', 'Report has already been submitted.');
        }

        try {
            // In a real system, this would submit to the regulatory authority's API
            $report->update(
                [
                    'status'       => 'submitted',
                    'submitted_at' => now(),
                    'submitted_by' => Auth::id(),
                ]
            );

            return back()->with('success', 'Report submitted successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to submit report: ' . $e->getMessage());
        }
    }
}
