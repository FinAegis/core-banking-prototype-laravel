<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Controller for AI agent discovery and AGENTS.md file management.
 *
 * Provides endpoints for AI tools to discover available AGENTS.md files
 * throughout the codebase, enabling better AI agent integration.
 */
class AgentsDiscoveryController extends Controller
{
    /**
     * List all available AGENTS.md files in the project.
     *
     * @OA\Get(
     *     path="/api/agents/discovery",
     *     operationId="discoverAgentsDocumentation",
     *     tags={"AI Agents"},
     *     summary="Discover all AGENTS.md files",
     *     description="Returns a list of all AGENTS.md files in the project with their locations and metadata",
     *     @OA\Response(
     *         response=200,
     *         description="Successful discovery",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="count", type="integer", example=15),
     *             @OA\Property(
     *                 property="agents",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="path", type="string", example="app/Domain/Exchange/AGENTS.md"),
     *                     @OA\Property(property="domain", type="string", example="Exchange"),
     *                     @OA\Property(property="type", type="string", example="domain"),
     *                     @OA\Property(property="size", type="integer", example=4567),
     *                     @OA\Property(property="last_modified", type="string", format="date-time"),
     *                     @OA\Property(property="url", type="string", example="/api/agents/content/app/Domain/Exchange")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function discover(): JsonResponse
    {
        $agentsFiles = [];
        $basePath = base_path();

        // Use Symfony Finder to locate all AGENTS.md files
        $finder = new Finder();
        $finder->files()
            ->in($basePath)
            ->name('AGENTS.md')
            ->exclude('vendor')
            ->exclude('node_modules')
            ->exclude('storage')
            ->exclude('.git');

        foreach ($finder as $file) {
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath); // Normalize path separators

            // Determine the type and domain from the path
            $pathInfo = $this->parseAgentPath($relativePath);

            $agentsFiles[] = [
                'path'          => $relativePath,
                'domain'        => $pathInfo['domain'],
                'type'          => $pathInfo['type'],
                'size'          => $file->getSize(),
                'last_modified' => date('c', $file->getMTime()),
                'url'           => route('api.agents.content', [
                    'path' => base64_encode($relativePath),
                ]),
            ];
        }

        // Sort by type, then by domain name
        usort($agentsFiles, function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcmp($a['domain'], $b['domain']);
            }

            // Prioritize root, then domain, then other types
            $priority = ['root' => 0, 'domain' => 1, 'test' => 2, 'other' => 3];

            return ($priority[$a['type']] ?? 3) <=> ($priority[$b['type']] ?? 3);
        });

        return response()->json([
            'success' => true,
            'count'   => count($agentsFiles),
            'agents'  => $agentsFiles,
            'meta'    => [
                'generated_at' => now()->toIso8601String(),
                'base_url'     => url('/'),
                'api_version'  => 'v1',
            ],
        ]);
    }

    /**
     * Get the content of a specific AGENTS.md file.
     *
     * @OA\Get(
     *     path="/api/agents/content/{path}",
     *     operationId="getAgentContent",
     *     tags={"AI Agents"},
     *     summary="Get AGENTS.md content",
     *     description="Returns the content of a specific AGENTS.md file",
     *     @OA\Parameter(
     *         name="path",
     *         in="path",
     *         required=true,
     *         description="Base64 encoded path to the AGENTS.md file",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful retrieval",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="metadata", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="File not found"
     *     )
     * )
     */
    public function getContent(string $path): JsonResponse
    {
        $decodedPath = base64_decode($path);
        $fullPath = base_path($decodedPath);

        // Security check: ensure the path is within the project and is an AGENTS.md file
        if (! str_ends_with($decodedPath, 'AGENTS.md') || ! File::exists($fullPath)) {
            return response()->json([
                'success' => false,
                'error'   => 'File not found',
            ], 404);
        }

        // Additional security: prevent directory traversal
        $realPath = realpath($fullPath);
        $basePath = realpath(base_path());
        if ($realPath === false || $basePath === false || ! str_starts_with($realPath, $basePath)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid path',
            ], 403);
        }

        $content = File::get($fullPath);
        $pathInfo = $this->parseAgentPath($decodedPath);

        return response()->json([
            'success'  => true,
            'path'     => $decodedPath,
            'content'  => $content,
            'metadata' => [
                'domain'        => $pathInfo['domain'],
                'type'          => $pathInfo['type'],
                'size'          => strlen($content),
                'lines'         => substr_count($content, "\n") + 1,
                'last_modified' => date('c', (int) filemtime($fullPath)),
            ],
        ]);
    }

    /**
     * Get a summary of all domains with AGENTS.md files.
     *
     * @OA\Get(
     *     path="/api/agents/summary",
     *     operationId="getAgentsSummary",
     *     tags={"AI Agents"},
     *     summary="Get AGENTS.md summary",
     *     description="Returns a summary of all domains with AGENTS.md coverage",
     *     @OA\Response(
     *         response=200,
     *         description="Successful summary",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total_files", type="integer"),
     *             @OA\Property(property="domains", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="coverage", type="object")
     *         )
     *     )
     * )
     */
    public function summary(): JsonResponse
    {
        $domains = [];
        $coverage = [
            'root'    => false,
            'domains' => [],
            'tests'   => false,
            'config'  => false,
        ];

        $finder = new Finder();
        $finder->files()
            ->in(base_path())
            ->name('AGENTS.md')
            ->exclude('vendor')
            ->exclude('node_modules')
            ->exclude('storage')
            ->exclude('.git');

        foreach ($finder as $file) {
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($relativePath === 'AGENTS.md') {
                $coverage['root'] = true;
            } elseif (str_starts_with($relativePath, 'app/Domain/')) {
                preg_match('/app\/Domain\/([^\/]+)\/AGENTS\.md/', $relativePath, $matches);
                if (isset($matches[1])) {
                    $domains[] = $matches[1];
                    $coverage['domains'][] = $matches[1];
                }
            } elseif (str_starts_with($relativePath, 'tests/')) {
                $coverage['tests'] = true;
            } elseif (str_starts_with($relativePath, 'config/')) {
                $coverage['config'] = true;
            }
        }

        // Get all domain directories to calculate coverage percentage
        $allDomains = [];
        if (is_dir(base_path('app/Domain'))) {
            $domainDirs = File::directories(base_path('app/Domain'));
            foreach ($domainDirs as $dir) {
                $allDomains[] = basename($dir);
            }
        }

        $coveragePercentage = count($allDomains) > 0
            ? round((count($coverage['domains']) / count($allDomains)) * 100, 2)
            : 0;

        return response()->json([
            'success'             => true,
            'total_files'         => count($finder),
            'domains_with_agents' => array_values(array_unique($domains)),
            'all_domains'         => $allDomains,
            'coverage'            => $coverage,
            'coverage_percentage' => $coveragePercentage,
            'recommendations'     => $this->getRecommendations($coverage, $allDomains, $domains),
        ]);
    }

    /**
     * Parse the agent file path to extract domain and type information.
     */
    private function parseAgentPath(string $path): array
    {
        $domain = 'General';
        $type = 'other';

        if ($path === 'AGENTS.md') {
            return ['domain' => 'Root', 'type' => 'root'];
        }

        if (str_starts_with($path, 'app/Domain/')) {
            preg_match('/app\/Domain\/([^\/]+)/', $path, $matches);
            if (isset($matches[1])) {
                $domain = $matches[1];
                $type = 'domain';
            }
        } elseif (str_starts_with($path, 'tests/')) {
            $domain = 'Testing';
            $type = 'test';
        } elseif (str_starts_with($path, 'config/')) {
            $domain = 'Configuration';
            $type = 'config';
        } elseif (str_starts_with($path, 'database/')) {
            $domain = 'Database';
            $type = 'database';
        } elseif (str_starts_with($path, 'routes/')) {
            $domain = 'Routing';
            $type = 'routes';
        }

        return ['domain' => $domain, 'type' => $type];
    }

    /**
     * Get recommendations for improving AGENTS.md coverage.
     */
    private function getRecommendations(array $coverage, array $allDomains, array $coveredDomains): array
    {
        $recommendations = [];

        if (! $coverage['root']) {
            $recommendations[] = 'Create root AGENTS.md file for project overview';
        }

        $missingDomains = array_diff($allDomains, $coveredDomains);
        if (! empty($missingDomains)) {
            $recommendations[] = sprintf(
                'Add AGENTS.md files for domains: %s',
                implode(', ', array_slice($missingDomains, 0, 5))
            );
        }

        if (! $coverage['tests']) {
            $recommendations[] = 'Create AGENTS.md in tests/ directory for testing guidance';
        }

        if (! $coverage['config']) {
            $recommendations[] = 'Add AGENTS.md in config/ for configuration documentation';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Excellent AGENTS.md coverage! Consider adding more detailed examples.';
        }

        return $recommendations;
    }
}
