<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use OpenApi\Attributes as OA;

/**
 * Treasury API Documentation Schemas.
 */
#[OA\Tag(
    name: 'Treasury Portfolio',
    description: 'Treasury portfolio management operations'
)]
#[OA\Tag(
    name: 'Treasury Operations',
    description: 'Treasury operational endpoints'
)]
#[OA\Schema(
    schema: 'TreasuryPortfolio',
    type: 'object',
    description: 'A treasury portfolio containing asset allocations',
    properties: [
    new OA\Property(property: 'portfolio_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
    new OA\Property(property: 'treasury_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440001'),
    new OA\Property(property: 'name', type: 'string', example: 'Main Treasury Portfolio'),
    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'rebalancing', 'liquidating'], example: 'active'),
    new OA\Property(property: 'total_value', type: 'number', format: 'float', example: 1000000.00),
    new OA\Property(property: 'asset_count', type: 'integer', example: 5),
    new OA\Property(property: 'is_rebalancing', type: 'boolean', example: false),
    new OA\Property(property: 'last_rebalance_date', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'TreasuryPortfolioDetailed',
    type: 'object',
    description: 'Detailed treasury portfolio with summary and rebalancing status',
    allOf: [
    new OA\Schema(ref: '#/components/schemas/TreasuryPortfolio'),
    new OA\Schema(properties: [
    new OA\Property(property: 'strategy', type: 'object', properties: [
    new OA\Property(property: 'type', type: 'string', example: 'balanced'),
    new OA\Property(property: 'risk_tolerance', type: 'string', example: 'moderate'),
    new OA\Property(property: 'target_allocations', type: 'object'),
    ]),
    new OA\Property(property: 'asset_allocations', type: 'array', items: new OA\Items(ref: '#/components/schemas/AssetAllocation')),
    new OA\Property(property: 'latest_metrics', type: 'object'),
    new OA\Property(property: 'summary', type: 'object'),
    new OA\Property(property: 'needs_rebalancing', type: 'boolean', example: false),
    ]),
    ]
)]
#[OA\Schema(
    schema: 'CreateTreasuryPortfolioRequest',
    type: 'object',
    required: ['treasury_id', 'name', 'strategy'],
    properties: [
    new OA\Property(property: 'treasury_id', type: 'string', format: 'uuid', description: 'ID of the treasury account'),
    new OA\Property(property: 'name', type: 'string', example: 'Growth Portfolio', description: 'Portfolio name'),
    new OA\Property(property: 'strategy', type: 'object', required: ['type'], properties: [
    new OA\Property(property: 'type', type: 'string', enum: ['conservative', 'balanced', 'aggressive', 'custom'], example: 'balanced'),
    new OA\Property(property: 'risk_tolerance', type: 'string', enum: ['low', 'moderate', 'high'], example: 'moderate'),
    new OA\Property(property: 'target_allocations', type: 'object', properties: [
    new OA\Property(property: 'USD', type: 'number', format: 'float', example: 40.0),
    new OA\Property(property: 'EUR', type: 'number', format: 'float', example: 30.0),
    new OA\Property(property: 'GCU', type: 'number', format: 'float', example: 30.0),
    ]),
    new OA\Property(property: 'rebalancing_threshold', type: 'number', format: 'float', example: 5.0, description: 'Percentage deviation threshold for rebalancing'),
    ]),
    ]
)]
#[OA\Schema(
    schema: 'UpdateTreasuryPortfolioRequest',
    type: 'object',
    required: ['strategy'],
    properties: [
    new OA\Property(property: 'strategy', type: 'object', properties: [
    new OA\Property(property: 'type', type: 'string', enum: ['conservative', 'balanced', 'aggressive', 'custom'], example: 'balanced'),
    new OA\Property(property: 'risk_tolerance', type: 'string', enum: ['low', 'moderate', 'high'], example: 'moderate'),
    new OA\Property(property: 'target_allocations', type: 'object'),
    new OA\Property(property: 'rebalancing_threshold', type: 'number', format: 'float', example: 5.0),
    ]),
    ]
)]
#[OA\Schema(
    schema: 'AllocateAssetsRequest',
    type: 'object',
    required: ['allocations'],
    properties: [
    new OA\Property(property: 'allocations', type: 'array', items: new OA\Items(type: 'object', properties: [
    new OA\Property(property: 'asset_symbol', type: 'string', example: 'USD'),
    new OA\Property(property: 'target_percentage', type: 'number', format: 'float', example: 40.0),
    new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 400000.00),
    ])),
    ]
)]
#[OA\Schema(
    schema: 'AssetAllocation',
    type: 'object',
    description: 'An asset allocation within a treasury portfolio',
    properties: [
    new OA\Property(property: 'asset_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'asset_symbol', type: 'string', example: 'USD'),
    new OA\Property(property: 'asset_type', type: 'string', enum: ['fiat', 'crypto', 'stablecoin', 'commodity'], example: 'fiat'),
    new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 500000.00),
    new OA\Property(property: 'current_value', type: 'number', format: 'float', example: 500000.00),
    new OA\Property(property: 'target_percentage', type: 'number', format: 'float', example: 50.0),
    new OA\Property(property: 'current_percentage', type: 'number', format: 'float', example: 48.5),
    new OA\Property(property: 'deviation', type: 'number', format: 'float', example: -1.5),
    ]
)]
#[OA\Schema(
    schema: 'TriggerRebalancingRequest',
    type: 'object',
    properties: [
    new OA\Property(property: 'reason', type: 'string', example: 'manual_trigger', description: 'Reason for triggering rebalancing'),
    ]
)]
#[OA\Schema(
    schema: 'RebalancingPlan',
    type: 'object',
    description: 'Treasury portfolio rebalancing plan',
    properties: [
    new OA\Property(property: 'plan_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'portfolio_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'executing', 'completed', 'cancelled'], example: 'pending'),
    new OA\Property(property: 'trades', type: 'array', items: new OA\Items(type: 'object', properties: [
    new OA\Property(property: 'asset_symbol', type: 'string'),
    new OA\Property(property: 'action', type: 'string', enum: ['buy', 'sell']),
    new OA\Property(property: 'quantity', type: 'number', format: 'float'),
    new OA\Property(property: 'estimated_value', type: 'number', format: 'float'),
    ])),
    new OA\Property(property: 'estimated_cost', type: 'number', format: 'float', example: 50.00),
    new OA\Property(property: 'current_allocations', type: 'array', items: new OA\Items(ref: '#/components/schemas/AssetAllocation')),
    new OA\Property(property: 'target_allocations', type: 'object'),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'approved_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ApproveRebalancingRequest',
    type: 'object',
    required: ['plan'],
    properties: [
    new OA\Property(property: 'plan', type: 'object', properties: [
    new OA\Property(property: 'trades', type: 'array', items: new OA\Items(type: 'object', properties: [
    new OA\Property(property: 'asset_symbol', type: 'string'),
    new OA\Property(property: 'action', type: 'string', enum: ['buy', 'sell']),
    new OA\Property(property: 'quantity', type: 'number', format: 'float'),
    ])),
    ]),
    ]
)]
#[OA\Schema(
    schema: 'PortfolioPerformance',
    type: 'object',
    description: 'Portfolio performance metrics',
    properties: [
    new OA\Property(property: 'portfolio_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'period', type: 'string', example: '30d'),
    new OA\Property(property: 'performance', type: 'object', properties: [
    new OA\Property(property: 'starting_value', type: 'number', format: 'float', example: 1000000.00),
    new OA\Property(property: 'ending_value', type: 'number', format: 'float', example: 1050000.00),
    new OA\Property(property: 'absolute_return', type: 'number', format: 'float', example: 50000.00),
    new OA\Property(property: 'percentage_return', type: 'number', format: 'float', example: 5.0),
    new OA\Property(property: 'volatility', type: 'number', format: 'float', example: 2.5),
    new OA\Property(property: 'sharpe_ratio', type: 'number', format: 'float', example: 1.5),
    ]),
    new OA\Property(property: 'rebalancing_metrics', type: 'object', properties: [
    new OA\Property(property: 'total_rebalances', type: 'integer', example: 3),
    new OA\Property(property: 'last_rebalance', type: 'string', format: 'date-time'),
    new OA\Property(property: 'deviation_score', type: 'number', format: 'float', example: 2.1),
    ]),
    new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'PortfolioValuation',
    type: 'object',
    description: 'Portfolio valuation details',
    properties: [
    new OA\Property(property: 'portfolio_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'valuation', type: 'object', properties: [
    new OA\Property(property: 'total_value', type: 'number', format: 'float', example: 1000000.00),
    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
    new OA\Property(property: 'assets', type: 'array', items: new OA\Items(type: 'object', properties: [
    new OA\Property(property: 'symbol', type: 'string'),
    new OA\Property(property: 'quantity', type: 'number', format: 'float'),
    new OA\Property(property: 'unit_price', type: 'number', format: 'float'),
    new OA\Property(property: 'total_value', type: 'number', format: 'float'),
    ])),
    ]),
    new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'PortfolioHistory',
    type: 'object',
    description: 'Portfolio historical data',
    properties: [
    new OA\Property(property: 'portfolio_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'type', type: 'string', enum: ['rebalancing', 'performance', 'all'], example: 'all'),
    new OA\Property(property: 'history', type: 'object', properties: [
    new OA\Property(property: 'rebalancing', type: 'array', items: new OA\Items(type: 'object', properties: [
    new OA\Property(property: 'date', type: 'string', format: 'date-time'),
    new OA\Property(property: 'reason', type: 'string'),
    new OA\Property(property: 'trades_executed', type: 'integer'),
    ])),
    new OA\Property(property: 'performance', type: 'array', items: new OA\Items(type: 'object', properties: [
    new OA\Property(property: 'date', type: 'string', format: 'date'),
    new OA\Property(property: 'value', type: 'number', format: 'float'),
    new OA\Property(property: 'daily_return', type: 'number', format: 'float'),
    ])),
    ]),
    ]
)]
#[OA\Schema(
    schema: 'CreateReportRequest',
    type: 'object',
    required: ['type', 'period'],
    properties: [
    new OA\Property(property: 'type', type: 'string', enum: ['summary', 'detailed', 'compliance', 'performance'], example: 'summary'),
    new OA\Property(property: 'period', type: 'string', example: '30d', description: 'Report period (e.g., 7d, 30d, 90d, 1y)'),
    ]
)]
#[OA\Schema(
    schema: 'PortfolioReport',
    type: 'object',
    description: 'Generated portfolio report',
    properties: [
    new OA\Property(property: 'report_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'portfolio_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'type', type: 'string', example: 'summary'),
    new OA\Property(property: 'period', type: 'string', example: '30d'),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'generating', 'completed', 'failed'], example: 'completed'),
    new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'download_url', type: 'string', format: 'uri', nullable: true),
    ]
)]
class TreasuryApiSchemas
{
    // This class exists only for OpenAPI documentation schemas
}
