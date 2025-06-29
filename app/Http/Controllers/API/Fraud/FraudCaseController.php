<?php

namespace App\Http\Controllers\API\Fraud;

use App\Http\Controllers\Controller;
use App\Domain\Fraud\Services\FraudCaseService;
use App\Models\FraudCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FraudCaseController extends Controller
{
    private FraudCaseService $caseService;
    
    public function __construct(FraudCaseService $caseService)
    {
        $this->caseService = $caseService;
    }
    
    /**
     * List fraud cases
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:open,investigating,closed',
            'priority' => 'nullable|in:low,medium,high,critical',
            'risk_level' => 'nullable|in:very_low,low,medium,high,very_high',
            'assigned_to' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'search' => 'nullable|string|max:100',
            'sort_by' => 'nullable|in:created_at,priority,loss_amount,risk_level',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);
        
        $cases = $this->caseService->searchCases($request->all());
        
        return response()->json($cases);
    }
    
    /**
     * Get fraud case details
     */
    public function show(string $caseId): JsonResponse
    {
        $case = FraudCase::with([
            'fraudScore',
            'fraudScore.entity',
        ])->findOrFail($caseId);
        
        // Ensure user can view this case
        $this->authorize('view', $case);
        
        // Get similar cases
        $similarCases = $this->caseService->linkSimilarCases($case);
        
        return response()->json([
            'case' => $case,
            'similar_cases' => $similarCases->map(function ($similarCase) {
                return [
                    'id' => $similarCase->id,
                    'case_number' => $similarCase->case_number,
                    'risk_level' => $similarCase->risk_level,
                    'status' => $similarCase->status,
                    'created_at' => $similarCase->created_at,
                ];
            }),
        ]);
    }
    
    /**
     * Update fraud case
     */
    public function update(Request $request, string $caseId): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:open,investigating,closed',
            'priority' => 'nullable|in:low,medium,high,critical',
            'assigned_to' => 'nullable|integer',
            'note' => 'nullable|string|max:1000',
            'note_type' => 'nullable|in:investigation,analysis,action,resolution',
            'evidence' => 'nullable|array',
            'evidence.type' => 'required_with:evidence|string',
            'evidence.description' => 'required_with:evidence|string',
            'evidence.file_path' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);
        
        $case = FraudCase::findOrFail($caseId);
        
        // Ensure user can update this case
        $this->authorize('update', $case);
        
        $updatedCase = $this->caseService->updateInvestigation($case, $request->all());
        
        return response()->json([
            'message' => 'Fraud case updated successfully',
            'case' => $updatedCase,
        ]);
    }
    
    /**
     * Resolve fraud case
     */
    public function resolve(Request $request, string $caseId): JsonResponse
    {
        $request->validate([
            'resolution' => 'required|string|max:500',
            'outcome' => 'required|in:fraud,legitimate,unknown',
            'recovery_amount' => 'nullable|numeric|min:0',
        ]);
        
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
        
        return response()->json([
            'message' => 'Fraud case resolved successfully',
            'case' => $resolvedCase,
        ]);
    }
    
    /**
     * Escalate fraud case
     */
    public function escalate(Request $request, string $caseId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        
        $case = FraudCase::findOrFail($caseId);
        
        // Ensure user can escalate this case
        $this->authorize('escalate', $case);
        
        $escalatedCase = $this->caseService->escalateCase($case, $request->reason);
        
        return response()->json([
            'message' => 'Fraud case escalated successfully',
            'case' => $escalatedCase,
        ]);
    }
    
    /**
     * Get fraud case statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        
        $statistics = $this->caseService->getCaseStatistics($request->all());
        
        return response()->json(['statistics' => $statistics]);
    }
    
    /**
     * Assign case to investigator
     */
    public function assign(Request $request, string $caseId): JsonResponse
    {
        $request->validate([
            'investigator_id' => 'required|integer|exists:users,id',
        ]);
        
        $case = FraudCase::findOrFail($caseId);
        
        // Ensure user can assign this case
        $this->authorize('assign', $case);
        
        $case->update(['assigned_to' => $request->investigator_id]);
        
        $case->addInvestigationNote(
            "Case assigned to investigator ID: {$request->investigator_id}",
            auth()->user()->name ?? 'System',
            'assignment'
        );
        
        return response()->json([
            'message' => 'Case assigned successfully',
            'case' => $case,
        ]);
    }
    
    /**
     * Add evidence to case
     */
    public function addEvidence(Request $request, string $caseId): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:document,screenshot,log,communication,other',
            'description' => 'required|string|max:500',
            'file' => 'nullable|file|max:10240', // 10MB max
            'metadata' => 'nullable|array',
        ]);
        
        $case = FraudCase::findOrFail($caseId);
        
        // Ensure user can add evidence to this case
        $this->authorize('update', $case);
        
        $evidenceData = [
            'type' => $request->type,
            'description' => $request->description,
            'metadata' => $request->metadata ?? [],
        ];
        
        // Handle file upload
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('fraud-evidence', 'private');
            $evidenceData['file_path'] = $path;
        }
        
        $updatedCase = $this->caseService->updateInvestigation($case, [
            'evidence' => $evidenceData,
        ]);
        
        return response()->json([
            'message' => 'Evidence added successfully',
            'case' => $updatedCase,
        ]);
    }
    
    /**
     * Get case timeline
     */
    public function timeline(string $caseId): JsonResponse
    {
        $case = FraudCase::findOrFail($caseId);
        
        // Ensure user can view this case
        $this->authorize('view', $case);
        
        $timeline = [];
        
        // Case created
        $timeline[] = [
            'timestamp' => $case->created_at,
            'type' => 'case_created',
            'description' => 'Fraud case created',
            'actor' => 'System',
        ];
        
        // Investigation started
        if ($case->investigation_started_at) {
            $timeline[] = [
                'timestamp' => $case->investigation_started_at,
                'type' => 'investigation_started',
                'description' => 'Investigation started',
                'actor' => 'System',
            ];
        }
        
        // Add investigation notes to timeline
        foreach ($case->investigation_notes ?? [] as $note) {
            $timeline[] = [
                'timestamp' => $note['timestamp'],
                'type' => $note['type'] ?? 'note',
                'description' => $note['note'],
                'actor' => $note['author'] ?? 'Unknown',
            ];
        }
        
        // Case resolved
        if ($case->resolved_at) {
            $timeline[] = [
                'timestamp' => $case->resolved_at,
                'type' => 'case_resolved',
                'description' => "Case resolved: {$case->resolution}",
                'actor' => $case->resolution_notes['resolved_by'] ?? 'Unknown',
            ];
        }
        
        // Sort by timestamp
        usort($timeline, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        
        return response()->json(['timeline' => $timeline]);
    }
}