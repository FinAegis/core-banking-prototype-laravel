<?php

namespace App\Http\Controllers;

use App\Domain\Regulatory\Models\RegulatoryReport;
use App\Domain\Regulatory\Models\RegulatoryThreshold;
use App\Domain\Regulatory\Services\ReportGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RegulatoryReportsController extends Controller
{
    protected ReportGenerator $reportGenerator;
    
    public function __construct(ReportGenerator $reportGenerator)
    {
        $this->reportGenerator = $reportGenerator;
    }
    
    /**
     * Display regulatory reports dashboard
     */
    public function index()
    {
        $this->authorize('generate_regulatory_reports');
        
        $reports = RegulatoryReport::with('generatedBy')
            ->latest()
            ->paginate(20);
            
        // Get statistics
        $stats = [
            'total_reports' => RegulatoryReport::count(),
            'pending_submission' => RegulatoryReport::where('status', 'pending_submission')->count(),
            'submitted' => RegulatoryReport::where('status', 'submitted')->count(),
            'this_month' => RegulatoryReport::whereMonth('created_at', now()->month)->count(),
        ];
        
        // Get active thresholds
        $thresholds = RegulatoryThreshold::active()
            ->get()
            ->groupBy('report_type');
        
        return view('regulatory.reports.index', compact('reports', 'stats', 'thresholds'));
    }
    
    /**
     * Show report generation form
     */
    public function create()
    {
        $this->authorize('generate_regulatory_reports');
        
        $reportTypes = [
            'ctr' => 'Currency Transaction Report (CTR)',
            'sar' => 'Suspicious Activity Report (SAR)',
            'monthly_compliance' => 'Monthly Compliance Report',
            'quarterly_risk' => 'Quarterly Risk Assessment',
            'annual_aml' => 'Annual AML Report',
        ];
        
        return view('regulatory.reports.create', compact('reportTypes'));
    }
    
    /**
     * Generate a new regulatory report
     */
    public function store(Request $request)
    {
        $this->authorize('generate_regulatory_reports');
        
        $request->validate([
            'report_type' => 'required|in:ctr,sar,monthly_compliance,quarterly_risk,annual_aml',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'jurisdiction' => 'required|string',
        ]);
        
        try {
            $report = $this->reportGenerator->generateReport(
                $request->report_type,
                $request->start_date,
                $request->end_date,
                [
                    'jurisdiction' => $request->jurisdiction,
                    'include_details' => $request->boolean('include_details'),
                ]
            );
            
            return redirect()->route('regulatory.reports.show', $report)
                ->with('success', 'Report generated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }
    
    /**
     * Display report details
     */
    public function show(RegulatoryReport $report)
    {
        $this->authorize('generate_regulatory_reports');
        
        return view('regulatory.reports.show', compact('report'));
    }
    
    /**
     * Download report
     */
    public function download(RegulatoryReport $report)
    {
        $this->authorize('generate_regulatory_reports');
        
        if (!$report->file_path || !Storage::exists($report->file_path)) {
            return back()->with('error', 'Report file not found.');
        }
        
        return Storage::download($report->file_path, $report->getFileName());
    }
    
    /**
     * Submit report to regulatory authority
     */
    public function submit(RegulatoryReport $report)
    {
        $this->authorize('generate_regulatory_reports');
        
        if ($report->status !== 'pending_submission') {
            return back()->with('error', 'Report has already been submitted.');
        }
        
        try {
            // In a real system, this would submit to the regulatory authority's API
            $report->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
            ]);
            
            return back()->with('success', 'Report submitted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to submit report: ' . $e->getMessage());
        }
    }
}