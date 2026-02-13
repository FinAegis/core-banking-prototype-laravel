<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Services\Certification\BreachNotificationService;
use App\Domain\Compliance\Services\Certification\ConsentManagementService;
use App\Domain\Compliance\Services\Certification\DataProcessingRegisterService;
use App\Domain\Compliance\Services\Certification\DataRetentionService;
use App\Domain\Compliance\Services\Certification\DpiaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @OA\Tag(
 *     name="GDPR Enhanced",
 *     description="GDPR Article 30, DPIA, Breach Notification, Consent v2, and Data Retention endpoints"
 * )
 */
class GdprEnhancedController extends Controller
{
    public function __construct(
        private readonly DataProcessingRegisterService $registerService,
        private readonly DpiaService $dpiaService,
        private readonly BreachNotificationService $breachService,
        private readonly ConsentManagementService $consentService,
        private readonly DataRetentionService $retentionService,
    ) {
    }

    // ── Article 30 Register ──────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/compliance/gdpr/v2/register",
     *     operationId="gDPREnhancedGetRegister",
     *     tags={"GDPR Enhanced"},
     *     summary="Get processing activities register",
     *     description="Returns the GDPR Article 30 processing activities register",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getRegister(Request $request): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $data = $demoMode
                ? $this->registerService->getDemoRegister()
                : $this->registerService->exportRegister();

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve processing register',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/gdpr/v2/register/activities",
     *     operationId="gDPREnhancedCreateActivity",
     *     tags={"GDPR Enhanced"},
     *     summary="Create a processing activity",
     *     description="Creates a new processing activity in the Article 30 register",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function createActivity(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name'             => ['required', 'string'],
                'purpose'          => ['required', 'string'],
                'legal_basis'      => ['required', 'string'],
                'data_categories'  => ['nullable', 'array'],
                'data_subjects'    => ['nullable', 'array'],
                'recipients'       => ['nullable', 'array'],
                'retention_period' => ['nullable', 'string'],
            ]);

            $activity = $this->registerService->createActivity($validated);

            return response()->json([
                'message' => 'Processing activity created successfully',
                'data'    => $activity,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to create processing activity',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/gdpr/v2/register/completeness",
     *     operationId="gDPREnhancedGetRegisterCompleteness",
     *     tags={"GDPR Enhanced"},
     *     summary="Get register completeness check",
     *     description="Checks completeness of the processing register",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getRegisterCompleteness(): JsonResponse
    {
        try {
            $completeness = $this->registerService->checkCompleteness();

            return response()->json(['data' => $completeness]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to check register completeness',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── DPIA ─────────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/compliance/gdpr/v2/dpia",
     *     operationId="gDPREnhancedGetDpiaSummary",
     *     tags={"GDPR Enhanced"},
     *     summary="Get DPIA summary",
     *     description="Returns Data Protection Impact Assessment summary",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getDpiaSummary(): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $data = $demoMode
                ? $this->dpiaService->getDemoSummary()
                : $this->dpiaService->getSummary();

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve DPIA summary',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/gdpr/v2/dpia",
     *     operationId="gDPREnhancedCreateDpia",
     *     tags={"GDPR Enhanced"},
     *     summary="Create a DPIA",
     *     description="Creates a new Data Protection Impact Assessment",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function createDpia(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title'                  => ['required', 'string'],
                'description'            => ['nullable', 'string'],
                'processing_activity_id' => ['nullable', 'string'],
                'risks'                  => ['nullable', 'array'],
                'mitigations'            => ['nullable', 'array'],
                'assessor'               => ['nullable', 'string'],
            ]);

            $assessment = $this->dpiaService->createAssessment($validated);

            return response()->json([
                'message' => 'DPIA created successfully',
                'data'    => $assessment,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to create DPIA',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/gdpr/v2/dpia/{id}/approve",
     *     operationId="gDPREnhancedApproveDpia",
     *     tags={"GDPR Enhanced"},
     *     summary="Approve a DPIA",
     *     description="Approves a Data Protection Impact Assessment",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function approveDpia(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reviewer' => ['required', 'string'],
            ]);

            $assessment = $this->dpiaService->approveAssessment($id, $validated['reviewer']);

            return response()->json([
                'message' => 'DPIA approved successfully',
                'data'    => $assessment,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to approve DPIA',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Breach Notification ──────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/compliance/gdpr/v2/breaches",
     *     operationId="gDPREnhancedGetBreachSummary",
     *     tags={"GDPR Enhanced"},
     *     summary="Get breach summary",
     *     description="Returns data breach notification summary",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getBreachSummary(): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $data = $demoMode
                ? $this->breachService->getDemoSummary()
                : $this->breachService->getSummary();

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve breach summary',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/gdpr/v2/breaches",
     *     operationId="gDPREnhancedReportBreach",
     *     tags={"GDPR Enhanced"},
     *     summary="Report a data breach",
     *     description="Reports a new data breach with 72h notification deadline",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function reportBreach(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title'                      => ['required', 'string'],
                'description'                => ['required', 'string'],
                'severity'                   => ['required', 'in:critical,high,medium,low'],
                'affected_data_types'        => ['nullable', 'array'],
                'affected_individuals_count' => ['nullable', 'integer'],
                'reported_by'                => ['nullable', 'string'],
            ]);

            $breach = $this->breachService->reportBreach($validated);

            return response()->json([
                'message' => 'Breach reported successfully — 72h notification deadline set',
                'data'    => $breach,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to report breach',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/gdpr/v2/breaches/{id}/notify-authority",
     *     operationId="gDPREnhancedNotifyAuthority",
     *     tags={"GDPR Enhanced"},
     *     summary="Notify authority about a breach",
     *     description="Records authority notification for a data breach",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function notifyAuthority(Request $request, string $id): JsonResponse
    {
        try {
            $breach = $this->breachService->notifyAuthority($id, $request->input('notes'));

            return response()->json([
                'message' => 'Authority notification recorded',
                'data'    => $breach,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to record authority notification',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/gdpr/v2/breaches/deadlines",
     *     operationId="gDPREnhancedCheckDeadlines",
     *     tags={"GDPR Enhanced"},
     *     summary="Check breach deadlines",
     *     description="Checks breach notification deadline status",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function checkDeadlines(): JsonResponse
    {
        try {
            $status = $this->breachService->checkDeadlines();

            return response()->json(['data' => $status]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to check deadlines',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Consent Management ───────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/compliance/gdpr/v2/consent",
     *     operationId="gDPREnhancedGetConsentStatus",
     *     tags={"GDPR Enhanced"},
     *     summary="Get consent status",
     *     description="Returns consent status for the authenticated user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getConsentStatus(Request $request): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);
            $userUuid = $request->user()?->uuid ?? 'demo-user';

            $data = $demoMode
                ? $this->consentService->getDemoStatus()
                : $this->consentService->getConsentStatus($userUuid);

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve consent status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/gdpr/v2/consent",
     *     operationId="gDPREnhancedRecordConsent",
     *     tags={"GDPR Enhanced"},
     *     summary="Record a consent decision",
     *     description="Records a consent decision for the authenticated user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function recordConsent(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'purpose' => ['required', 'string'],
                'granted' => ['required', 'boolean'],
                'version' => ['sometimes', 'string'],
            ]);

            $userUuid = $request->user()?->uuid ?? 'demo-user';

            $record = $this->consentService->recordConsent(
                $userUuid,
                $validated['purpose'],
                $validated['granted'],
                [
                    'version'    => $validated['version'] ?? '1.0',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            );

            return response()->json([
                'message' => 'Consent recorded successfully',
                'data'    => $record,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to record consent',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Data Retention ───────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/compliance/gdpr/v2/retention",
     *     operationId="gDPREnhancedGetRetentionSummary",
     *     tags={"GDPR Enhanced"},
     *     summary="Get retention policy summary",
     *     description="Returns data retention policy summary",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getRetentionSummary(): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $data = $demoMode
                ? $this->retentionService->getDemoSummary()
                : $this->retentionService->getSummary();

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve retention summary',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/gdpr/v2/retention/policies",
     *     operationId="gDPREnhancedCreateRetentionPolicy",
     *     tags={"GDPR Enhanced"},
     *     summary="Create a retention policy",
     *     description="Creates a new data retention policy",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function createRetentionPolicy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'data_type'      => ['required', 'string'],
                'model_class'    => ['nullable', 'string'],
                'retention_days' => ['required', 'integer', 'min:1'],
                'action'         => ['sometimes', 'in:delete,archive,anonymize'],
                'description'    => ['nullable', 'string'],
            ]);

            $policy = $this->retentionService->createPolicy($validated);

            return response()->json([
                'message' => 'Retention policy created successfully',
                'data'    => $policy,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to create retention policy',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
