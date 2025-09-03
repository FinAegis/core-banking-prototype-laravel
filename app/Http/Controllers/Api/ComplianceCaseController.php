<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Models\ComplianceCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ComplianceCaseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/compliance/cases",
     *     operationId="getComplianceCases",
     *     tags={"Compliance Cases"},
     *     summary="Get compliance cases",
     *     description="Retrieve compliance cases with filtering options",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"open", "investigating", "pending_review", "resolved", "closed"})
     *     ),
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filter by priority",
     *         required=false,
     *         @OA\Schema(type="string", enum={"low", "medium", "high", "critical"})
     *     ),
     *     @OA\Parameter(
     *         name="assigned_to",
     *         in="query",
     *         description="Filter by assigned user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of compliance cases",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'      => ['sometimes', 'string', Rule::in(['open', 'in_progress', 'investigating', 'pending_review', 'resolved', 'closed'])],
            'priority'    => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'page'        => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = ComplianceCase::with(['assignedTo', 'alerts']);

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        if (isset($validated['assigned_to'])) {
            $query->where('assigned_to', $validated['assigned_to']);
        }

        $cases = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $cases->items(),
            'meta' => [
                'total'        => $cases->total(),
                'per_page'     => $cases->perPage(),
                'current_page' => $cases->currentPage(),
                'last_page'    => $cases->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/cases/{id}",
     *     operationId="getComplianceCase",
     *     tags={"Compliance Cases"},
     *     summary="Get case details",
     *     description="Retrieve detailed information about a specific compliance case",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Case ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Case details",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Case not found"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $case = ComplianceCase::with([
            'assignedTo',
            'alerts',
            'alerts.assignedTo',
        ])->findOrFail($id);

        return response()->json([
            'data' => $case,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/cases",
     *     operationId="createComplianceCase",
     *     tags={"Compliance Cases"},
     *     summary="Create case",
     *     description="Create a new compliance case",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "priority"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "critical"}),
     *             @OA\Property(property="entities", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="evidence", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Case created successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'priority'    => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'type'        => ['required', Rule::in(['investigation', 'sar', 'ctr', 'regulatory', 'fraud', 'aml'])],
            'entities'    => ['sometimes', 'array'],
            'evidence'    => ['sometimes', 'array'],
        ]);

        $case = DB::transaction(function () use ($validated) {
            // Generate a case number
            $year = now()->format('Y');
            $lastCase = ComplianceCase::where('case_number', 'like', "CASE-{$year}-%")
                ->orderBy('case_number', 'desc')
                ->first();

            if ($lastCase) {
                $lastNumber = (int) substr($lastCase->case_number, -6);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            $caseNumber = sprintf('CASE-%s-%06d', $year, $newNumber);

            return ComplianceCase::create([
                'case_number' => $caseNumber,
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority'    => $validated['priority'],
                'type'        => $validated['type'],
                'status'      => 'open',
                'entities'    => $validated['entities'] ?? [],
                'evidence'    => $validated['evidence'] ?? [],
                'created_by'  => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'Case created successfully',
            'data'    => $case,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/compliance/cases/{id}",
     *     operationId="updateComplianceCase",
     *     tags={"Compliance Cases"},
     *     summary="Update case",
     *     description="Update a compliance case",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Case ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "critical"}),
     *             @OA\Property(property="status", type="string", enum={"open", "investigating", "pending_review", "resolved", "closed"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Case updated successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $case = ComplianceCase::findOrFail($id);

        $validated = $request->validate([
            'title'                 => ['sometimes', 'string', 'max:255'],
            'description'           => ['sometimes', 'string', 'max:2000'],
            'priority'              => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status'                => ['sometimes', Rule::in(['open', 'in_progress', 'investigating', 'pending_review', 'resolved', 'closed'])],
            'closure_reason'        => ['sometimes', 'string', 'max:255'],
            'closure_notes'         => ['sometimes', 'string', 'max:2000'],
            'actions_taken'         => ['sometimes', 'array'],
            'investigation_summary' => ['sometimes', 'string', 'max:5000'],
            'findings'              => ['sometimes', 'array'],
            'recommendations'       => ['sometimes', 'array'],
        ]);

        $case->update($validated);

        if (isset($validated['status']) && in_array($validated['status'], ['resolved', 'closed'])) {
            $updates = [
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ];
            if (isset($validated['closure_reason'])) {
                $updates['closure_reason'] = $validated['closure_reason'];
            }
            if (isset($validated['closure_notes'])) {
                $updates['closure_notes'] = $validated['closure_notes'];
            }
            if (isset($validated['actions_taken'])) {
                $updates['actions_taken'] = $validated['actions_taken'];
            }
            $case->update($updates);
        }

        return response()->json([
            'message' => 'Case updated successfully',
            'data'    => $case,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/compliance/cases/{id}/assign",
     *     operationId="assignCase",
     *     tags={"Compliance Cases"},
     *     summary="Assign case",
     *     description="Assign a compliance case to a user",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Case ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Case assigned successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function assign(string $id, Request $request): JsonResponse
    {
        $case = ComplianceCase::findOrFail($id);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'notes'   => ['sometimes', 'string', 'max:500'],
        ]);

        $case->update([
            'assigned_to' => $validated['user_id'],
            'assigned_at' => now(),
        ]);

        return response()->json([
            'message' => 'Case assigned successfully',
            'data'    => $case,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/cases/{id}/evidence",
     *     operationId="addCaseEvidence",
     *     tags={"Compliance Cases"},
     *     summary="Add evidence",
     *     description="Add evidence to a compliance case",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Case ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "description"},
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="source", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Evidence added successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function addEvidence(string $id, Request $request): JsonResponse
    {
        $case = ComplianceCase::findOrFail($id);

        $validated = $request->validate([
            'type'        => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:1000'],
            'data'        => ['sometimes', 'array'],
            'source'      => ['sometimes', 'string', 'max:255'],
        ]);

        $evidence = $case->evidence ?? [];
        $evidence[] = array_merge($validated, [
            'added_by' => auth()->id(),
            'added_at' => now(),
        ]);

        $case->update(['evidence' => $evidence]);

        return response()->json([
            'message' => 'Evidence added successfully',
            'data'    => $case,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/cases/{id}/notes",
     *     operationId="addCaseNote",
     *     tags={"Compliance Cases"},
     *     summary="Add case note",
     *     description="Add a note to a compliance case",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Case ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"note"},
     *             @OA\Property(property="note", type="string"),
     *             @OA\Property(property="type", type="string", enum={"general", "investigation", "review", "decision"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Note added successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function addNote(string $id, Request $request): JsonResponse
    {
        $case = ComplianceCase::findOrFail($id);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'type' => ['sometimes', Rule::in(['general', 'investigation', 'review', 'decision'])],
        ]);

        $notes = $case->notes ?? [];
        $notes[] = [
            'note'       => $validated['note'],
            'type'       => $validated['type'] ?? 'general',
            'created_by' => auth()->id(),
            'created_at' => now(),
        ];

        $case->update(['notes' => $notes]);

        return response()->json([
            'message' => 'Note added successfully',
            'data'    => $case,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/cases/{id}/escalate",
     *     operationId="escalateCase",
     *     tags={"Compliance Cases"},
     *     summary="Escalate case",
     *     description="Escalate a compliance case",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Case ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(property="escalate_to", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Case escalated successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function escalate(string $id, Request $request): JsonResponse
    {
        $case = ComplianceCase::findOrFail($id);

        $validated = $request->validate([
            'reason'      => ['required', 'string', 'max:1000'],
            'escalate_to' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        // Escalation automatically raises priority to critical
        $case->update([
            'priority'         => 'critical',
            'status'           => 'escalated',
            'escalation_level' => ($case->escalation_level ?? 0) + 1,
        ]);

        // Add escalation note
        $notes = $case->notes ?? [];
        $notes[] = [
            'note'         => 'ESCALATION: ' . $validated['reason'],
            'type'         => 'escalation',
            'created_by'   => auth()->id(),
            'created_at'   => now(),
            'escalated_to' => $validated['escalate_to'] ?? null,
        ];

        $case->update(['notes' => $notes]);

        // If escalated to specific user, assign to them
        if (isset($validated['escalate_to'])) {
            $case->update([
                'assigned_to' => $validated['escalate_to'],
                'assigned_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Case escalated successfully',
            'data'    => $case,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/cases/{id}/timeline",
     *     operationId="getCaseTimeline",
     *     tags={"Compliance Cases"},
     *     summary="Get case timeline",
     *     description="Get the timeline of events for a compliance case",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Case ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Case timeline",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function timeline(string $id): JsonResponse
    {
        $case = ComplianceCase::with(['alerts'])->findOrFail($id);

        $timeline = [];

        // Add case creation
        $timeline[] = [
            'type'        => 'case_created',
            'timestamp'   => $case->created_at,
            'description' => 'Case created',
            'user'        => $case->created_by,
        ];

        // Add alerts
        foreach ($case->alerts as $alert) {
            $timeline[] = [
                'type'        => 'alert_linked',
                'timestamp'   => $alert->pivot->created_at ?? $alert->created_at,
                'description' => "Alert {$alert->alert_id} linked to case",
                'alert_id'    => $alert->alert_id,
            ];
        }

        // Add notes
        foreach ($case->notes ?? [] as $note) {
            $timeline[] = [
                'type'        => 'note_added',
                'timestamp'   => $note['created_at'],
                'description' => $note['note'],
                'user'        => $note['created_by'],
            ];
        }

        // Sort by timestamp
        /** @phpstan-ignore-next-line */
        usort($timeline, function ($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return response()->json([
            'data' => $timeline,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/compliance/cases/{id}",
     *     operationId="deleteComplianceCase",
     *     tags={"Compliance Cases"},
     *     summary="Delete case",
     *     description="Delete a compliance case (soft delete)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Case ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Case deleted successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $case = ComplianceCase::findOrFail($id);

        // Prevent deletion of active cases
        $activeStatuses = ['open', 'in_progress', 'pending_review', 'investigating'];
        if (in_array($case->status, $activeStatuses)) {
            return response()->json([
                'message' => 'Cannot delete active case',
            ], 422);
        }

        $case->delete();

        return response()->json([
            'message' => 'Case deleted successfully',
        ]);
    }
}
