<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Infrastructure\Domain\DataObjects\DomainInfo;
use App\Infrastructure\Domain\DomainManager;
use App\Infrastructure\Domain\Enums\DomainStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Module Management API Controller.
 *
 * Provides REST endpoints for managing FinAegis domain modules:
 * listing, inspecting, enabling/disabling, and health verification.
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly DomainManager $domainManager,
    ) {
    }

    /**
     * List all modules with optional status filter.
     */
    #[OA\Get(
        path: '/api/v2/modules',
        operationId: 'moduleIndex',
        summary: 'List all modules',
        description: 'Returns a list of all available domain modules with optional status and type filters.',
        tags: ['Module Management'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Filter by module status', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'type', in: 'query', required: false, description: 'Filter by module type', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'installed', type: 'integer'),
        new OA\Property(property: 'disabled', type: 'integer'),
        new OA\Property(property: 'statuses', type: 'array', items: new OA\Items(type: 'string')),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Invalid filter value'
    )]
    public function index(Request $request): JsonResponse
    {
        $domains = $this->domainManager->getAvailableDomains();

        // Apply status filter if provided
        $statusFilter = $request->query('status');
        if (is_string($statusFilter) && $statusFilter !== '') {
            $status = DomainStatus::tryFrom($statusFilter);
            if ($status === null) {
                return response()->json([
                    'error' => [
                        'code'    => 'INVALID_FILTER',
                        'message' => 'Invalid status filter. Allowed values: ' . implode(', ', DomainStatus::values()),
                    ],
                ], 422);
            }

            $domains = $domains->filter(fn (DomainInfo $info) => $info->status === $status)->values();
        }

        // Apply type filter if provided
        $typeFilter = $request->query('type');
        if (is_string($typeFilter) && $typeFilter !== '') {
            $domains = $domains->filter(fn (DomainInfo $info) => $info->type->value === $typeFilter)->values();
        }

        $installed = $this->domainManager->getInstalledDomains();
        $disabled = $this->domainManager->getDisabledDomains();

        return response()->json([
            'data' => $domains->map(fn (DomainInfo $info) => $info->toArray()),
            'meta' => [
                'total'     => $domains->count(),
                'installed' => count($installed),
                'disabled'  => count($disabled),
                'statuses'  => DomainStatus::values(),
            ],
        ]);
    }

    /**
     * Get detailed information about a single module.
     */
    #[OA\Get(
        path: '/api/v2/modules/{name}',
        operationId: 'moduleShow',
        summary: 'Get module details',
        description: 'Returns detailed information about a single module including manifest and verification status.',
        tags: ['Module Management'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'name', in: 'path', required: true, description: 'Module name or package identifier', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'module', type: 'object'),
        new OA\Property(property: 'manifest', type: 'object', nullable: true),
        new OA\Property(property: 'verification', type: 'object'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 404,
        description: 'Module not found'
    )]
    public function show(string $name): JsonResponse
    {
        $domains = $this->domainManager->getAvailableDomains();
        $normalizedName = $this->normalizeName($name);

        $domain = $domains->first(fn (DomainInfo $info) => $info->name === $normalizedName);

        if ($domain === null) {
            return response()->json([
                'error' => [
                    'code'    => 'MODULE_NOT_FOUND',
                    'message' => "Module '{$name}' not found",
                ],
            ], 404);
        }

        // Load manifest for detailed info
        $manifests = $this->domainManager->loadAllManifests();
        $manifest = $manifests[$normalizedName] ?? null;

        // Run verification for health status
        $verification = $this->domainManager->verify($name);

        return response()->json([
            'data' => [
                'module'       => $domain->toArray(),
                'manifest'     => $manifest?->toArray(),
                'verification' => $verification->toArray(),
            ],
        ]);
    }

    /**
     * Enable a disabled module.
     *
     * Requires admin privileges (enforced via route middleware).
     */
    #[OA\Post(
        path: '/api/v2/modules/{name}/enable',
        operationId: 'moduleEnable',
        summary: 'Enable a module',
        description: 'Enables a previously disabled domain module. Requires admin privileges and ADMIN_RUNTIME_MANAGEMENT_ENABLED.',
        tags: ['Module Management'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'name', in: 'path', required: true, description: 'Module name', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Module enabled successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object'),
        new OA\Property(property: 'message', type: 'string'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Enable failed'
    )]
    #[OA\Response(
        response: 403,
        description: 'Not an administrator'
    )]
    #[OA\Response(
        response: 404,
        description: 'Not available: runtime management is turned off (ADMIN_RUNTIME_MANAGEMENT_ENABLED)'
    )]
    public function enable(string $name): JsonResponse
    {
        $result = $this->domainManager->enable($name);

        if (! $result->success) {
            return response()->json([
                'error' => [
                    'code'    => 'ENABLE_FAILED',
                    'message' => $result->getSummary(),
                    'errors'  => $result->errors,
                ],
            ], 422);
        }

        return response()->json([
            'data'    => $result->toArray(),
            'message' => "Module '{$name}' enabled successfully",
        ]);
    }

    /**
     * Disable an active module.
     *
     * Requires admin privileges (enforced via route middleware).
     * Core modules cannot be disabled.
     */
    #[OA\Post(
        path: '/api/v2/modules/{name}/disable',
        operationId: 'moduleDisable',
        summary: 'Disable a module',
        description: 'Disables an active domain module. Core modules cannot be disabled. Requires admin privileges and ADMIN_RUNTIME_MANAGEMENT_ENABLED.',
        tags: ['Module Management'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'name', in: 'path', required: true, description: 'Module name', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Module disabled successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object'),
        new OA\Property(property: 'message', type: 'string'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Disable failed'
    )]
    #[OA\Response(
        response: 403,
        description: 'Not an administrator'
    )]
    #[OA\Response(
        response: 404,
        description: 'Not available: runtime management is turned off (ADMIN_RUNTIME_MANAGEMENT_ENABLED)'
    )]
    public function disable(string $name): JsonResponse
    {
        $result = $this->domainManager->disable($name);

        if (! $result->success) {
            return response()->json([
                'error' => [
                    'code'    => 'DISABLE_FAILED',
                    'message' => $result->getSummary(),
                    'errors'  => $result->errors,
                ],
            ], 422);
        }

        return response()->json([
            'data'    => $result->toArray(),
            'message' => "Module '{$name}' disabled successfully",
        ]);
    }

    /**
     * Get overall module health summary across all domains.
     */
    #[OA\Get(
        path: '/api/v2/modules/health',
        operationId: 'moduleHealth',
        summary: 'Get module health summary',
        description: 'Returns an overall health summary across all installed domain modules.',
        tags: ['Module Management'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'healthy', type: 'boolean'),
        new OA\Property(property: 'modules', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'healthy', type: 'boolean'),
        new OA\Property(property: 'passed', type: 'integer'),
        new OA\Property(property: 'failed', type: 'integer'),
        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string')),
        ])),
        ]),
        new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'total_installed', type: 'integer'),
        new OA\Property(property: 'healthy', type: 'integer'),
        new OA\Property(property: 'unhealthy', type: 'integer'),
        new OA\Property(property: 'checked_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function health(): JsonResponse
    {
        $domains = $this->domainManager->getAvailableDomains();
        $installed = $domains->filter(fn (DomainInfo $info) => $info->status === DomainStatus::INSTALLED);

        $results = [];
        $healthyCount = 0;
        $unhealthyCount = 0;

        foreach ($installed as $domain) {
            $verification = $this->domainManager->verify($domain->name);
            $results[] = [
                'name'    => $domain->name,
                'healthy' => $verification->valid,
                'passed'  => $verification->getPassedCount(),
                'failed'  => $verification->getFailedCount(),
                'errors'  => $verification->errors,
            ];

            if ($verification->valid) {
                $healthyCount++;
            } else {
                $unhealthyCount++;
            }
        }

        $overallHealthy = $unhealthyCount === 0;

        return response()->json([
            'data' => [
                'healthy' => $overallHealthy,
                'modules' => $results,
            ],
            'meta' => [
                'total_installed' => $installed->count(),
                'healthy'         => $healthyCount,
                'unhealthy'       => $unhealthyCount,
                'checked_at'      => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Verify health of a specific module.
     *
     * Requires admin privileges (enforced via route middleware).
     */
    #[OA\Post(
        path: '/api/v2/modules/{name}/verify',
        operationId: 'moduleVerify',
        summary: 'Verify module health',
        description: 'Runs health verification checks on a specific module. Requires admin privileges and ADMIN_RUNTIME_MANAGEMENT_ENABLED.',
        tags: ['Module Management'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'name', in: 'path', required: true, description: 'Module name', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Module is healthy',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object'),
        new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'summary', type: 'string'),
        new OA\Property(property: 'checked_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Not an administrator'
    )]
    #[OA\Response(
        response: 404,
        description: 'Module not found, or runtime management is turned off (ADMIN_RUNTIME_MANAGEMENT_ENABLED)'
    )]
    #[OA\Response(
        response: 503,
        description: 'Module is unhealthy'
    )]
    public function verify(string $name): JsonResponse
    {
        $verification = $this->domainManager->verify($name);

        if (empty($verification->checks) && ! empty($verification->errors)) {
            return response()->json([
                'error' => [
                    'code'    => 'MODULE_NOT_FOUND',
                    'message' => "Module '{$name}' not found",
                    'errors'  => $verification->errors,
                ],
            ], 404);
        }

        $statusCode = $verification->valid ? 200 : 503;

        return response()->json([
            'data' => $verification->toArray(),
            'meta' => [
                'summary'    => $verification->getSummary(),
                'checked_at' => now()->toIso8601String(),
            ],
        ], $statusCode);
    }

    /**
     * Normalize a module name to the full package format.
     */
    private function normalizeName(string $name): string
    {
        if (! str_contains($name, '/')) {
            return "finaegis/{$name}";
        }

        return $name;
    }
}
