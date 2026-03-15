<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Cache;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Public API',
    description: 'Public API endpoints for external developers'
)]
class PublicApiController extends Controller
{
        #[OA\Get(
            path: '/',
            operationId: 'getApiInfo',
            tags: ['Public API'],
            summary: 'Get API information',
            description: 'Returns information about the API including version, status, and available endpoints'
        )]
    #[OA\Response(
        response: 200,
        description: 'API information',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Zelta Public API'),
        new OA\Property(property: 'version', type: 'string', example: '2.0.0'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'status', type: 'string', example: 'operational'),
        new OA\Property(property: 'endpoints', type: 'object', properties: [
        new OA\Property(property: 'accounts', type: 'string', example: '/v2/accounts'),
        new OA\Property(property: 'assets', type: 'string', example: '/v2/assets'),
        new OA\Property(property: 'exchange_rates', type: 'string', example: '/v2/exchange-rates'),
        new OA\Property(property: 'baskets', type: 'string', example: '/v2/baskets'),
        new OA\Property(property: 'webhooks', type: 'string', example: '/v2/webhooks'),
        ]),
        new OA\Property(property: 'documentation', type: 'string', example: 'https://docs.zelta.app'),
        new OA\Property(property: 'support', type: 'string', example: 'api@zelta.app'),
        ])
    )]
    public function index(): JsonResponse
    {
        return response()->json(
            [
                'name'        => config('brand.name', 'Zelta') . ' Public API',
                'version'     => '2.0.0',
                'description' => 'Public API for ' . config('brand.name', 'Zelta') . ' — agentic payments platform',
                'status'      => 'operational',
                'endpoints'   => [
                    'accounts'       => '/v2/accounts',
                    'assets'         => '/v2/assets',
                    'exchange_rates' => '/v2/exchange-rates',
                    'baskets'        => '/v2/baskets',
                    'transactions'   => '/v2/transactions',
                    'transfers'      => '/v2/transfers',
                    'webhooks'       => '/v2/webhooks',
                    'gcu'            => '/v2/gcu',
                ],
                'features' => [
                    'multi_asset_support' => true,
                    'basket_assets'       => true,
                    'webhooks'            => true,
                    'real_time_rates'     => true,
                    'bank_integration'    => true,
                    'governance_voting'   => true,
                ],
                'rate_limits' => [
                    'requests_per_minute' => 60,
                    'requests_per_hour'   => 1000,
                    'burst_limit'         => 100,
                ],
                'documentation' => config('app.url') . '/api/documentation',
                'support'       => config('brand.support_email', 'info@finaegis.org'),
                'sandbox'       => config('app.url') . '/api/v2',
            ]
        );
    }

        #[OA\Get(
            path: '/status',
            operationId: 'getApiStatus',
            tags: ['Public API'],
            summary: 'Get API status',
            description: 'Returns the current operational status of the API and its components'
        )]
    #[OA\Response(
        response: 200,
        description: 'API status',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'status', type: 'string', example: 'operational'),
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
        new OA\Property(property: 'components', type: 'object', properties: [
        new OA\Property(property: 'api', type: 'string', example: 'operational'),
        new OA\Property(property: 'database', type: 'string', example: 'operational'),
        new OA\Property(property: 'redis', type: 'string', example: 'operational'),
        new OA\Property(property: 'bank_connectors', type: 'object', properties: [
        new OA\Property(property: 'paysera', type: 'string', example: 'operational'),
        new OA\Property(property: 'deutsche_bank', type: 'string', example: 'operational'),
        new OA\Property(property: 'santander', type: 'string', example: 'degraded'),
        ]),
        ]),
        new OA\Property(property: 'metrics', type: 'object', properties: [
        new OA\Property(property: 'response_time_ms', type: 'integer', example: 45),
        new OA\Property(property: 'uptime_percentage', type: 'number', example: 99.95),
        ]),
        ])
    )]
    public function status(): JsonResponse
    {
        $startTime = microtime(true);

        // Check component status
        $components = [
            'api'             => 'operational',
            'database'        => $this->checkDatabaseStatus(),
            'redis'           => $this->checkRedisStatus(),
            'bank_connectors' => $this->checkBankConnectorsStatus(),
        ];

        $overallStatus = $this->determineOverallStatus($components);

        return response()->json(
            [
                'status'     => $overallStatus,
                'timestamp'  => now()->toIso8601String(),
                'components' => $components,
                'metrics'    => [
                    'response_time_ms'  => round((microtime(true) - $startTime) * 1000),
                    'uptime_percentage' => 99.95, // In production, calculate from monitoring data
                ],
            ]
        );
    }

    private function checkDatabaseStatus(): string
    {
        try {
            DB::select('SELECT 1');

            return 'operational';
        } catch (Exception $e) {
            return 'down';
        }
    }

    private function checkRedisStatus(): string
    {
        try {
            Cache::store('redis')->get('health_check');

            return 'operational';
        } catch (Exception $e) {
            return 'down';
        }
    }

    private function checkBankConnectorsStatus(): array
    {
        $healthMonitor = app(\App\Domain\Custodian\Services\CustodianHealthMonitor::class);
        $allHealth = $healthMonitor->getAllCustodiansHealth();

        $status = [];
        foreach ($allHealth as $custodian => $health) {
            $status[$custodian] = match ($health['status']) {
                'healthy'   => 'operational',
                'degraded'  => 'degraded',
                'unhealthy' => 'down',
                default     => 'unknown',
            };
        }

        return $status;
    }

    private function determineOverallStatus(array $components): string
    {
        $flatComponents = [];
        array_walk_recursive(
            $components,
            function ($value) use (&$flatComponents) {
                $flatComponents[] = $value;
            }
        );

        if (in_array('down', $flatComponents)) {
            return 'major_outage';
        } elseif (in_array('degraded', $flatComponents)) {
            return 'partial_outage';
        }

        return 'operational';
    }
}
