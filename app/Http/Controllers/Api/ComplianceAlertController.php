<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Services\AlertManagementService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplianceAlertController extends Controller
{
    public function __construct(
        private readonly AlertManagementService $alertService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/alerts",
     *     operationId="getComplianceAlerts",
     *     tags={"Compliance Alerts"},
     *     summary="Get compliance alerts",
     *     description="Retrieve compliance alerts with filtering options",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"new", "assigned", "investigating", "escalated", "resolved", "closed"})
     *     ),
     *     @OA\Parameter(
     *         name="severity",
     *         in="query",
     *         description="Filter by severity",
     *         required=false,
     *         @OA\Schema(type="string", enum={"low", "medium", "high", "critical"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by alert type",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *         description="List of compliance alerts",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'      => ['sometimes', 'string', Rule::in(['new', 'assigned', 'investigating', 'escalated', 'resolved', 'closed'])],
            'severity'    => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'type'        => ['sometimes', 'string', 'max:50'],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'page'        => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = ComplianceAlert::query();

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['severity'])) {
            $query->where('severity', $validated['severity']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['assigned_to'])) {
            $query->where('assigned_to', $validated['assigned_to']);
        }

        $alerts = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $alerts->items(),
            'meta' => [
                'total'        => $alerts->total(),
                'per_page'     => $alerts->perPage(),
                'current_page' => $alerts->currentPage(),
                'last_page'    => $alerts->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/alerts/{id}",
     *     operationId="getComplianceAlert",
     *     tags={"Compliance Alerts"},
     *     summary="Get alert details",
     *     description="Retrieve detailed information about a specific compliance alert",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Alert ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alert details",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Alert not found"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $alert = ComplianceAlert::with(['assignedTo', 'case'])
            ->findOrFail($id);

        return response()->json([
            'data' => $alert,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/alerts",
     *     operationId="createComplianceAlert",
     *     tags={"Compliance Alerts"},
     *     summary="Create alert",
     *     description="Create a new compliance alert",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "severity", "description"},
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="severity", type="string", enum={"low", "medium", "high", "critical"}),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="entity_type", type="string"),
     *             @OA\Property(property="entity_id", type="string"),
     *             @OA\Property(property="details", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Alert created successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'        => ['required', 'string', 'max:50'],
            'severity'    => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['required', 'string', 'max:1000'],
            'entity_type' => ['sometimes', 'string', 'max:50'],
            'entity_id'   => ['sometimes', 'string', 'max:255'],
            'details'     => ['sometimes', 'array'],
        ]);

        $alert = $this->alertService->createAlert($validated);

        return response()->json([
            'message' => 'Alert created successfully',
            'data'    => $alert,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/compliance/alerts/{id}/status",
     *     operationId="updateAlertStatus",
     *     tags={"Compliance Alerts"},
     *     summary="Update alert status",
     *     description="Update the status of a compliance alert",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Alert ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"new", "assigned", "investigating", "escalated", "resolved", "closed"}),
     *             @OA\Property(property="notes", type="string"),
     *             @OA\Property(property="resolution", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'     => ['required', Rule::in(['new', 'assigned', 'investigating', 'escalated', 'resolved', 'closed'])],
            'notes'      => ['sometimes', 'string', 'max:1000'],
            'resolution' => ['required_if:status,resolved,closed', 'string', 'max:1000'],
        ]);

        $alert = $this->alertService->updateAlertStatus(
            $id,
            $validated['status'],
            $validated['notes'] ?? null,
            $validated['resolution'] ?? null
        );

        return response()->json([
            'message' => 'Alert status updated successfully',
            'data'    => $alert,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/compliance/alerts/{id}/assign",
     *     operationId="assignAlert",
     *     tags={"Compliance Alerts"},
     *     summary="Assign alert",
     *     description="Assign a compliance alert to a user",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Alert ID",
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
     *         description="Alert assigned successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function assign(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'notes'   => ['sometimes', 'string', 'max:500'],
        ]);

        $alert = $this->alertService->assignAlert(
            $id,
            $validated['user_id'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Alert assigned successfully',
            'data'    => $alert,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/alerts/{id}/notes",
     *     operationId="addAlertNote",
     *     tags={"Compliance Alerts"},
     *     summary="Add investigation note",
     *     description="Add an investigation note to a compliance alert",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Alert ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"note"},
     *             @OA\Property(property="note", type="string"),
     *             @OA\Property(property="findings", type="object")
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
        $validated = $request->validate([
            'note'     => ['required', 'string', 'max:2000'],
            'findings' => ['sometimes', 'array'],
        ]);

        $this->alertService->addInvestigationNote(
            $id,
            $validated['note'],
            $validated['findings'] ?? []
        );

        return response()->json([
            'message' => 'Investigation note added successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/alerts/link",
     *     operationId="linkAlerts",
     *     tags={"Compliance Alerts"},
     *     summary="Link related alerts",
     *     description="Link multiple related compliance alerts",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"alert_ids", "relationship_type"},
     *             @OA\Property(
     *                 property="alert_ids",
     *                 type="array",
     *                 @OA\Items(type="string")
     *             ),
     *             @OA\Property(property="relationship_type", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alerts linked successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function linkAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_ids'         => ['required', 'array', 'min:2'],
            'alert_ids.*'       => ['string', 'exists:compliance_alerts,alert_id'],
            'relationship_type' => ['required', 'string', 'max:50'],
            'notes'             => ['sometimes', 'string', 'max:500'],
        ]);

        $this->alertService->linkAlerts(
            $validated['alert_ids'],
            $validated['relationship_type'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Alerts linked successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/alerts/create-case",
     *     operationId="createCaseFromAlerts",
     *     tags={"Compliance Alerts"},
     *     summary="Create case from alerts",
     *     description="Create a compliance case from multiple alerts",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"alert_ids", "title"},
     *             @OA\Property(
     *                 property="alert_ids",
     *                 type="array",
     *                 @OA\Items(type="string")
     *             ),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "critical"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Case created successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function createCase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_ids'   => ['required', 'array', 'min:1'],
            'alert_ids.*' => ['string', 'exists:compliance_alerts,alert_id'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'priority'    => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
        ]);

        $case = $this->alertService->createCaseFromAlerts(
            $validated['alert_ids'],
            $validated['title'],
            $validated['description'] ?? null
        );

        return response()->json([
            'message' => 'Case created successfully',
            'data'    => $case,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/alerts/statistics",
     *     operationId="getAlertStatistics",
     *     tags={"Compliance Alerts"},
     *     summary="Get alert statistics",
     *     description="Get statistics and metrics for compliance alerts",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period for statistics",
     *         required=false,
     *         @OA\Schema(type="string", enum={"today", "week", "month", "quarter", "year"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alert statistics",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->query('period', 'month');

        $statistics = $this->alertService->getAlertStatistics($period);

        return response()->json([
            'data' => $statistics,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/alerts/trends",
     *     operationId="getAlertTrends",
     *     tags={"Compliance Alerts"},
     *     summary="Get alert trends",
     *     description="Get trend analysis for compliance alerts",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days for trend analysis",
     *         required=false,
     *         @OA\Schema(type="integer", default=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alert trends",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function trends(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);

        $trends = $this->alertService->getAlertTrends($days);

        return response()->json([
            'data' => $trends,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/alerts/search",
     *     operationId="searchAlerts",
     *     tags={"Compliance Alerts"},
     *     summary="Search alerts",
     *     description="Search compliance alerts with advanced filters",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="query", type="string"),
     *             @OA\Property(property="entity_type", type="string"),
     *             @OA\Property(property="date_from", type="string", format="date"),
     *             @OA\Property(property="date_to", type="string", format="date"),
     *             @OA\Property(property="min_severity", type="string"),
     *             @OA\Property(property="include_resolved", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query'            => ['sometimes', 'string', 'max:255'],
            'entity_type'      => ['sometimes', 'string', 'max:50'],
            'date_from'        => ['sometimes', 'date'],
            'date_to'          => ['sometimes', 'date', 'after_or_equal:date_from'],
            'min_severity'     => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'include_resolved' => ['sometimes', 'boolean'],
        ]);

        $results = $this->alertService->searchAlerts($validated);

        return response()->json([
            'data' => $results,
        ]);
    }
}
