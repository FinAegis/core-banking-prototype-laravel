<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Schema(
 *     schema="Custodian",
 *     required={"id", "code", "name", "type", "is_active", "capabilities"},
 *     @OA\Property(property="id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="code", type="string", example="paysera", description="Unique custodian code"),
 *     @OA\Property(property="name", type="string", example="Paysera", description="Custodian display name"),
 *     @OA\Property(property="type", type="string", enum={"bank", "emi", "crypto_exchange", "wallet_provider"}, example="emi"),
 *     @OA\Property(property="country", type="string", example="LT", description="ISO country code"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="capabilities", type="array", @OA\Items(type="string"), example={"sepa", "sepa_instant", "swift"}),
 *     @OA\Property(property="supported_currencies", type="array", @OA\Items(type="string"), example={"EUR", "USD", "GBP"}),
 *     @OA\Property(property="api_version", type="string", example="v2.0"),
 *     @OA\Property(property="health_status", type="string", enum={"healthy", "degraded", "unhealthy"}, example="healthy"),
 *     @OA\Property(property="metadata", type="object", description="Additional custodian-specific data"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="CustodianBalance",
 *     required={"custodian_code", "currency", "available_balance", "pending_balance", "reserved_balance", "last_updated"},
 *     @OA\Property(property="custodian_code", type="string", example="deutsche_bank"),
 *     @OA\Property(property="currency", type="string", example="EUR"),
 *     @OA\Property(property="available_balance", type="integer", example=10000000, description="Available balance in cents"),
 *     @OA\Property(property="pending_balance", type="integer", example=500000, description="Pending incoming balance in cents"),
 *     @OA\Property(property="reserved_balance", type="integer", example=200000, description="Reserved for outgoing transfers in cents"),
 *     @OA\Property(property="total_balance", type="integer", example=10700000, description="Total balance including pending"),
 *     @OA\Property(property="last_updated", type="string", format="date-time"),
 *     @OA\Property(property="account_numbers", type="array", @OA\Items(
 *         @OA\Property(property="type", type="string", example="iban"),
 *         @OA\Property(property="value", type="string", example="LT123456789012345678")
 *     ))
 * )
 */

/**
 * @OA\Schema(
 *     schema="CustodianTransfer",
 *     required={"id", "custodian_code", "direction", "amount", "currency", "status", "created_at"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="custodian_code", type="string", example="santander"),
 *     @OA\Property(property="direction", type="string", enum={"incoming", "outgoing", "internal"}, example="outgoing"),
 *     @OA\Property(property="amount", type="integer", example=100000, description="Amount in cents"),
 *     @OA\Property(property="currency", type="string", example="EUR"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed", "cancelled"}, example="completed"),
 *     @OA\Property(property="reference", type="string", example="TRF-2025-001", description="Internal reference"),
 *     @OA\Property(property="external_reference", type="string", example="SEPA123456", description="Custodian's reference"),
 *     @OA\Property(property="from_account", type="object",
 *         @OA\Property(property="iban", type="string", example="LT123456789012345678"),
 *         @OA\Property(property="name", type="string", example="John Doe")
 *     ),
 *     @OA\Property(property="to_account", type="object",
 *         @OA\Property(property="iban", type="string", example="DE89370400440532013000"),
 *         @OA\Property(property="name", type="string", example="Jane Smith")
 *     ),
 *     @OA\Property(property="fees", type="object",
 *         @OA\Property(property="amount", type="integer", example=250),
 *         @OA\Property(property="currency", type="string", example="EUR")
 *     ),
 *     @OA\Property(property="executed_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="InitiateCustodianTransferRequest",
 *     required={"from_custodian", "to_custodian", "amount", "currency"},
 *     @OA\Property(property="from_custodian", type="string", example="paysera", description="Source custodian code"),
 *     @OA\Property(property="to_custodian", type="string", example="deutsche_bank", description="Destination custodian code"),
 *     @OA\Property(property="amount", type="integer", example=100000, description="Amount in cents"),
 *     @OA\Property(property="currency", type="string", example="EUR"),
 *     @OA\Property(property="reference", type="string", example="Settlement-2025-001"),
 *     @OA\Property(property="urgency", type="string", enum={"normal", "urgent", "instant"}, example="normal"),
 *     @OA\Property(property="metadata", type="object", description="Additional transfer data")
 * )
 */

/**
 * @OA\Schema(
 *     schema="CustodianReconciliation",
 *     required={"id", "custodian_code", "reconciliation_date", "status", "discrepancies"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="custodian_code", type="string", example="revolut"),
 *     @OA\Property(property="reconciliation_date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "failed"}, example="completed"),
 *     @OA\Property(property="internal_balance", type="object",
 *         @OA\Property(property="EUR", type="integer", example=5000000),
 *         @OA\Property(property="USD", type="integer", example=2000000)
 *     ),
 *     @OA\Property(property="custodian_balance", type="object",
 *         @OA\Property(property="EUR", type="integer", example=5000000),
 *         @OA\Property(property="USD", type="integer", example=1999500)
 *     ),
 *     @OA\Property(property="discrepancies", type="array", @OA\Items(
 *         @OA\Property(property="currency", type="string", example="USD"),
 *         @OA\Property(property="internal_amount", type="integer", example=2000000),
 *         @OA\Property(property="custodian_amount", type="integer", example=1999500),
 *         @OA\Property(property="difference", type="integer", example=500),
 *         @OA\Property(property="explanation", type="string", example="Pending fee deduction")
 *     )),
 *     @OA\Property(property="transaction_count", type="object",
 *         @OA\Property(property="internal", type="integer", example=150),
 *         @OA\Property(property="custodian", type="integer", example=150)
 *     ),
 *     @OA\Property(property="completed_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="CustodianWebhookPayload",
 *     required={"event_type", "custodian_code", "timestamp", "data"},
 *     @OA\Property(property="event_type", type="string", enum={"transfer.completed", "transfer.failed", "balance.updated", "account.blocked"}, example="transfer.completed"),
 *     @OA\Property(property="custodian_code", type="string", example="n26"),
 *     @OA\Property(property="timestamp", type="string", format="date-time"),
 *     @OA\Property(property="data", type="object", description="Event-specific data"),
 *     @OA\Property(property="signature", type="string", example="sha256=abc123...", description="HMAC signature for verification")
 * )
 */

/**
 * @OA\Schema(
 *     schema="CustodianHealthStatus",
 *     required={"custodian_code", "status", "last_check", "metrics"},
 *     @OA\Property(property="custodian_code", type="string", example="paysera"),
 *     @OA\Property(property="status", type="string", enum={"healthy", "degraded", "unhealthy"}, example="healthy"),
 *     @OA\Property(property="last_check", type="string", format="date-time"),
 *     @OA\Property(property="uptime_percentage", type="number", example=99.95),
 *     @OA\Property(property="metrics", type="object",
 *         @OA\Property(property="response_time_ms", type="integer", example=250),
 *         @OA\Property(property="success_rate", type="number", example=99.8),
 *         @OA\Property(property="error_rate", type="number", example=0.2)
 *     ),
 *     @OA\Property(property="recent_errors", type="array", @OA\Items(
 *         @OA\Property(property="timestamp", type="string", format="date-time"),
 *         @OA\Property(property="error_type", type="string", example="timeout"),
 *         @OA\Property(property="message", type="string", example="API request timeout after 30s")
 *     )),
 *     @OA\Property(property="circuit_breaker", type="object",
 *         @OA\Property(property="state", type="string", enum={"closed", "open", "half_open"}, example="closed"),
 *         @OA\Property(property="failure_count", type="integer", example=0),
 *         @OA\Property(property="last_failure", type="string", format="date-time")
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="CustodianSettlement",
 *     required={"id", "settlement_date", "status", "total_amount", "transactions"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="settlement_date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="completed"),
 *     @OA\Property(property="total_amount", type="object",
 *         @OA\Property(property="EUR", type="integer", example=1000000),
 *         @OA\Property(property="USD", type="integer", example=500000)
 *     ),
 *     @OA\Property(property="transactions", type="array", @OA\Items(
 *         @OA\Property(property="from_custodian", type="string", example="paysera"),
 *         @OA\Property(property="to_custodian", type="string", example="deutsche_bank"),
 *         @OA\Property(property="amount", type="integer", example=250000),
 *         @OA\Property(property="currency", type="string", example="EUR"),
 *         @OA\Property(property="type", type="string", enum={"net", "gross"}, example="net")
 *     )),
 *     @OA\Property(property="settlement_method", type="string", enum={"net", "gross", "batch"}, example="net"),
 *     @OA\Property(property="executed_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

class CustodianSchemas
{
    // This class only contains OpenAPI schema definitions
}