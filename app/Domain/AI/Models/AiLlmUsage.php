<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $conversation_id
 * @property string|null $user_uuid
 * @property string $provider
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property float $cost_usd
 * @property int $latency_ms
 * @property string|null $request_type
 * @property bool $success
 * @property string|null $error_message
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder provider(string $provider)
 * @method static \Illuminate\Database\Eloquent\Builder successful()
 * @method static \Illuminate\Database\Eloquent\Builder failed()
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static create(array $attributes = [])
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 */
class AiLlmUsage extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_ANTHROPIC = 'anthropic';

    public const PROVIDER_DEMO = 'demo';

    public const REQUEST_TYPE_QUERY = 'query';

    public const REQUEST_TYPE_ANALYSIS = 'analysis';

    public const REQUEST_TYPE_COMPLIANCE = 'compliance';

    public const REQUEST_TYPE_CODE_GENERATION = 'code_generation';

    /**
     * Cost per 1K tokens for different models (in USD).
     */
    public const PRICING = [
        'gpt-4'                  => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo'            => ['input' => 0.01, 'output' => 0.03],
        'gpt-3.5-turbo'          => ['input' => 0.0005, 'output' => 0.0015],
        'claude-3-opus'          => ['input' => 0.015, 'output' => 0.075],
        'claude-3-sonnet'        => ['input' => 0.003, 'output' => 0.015],
        'claude-3-haiku'         => ['input' => 0.00025, 'output' => 0.00125],
        'claude-3-opus-20240229' => ['input' => 0.015, 'output' => 0.075],
        'claude-opus-4-20250514' => ['input' => 0.015, 'output' => 0.075],
    ];

    protected $table = 'ai_llm_usage';

    protected $fillable = [
        'conversation_id',
        'user_uuid',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
        'latency_ms',
        'request_type',
        'success',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'prompt_tokens'     => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens'      => 'integer',
        'cost_usd'          => 'decimal:6',
        'latency_ms'        => 'integer',
        'success'           => 'boolean',
        'metadata'          => 'array',
    ];

    /**
     * Scope to filter by provider.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $provider
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter successful requests.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope to filter failed requests.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Calculate cost based on model and tokens.
     */
    public static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = self::PRICING[$model] ?? ['input' => 0.01, 'output' => 0.03]; // Default pricing

        $inputCost = ($promptTokens / 1000) * $pricing['input'];
        $outputCost = ($completionTokens / 1000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Log an LLM usage record.
     *
     * @param array<string, mixed> $data
     */
    public static function log(array $data): self
    {
        $promptTokens = $data['prompt_tokens'] ?? 0;
        $completionTokens = $data['completion_tokens'] ?? 0;
        $model = $data['model'] ?? 'unknown';

        $data['total_tokens'] = $promptTokens + $completionTokens;
        $data['cost_usd'] = $data['cost_usd'] ?? self::calculateCost($model, $promptTokens, $completionTokens);

        return self::create($data);
    }

    /**
     * Get total cost for a user in a date range.
     *
     * @param string $userUuid
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return float
     */
    public static function getTotalCostForUser(string $userUuid, $startDate, $endDate): float
    {
        return (float) self::where('user_uuid', $userUuid)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('cost_usd');
    }

    /**
     * Get usage statistics for a provider.
     *
     * @param string $provider
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return array<string, mixed>
     */
    public static function getProviderStats(string $provider, $startDate, $endDate): array
    {
        $baseQuery = self::where('provider', $provider)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalRequests = $baseQuery->clone()->count();
        $totalTokens = (int) $baseQuery->clone()->sum('total_tokens');
        $totalCost = (float) $baseQuery->clone()->sum('cost_usd');
        $avgLatency = (float) $baseQuery->clone()->avg('latency_ms');
        $successCount = $baseQuery->clone()->successful()->count();

        return [
            'total_requests' => $totalRequests,
            'total_tokens'   => $totalTokens,
            'total_cost_usd' => $totalCost,
            'avg_latency_ms' => $avgLatency,
            'success_rate'   => $totalRequests > 0
                ? round(($successCount / $totalRequests) * 100, 2)
                : 0,
        ];
    }
}
