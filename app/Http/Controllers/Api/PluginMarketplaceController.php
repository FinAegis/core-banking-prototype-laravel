<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Shared\Models\Plugin;
use App\Http\Controllers\Controller;
use App\Infrastructure\Plugins\PluginManager;
use App\Infrastructure\Plugins\PluginSecurityScanner;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Plugin Marketplace",
 *     description="Plugin management, security scanning, and marketplace endpoints"
 * )
 */
class PluginMarketplaceController extends Controller
{
    public function __construct(
        private readonly PluginManager $pluginManager,
        private readonly PluginSecurityScanner $securityScanner,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/plugins",
     *     operationId="pluginMarketplaceIndex",
     *     tags={"Plugin Marketplace"},
     *     summary="List all installed plugins",
     *     description="Returns a list of all installed plugins with status",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(): JsonResponse
    {
        $plugins = $this->pluginManager->list();

        return response()->json([
            'data' => $plugins->map(fn (Plugin $p) => [
                'id'           => $p->id,
                'vendor'       => $p->vendor,
                'name'         => $p->name,
                'full_name'    => $p->getFullName(),
                'version'      => $p->version,
                'display_name' => $p->display_name,
                'description'  => $p->description,
                'status'       => $p->status,
                'is_system'    => $p->is_system,
                'permissions'  => $p->permissions,
                'installed_at' => $p->installed_at?->toIso8601String(),
            ]),
            'meta' => [
                'total'  => $plugins->count(),
                'active' => $plugins->where('status', 'active')->count(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/plugins/{id}",
     *     operationId="pluginMarketplaceShow",
     *     tags={"Plugin Marketplace"},
     *     summary="Show a specific plugin",
     *     description="Returns detailed information about a specific plugin",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $plugin = Plugin::findOrFail($id);

        return response()->json([
            'data' => [
                'id'              => $plugin->id,
                'vendor'          => $plugin->vendor,
                'name'            => $plugin->name,
                'full_name'       => $plugin->getFullName(),
                'version'         => $plugin->version,
                'display_name'    => $plugin->display_name,
                'description'     => $plugin->description,
                'author'          => $plugin->author,
                'license'         => $plugin->license,
                'homepage'        => $plugin->homepage,
                'status'          => $plugin->status,
                'is_system'       => $plugin->is_system,
                'permissions'     => $plugin->permissions,
                'dependencies'    => $plugin->dependencies,
                'metadata'        => $plugin->metadata,
                'installed_at'    => $plugin->installed_at?->toIso8601String(),
                'activated_at'    => $plugin->activated_at?->toIso8601String(),
                'last_updated_at' => $plugin->last_updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/plugins/{id}/enable",
     *     operationId="pluginMarketplaceEnable",
     *     tags={"Plugin Marketplace"},
     *     summary="Enable a plugin",
     *     description="Enables a disabled plugin",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function enable(string $id): JsonResponse
    {
        $plugin = Plugin::findOrFail($id);
        $result = $this->pluginManager->enable($plugin->vendor, $plugin->name);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * @OA\Post(
     *     path="/api/plugins/{id}/disable",
     *     operationId="pluginMarketplaceDisable",
     *     tags={"Plugin Marketplace"},
     *     summary="Disable a plugin",
     *     description="Disables an active plugin",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function disable(string $id): JsonResponse
    {
        $plugin = Plugin::findOrFail($id);
        $result = $this->pluginManager->disable($plugin->vendor, $plugin->name);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * @OA\Delete(
     *     path="/api/plugins/{id}",
     *     operationId="pluginMarketplaceDestroy",
     *     tags={"Plugin Marketplace"},
     *     summary="Remove a plugin",
     *     description="Removes a plugin from the system",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $plugin = Plugin::findOrFail($id);
        $result = $this->pluginManager->remove($plugin->vendor, $plugin->name);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * @OA\Post(
     *     path="/api/plugins/{id}/scan",
     *     operationId="pluginMarketplaceScan",
     *     tags={"Plugin Marketplace"},
     *     summary="Scan a plugin for security issues",
     *     description="Runs security scanner on a specific plugin",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function scan(string $id): JsonResponse
    {
        $plugin = Plugin::findOrFail($id);
        $result = $this->securityScanner->scan($plugin->path);

        return response()->json([
            'plugin'  => $plugin->getFullName(),
            'safe'    => $result['safe'],
            'issues'  => $result['issues'],
            'summary' => $this->securityScanner->summarize($result['issues']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/plugins/discover",
     *     operationId="pluginMarketplaceDiscover",
     *     tags={"Plugin Marketplace"},
     *     summary="Discover new plugins",
     *     description="Discovers new plugins from the filesystem",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function discover(): JsonResponse
    {
        $result = $this->pluginManager->discover();

        return response()->json([
            'discovered' => $result['discovered'],
            'new'        => $result['new'],
        ]);
    }
}
