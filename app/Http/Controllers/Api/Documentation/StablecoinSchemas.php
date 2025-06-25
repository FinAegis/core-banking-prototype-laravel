<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Schema(
 *     schema="Stablecoin",
 *     required={"code", "name", "symbol", "pegged_currency", "pegged_value", "reserve_requirement", "is_active"},
 *     @OA\Property(property="code", type="string", example="STABLE_LITAS", description="Unique stablecoin code"),
 *     @OA\Property(property="name", type="string", example="Stable LITAS", description="Stablecoin name"),
 *     @OA\Property(property="symbol", type="string", example="sLITAS", description="Trading symbol"),
 *     @OA\Property(property="peg_asset_code", type="string", example="EUR", description="Asset the stablecoin is pegged to"),
 *     @OA\Property(property="peg_ratio", type="string", example="1.00000000", description="Peg ratio"),
 *     @OA\Property(property="target_price", type="string", example="1.00000000", description="Target price"),
 *     @OA\Property(property="stability_mechanism", type="string", enum={"collateralized", "algorithmic", "hybrid"}, example="collateralized"),
 *     @OA\Property(property="collateral_ratio", type="string", example="1.5000", description="Required collateral ratio"),
 *     @OA\Property(property="min_collateral_ratio", type="string", example="1.2000", description="Minimum collateral ratio before liquidation"),
 *     @OA\Property(property="liquidation_penalty", type="string", example="0.1000", description="Liquidation penalty percentage"),
 *     @OA\Property(property="total_supply", type="integer", example=1000000, description="Total supply in smallest unit"),
 *     @OA\Property(property="max_supply", type="integer", example=10000000, description="Maximum supply limit"),
 *     @OA\Property(property="total_collateral_value", type="integer", example=1500000, description="Total collateral value"),
 *     @OA\Property(property="mint_fee", type="string", example="0.005000", description="Minting fee percentage"),
 *     @OA\Property(property="burn_fee", type="string", example="0.003000", description="Burning fee percentage"),
 *     @OA\Property(property="precision", type="integer", example=2, description="Decimal precision"),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="Whether the stablecoin is active"),
 *     @OA\Property(property="minting_enabled", type="boolean", example=true, description="Whether minting is enabled"),
 *     @OA\Property(property="burning_enabled", type="boolean", example=true, description="Whether burning is enabled"),
 *     @OA\Property(property="metadata", type="object", description="Additional metadata"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T10:00:00Z")
 * )
 */


/**
 * @OA\Schema(
 *     schema="CreateStablecoinRequest",
 *     required={"code", "name", "symbol", "pegged_currency", "pegged_value", "initial_reserve", "reserve_requirement"},
 *     @OA\Property(property="code", type="string", example="STABLE_LITAS", description="Unique stablecoin code"),
 *     @OA\Property(property="name", type="string", example="Stable LITAS", description="Stablecoin name"),
 *     @OA\Property(property="symbol", type="string", example="sLITAS", description="Trading symbol"),
 *     @OA\Property(property="pegged_currency", type="string", example="EUR", description="Currency to peg to"),
 *     @OA\Property(property="pegged_value", type="number", example=1.0, description="Pegged value ratio"),
 *     @OA\Property(property="initial_reserve", type="integer", example=1000000, description="Initial reserve amount"),
 *     @OA\Property(property="reserve_requirement", type="number", example=1.1, description="Required reserve ratio"),
 *     @OA\Property(property="metadata", type="object", description="Additional metadata")
 * )
 */

/**
 * @OA\Schema(
 *     schema="MintStablecoinRequest",
 *     required={"account_uuid", "amount"},
 *     @OA\Property(property="account_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000", description="Account to mint tokens to"),
 *     @OA\Property(property="amount", type="integer", example=100000, description="Amount to mint in smallest unit"),
 *     @OA\Property(property="reference", type="string", example="MINT-2025-001", description="Reference for the minting operation"),
 *     @OA\Property(property="metadata", type="object", description="Additional metadata for the operation")
 * )
 */

/**
 * @OA\Schema(
 *     schema="BurnStablecoinRequest",
 *     required={"account_uuid", "amount"},
 *     @OA\Property(property="account_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000", description="Account to burn tokens from"),
 *     @OA\Property(property="amount", type="integer", example=50000, description="Amount to burn in smallest unit"),
 *     @OA\Property(property="reference", type="string", example="BURN-2025-001", description="Reference for the burning operation"),
 *     @OA\Property(property="metadata", type="object", description="Additional metadata for the operation")
 * )
 */

/**
 * @OA\Schema(
 *     schema="StablecoinOperation",
 *     required={"id", "stablecoin_code", "type", "account_uuid", "amount", "status", "reference"},
 *     @OA\Property(property="id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="stablecoin_code", type="string", example="STABLE_LITAS"),
 *     @OA\Property(property="type", type="string", enum={"mint", "burn", "transfer"}, example="mint"),
 *     @OA\Property(property="account_uuid", type="string", format="uuid"),
 *     @OA\Property(property="amount", type="integer", example=100000),
 *     @OA\Property(property="status", type="string", enum={"pending", "completed", "failed", "cancelled"}, example="completed"),
 *     @OA\Property(property="reference", type="string", example="MINT-2025-001"),
 *     @OA\Property(property="tx_hash", type="string", example="0x123...abc", description="Blockchain transaction hash if applicable"),
 *     @OA\Property(property="metadata", type="object"),
 *     @OA\Property(property="executed_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="StablecoinReserve",
 *     required={"stablecoin_code", "reserve_amount", "required_amount", "reserve_ratio", "is_compliant"},
 *     @OA\Property(property="stablecoin_code", type="string", example="STABLE_LITAS"),
 *     @OA\Property(property="reserve_amount", type="integer", example=1100000, description="Current reserve amount"),
 *     @OA\Property(property="required_amount", type="integer", example=1000000, description="Required reserve amount"),
 *     @OA\Property(property="reserve_ratio", type="number", example=1.1, description="Current reserve ratio"),
 *     @OA\Property(property="is_compliant", type="boolean", example=true, description="Whether reserves meet requirements"),
 *     @OA\Property(property="last_audit_at", type="string", format="date-time"),
 *     @OA\Property(property="custodian_balances", type="array", @OA\Items(
 *         @OA\Property(property="custodian", type="string", example="deutsche_bank"),
 *         @OA\Property(property="amount", type="integer", example=550000)
 *     ))
 * )
 */

/**
 * @OA\Schema(
 *     schema="LiquidationCheckResult",
 *     required={"can_liquidate", "liquidation_amount", "reserve_after", "ratio_after"},
 *     @OA\Property(property="can_liquidate", type="boolean", example=true),
 *     @OA\Property(property="liquidation_amount", type="integer", example=50000, description="Maximum amount that can be liquidated"),
 *     @OA\Property(property="current_reserve", type="integer", example=1100000),
 *     @OA\Property(property="reserve_after", type="integer", example=1050000, description="Reserve after liquidation"),
 *     @OA\Property(property="ratio_after", type="number", example=1.05, description="Reserve ratio after liquidation"),
 *     @OA\Property(property="minimum_required_reserve", type="integer", example=1000000)
 * )
 */

class StablecoinSchemas
{
    // This class only contains OpenAPI schema definitions
}