<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

/**
 * Treasury API Documentation Schemas.
 *
 * @OA\Tag(
 *     name="Treasury Portfolio",
 *     description="Treasury portfolio management operations"
 * )
 *
 * @OA\Tag(
 *     name="Treasury Operations",
 *     description="Treasury operational endpoints"
 * )
 *
 * @OA\Schema(
 *     schema="TreasuryPortfolio",
 *     type="object",
 *     description="A treasury portfolio containing asset allocations",
 *     @OA\Property(property="portfolio_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="treasury_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440001"),
 *     @OA\Property(property="name", type="string", example="Main Treasury Portfolio"),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"active", "inactive", "rebalancing", "liquidating"},
 *         example="active"
 *     ),
 *     @OA\Property(property="total_value", type="number", format="float", example=1000000.00),
 *     @OA\Property(property="asset_count", type="integer", example=5),
 *     @OA\Property(property="is_rebalancing", type="boolean", example=false),
 *     @OA\Property(property="last_rebalance_date", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="TreasuryPortfolioDetailed",
 *     type="object",
 *     description="Detailed treasury portfolio with summary and rebalancing status",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/TreasuryPortfolio"),
 *         @OA\Schema(
 *             @OA\Property(property="strategy", type="object",
 *                 @OA\Property(property="type", type="string", example="balanced"),
 *                 @OA\Property(property="risk_tolerance", type="string", example="moderate"),
 *                 @OA\Property(property="target_allocations", type="object")
 *             ),
 *             @OA\Property(property="asset_allocations", type="array", @OA\Items(ref="#/components/schemas/AssetAllocation")),
 *             @OA\Property(property="latest_metrics", type="object"),
 *             @OA\Property(property="summary", type="object"),
 *             @OA\Property(property="needs_rebalancing", type="boolean", example=false)
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="CreateTreasuryPortfolioRequest",
 *     type="object",
 *     required={"treasury_id", "name", "strategy"},
 *     @OA\Property(property="treasury_id", type="string", format="uuid", description="ID of the treasury account"),
 *     @OA\Property(property="name", type="string", example="Growth Portfolio", description="Portfolio name"),
 *     @OA\Property(property="strategy", type="object", required={"type"},
 *         @OA\Property(property="type", type="string", enum={"conservative", "balanced", "aggressive", "custom"}, example="balanced"),
 *         @OA\Property(property="risk_tolerance", type="string", enum={"low", "moderate", "high"}, example="moderate"),
 *         @OA\Property(property="target_allocations", type="object",
 *             @OA\Property(property="USD", type="number", format="float", example=40.0),
 *             @OA\Property(property="EUR", type="number", format="float", example=30.0),
 *             @OA\Property(property="GCU", type="number", format="float", example=30.0)
 *         ),
 *         @OA\Property(property="rebalancing_threshold", type="number", format="float", example=5.0, description="Percentage deviation threshold for rebalancing")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UpdateTreasuryPortfolioRequest",
 *     type="object",
 *     required={"strategy"},
 *     @OA\Property(property="strategy", type="object",
 *         @OA\Property(property="type", type="string", enum={"conservative", "balanced", "aggressive", "custom"}, example="balanced"),
 *         @OA\Property(property="risk_tolerance", type="string", enum={"low", "moderate", "high"}, example="moderate"),
 *         @OA\Property(property="target_allocations", type="object"),
 *         @OA\Property(property="rebalancing_threshold", type="number", format="float", example=5.0)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AllocateAssetsRequest",
 *     type="object",
 *     required={"allocations"},
 *     @OA\Property(property="allocations", type="array",
 *         @OA\Items(type="object",
 *             @OA\Property(property="asset_symbol", type="string", example="USD"),
 *             @OA\Property(property="target_percentage", type="number", format="float", example=40.0),
 *             @OA\Property(property="quantity", type="number", format="float", example=400000.00)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AssetAllocation",
 *     type="object",
 *     description="An asset allocation within a treasury portfolio",
 *     @OA\Property(property="asset_id", type="string", format="uuid"),
 *     @OA\Property(property="asset_symbol", type="string", example="USD"),
 *     @OA\Property(property="asset_type", type="string", enum={"fiat", "crypto", "stablecoin", "commodity"}, example="fiat"),
 *     @OA\Property(property="quantity", type="number", format="float", example=500000.00),
 *     @OA\Property(property="current_value", type="number", format="float", example=500000.00),
 *     @OA\Property(property="target_percentage", type="number", format="float", example=50.0),
 *     @OA\Property(property="current_percentage", type="number", format="float", example=48.5),
 *     @OA\Property(property="deviation", type="number", format="float", example=-1.5)
 * )
 *
 * @OA\Schema(
 *     schema="TriggerRebalancingRequest",
 *     type="object",
 *     @OA\Property(property="reason", type="string", example="manual_trigger", description="Reason for triggering rebalancing")
 * )
 *
 * @OA\Schema(
 *     schema="RebalancingPlan",
 *     type="object",
 *     description="Treasury portfolio rebalancing plan",
 *     @OA\Property(property="plan_id", type="string", format="uuid"),
 *     @OA\Property(property="portfolio_id", type="string", format="uuid"),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"pending", "approved", "executing", "completed", "cancelled"},
 *         example="pending"
 *     ),
 *     @OA\Property(property="trades", type="array", @OA\Items(
 *         type="object",
 *         @OA\Property(property="asset_symbol", type="string"),
 *         @OA\Property(property="action", type="string", enum={"buy", "sell"}),
 *         @OA\Property(property="quantity", type="number", format="float"),
 *         @OA\Property(property="estimated_value", type="number", format="float")
 *     )),
 *     @OA\Property(property="estimated_cost", type="number", format="float", example=50.00),
 *     @OA\Property(property="current_allocations", type="array", @OA\Items(ref="#/components/schemas/AssetAllocation")),
 *     @OA\Property(property="target_allocations", type="object"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ApproveRebalancingRequest",
 *     type="object",
 *     required={"plan"},
 *     @OA\Property(property="plan", type="object",
 *         @OA\Property(property="trades", type="array", @OA\Items(
 *             type="object",
 *             @OA\Property(property="asset_symbol", type="string"),
 *             @OA\Property(property="action", type="string", enum={"buy", "sell"}),
 *             @OA\Property(property="quantity", type="number", format="float")
 *         ))
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PortfolioPerformance",
 *     type="object",
 *     description="Portfolio performance metrics",
 *     @OA\Property(property="portfolio_id", type="string", format="uuid"),
 *     @OA\Property(property="period", type="string", example="30d"),
 *     @OA\Property(property="performance", type="object",
 *         @OA\Property(property="starting_value", type="number", format="float", example=1000000.00),
 *         @OA\Property(property="ending_value", type="number", format="float", example=1050000.00),
 *         @OA\Property(property="absolute_return", type="number", format="float", example=50000.00),
 *         @OA\Property(property="percentage_return", type="number", format="float", example=5.0),
 *         @OA\Property(property="volatility", type="number", format="float", example=2.5),
 *         @OA\Property(property="sharpe_ratio", type="number", format="float", example=1.5)
 *     ),
 *     @OA\Property(property="rebalancing_metrics", type="object",
 *         @OA\Property(property="total_rebalances", type="integer", example=3),
 *         @OA\Property(property="last_rebalance", type="string", format="date-time"),
 *         @OA\Property(property="deviation_score", type="number", format="float", example=2.1)
 *     ),
 *     @OA\Property(property="generated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="PortfolioValuation",
 *     type="object",
 *     description="Portfolio valuation details",
 *     @OA\Property(property="portfolio_id", type="string", format="uuid"),
 *     @OA\Property(property="valuation", type="object",
 *         @OA\Property(property="total_value", type="number", format="float", example=1000000.00),
 *         @OA\Property(property="currency", type="string", example="USD"),
 *         @OA\Property(property="assets", type="array", @OA\Items(
 *             type="object",
 *             @OA\Property(property="symbol", type="string"),
 *             @OA\Property(property="quantity", type="number", format="float"),
 *             @OA\Property(property="unit_price", type="number", format="float"),
 *             @OA\Property(property="total_value", type="number", format="float")
 *         ))
 *     ),
 *     @OA\Property(property="timestamp", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="PortfolioHistory",
 *     type="object",
 *     description="Portfolio historical data",
 *     @OA\Property(property="portfolio_id", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string", enum={"rebalancing", "performance", "all"}, example="all"),
 *     @OA\Property(property="history", type="object",
 *         @OA\Property(property="rebalancing", type="array", @OA\Items(
 *             type="object",
 *             @OA\Property(property="date", type="string", format="date-time"),
 *             @OA\Property(property="reason", type="string"),
 *             @OA\Property(property="trades_executed", type="integer")
 *         )),
 *         @OA\Property(property="performance", type="array", @OA\Items(
 *             type="object",
 *             @OA\Property(property="date", type="string", format="date"),
 *             @OA\Property(property="value", type="number", format="float"),
 *             @OA\Property(property="daily_return", type="number", format="float")
 *         ))
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CreateReportRequest",
 *     type="object",
 *     required={"type", "period"},
 *     @OA\Property(property="type", type="string", enum={"summary", "detailed", "compliance", "performance"}, example="summary"),
 *     @OA\Property(property="period", type="string", example="30d", description="Report period (e.g., 7d, 30d, 90d, 1y)")
 * )
 *
 * @OA\Schema(
 *     schema="PortfolioReport",
 *     type="object",
 *     description="Generated portfolio report",
 *     @OA\Property(property="report_id", type="string", format="uuid"),
 *     @OA\Property(property="portfolio_id", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string", example="summary"),
 *     @OA\Property(property="period", type="string", example="30d"),
 *     @OA\Property(property="status", type="string", enum={"pending", "generating", "completed", "failed"}, example="completed"),
 *     @OA\Property(property="generated_at", type="string", format="date-time"),
 *     @OA\Property(property="download_url", type="string", format="uri", nullable=true)
 * )
 */
class TreasuryApiSchemas
{
    // This class exists only for OpenAPI documentation schemas
}
