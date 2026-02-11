<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\RegTech;

use App\Domain\RegTech\Enums\Jurisdiction;
use App\Domain\RegTech\Services\MicaComplianceService;
use App\Domain\RegTech\Services\MifidReportingService;
use App\Domain\RegTech\Services\RegTechOrchestrationService;
use App\Domain\RegTech\Services\TravelRuleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegTech\ApplicableRegulationsRequest;
use App\Http\Requests\RegTech\SubmitReportRequest;
use App\Http\Requests\RegTech\TravelRuleCheckRequest;
use App\Http\Requests\RegTech\WhitepaperValidationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="RegTech",
 *     description="Regulatory Technology — compliance reporting, MiFID II, MiCA, Travel Rule"
 * )
 */
class RegTechController extends Controller
{
    public function __construct(
        private readonly RegTechOrchestrationService $orchestrationService,
        private readonly MifidReportingService $mifidService,
        private readonly MicaComplianceService $micaService,
        private readonly TravelRuleService $travelRuleService
    ) {
    }

    /**
     * Get RegTech compliance summary.
     *
     * Returns an overview of compliance status, optionally filtered by jurisdiction.
     *
     * @OA\Get(
     *     path="/api/regtech/compliance/summary",
     *     operationId="regtechComplianceSummary",
     *     summary="Get compliance summary",
     *     description="Returns an overview of compliance status across all registered adapters. Optionally filter by jurisdiction.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="jurisdiction",
     *         in="query",
     *         required=false,
     *         description="Filter by jurisdiction code",
     *         @OA\Schema(type="string", enum={"US", "EU", "UK", "SG"}, example="EU")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance summary retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="overall_status", type="string", example="compliant"),
     *                 @OA\Property(property="jurisdictions", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="jurisdiction", type="string", example="EU"),
     *                         @OA\Property(property="status", type="string", example="compliant"),
     *                         @OA\Property(property="last_checked", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function complianceSummary(Request $request): JsonResponse
    {
        $jurisdiction = $request->query('jurisdiction');
        $jurisdictionEnum = $jurisdiction ? Jurisdiction::tryFrom((string) $jurisdiction) : null;

        $summary = $this->orchestrationService->getComplianceSummary($jurisdictionEnum);

        return response()->json([
            'success' => true,
            'data'    => $summary,
        ]);
    }

    /**
     * Submit a regulatory report.
     *
     * Validates and submits a report to the appropriate jurisdiction adapter.
     *
     * @OA\Post(
     *     path="/api/regtech/reports",
     *     operationId="regtechSubmitReport",
     *     summary="Submit regulatory report",
     *     description="Validates and submits a regulatory report to the appropriate jurisdiction adapter. Returns a reference ID for tracking.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"report_type", "jurisdiction", "report_data"},
     *             @OA\Property(property="report_type", type="string", maxLength=50, example="transaction_report", description="Type of regulatory report"),
     *             @OA\Property(property="jurisdiction", type="string", enum={"US", "EU", "UK", "SG"}, example="EU", description="Target jurisdiction"),
     *             @OA\Property(property="report_data", type="object", description="Report payload data",
     *                 @OA\Property(property="transaction_id", type="string", example="txn_abc123"),
     *                 @OA\Property(property="amount", type="number", format="float", example=10000.00),
     *                 @OA\Property(property="currency", type="string", example="EUR")
     *             ),
     *             @OA\Property(property="metadata", type="object", nullable=true, description="Optional metadata",
     *                 @OA\Property(property="priority", type="string", example="high"),
     *                 @OA\Property(property="notes", type="string", example="Quarterly filing")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Report submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="reference", type="string", example="RPT-EU-2024-abc123"),
     *                 @OA\Property(property="details", type="object",
     *                     @OA\Property(property="status", type="string", example="submitted"),
     *                     @OA\Property(property="submitted_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="error", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed or invalid jurisdiction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="data", type="object", nullable=true),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="INVALID_JURISDICTION"),
     *                 @OA\Property(property="message", type="string", example="The provided jurisdiction is not supported.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function submitReport(SubmitReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Log::info('RegTech: Report submission requested', [
            'user_id'      => $request->user()?->id,
            'ip_address'   => $request->ip(),
            'report_type'  => $validated['report_type'],
            'jurisdiction' => $validated['jurisdiction'],
        ]);

        $jurisdiction = Jurisdiction::tryFrom($validated['jurisdiction']);

        if (! $jurisdiction) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => [
                    'code'    => 'INVALID_JURISDICTION',
                    'message' => 'The provided jurisdiction is not supported.',
                ],
            ], 422);
        }

        $result = $this->orchestrationService->submitReport(
            $validated['report_type'],
            $jurisdiction,
            $validated['report_data'],
            $validated['metadata'] ?? []
        );

        $statusCode = $result['success'] ? 201 : 422;

        return response()->json([
            'success' => $result['success'],
            'data'    => $result['success'] ? [
                'reference' => $result['reference'],
                'details'   => $result['details'],
            ] : null,
            'error' => ! $result['success'] ? [
                'code'    => 'VALIDATION_FAILED',
                'message' => $result['errors'][0] ?? 'Report submission failed.',
                'details' => ['errors' => $result['errors']],
            ] : null,
        ], $statusCode);
    }

    /**
     * Check status of a submitted report.
     *
     * Retrieves the current processing status of a previously submitted report
     * by its reference identifier.
     *
     * @OA\Get(
     *     path="/api/regtech/reports/{reference}/status",
     *     operationId="regtechReportStatus",
     *     summary="Check report status",
     *     description="Retrieves the current processing status of a previously submitted regulatory report by its reference identifier.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="reference",
     *         in="path",
     *         required=true,
     *         description="Report reference identifier (alphanumeric, hyphens, underscores; max 100 chars)",
     *         @OA\Schema(type="string", pattern="^[A-Za-z0-9\-_]{1,100}$", example="RPT-EU-2024-abc123")
     *     ),
     *     @OA\Parameter(
     *         name="jurisdiction",
     *         in="query",
     *         required=false,
     *         description="Jurisdiction for status lookup (defaults to US)",
     *         @OA\Schema(type="string", enum={"US", "EU", "UK", "SG"}, default="US", example="EU")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="reference", type="string", example="RPT-EU-2024-abc123"),
     *                 @OA\Property(property="status", type="string", example="accepted"),
     *                 @OA\Property(property="submitted_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid reference format",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="INVALID_REFERENCE"),
     *                 @OA\Property(property="message", type="string", example="Invalid report reference format.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function reportStatus(string $reference, Request $request): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9\-_]{1,100}$/', $reference)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_REFERENCE',
                    'message' => 'Invalid report reference format.',
                ],
            ], 422);
        }

        $jurisdiction = $request->query('jurisdiction', 'US');
        $jurisdictionEnum = Jurisdiction::tryFrom((string) $jurisdiction) ?? Jurisdiction::US;

        $result = $this->orchestrationService->checkReportStatus($reference, $jurisdictionEnum);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * Get registered adapters and their status.
     *
     * Returns all registered jurisdiction adapters with their capabilities and availability.
     *
     * @OA\Get(
     *     path="/api/regtech/adapters",
     *     operationId="regtechAdapters",
     *     summary="Get registered adapters",
     *     description="Returns all registered jurisdiction adapters with their report types, availability, and sandbox mode status.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Adapter list retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="adapters", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="key", type="string", example="eu_mifid"),
     *                         @OA\Property(property="name", type="string", example="EU MiFID II Adapter"),
     *                         @OA\Property(property="jurisdiction", type="string", example="EU"),
     *                         @OA\Property(property="report_types", type="array", @OA\Items(type="string"), example={"transaction_report", "best_execution"}),
     *                         @OA\Property(property="available", type="boolean", example=true),
     *                         @OA\Property(property="sandbox", type="boolean", example=true)
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=4),
     *                 @OA\Property(property="demo_mode", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function adapters(): JsonResponse
    {
        $adapters = $this->orchestrationService->getAdapters();

        $data = [];
        foreach ($adapters as $key => $adapter) {
            $data[] = [
                'key'          => $key,
                'name'         => $adapter->getName(),
                'jurisdiction' => $adapter->getJurisdiction()->value,
                'report_types' => $adapter->getSupportedReportTypes(),
                'available'    => $adapter->isAvailable(),
                'sandbox'      => $adapter->isSandboxMode(),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'adapters'  => $data,
                'total'     => count($data),
                'demo_mode' => $this->orchestrationService->isDemoMode(),
            ],
        ]);
    }

    /**
     * Get applicable regulations for a transaction.
     *
     * Determines which regulations apply based on transaction parameters
     * such as amount, currency, type, and whether it involves crypto assets.
     *
     * @OA\Get(
     *     path="/api/regtech/regulations/applicable",
     *     operationId="regtechApplicableRegulations",
     *     summary="Get applicable regulations",
     *     description="Determines which regulations apply to a given transaction based on amount, currency, type, and whether it involves crypto assets.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="amount",
     *         in="query",
     *         required=true,
     *         description="Transaction amount",
     *         @OA\Schema(type="number", format="float", minimum=0, maximum=999999999999.99, example=15000.00)
     *     ),
     *     @OA\Parameter(
     *         name="currency",
     *         in="query",
     *         required=true,
     *         description="ISO 4217 currency code (3 uppercase letters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="EUR")
     *     ),
     *     @OA\Parameter(
     *         name="transaction_type",
     *         in="query",
     *         required=true,
     *         description="Type of transaction",
     *         @OA\Schema(type="string", maxLength=50, example="wire_transfer")
     *     ),
     *     @OA\Parameter(
     *         name="is_crypto",
     *         in="query",
     *         required=false,
     *         description="Whether the transaction involves crypto assets",
     *         @OA\Schema(type="boolean", default=false, example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Applicable regulations retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="regulations", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="name", type="string", example="MiFID II"),
     *                         @OA\Property(property="jurisdiction", type="string", example="EU"),
     *                         @OA\Property(property="applicable", type="boolean", example=true),
     *                         @OA\Property(property="reason", type="string", example="Transaction exceeds reporting threshold")
     *                     )
     *                 ),
     *                 @OA\Property(property="query", type="object",
     *                     @OA\Property(property="amount", type="number", format="float", example=15000.00),
     *                     @OA\Property(property="currency", type="string", example="EUR"),
     *                     @OA\Property(property="transaction_type", type="string", example="wire_transfer"),
     *                     @OA\Property(property="is_crypto", type="boolean", example=false)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function applicableRegulations(ApplicableRegulationsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $amount = (float) $validated['amount'];
        $currency = $validated['currency'];
        $transactionType = $validated['transaction_type'];
        $isCrypto = (bool) ($validated['is_crypto'] ?? false);

        $regulations = $this->orchestrationService->getApplicableRegulations(
            $amount,
            $currency,
            $transactionType,
            ['is_crypto' => $isCrypto]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'regulations' => $regulations,
                'query'       => [
                    'amount'           => $amount,
                    'currency'         => $currency,
                    'transaction_type' => $transactionType,
                    'is_crypto'        => $isCrypto,
                ],
            ],
        ]);
    }

    /**
     * Get MiFID II reporting status and best execution analysis.
     *
     * Returns MiFID II enablement status, best execution analysis data,
     * and instrument reference data status.
     *
     * @OA\Get(
     *     path="/api/regtech/mifid/status",
     *     operationId="regtechMifidStatus",
     *     summary="MiFID II status",
     *     description="Returns MiFID II enablement status, best execution analysis data, and instrument reference data status.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="MiFID II status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="enabled", type="boolean", example=true),
     *                 @OA\Property(property="best_execution", type="object",
     *                     @OA\Property(property="venues_monitored", type="integer", example=5),
     *                     @OA\Property(property="last_analysis", type="string", format="date-time"),
     *                     @OA\Property(property="compliance_score", type="number", format="float", example=0.95)
     *                 ),
     *                 @OA\Property(property="instrument_reference_data", type="object",
     *                     @OA\Property(property="total_instruments", type="integer", example=1200),
     *                     @OA\Property(property="last_updated", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function mifidStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'enabled'                   => $this->mifidService->isEnabled(),
                'best_execution'            => $this->mifidService->getBestExecutionAnalysis(),
                'instrument_reference_data' => $this->mifidService->getInstrumentReferenceDataStatus(),
            ],
        ]);
    }

    /**
     * Get MiCA compliance status.
     *
     * Returns the current MiCA (Markets in Crypto-Assets) compliance status
     * including licensing, reserve, and whitepaper requirements.
     *
     * @OA\Get(
     *     path="/api/regtech/mica/status",
     *     operationId="regtechMicaStatus",
     *     summary="MiCA status",
     *     description="Returns the current MiCA (Markets in Crypto-Assets) compliance status including licensing, reserve, and whitepaper requirements.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="MiCA compliance status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="licensed", type="boolean", example=true),
     *                 @OA\Property(property="whitepaper_approved", type="boolean", example=true),
     *                 @OA\Property(property="reserve_compliant", type="boolean", example=true),
     *                 @OA\Property(property="last_audit", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function micaStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->micaService->getComplianceStatus(),
        ]);
    }

    /**
     * Validate a crypto-asset whitepaper.
     *
     * Checks a whitepaper against MiCA requirements for completeness
     * and regulatory compliance.
     *
     * @OA\Post(
     *     path="/api/regtech/mica/whitepaper/validate",
     *     operationId="regtechValidateWhitepaper",
     *     summary="Validate whitepaper",
     *     description="Validates a crypto-asset whitepaper against MiCA requirements for completeness, required sections, and regulatory compliance.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="issuer_legal_name", type="string", nullable=true, maxLength=255, example="FinAegis Digital Assets AG", description="Legal name of the issuer"),
     *             @OA\Property(property="publication_date", type="string", format="date", nullable=true, example="2024-06-15", description="Whitepaper publication date"),
     *             @OA\Property(property="page_count", type="integer", nullable=true, minimum=0, example=42, description="Number of pages in the whitepaper"),
     *             @OA\Property(property="sections", type="array", nullable=true, description="Whitepaper sections to validate",
     *                 @OA\Items(type="string", example="risk_factors")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Whitepaper validation result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="valid", type="boolean", example=true),
     *                 @OA\Property(property="score", type="number", format="float", example=0.85),
     *                 @OA\Property(property="missing_sections", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="warnings", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="recommendations", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function validateWhitepaper(WhitepaperValidationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->micaService->validateWhitepaper($validated);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * Get MiCA reserve management status.
     *
     * Returns the current status of reserves held to back crypto-asset tokens,
     * as required by MiCA regulation.
     *
     * @OA\Get(
     *     path="/api/regtech/mica/reserves",
     *     operationId="regtechMicaReserves",
     *     summary="MiCA reserves",
     *     description="Returns the current status of reserves held to back crypto-asset tokens, as required by MiCA regulation. Includes reserve ratios, composition, and audit status.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="MiCA reserve status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_reserves", type="number", format="float", example=50000000.00),
     *                 @OA\Property(property="reserve_ratio", type="number", format="float", example=1.02),
     *                 @OA\Property(property="compliant", type="boolean", example=true),
     *                 @OA\Property(property="last_audit", type="string", format="date-time"),
     *                 @OA\Property(property="composition", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="asset_type", type="string", example="government_bonds"),
     *                         @OA\Property(property="percentage", type="number", format="float", example=60.0)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function micaReserves(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->micaService->getReserveStatus(),
        ]);
    }

    /**
     * Check travel rule compliance for a transfer.
     *
     * Evaluates whether a transfer meets FATF Travel Rule requirements
     * based on amount, currency, and originator/beneficiary information.
     *
     * @OA\Post(
     *     path="/api/regtech/travel-rule/check",
     *     operationId="regtechTravelRuleCheck",
     *     summary="Travel rule check",
     *     description="Evaluates whether a transfer meets FATF Travel Rule requirements based on amount, currency, and originator/beneficiary information.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "currency"},
     *             @OA\Property(property="amount", type="number", format="float", minimum=0, example=5000.00, description="Transfer amount"),
     *             @OA\Property(property="currency", type="string", pattern="^[A-Z]{3}$", example="USD", description="ISO 4217 currency code"),
     *             @OA\Property(property="originator", type="object", nullable=true, description="Originator details",
     *                 @OA\Property(property="name", type="string", maxLength=255, example="John Doe"),
     *                 @OA\Property(property="address", type="string", maxLength=500, example="123 Main St, New York, NY"),
     *                 @OA\Property(property="account_number", type="string", maxLength=100, example="0x742d35Cc6634C0532925a3b844Bc454e4438f44e"),
     *                 @OA\Property(property="doc_id", type="string", maxLength=100, example="PASSPORT-12345")
     *             ),
     *             @OA\Property(property="beneficiary", type="object", nullable=true, description="Beneficiary details",
     *                 @OA\Property(property="name", type="string", maxLength=255, example="Jane Smith"),
     *                 @OA\Property(property="account_number", type="string", maxLength=100, example="0x8ba1f109551bD432803012645Ac136ddd64DBA72")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Travel rule evaluation result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="compliant", type="boolean", example=true),
     *                 @OA\Property(property="threshold_exceeded", type="boolean", example=true),
     *                 @OA\Property(property="applicable_jurisdictions", type="array", @OA\Items(type="string"), example={"US", "EU"}),
     *                 @OA\Property(property="required_fields", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="missing_fields", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function travelRuleCheck(TravelRuleCheckRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->travelRuleService->evaluate($validated);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * Get travel rule thresholds for all jurisdictions.
     *
     * Returns the monetary thresholds at which the FATF Travel Rule
     * applies for each supported jurisdiction.
     *
     * @OA\Get(
     *     path="/api/regtech/travel-rule/thresholds",
     *     operationId="regtechTravelRuleThresholds",
     *     summary="Travel rule thresholds",
     *     description="Returns the monetary thresholds at which the FATF Travel Rule applies for each supported jurisdiction, including currency and amount details.",
     *     tags={"RegTech"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Travel rule thresholds retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="thresholds", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="jurisdiction", type="string", example="US"),
     *                         @OA\Property(property="threshold_amount", type="number", format="float", example=3000.00),
     *                         @OA\Property(property="currency", type="string", example="USD"),
     *                         @OA\Property(property="applies_to_crypto", type="boolean", example=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function travelRuleThresholds(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->travelRuleService->getThresholds(),
        ]);
    }
}
