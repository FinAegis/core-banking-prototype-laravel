<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Services\Certification\AccessReviewService;
use App\Domain\Compliance\Services\Certification\DataClassificationService;
use App\Domain\Compliance\Services\Certification\DataResidencyService;
use App\Domain\Compliance\Services\Certification\EncryptionVerificationService;
use App\Domain\Compliance\Services\Certification\EvidenceCollectionService;
use App\Domain\Compliance\Services\Certification\GeoRoutingService;
use App\Domain\Compliance\Services\Certification\IncidentResponseService;
use App\Domain\Compliance\Services\Certification\KeyRotationService;
use App\Domain\Compliance\Services\Certification\NetworkSegmentationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @OA\Tag(
 *     name="Compliance Certification",
 *     description="SOC 2, PCI DSS, data residency, and incident management endpoints"
 * )
 */
class ComplianceCertificationController extends Controller
{
    public function __construct(
        private readonly EvidenceCollectionService $evidenceService,
        private readonly AccessReviewService $accessReviewService,
        private readonly IncidentResponseService $incidentResponseService,
        private readonly DataClassificationService $classificationService,
        private readonly EncryptionVerificationService $encryptionVerificationService,
        private readonly KeyRotationService $keyRotationService,
        private readonly NetworkSegmentationService $networkSegmentationService,
        private readonly DataResidencyService $dataResidencyService,
        private readonly GeoRoutingService $geoRoutingService,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/evidence",
     *     operationId="complianceCertificationGetEvidence",
     *     tags={"Compliance Certification"},
     *     summary="Get SOC 2 compliance evidence",
     *     description="Returns SOC 2 compliance evidence with optional period and type filters",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getEvidence(Request $request): JsonResponse
    {
        try {
            $evidence = $this->evidenceService->getEvidence(
                $request->query('period'),
                $request->query('type'),
            );

            return response()->json([
                'data' => $evidence,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve evidence',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/certification/evidence/collect",
     *     operationId="complianceCertificationCollectEvidence",
     *     tags={"Compliance Certification"},
     *     summary="Collect SOC 2 compliance evidence",
     *     description="Initiates SOC 2 compliance evidence collection for a given period",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function collectEvidence(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period' => ['required', 'string'],
                'type'   => ['sometimes', 'string'],
            ]);

            $result = $this->evidenceService->collectEvidence(
                $validated['period'],
                $validated['type'] ?? 'all',
            );

            return response()->json([
                'message' => 'Evidence collection initiated successfully',
                'data'    => $result,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to collect evidence',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/access-review",
     *     operationId="complianceCertificationGetAccessReview",
     *     tags={"Compliance Certification"},
     *     summary="Get access review report",
     *     description="Returns the access review report for SOC 2 compliance",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getAccessReview(): JsonResponse
    {
        try {
            $report = $this->accessReviewService->generateReviewReport();

            return response()->json([
                'data' => $report,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to generate access review report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/access-review/privileged-users",
     *     operationId="complianceCertificationGetPrivilegedUsers",
     *     tags={"Compliance Certification"},
     *     summary="Get privileged users list",
     *     description="Returns a list of privileged users for access review",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getPrivilegedUsers(): JsonResponse
    {
        try {
            $users = $this->accessReviewService->getPrivilegedUsers();

            return response()->json([
                'data' => $users->toArray(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve privileged users',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/incidents",
     *     operationId="complianceCertificationGetIncidents",
     *     tags={"Compliance Certification"},
     *     summary="Get incidents list",
     *     description="Returns incidents with optional status and severity filters",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getIncidents(Request $request): JsonResponse
    {
        try {
            $incidents = $this->incidentResponseService->getIncidents(
                $request->query('status'),
                $request->query('severity'),
            );

            return response()->json([
                'data' => $incidents,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve incidents',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/certification/incidents",
     *     operationId="complianceCertificationCreateIncident",
     *     tags={"Compliance Certification"},
     *     summary="Create a new incident",
     *     description="Creates a new security incident for incident response tracking",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function createIncident(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title'            => ['required', 'string'],
                'description'      => ['required', 'string'],
                'severity'         => ['required', 'in:critical,high,medium,low'],
                'affected_systems' => ['nullable', 'array'],
            ]);

            $incident = $this->incidentResponseService->createIncident($validated);

            return response()->json([
                'message' => 'Incident created successfully',
                'data'    => $incident,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to create incident',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/compliance/certification/incidents/{id}",
     *     operationId="complianceCertificationUpdateIncident",
     *     tags={"Compliance Certification"},
     *     summary="Update an existing incident",
     *     description="Updates an existing security incident with new information",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function updateIncident(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title'            => ['sometimes', 'string'],
                'description'      => ['sometimes', 'string'],
                'severity'         => ['sometimes', 'in:critical,high,medium,low'],
                'status'           => ['sometimes', 'string'],
                'affected_systems' => ['sometimes', 'array'],
            ]);

            $incident = $this->incidentResponseService->updateIncident($id, $validated);

            return response()->json([
                'message' => 'Incident updated successfully',
                'data'    => $incident,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to update incident',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/certification/incidents/{id}/resolve",
     *     operationId="complianceCertificationResolveIncident",
     *     tags={"Compliance Certification"},
     *     summary="Resolve an incident",
     *     description="Resolves a security incident with a resolution description",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function resolveIncident(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'resolution' => ['required', 'string'],
            ]);

            $incident = $this->incidentResponseService->resolveIncident($id, $validated['resolution']);

            return response()->json([
                'message' => 'Incident resolved successfully',
                'data'    => $incident,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to resolve incident',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/incidents/{id}/postmortem",
     *     operationId="complianceCertificationGetPostmortem",
     *     tags={"Compliance Certification"},
     *     summary="Get incident postmortem report",
     *     description="Returns the postmortem report for a resolved incident",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getPostmortem(string $id): JsonResponse
    {
        try {
            $postmortem = $this->incidentResponseService->generatePostmortem($id);

            return response()->json([
                'data' => $postmortem,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to generate postmortem',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── PCI DSS Endpoints ──────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/pci/classification",
     *     operationId="complianceCertificationGetDataClassification",
     *     tags={"Compliance Certification"},
     *     summary="Get data classification report",
     *     description="Returns the PCI DSS data classification report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getDataClassification(Request $request): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $report = $demoMode
                ? $this->classificationService->getDemoReport()
                : $this->classificationService->generateComplianceReport();

            return response()->json([
                'data' => $report,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve data classification report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/pci/encryption",
     *     operationId="complianceCertificationGetEncryptionVerification",
     *     tags={"Compliance Certification"},
     *     summary="Run encryption verification suite",
     *     description="Returns the PCI DSS encryption verification results",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getEncryptionVerification(): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $results = $demoMode
                ? $this->encryptionVerificationService->getDemoResults()
                : $this->encryptionVerificationService->runVerification();

            return response()->json([
                'data' => $results,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to run encryption verification',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/pci/key-rotation",
     *     operationId="complianceCertificationGetKeyRotationStatus",
     *     tags={"Compliance Certification"},
     *     summary="Get key rotation status report",
     *     description="Returns the PCI DSS key rotation status report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getKeyRotationStatus(): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $report = $demoMode
                ? $this->keyRotationService->getDemoReport()
                : $this->keyRotationService->generateRotationReport();

            return response()->json([
                'data' => $report,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve key rotation status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/certification/pci/key-rotation/rotate",
     *     operationId="complianceCertificationRotateKey",
     *     tags={"Compliance Certification"},
     *     summary="Rotate a specific key",
     *     description="Rotates a specific encryption key (demo-safe with dry_run option)",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function rotateKey(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'key_identifier' => ['required', 'string'],
                'dry_run'        => ['sometimes', 'boolean'],
            ]);

            $result = $this->keyRotationService->rotateKey(
                $validated['key_identifier'],
                $validated['dry_run'] ?? false,
            );

            $status = $result['success'] ? 200 : 404;

            return response()->json([
                'data' => $result,
            ], $status);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to rotate key',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/pci/network-segmentation",
     *     operationId="complianceCertificationGetNetworkSegmentation",
     *     tags={"Compliance Certification"},
     *     summary="Get network segmentation verification report",
     *     description="Returns the PCI DSS network segmentation verification report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getNetworkSegmentation(): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $report = $demoMode
                ? $this->networkSegmentationService->getDemoReport()
                : $this->networkSegmentationService->verifySegmentation();

            return response()->json([
                'data' => $report,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve network segmentation report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Data Residency Endpoints ─────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/data-residency/status",
     *     operationId="complianceCertificationGetResidencyStatus",
     *     tags={"Compliance Certification"},
     *     summary="Get data residency status",
     *     description="Returns data residency status for current tenant or specified region",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getResidencyStatus(Request $request): JsonResponse
    {
        try {
            $demoMode = config('compliance-certification.soc2.demo_mode', true);

            $status = $demoMode
                ? $this->dataResidencyService->getDemoStatus()
                : $this->dataResidencyService->getResidencyStatus();

            return response()->json([
                'data' => $status,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve data residency status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/data-residency/transfers",
     *     operationId="complianceCertificationGetTransferLogs",
     *     tags={"Compliance Certification"},
     *     summary="Get cross-region data transfer logs",
     *     description="Returns cross-region data transfer logs with optional region filters",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getTransferLogs(Request $request): JsonResponse
    {
        try {
            $logs = $this->dataResidencyService->getTransferLogs(
                $request->query('from_region'),
                $request->query('to_region'),
            );

            return response()->json([
                'data' => $logs,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve transfer logs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/compliance/certification/data-residency/transfers",
     *     operationId="complianceCertificationLogTransfer",
     *     tags={"Compliance Certification"},
     *     summary="Log a cross-region data transfer",
     *     description="Records a cross-region data transfer for compliance tracking",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function logTransfer(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'from_region' => ['required', 'string'],
                'to_region'   => ['required', 'string'],
                'data_type'   => ['required', 'string'],
                'reason'      => ['required', 'string'],
            ]);

            $log = $this->dataResidencyService->logTransfer(
                $validated['from_region'],
                $validated['to_region'],
                $validated['data_type'],
                $validated['reason'],
            );

            return response()->json([
                'message' => 'Transfer logged successfully',
                'data'    => $log,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to log transfer',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compliance/certification/data-residency/routing",
     *     operationId="complianceCertificationGetRoutingConfig",
     *     tags={"Compliance Certification"},
     *     summary="Get geo-routing configuration",
     *     description="Returns geo-routing configuration and available regions",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getRoutingConfig(): JsonResponse
    {
        try {
            $config = $this->geoRoutingService->getRoutingConfig();

            return response()->json([
                'data' => $config,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Failed to retrieve routing configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
