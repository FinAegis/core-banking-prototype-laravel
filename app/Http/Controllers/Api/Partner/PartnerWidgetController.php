<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Services\EmbeddableWidgetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerWidgetController extends Controller
{
    public function __construct(
        private readonly EmbeddableWidgetService $widgetService,
    ) {
    }

    /**
     * Get available widget types.
     *
     * GET /api/partner/v1/widgets
     *
     * @OA\Get(
     *     path="/api/partner/v1/widgets",
     *     operationId="partnerListWidgets",
     *     summary="Get available widget types",
     *     description="Returns a list of all embeddable widget types available for the partner's tier, including their descriptions and configuration options.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Available widget types",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="type", type="string", example="exchange"),
     *                 @OA\Property(property="label", type="string", example="Exchange Widget"),
     *                 @OA\Property(property="description", type="string", example="Embeddable exchange trading widget")
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
            'data'    => $this->widgetService->getAvailableWidgets(),
        ]);
    }

    /**
     * Generate embed code for a widget.
     *
     * POST /api/partner/v1/widgets/{type}/embed
     *
     * @OA\Post(
     *     path="/api/partner/v1/widgets/{type}/embed",
     *     operationId="partnerGenerateWidgetEmbed",
     *     summary="Generate embed code for a widget",
     *     description="Generates HTML/JavaScript embed code for the specified widget type, customized with the partner's branding and optional layout parameters.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         description="Widget type identifier",
     *         @OA\Schema(type="string", example="exchange")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="container_id", type="string", example="widget-container", description="HTML container element ID (max 100 chars)"),
     *             @OA\Property(property="width", type="string", example="400px", description="Widget width (max 20 chars)"),
     *             @OA\Property(property="height", type="string", example="600px", description="Widget height (max 20 chars)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Embed code generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="embed_code", type="string", description="HTML/JS embed snippet"),
     *                 @OA\Property(property="type", type="string", example="exchange")
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
     *         description="Invalid widget type or tier restriction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=false),
     *                 @OA\Property(property="error", type="string", example="Widget type not available for your tier")
     *             )
     *         )
     *     )
     * )
     */
    public function embed(Request $request, string $type): JsonResponse
    {
        $partner = $this->getPartner($request);

        $options = $request->validate([
            'container_id' => 'sometimes|string|max:100',
            'width'        => 'sometimes|string|max:20',
            'height'       => 'sometimes|string|max:20',
        ]);

        $result = $this->widgetService->generateEmbedCode($partner, $type, $options);

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Preview a widget with branding.
     *
     * GET /api/partner/v1/widgets/{type}/preview
     *
     * @OA\Get(
     *     path="/api/partner/v1/widgets/{type}/preview",
     *     operationId="partnerPreviewWidget",
     *     summary="Preview a widget with branding",
     *     description="Returns a preview of the widget with the partner's current branding applied. Useful for testing branding changes before deploying to production.",
     *     tags={"Partner BaaS"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         description="Widget type identifier",
     *         @OA\Schema(type="string", example="exchange")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Widget preview",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="preview_url", type="string", example="https://example.com/widget/preview/abc123"),
     *                 @OA\Property(property="type", type="string", example="exchange")
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
     *         description="Invalid widget type or tier restriction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=false),
     *                 @OA\Property(property="error", type="string", example="Widget type not available for your tier")
     *             )
     *         )
     *     )
     * )
     */
    public function preview(Request $request, string $type): JsonResponse
    {
        $partner = $this->getPartner($request);
        $result = $this->widgetService->previewWidget($partner, $type);

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
        ], $result['success'] ? 200 : 422);
    }

    private function getPartner(Request $request): FinancialInstitutionPartner
    {
        return $request->attributes->get('partner');
    }
}
