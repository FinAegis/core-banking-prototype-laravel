<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Services\RegulatoryReportingService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Regulatory Reporting",
 *     description="Compliance and regulatory report generation and management"
 * )
 */
class RegulatoryReportingController extends Controller
{
    public function __construct(
        private readonly RegulatoryReportingService $regulatoryReportingService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin')->except(['getReport', 'listReports']);
    }

    /**
     * Generate Currency Transaction Report (CTR)
     * 
     * @OA\Post(
     *     path="/api/regulatory/reports/ctr",
     *     operationId="generateCTR",
     *     tags={"Regulatory Reporting"},
     *     summary="Generate Currency Transaction Report",
     *     description="Generates a CTR report for transactions exceeding regulatory thresholds (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"date"},
     *             @OA\Property(
     *                 property="date",
     *                 type="string",
     *                 format="date",
     *                 example="2024-01-15",
     *                 description="Date for which to generate the CTR (must be today or earlier)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CTR generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="type", type="string", example="ctr"),
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="generated_at", type="string", format="date-time"),
     *                 @OA\Property(property="download_url", type="string")
     *             ),
     *             @OA\Property(property="message", type="string", example="Currency Transaction Report generated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to generate report"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function generateCTR(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        try {
            $date = Carbon::parse($request->date);
            $filename = $this->regulatoryReportingService->generateCTR($date);
            
            return response()->json([
                'data' => [
                    'type' => 'ctr',
                    'date' => $date->toDateString(),
                    'filename' => $filename,
                    'generated_at' => now()->toISOString(),
                    'download_url' => route('api.regulatory.download', ['filename' => basename($filename)]),
                ],
                'message' => 'Currency Transaction Report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate CTR report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Suspicious Activity Report (SAR) candidates
     * 
     * @OA\Post(
     *     path="/api/regulatory/reports/sar",
     *     operationId="generateSARCandidates",
     *     tags={"Regulatory Reporting"},
     *     summary="Generate SAR candidates report",
     *     description="Generates a report of potential suspicious activities requiring SAR filing (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"start_date", "end_date"},
     *             @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-01-31")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SAR candidates report generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="type", type="string", example="sar_candidates"),
     *                 @OA\Property(property="period_start", type="string", format="date"),
     *                 @OA\Property(property="period_end", type="string", format="date"),
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="generated_at", type="string", format="date-time"),
     *                 @OA\Property(property="download_url", type="string")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to generate report"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function generateSARCandidates(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            
            $filename = $this->regulatoryReportingService->generateSARCandidates($startDate, $endDate);
            
            return response()->json([
                'data' => [
                    'type' => 'sar_candidates',
                    'period_start' => $startDate->toDateString(),
                    'period_end' => $endDate->toDateString(),
                    'filename' => $filename,
                    'generated_at' => now()->toISOString(),
                    'download_url' => route('api.regulatory.download', ['filename' => basename($filename)]),
                ],
                'message' => 'SAR candidates report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate SAR candidates report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate compliance summary report
     * 
     * @OA\Post(
     *     path="/api/regulatory/reports/compliance-summary",
     *     operationId="generateComplianceSummary",
     *     tags={"Regulatory Reporting"},
     *     summary="Generate monthly compliance summary",
     *     description="Generates a comprehensive compliance summary report for the specified month (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"month"},
     *             @OA\Property(
     *                 property="month",
     *                 type="string",
     *                 pattern="^\d{4}-\d{2}$",
     *                 example="2024-01",
     *                 description="Month in YYYY-MM format"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance summary generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="type", type="string", example="compliance_summary"),
     *                 @OA\Property(property="month", type="string", example="January 2024"),
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="generated_at", type="string", format="date-time"),
     *                 @OA\Property(property="download_url", type="string")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to generate report"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function generateComplianceSummary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m|before_or_equal:' . now()->format('Y-m'),
        ]);

        try {
            $month = Carbon::createFromFormat('Y-m', $request->month);
            $filename = $this->regulatoryReportingService->generateComplianceSummary($month);
            
            return response()->json([
                'data' => [
                    'type' => 'compliance_summary',
                    'month' => $month->format('F Y'),
                    'filename' => $filename,
                    'generated_at' => now()->toISOString(),
                    'download_url' => route('api.regulatory.download', ['filename' => basename($filename)]),
                ],
                'message' => 'Compliance summary report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate compliance summary report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate KYC compliance report
     * 
     * @OA\Post(
     *     path="/api/regulatory/reports/kyc",
     *     operationId="generateKycReport",
     *     tags={"Regulatory Reporting"},
     *     summary="Generate KYC compliance report",
     *     description="Generates a Know Your Customer (KYC) compliance status report (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="KYC report generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="type", type="string", example="kyc_compliance"),
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="generated_at", type="string", format="date-time"),
     *                 @OA\Property(property="download_url", type="string")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to generate report"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function generateKycReport(): JsonResponse
    {
        try {
            $filename = $this->regulatoryReportingService->generateKycReport();
            
            return response()->json([
                'data' => [
                    'type' => 'kyc_compliance',
                    'filename' => $filename,
                    'generated_at' => now()->toISOString(),
                    'download_url' => route('api.regulatory.download', ['filename' => basename($filename)]),
                ],
                'message' => 'KYC compliance report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate KYC compliance report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all available regulatory reports
     * 
     * @OA\Get(
     *     path="/api/regulatory/reports",
     *     operationId="listRegulatoryReports",
     *     tags={"Regulatory Reporting"},
     *     summary="List all regulatory reports",
     *     description="Retrieves a paginated list of all generated regulatory reports",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Filter by report type",
     *         @OA\Schema(type="string", enum={"ctr", "sar", "compliance", "kyc"})
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of reports per page",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reports list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="reports",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="type", type="string"),
     *                         @OA\Property(property="filename", type="string"),
     *                         @OA\Property(property="full_path", type="string"),
     *                         @OA\Property(property="size", type="integer"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="download_url", type="string")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="last_page", type="integer"),
     *                     @OA\Property(property="has_more", type="boolean")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(
     *                     property="available_types",
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 ),
     *                 @OA\Property(property="total_reports", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to list reports"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function listReports(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'sometimes|in:ctr,sar,compliance,kyc',
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        try {
            $type = $request->get('type');
            $limit = $request->get('limit', 20);
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $limit;

            $directories = [
                'ctr' => 'regulatory/ctr/',
                'sar' => 'regulatory/sar/',
                'compliance' => 'regulatory/compliance/',
                'kyc' => 'regulatory/kyc/',
            ];

            $reports = collect();

            $searchDirs = $type ? [$type => $directories[$type]] : $directories;

            foreach ($searchDirs as $reportType => $directory) {
                $files = Storage::files($directory);
                
                foreach ($files as $file) {
                    $reports->push([
                        'type' => $reportType,
                        'filename' => basename($file),
                        'full_path' => $file,
                        'size' => Storage::size($file),
                        'created_at' => Carbon::createFromTimestamp(Storage::lastModified($file))->toISOString(),
                        'download_url' => route('api.regulatory.download', ['filename' => basename($file)]),
                    ]);
                }
            }

            // Sort by creation date (newest first)
            $reports = $reports->sortByDesc('created_at');
            
            $total = $reports->count();
            $paginatedReports = $reports->slice($offset, $limit)->values();

            return response()->json([
                'data' => [
                    'reports' => $paginatedReports,
                    'pagination' => [
                        'total' => $total,
                        'per_page' => $limit,
                        'current_page' => $page,
                        'last_page' => ceil($total / $limit),
                        'has_more' => $page < ceil($total / $limit),
                    ],
                ],
                'meta' => [
                    'available_types' => array_keys($directories),
                    'total_reports' => $total,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to list regulatory reports',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific report content
     * 
     * @OA\Get(
     *     path="/api/regulatory/reports/{filename}",
     *     operationId="getRegulatoryReport",
     *     tags={"Regulatory Reporting"},
     *     summary="Get specific report content",
     *     description="Retrieves the full content of a specific regulatory report",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Report filename",
     *         @OA\Schema(type="string", example="ctr-2024-01-15.json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="file_path", type="string"),
     *                 @OA\Property(property="size", type="integer"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="content", type="object", description="Full report content")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid filename format"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Report not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve report"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getReport(Request $request, string $filename): JsonResponse
    {
        try {
            // Security: Only allow specific file extensions and patterns
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.json$/', $filename)) {
                return response()->json([
                    'error' => 'Invalid filename format'
                ], 400);
            }

            // Search for the file in all regulatory directories
            $directories = [
                'regulatory/ctr/',
                'regulatory/sar/',
                'regulatory/compliance/',
                'regulatory/kyc/',
            ];

            $filePath = null;
            foreach ($directories as $directory) {
                $possiblePath = $directory . $filename;
                if (Storage::exists($possiblePath)) {
                    $filePath = $possiblePath;
                    break;
                }
            }

            if (!$filePath) {
                return response()->json([
                    'error' => 'Report not found'
                ], 404);
            }

            $content = Storage::get($filePath);
            $reportData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Invalid report format'
                ], 500);
            }

            return response()->json([
                'data' => [
                    'filename' => $filename,
                    'file_path' => $filePath,
                    'size' => Storage::size($filePath),
                    'created_at' => Carbon::createFromTimestamp(Storage::lastModified($filePath))->toISOString(),
                    'content' => $reportData,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download report file
     * 
     * @OA\Get(
     *     path="/api/regulatory/reports/{filename}/download",
     *     operationId="downloadRegulatoryReport",
     *     tags={"Regulatory Reporting"},
     *     summary="Download regulatory report file",
     *     description="Downloads a regulatory report file as JSON attachment",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Report filename to download",
     *         @OA\Schema(type="string", example="ctr-2024-01-15.json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File download successful",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(type="string", format="binary")
     *         ),
     *         @OA\Header(
     *             header="Content-Type",
     *             description="Content type of the file",
     *             @OA\Schema(type="string", example="application/json")
     *         ),
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="Attachment header with filename",
     *             @OA\Schema(type="string", example="attachment; filename=\"ctr-2024-01-15.json\"")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid filename format"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Report not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function downloadReport(string $filename)
    {
        try {
            // Security: Only allow specific file extensions and patterns
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.json$/', $filename)) {
                return response()->json([
                    'error' => 'Invalid filename format'
                ], 400);
            }

            // Search for the file in all regulatory directories
            $directories = [
                'regulatory/ctr/',
                'regulatory/sar/',
                'regulatory/compliance/',
                'regulatory/kyc/',
            ];

            $filePath = null;
            foreach ($directories as $directory) {
                $possiblePath = $directory . $filename;
                if (Storage::exists($possiblePath)) {
                    $filePath = $possiblePath;
                    break;
                }
            }

            if (!$filePath) {
                return response()->json([
                    'error' => 'Report not found'
                ], 404);
            }

            return Storage::download($filePath, $filename, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to download report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a regulatory report
     * 
     * @OA\Delete(
     *     path="/api/regulatory/reports/{filename}",
     *     operationId="deleteRegulatoryReport",
     *     tags={"Regulatory Reporting"},
     *     summary="Delete a regulatory report",
     *     description="Permanently deletes a regulatory report file (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Report filename to delete",
     *         @OA\Schema(type="string", example="ctr-2024-01-15.json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="message", type="string", example="Report deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid filename format"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Report not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to delete report"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function deleteReport(string $filename): JsonResponse
    {
        try {
            // Security: Only allow specific file extensions and patterns
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.json$/', $filename)) {
                return response()->json([
                    'error' => 'Invalid filename format'
                ], 400);
            }

            // Search for the file in all regulatory directories
            $directories = [
                'regulatory/ctr/',
                'regulatory/sar/',
                'regulatory/compliance/',
                'regulatory/kyc/',
            ];

            $filePath = null;
            foreach ($directories as $directory) {
                $possiblePath = $directory . $filename;
                if (Storage::exists($possiblePath)) {
                    $filePath = $possiblePath;
                    break;
                }
            }

            if (!$filePath) {
                return response()->json([
                    'error' => 'Report not found'
                ], 404);
            }

            Storage::delete($filePath);

            return response()->json([
                'data' => [
                    'filename' => $filename,
                    'deleted_at' => now()->toISOString(),
                ],
                'message' => 'Report deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get regulatory metrics summary
     * 
     * @OA\Get(
     *     path="/api/regulatory/metrics",
     *     operationId="getRegulatoryMetrics",
     *     tags={"Regulatory Reporting"},
     *     summary="Get regulatory compliance metrics",
     *     description="Retrieves comprehensive regulatory compliance metrics and KPIs (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Time period for metrics",
     *         @OA\Schema(type="string", enum={"week", "month", "quarter", "year"}, default="month")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="period", type="string"),
     *                 @OA\Property(property="period_start", type="string", format="date"),
     *                 @OA\Property(property="period_end", type="string", format="date"),
     *                 @OA\Property(
     *                     property="metrics",
     *                     type="object",
     *                     @OA\Property(property="kyc", type="object", description="KYC compliance metrics"),
     *                     @OA\Property(property="transactions", type="object", description="Transaction monitoring metrics"),
     *                     @OA\Property(property="users", type="object", description="User compliance metrics"),
     *                     @OA\Property(property="risk", type="object", description="Risk assessment metrics"),
     *                     @OA\Property(property="gdpr", type="object", description="GDPR compliance metrics")
     *                 ),
     *                 @OA\Property(property="generated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve metrics"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|in:week,month,quarter,year',
        ]);

        try {
            $period = $request->get('period', 'month');
            
            $endDate = now();
            $startDate = match($period) {
                'week' => $endDate->copy()->subWeek(),
                'month' => $endDate->copy()->subMonth(),
                'quarter' => $endDate->copy()->subQuarter(),
                'year' => $endDate->copy()->subYear(),
                default => $endDate->copy()->subMonth(),
            };

            // Get summary metrics using reflection to access protected methods
            $reflection = new \ReflectionClass($this->regulatoryReportingService);
            
            $kycMetrics = $reflection->getMethod('getKycMetrics');
            $kycMetrics->setAccessible(true);
            
            $transactionMetrics = $reflection->getMethod('getTransactionMetrics');
            $transactionMetrics->setAccessible(true);
            
            $userMetrics = $reflection->getMethod('getUserMetrics');
            $userMetrics->setAccessible(true);
            
            $riskMetrics = $reflection->getMethod('getRiskMetrics');
            $riskMetrics->setAccessible(true);
            
            $gdprMetrics = $reflection->getMethod('getGdprMetrics');
            $gdprMetrics->setAccessible(true);

            return response()->json([
                'data' => [
                    'period' => $period,
                    'period_start' => $startDate->toDateString(),
                    'period_end' => $endDate->toDateString(),
                    'metrics' => [
                        'kyc' => $kycMetrics->invoke($this->regulatoryReportingService, $startDate, $endDate),
                        'transactions' => $transactionMetrics->invoke($this->regulatoryReportingService, $startDate, $endDate),
                        'users' => $userMetrics->invoke($this->regulatoryReportingService, $startDate, $endDate),
                        'risk' => $riskMetrics->invoke($this->regulatoryReportingService),
                        'gdpr' => $gdprMetrics->invoke($this->regulatoryReportingService, $startDate, $endDate),
                    ],
                    'generated_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve regulatory metrics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}