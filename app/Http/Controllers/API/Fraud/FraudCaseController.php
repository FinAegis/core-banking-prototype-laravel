<?php

namespace App\Http\Controllers\API\Fraud;

use App\Domain\Fraud\Models\FraudCase;
use App\Domain\Fraud\Services\FraudCaseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Fraud Cases",
 *     description="Fraud case management, investigation, and resolution"
 * )
 */
class FraudCaseController extends Controller
{
    private FraudCaseService $caseService;

    public function __construct(FraudCaseService $caseService)
    {
        $this->caseService = $caseService;
    }

    /**
     * @OA\Get(
     *     path="/api/v2/fraud/cases",
     *     operationId="fraudCasesIndex",
     *     tags={"Fraud Cases"},
     *     summary="List fraud cases",
     *     description="Returns paginated list of fraud cases with filters",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(
            [
                'status'      => 'nullable|in:open,investigating,closed',
                'priority'    => 'nullable|in:low,medium,high,critical',
                'risk_level'  => 'nullable|in:very_low,low,medium,high,very_high',
                'assigned_to' => 'nullable|integer',
                'date_from'   => 'nullable|date',
                'date_to'     => 'nullable|date|after_or_equal:date_from',
                'min_amount'  => 'nullable|numeric|min:0',
                'max_amount'  => 'nullable|numeric|min:0',
                'search'      => 'nullable|string|max:100',
                'sort_by'     => 'nullable|in:created_at,priority,loss_amount,risk_level',
                'sort_order'  => 'nullable|in:asc,desc',
                'per_page'    => 'nullable|integer|min:10|max:100',
            ]
        );

        $cases = $this->caseService->searchCases($request->all());

        return response()->json($cases);
    }

    /**
     * @OA\Get(
     *     path="/api/v2/fraud/cases/{caseId}",
     *     operationId="fraudCasesShow",
     *     tags={"Fraud Cases"},
     *     summary="Get fraud case details",
     *     description="Returns detailed information about a fraud case",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="caseId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(string $caseId): JsonResponse
    {
        $case = FraudCase::with(
            [
                'fraudScore',
                'fraudScore.entity',
            ]
        )->findOrFail($caseId);

        // Ensure user can view this case
        $this->authorize('view', $case);

        // Get similar cases
        $similarCases = $this->caseService->linkSimilarCases($case);

        return response()->json(
            [
                'case'          => $case,
                'similar_cases' => $similarCases->map(
                    function ($similarCase) {
                        return [
                            'id'          => $similarCase->id,
                            'case_number' => $similarCase->case_number,
                            'risk_level'  => $similarCase->risk_level,
                            'status'      => $similarCase->status,
                            'created_at'  => $similarCase->created_at,
                        ];
                    }
                ),
            ]
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v2/fraud/cases/{caseId}",
     *     operationId="fraudCasesUpdate",
     *     tags={"Fraud Cases"},
     *     summary="Update fraud case",
     *     description="Updates a fraud case with new information",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="caseId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request, string $caseId): JsonResponse
    {
        $request->validate(
            [
                'status'               => 'nullable|in:open,investigating,closed',
                'priority'             => 'nullable|in:low,medium,high,critical',
                'assigned_to'          => 'nullable|integer',
                'note'                 => 'nullable|string|max:1000',
                'note_type'            => 'nullable|in:investigation,analysis,action,resolution',
                'evidence'             => 'nullable|array',
                'evidence.type'        => 'required_with:evidence|string',
                'evidence.description' => 'required_with:evidence|string',
                'evidence.file_path'   => 'nullable|string',
                'tags'                 => 'nullable|array',
                'tags.*'               => 'string|max:50',
            ]
        );

        $case = FraudCase::findOrFail($caseId);

        // Ensure user can update this case
        $this->authorize('update', $case);

        $updatedCase = $this->caseService->updateInvestigation($case, $request->all());

        return response()->json(
            [
                'message' => 'Fraud case updated successfully',
                'case'    => $updatedCase,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/cases/{caseId}/resolve",
     *     operationId="fraudCasesResolve",
     *     tags={"Fraud Cases"},
     *     summary="Resolve fraud case",
     *     description="Resolves a fraud case with outcome",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="caseId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function resolve(Request $request, string $caseId): JsonResponse
    {
        $request->validate(
            [
                'resolution'      => 'required|string|max:500',
                'outcome'         => 'required|in:fraud,legitimate,unknown',
                'recovery_amount' => 'nullable|numeric|min:0',
            ]
        );

        $case = FraudCase::findOrFail($caseId);

        // Ensure user can resolve this case
        $this->authorize('resolve', $case);

        // Update recovery amount if provided
        if ($request->has('recovery_amount')) {
            $case->update(['recovery_amount' => $request->recovery_amount]);
        }

        $resolvedCase = $this->caseService->resolveCase(
            $case,
            $request->resolution,
            $request->outcome
        );

        return response()->json(
            [
                'message' => 'Fraud case resolved successfully',
                'case'    => $resolvedCase,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/cases/{caseId}/escalate",
     *     operationId="fraudCasesEscalate",
     *     tags={"Fraud Cases"},
     *     summary="Escalate fraud case",
     *     description="Escalates a fraud case with reason",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="caseId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function escalate(Request $request, string $caseId): JsonResponse
    {
        $request->validate(
            [
                'reason' => 'required|string|max:500',
            ]
        );

        $case = FraudCase::findOrFail($caseId);

        // Ensure user can escalate this case
        $this->authorize('escalate', $case);

        $escalatedCase = $this->caseService->escalateCase($case, $request->reason);

        return response()->json(
            [
                'message' => 'Fraud case escalated successfully',
                'case'    => $escalatedCase,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/fraud/cases/statistics",
     *     operationId="fraudCasesStatistics",
     *     tags={"Fraud Cases"},
     *     summary="Get fraud case statistics",
     *     description="Returns fraud case statistics with optional date filters",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate(
            [
                'date_from' => 'nullable|date',
                'date_to'   => 'nullable|date|after_or_equal:date_from',
            ]
        );

        $statistics = $this->caseService->getCaseStatistics($request->all());

        return response()->json(['statistics' => $statistics]);
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/cases/{caseId}/assign",
     *     operationId="fraudCasesAssign",
     *     tags={"Fraud Cases"},
     *     summary="Assign case to investigator",
     *     description="Assigns a fraud case to an investigator",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="caseId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function assign(Request $request, string $caseId): JsonResponse
    {
        $request->validate(
            [
                'investigator_id' => 'required|integer|exists:users,id',
            ]
        );

        $case = FraudCase::findOrFail($caseId);

        // Ensure user can assign this case
        $this->authorize('assign', $case);

        $case->update(['assigned_to' => $request->investigator_id]);

        $case->addInvestigationNote(
            "Case assigned to investigator ID: {$request->investigator_id}",
            auth()->user()->name ?? 'System',
            'assignment'
        );

        return response()->json(
            [
                'message' => 'Case assigned successfully',
                'case'    => $case,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/cases/{caseId}/evidence",
     *     operationId="fraudCasesAddEvidence",
     *     tags={"Fraud Cases"},
     *     summary="Add evidence to case",
     *     description="Adds evidence to a fraud case",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="caseId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function addEvidence(Request $request, string $caseId): JsonResponse
    {
        $request->validate(
            [
                'type'        => 'required|in:document,screenshot,log,communication,other',
                'description' => 'required|string|max:500',
                'file'        => 'nullable|file|max:10240', // 10MB max
                'metadata'    => 'nullable|array',
            ]
        );

        $case = FraudCase::findOrFail($caseId);

        // Ensure user can add evidence to this case
        $this->authorize('update', $case);

        $evidenceData = [
            'type'        => $request->type,
            'description' => $request->description,
            'metadata'    => $request->metadata ?? [],
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('fraud-evidence', 'private');
            $evidenceData['file_path'] = $path;
        }

        $updatedCase = $this->caseService->updateInvestigation(
            $case,
            [
                'evidence' => $evidenceData,
            ]
        );

        return response()->json(
            [
                'message' => 'Evidence added successfully',
                'case'    => $updatedCase,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/fraud/cases/{caseId}/timeline",
     *     operationId="fraudCasesTimeline",
     *     tags={"Fraud Cases"},
     *     summary="Get case timeline",
     *     description="Returns the investigation timeline for a fraud case",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="caseId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function timeline(string $caseId): JsonResponse
    {
        $case = FraudCase::findOrFail($caseId);

        // Ensure user can view this case
        $this->authorize('view', $case);

        $timeline = [];

        // Case created
        $timeline[] = [
            'timestamp'   => $case->created_at,
            'type'        => 'case_created',
            'description' => 'Fraud case created',
            'actor'       => 'System',
        ];

        // Investigation started
        if ($case->investigation_started_at) {
            $timeline[] = [
                'timestamp'   => $case->investigation_started_at,
                'type'        => 'investigation_started',
                'description' => 'Investigation started',
                'actor'       => 'System',
            ];
        }

        // Add investigation notes to timeline
        foreach ($case->investigation_notes ?? [] as $note) {
            $timeline[] = [
                'timestamp'   => $note['timestamp'],
                'type'        => $note['type'] ?? 'note',
                'description' => $note['note'],
                'actor'       => $note['author'] ?? 'Unknown',
            ];
        }

        // Case resolved
        if ($case->resolved_at) {
            $timeline[] = [
                'timestamp'   => $case->resolved_at,
                'type'        => 'case_resolved',
                'description' => "Case resolved: {$case->resolution}",
                'actor'       => $case->resolution_notes['resolved_by'] ?? 'Unknown',
            ];
        }

        // Sort by timestamp
        usort($timeline, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return response()->json(['timeline' => $timeline]);
    }
}
