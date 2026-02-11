<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Services\PartnerMarketplaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerMarketplaceController extends Controller
{
    public function __construct(
        private readonly PartnerMarketplaceService $marketplaceService,
    ) {
    }

    /**
     * List available integrations.
     *
     * GET /api/partner/v1/marketplace
     *
     * @OA\Get(
     *     path="/api/partner/v1/marketplace",
     *     operationId="partnerMarketplaceList",
     *     summary="List available integrations",
     *     description="Returns a catalog of all available third-party integrations that partners can enable, organized by category (KYC, payments, analytics, etc.).",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Available integrations",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="category", type="string", example="kyc"),
     *                 @OA\Property(property="providers", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="name", type="string", example="sumsub"),
     *                     @OA\Property(property="label", type="string", example="SumSub"),
     *                     @OA\Property(property="description", type="string")
     *                 ))
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->marketplaceService->listAvailableIntegrations(),
        ]);
    }

    /**
     * Get partner's active integrations.
     *
     * GET /api/partner/v1/marketplace/integrations
     *
     * @OA\Get(
     *     path="/api/partner/v1/marketplace/integrations",
     *     operationId="partnerMarketplaceIntegrations",
     *     summary="Get partner's active integrations",
     *     description="Returns a list of integrations currently enabled for the authenticated partner, including their configuration and status.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Active integrations",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="category", type="string", example="kyc"),
     *                 @OA\Property(property="provider", type="string", example="sumsub"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="enabled_at", type="string", format="date-time")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     )
     * )
     */
    public function integrations(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request);
        $integrations = $this->marketplaceService->getPartnerIntegrations($partner);

        return response()->json([
            'success' => true,
            'data'    => $integrations,
        ]);
    }

    /**
     * Enable an integration.
     *
     * POST /api/partner/v1/marketplace/integrations
     *
     * @OA\Post(
     *     path="/api/partner/v1/marketplace/integrations",
     *     operationId="partnerMarketplaceEnable",
     *     summary="Enable an integration",
     *     description="Enables a third-party integration for the partner. Requires specifying the category and provider, with optional configuration parameters.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"category", "provider"},
     *             @OA\Property(property="category", type="string", example="kyc", description="Integration category"),
     *             @OA\Property(property="provider", type="string", example="sumsub", description="Integration provider"),
     *             @OA\Property(property="config", type="object", description="Optional provider-specific configuration",
     *                 example={"api_key": "key_123", "webhook_url": "https://example.com/webhook"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Integration enabled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="integration_id", type="integer", example=1),
     *                 @OA\Property(property="category", type="string", example="kyc"),
     *                 @OA\Property(property="provider", type="string", example="sumsub")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Integration cannot be enabled (invalid provider or tier restriction)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=false),
     *                 @OA\Property(property="error", type="string", example="Provider not available for your tier")
     *             )
     *         )
     *     )
     * )
     */
    public function enable(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request);

        $validated = $request->validate([
            'category' => 'required|string',
            'provider' => 'required|string',
            'config'   => 'sometimes|array',
        ]);

        $result = $this->marketplaceService->enableIntegration(
            $partner,
            $validated['category'],
            $validated['provider'],
            $validated['config'] ?? [],
        );

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
        ], $result['success'] ? 201 : 422);
    }

    /**
     * Disable an integration.
     *
     * DELETE /api/partner/v1/marketplace/integrations/{id}
     *
     * @OA\Delete(
     *     path="/api/partner/v1/marketplace/integrations/{id}",
     *     operationId="partnerMarketplaceDisable",
     *     summary="Disable an integration",
     *     description="Disables a previously enabled integration by its ID. The integration's configuration is preserved for potential re-enablement.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Integration ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Integration disabled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Integration disabled successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Integration not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Integration not found")
     *         )
     *     )
     * )
     */
    public function disable(Request $request, int $id): JsonResponse
    {
        $partner = $this->getPartner($request);
        $result = $this->marketplaceService->disableIntegration($partner, $id);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 404);
    }

    /**
     * Test an integration connection.
     *
     * POST /api/partner/v1/marketplace/integrations/{id}/test
     *
     * @OA\Post(
     *     path="/api/partner/v1/marketplace/integrations/{id}/test",
     *     operationId="partnerMarketplaceTestConnection",
     *     summary="Test an integration connection",
     *     description="Performs a connectivity test against the specified integration to verify that API keys and configuration are valid.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Integration ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connection test result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="latency_ms", type="integer", example=120),
     *                 @OA\Property(property="message", type="string", example="Connection successful")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Integration not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=false),
     *                 @OA\Property(property="error", type="string", example="Integration not found")
     *             )
     *         )
     *     )
     * )
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $partner = $this->getPartner($request);
        $result = $this->marketplaceService->testConnection($partner, $id);

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
        ], $result['success'] ? 200 : 404);
    }

    /**
     * Get integration health overview.
     *
     * GET /api/partner/v1/marketplace/health
     *
     * @OA\Get(
     *     path="/api/partner/v1/marketplace/health",
     *     operationId="partnerMarketplaceHealth",
     *     summary="Get integration health overview",
     *     description="Returns a health status overview of all enabled integrations, including uptime, error rates, and last successful connection timestamps.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Integration health overview",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_integrations", type="integer", example=3),
     *                 @OA\Property(property="healthy", type="integer", example=2),
     *                 @OA\Property(property="degraded", type="integer", example=1),
     *                 @OA\Property(property="down", type="integer", example=0),
     *                 @OA\Property(property="integrations", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="provider", type="string", example="sumsub"),
     *                     @OA\Property(property="status", type="string", enum={"healthy", "degraded", "down"}, example="healthy"),
     *                     @OA\Property(property="last_check", type="string", format="date-time")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     )
     * )
     */
    public function health(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request);
        $health = $this->marketplaceService->getIntegrationHealth($partner);

        return response()->json([
            'success' => true,
            'data'    => $health,
        ]);
    }

    private function getPartner(Request $request): FinancialInstitutionPartner
    {
        return $request->attributes->get('partner');
    }
}
