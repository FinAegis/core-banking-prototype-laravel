<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read view of `mcp_tool_invocations`.
 *
 * The audit log is appended via {@see \App\Domain\MCP\Audit\ToolInvocationLogger}
 * with raw DB::table() — the model is here so Filament + admin queries can
 * pivot/filter the data, NOT for writes. Don't add fillables; treat the
 * invocation row as immutable history.
 *
 * @property int         $id
 * @property string      $token_id
 * @property string      $client_id
 * @property int|null    $user_id
 * @property string      $tool_name
 * @property string      $args_hash
 * @property string|null $idempotency_key
 * @property string      $result_status
 * @property string|null $error_code
 * @property int|null    $settlement_amount_minor
 * @property string|null $settlement_currency
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property int|null    $duration_ms
 * @property \Illuminate\Support\Carbon $created_at
 */
class McpToolInvocation extends Model
{
    protected $table = 'mcp_tool_invocations';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'user_id'                 => 'integer',
        'settlement_amount_minor' => 'integer',
        'duration_ms'             => 'integer',
        'created_at'              => 'datetime',
    ];

    /**
     * Format the settlement amount for display, given the currency's minor-unit
     * decimals. We assume 2 for fiat-like codes; crypto amounts that need a
     * different scale should be formatted at the call site.
     */
    public function formattedSettlement(int $decimals = 2): ?string
    {
        if ($this->settlement_amount_minor === null || $this->settlement_currency === null) {
            return null;
        }

        $major = bcdiv((string) $this->settlement_amount_minor, bcpow('10', (string) $decimals), $decimals);

        return $this->settlement_currency . ' ' . $major;
    }
}
