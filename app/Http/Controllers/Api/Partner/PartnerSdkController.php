<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Services\SdkGeneratorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerSdkController extends Controller
{
    public function __construct(
        private readonly SdkGeneratorService $sdkService,
    ) {
    }

    /**
     * Get available SDK languages.
     *
     * GET /api/partner/v1/sdk/languages
     *
     * @OA\Get(
     *     path="/api/partner/v1/sdk/languages",
     *     operationId="partnerSdkLanguages",
     *     summary="Get available SDK languages",
     *     description="Returns a list of programming languages for which SDKs can be generated.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Available SDK languages",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="language", type="string", example="typescript"),
     *                 @OA\Property(property="label", type="string", example="TypeScript"),
     *                 @OA\Property(property="version", type="string", example="1.0.0")
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
    public function languages(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->sdkService->getAvailableLanguages(),
        ]);
    }

    /**
     * Generate an SDK.
     *
     * POST /api/partner/v1/sdk/generate
     *
     * @OA\Post(
     *     path="/api/partner/v1/sdk/generate",
     *     operationId="partnerSdkGenerate",
     *     summary="Generate an SDK for a specific language",
     *     description="Triggers SDK generation for the specified programming language. The SDK is customized with the partner's API keys and branding.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"language"},
     *             @OA\Property(property="language", type="string", example="typescript", description="Programming language for SDK generation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SDK generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="language", type="string", example="typescript"),
     *                 @OA\Property(property="download_url", type="string", example="https://example.com/sdk/download/abc123"),
     *                 @OA\Property(property="version", type="string", example="1.0.0")
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
     *         description="SDK generation failed (unsupported language or tier restriction)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=false),
     *                 @OA\Property(property="error", type="string", example="Unsupported language")
     *             )
     *         )
     *     )
     * )
     */
    public function generate(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request);

        $validated = $request->validate([
            'language' => 'required|string',
        ]);

        $result = $this->sdkService->generate($partner, $validated['language']);

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Get SDK status for a language.
     *
     * GET /api/partner/v1/sdk/{language}
     *
     * @OA\Get(
     *     path="/api/partner/v1/sdk/{language}",
     *     operationId="partnerSdkStatus",
     *     summary="Get SDK status for a specific language",
     *     description="Returns the current status of the SDK for the specified language, including version, build status, and download URL if available.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="language",
     *         in="path",
     *         required=true,
     *         description="SDK language identifier",
     *         @OA\Schema(type="string", example="typescript")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SDK status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="language", type="string", example="typescript"),
     *                 @OA\Property(property="status", type="string", example="ready"),
     *                 @OA\Property(property="version", type="string", example="1.0.0"),
     *                 @OA\Property(property="download_url", type="string", nullable=true)
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
    public function status(Request $request, string $language): JsonResponse
    {
        $partner = $this->getPartner($request);
        $status = $this->sdkService->getSdkStatus($partner, $language);

        return response()->json([
            'success' => true,
            'data'    => $status,
        ]);
    }

    /**
     * Get the OpenAPI spec.
     *
     * GET /api/partner/v1/sdk/openapi-spec
     *
     * @OA\Get(
     *     path="/api/partner/v1/sdk/openapi-spec",
     *     operationId="partnerSdkOpenapiSpec",
     *     summary="Get the OpenAPI specification",
     *     description="Returns the full OpenAPI (Swagger) specification as JSON. This spec can be used with code generators or API documentation tools.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="OpenAPI specification",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Full OpenAPI 3.0 specification")
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
     *         description="OpenAPI spec not available",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="OpenAPI spec not available")
     *         )
     *     )
     * )
     */
    public function openapiSpec(Request $request): JsonResponse
    {
        $spec = $this->sdkService->getOpenApiSpec();

        if ($spec === null) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAPI spec not available',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => json_decode($spec, true),
        ]);
    }

    private function getPartner(Request $request): FinancialInstitutionPartner
    {
        return $request->attributes->get('partner');
    }
}
