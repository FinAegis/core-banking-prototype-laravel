<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Schema(
 *     schema="Account",
 *     type="object",
 *     title="Account",
 *     required={"uuid", "user_uuid", "name", "balance", "frozen"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="user_uuid", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="name", type="string", example="Savings Account"),
 *     @OA\Property(property="balance", type="integer", example=50000, description="Balance in cents"),
 *     @OA\Property(property="frozen", type="boolean", example=false, description="Whether the account is frozen"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="Transaction",
 *     type="object",
 *     title="Transaction",
 *     required={"uuid", "account_uuid", "type", "amount", "balance_after", "description", "hash"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="770e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="account_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="type", type="string", enum={"deposit", "withdrawal"}, example="deposit"),
 *     @OA\Property(property="amount", type="integer", example=10000, description="Amount in cents"),
 *     @OA\Property(property="balance_after", type="integer", example=60000, description="Balance after transaction in cents"),
 *     @OA\Property(property="description", type="string", example="Monthly salary deposit"),
 *     @OA\Property(property="hash", type="string", example="3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e", description="SHA3-512 transaction hash"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="Transfer",
 *     type="object",
 *     title="Transfer",
 *     required={"uuid", "from_account_uuid", "to_account_uuid", "amount", "description", "status", "hash"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="880e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="from_account_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="to_account_uuid", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="amount", type="integer", example=5000, description="Amount in cents"),
 *     @OA\Property(property="description", type="string", example="Payment for services"),
 *     @OA\Property(property="status", type="string", enum={"pending", "completed", "failed"}, example="completed"),
 *     @OA\Property(property="hash", type="string", example="4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f", description="SHA3-512 transfer hash"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="completed_at", type="string", format="date-time", example="2024-01-01T00:00:01Z", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="Balance",
 *     type="object",
 *     title="Balance",
 *     required={"account_uuid", "balance", "frozen"},
 *     @OA\Property(property="account_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="balance", type="integer", example=50000, description="Current balance in cents"),
 *     @OA\Property(property="frozen", type="boolean", example=false),
 *     @OA\Property(property="last_updated", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(
 *         property="turnover",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="total_debit", type="integer", example=100000),
 *         @OA\Property(property="total_credit", type="integer", example=150000),
 *         @OA\Property(property="month", type="integer", example=1),
 *         @OA\Property(property="year", type="integer", example=2024)
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="Asset",
 *     type="object",
 *     title="Asset",
 *     required={"code", "name", "type", "precision", "is_active"},
 *     @OA\Property(property="code", type="string", example="USD", description="Asset code (e.g., USD, EUR, BTC)"),
 *     @OA\Property(property="name", type="string", example="US Dollar"),
 *     @OA\Property(property="type", type="string", enum={"fiat", "crypto", "commodity", "custom"}, example="fiat"),
 *     @OA\Property(property="precision", type="integer", example=2, description="Number of decimal places"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="metadata", type="object", nullable=true, description="Additional asset metadata"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="ExchangeRate",
 *     type="object",
 *     title="Exchange Rate",
 *     required={"from_asset_code", "to_asset_code", "rate", "is_active"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="from_asset_code", type="string", example="USD"),
 *     @OA\Property(property="to_asset_code", type="string", example="EUR"),
 *     @OA\Property(property="rate", type="string", example="0.8500000000", description="Exchange rate with 10 decimal precision"),
 *     @OA\Property(property="bid", type="string", nullable=true, example="0.8495000000"),
 *     @OA\Property(property="ask", type="string", nullable=true, example="0.8505000000"),
 *     @OA\Property(property="source", type="string", example="manual", description="Rate source"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="valid_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="AccountBalance",
 *     type="object",
 *     title="Account Balance",
 *     required={"account_uuid", "asset_code", "balance"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="account_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="asset_code", type="string", example="USD"),
 *     @OA\Property(property="balance", type="integer", example=50000, description="Balance in smallest unit (cents for USD)"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="asset", ref="#/components/schemas/Asset"),
 *     @OA\Property(property="account", ref="#/components/schemas/Account")
 * )
 * 
 * @OA\Schema(
 *     schema="Poll",
 *     type="object",
 *     title="Poll",
 *     required={"id", "title", "type", "status", "options", "start_date", "end_date"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Should we add support for Japanese Yen?"),
 *     @OA\Property(property="description", type="string", nullable=true, example="This poll determines whether to add JPY support to the platform"),
 *     @OA\Property(property="type", type="string", enum={"single_choice", "multiple_choice", "weighted_choice", "yes_no", "ranked_choice"}, example="yes_no"),
 *     @OA\Property(property="status", type="string", enum={"draft", "active", "completed", "cancelled"}, example="active"),
 *     @OA\Property(
 *         property="options",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="string", example="yes"),
 *             @OA\Property(property="label", type="string", example="Yes, add JPY support")
 *         )
 *     ),
 *     @OA\Property(property="voting_power_strategy", type="string", example="OneUserOneVoteStrategy"),
 *     @OA\Property(property="execution_workflow", type="string", nullable=true, example="AddAssetWorkflow"),
 *     @OA\Property(property="min_participation", type="integer", nullable=true, example=100),
 *     @OA\Property(property="winning_threshold", type="number", format="float", nullable=true, example=0.5),
 *     @OA\Property(property="start_date", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="end_date", type="string", format="date-time", example="2024-01-08T00:00:00Z"),
 *     @OA\Property(property="created_by", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="votes_count", type="integer", example=150, description="Total number of votes"),
 *     @OA\Property(property="total_voting_power", type="integer", example=500, description="Total voting power cast")
 * )
 * 
 * @OA\Schema(
 *     schema="Vote",
 *     type="object",
 *     title="Vote",
 *     required={"id", "poll_id", "user_uuid", "selected_options", "voting_power", "voted_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="poll_id", type="integer", example=1),
 *     @OA\Property(property="user_uuid", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(
 *         property="selected_options",
 *         type="array",
 *         @OA\Items(type="string"),
 *         example={"yes"}
 *     ),
 *     @OA\Property(property="voting_power", type="integer", example=10),
 *     @OA\Property(property="signature", type="string", nullable=true, example="abc123def456"),
 *     @OA\Property(property="voted_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
 *     @OA\Property(property="poll", ref="#/components/schemas/Poll")
 * )
 * 
 * @OA\Schema(
 *     schema="PollResult",
 *     type="object",
 *     title="Poll Result",
 *     required={"poll", "results", "participation"},
 *     @OA\Property(property="poll", ref="#/components/schemas/Poll"),
 *     @OA\Property(
 *         property="results",
 *         type="object",
 *         description="Vote results by option",
 *         example={
 *             "yes": {"votes": 75, "voting_power": 250},
 *             "no": {"votes": 25, "voting_power": 100}
 *         }
 *     ),
 *     @OA\Property(
 *         property="participation",
 *         type="object",
 *         @OA\Property(property="total_votes", type="integer", example=100),
 *         @OA\Property(property="total_voting_power", type="integer", example=350),
 *         @OA\Property(property="participation_rate", type="number", format="float", example=0.25),
 *         @OA\Property(property="winning_option", type="string", nullable=true, example="yes"),
 *         @OA\Property(property="meets_threshold", type="boolean", example=true)
 *     ),
 *     @OA\Property(property="calculated_at", type="string", format="date-time", example="2024-01-01T12:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     title="Error Response",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(property="error", type="string", example="VALIDATION_ERROR", nullable=true),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         nullable=true,
 *         additionalProperties={"type":"array", "items":{"type":"string"}}
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     title="Validation Error Response",
 *     required={"message", "errors"},
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         additionalProperties={
 *             "type":"array",
 *             "items":{"type":"string"}
 *         },
 *         example={
 *             "email": {"The email field is required."},
 *             "amount": {"The amount must be greater than 0."}
 *         }
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="BasketAsset",
 *     type="object",
 *     title="Basket Asset",
 *     required={"code", "name", "type", "components"},
 *     @OA\Property(property="code", type="string", example="STABLE_BASKET"),
 *     @OA\Property(property="name", type="string", example="Stable Currency Basket"),
 *     @OA\Property(property="description", type="string", example="A diversified basket of stable fiat currencies"),
 *     @OA\Property(property="type", type="string", enum={"fixed", "dynamic"}, example="fixed"),
 *     @OA\Property(property="rebalance_frequency", type="string", enum={"daily", "weekly", "monthly", "quarterly", "never"}, example="never"),
 *     @OA\Property(property="last_rebalanced_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(
 *         property="components",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/BasketComponent")
 *     ),
 *     @OA\Property(
 *         property="latest_value",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="value", type="number", format="float", example=1.0975),
 *         @OA\Property(property="calculated_at", type="string", format="date-time")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="BasketComponent",
 *     type="object",
 *     title="Basket Component",
 *     required={"asset_code", "weight"},
 *     @OA\Property(property="asset_code", type="string", example="USD"),
 *     @OA\Property(property="asset_name", type="string", example="US Dollar"),
 *     @OA\Property(property="weight", type="number", format="float", example=40.0, description="Weight percentage (0-100)"),
 *     @OA\Property(property="min_weight", type="number", format="float", nullable=true, example=35.0),
 *     @OA\Property(property="max_weight", type="number", format="float", nullable=true, example=45.0),
 *     @OA\Property(property="is_active", type="boolean", default=true)
 * )
 * 
 * @OA\Schema(
 *     schema="BasketValue",
 *     type="object",
 *     title="Basket Value",
 *     required={"basket_code", "value", "calculated_at"},
 *     @OA\Property(property="basket_code", type="string", example="STABLE_BASKET"),
 *     @OA\Property(property="value", type="number", format="float", example=1.0975, description="Current value in base currency (USD)"),
 *     @OA\Property(property="calculated_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
 *     @OA\Property(
 *         property="component_values",
 *         type="object",
 *         description="Breakdown of component values",
 *         example={
 *             "USD": {"value": 1.0, "weight": 40.0, "weighted_value": 0.4},
 *             "EUR": {"value": 1.1, "weight": 35.0, "weighted_value": 0.385},
 *             "GBP": {"value": 1.25, "weight": 25.0, "weighted_value": 0.3125}
 *         }
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="UserVotingPoll",
 *     type="object",
 *     title="User Voting Poll",
 *     required={"uuid", "title", "type", "status", "options", "start_date", "end_date", "user_context"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="990e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="title", type="string", example="Monthly GCU Basket Allocation for June 2025"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Vote on the currency allocation for the Global Currency Unit basket"),
 *     @OA\Property(property="type", type="string", enum={"single_choice", "multiple_choice", "weighted_choice", "yes_no", "ranked_choice"}, example="weighted_choice"),
 *     @OA\Property(property="status", type="string", enum={"draft", "active", "closed", "cancelled"}, example="active"),
 *     @OA\Property(
 *         property="options",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="string", example="USD"),
 *             @OA\Property(property="label", type="string", example="US Dollar")
 *         )
 *     ),
 *     @OA\Property(property="start_date", type="string", format="date-time", example="2025-06-01T00:00:00Z"),
 *     @OA\Property(property="end_date", type="string", format="date-time", example="2025-06-08T00:00:00Z"),
 *     @OA\Property(property="required_participation", type="number", format="float", nullable=true, example=25.0),
 *     @OA\Property(property="current_participation", type="number", format="float", example=15.5),
 *     @OA\Property(
 *         property="user_context",
 *         type="object",
 *         @OA\Property(property="has_voted", type="boolean", example=false),
 *         @OA\Property(property="voting_power", type="integer", example=1000),
 *         @OA\Property(property="can_vote", type="boolean", example=true),
 *         @OA\Property(
 *             property="vote",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(
 *                 property="selected_options",
 *                 type="array",
 *                 @OA\Items(type="string")
 *             ),
 *             @OA\Property(property="voted_at", type="string", format="date-time")
 *         )
 *     ),
 *     @OA\Property(
 *         property="metadata",
 *         type="object",
 *         @OA\Property(property="is_gcu_poll", type="boolean", example=true),
 *         @OA\Property(property="voting_month", type="string", nullable=true, example="2025-06"),
 *         @OA\Property(property="template", type="string", nullable=true, example="monthly_gcu_allocation")
 *     ),
 *     @OA\Property(property="results_visible", type="boolean", example=false),
 *     @OA\Property(
 *         property="time_remaining",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="days", type="integer", example=6),
 *         @OA\Property(property="hours", type="integer", example=12),
 *         @OA\Property(property="human_readable", type="string", example="6 days from now")
 *     )
 * )
 */
class Schemas
{
}