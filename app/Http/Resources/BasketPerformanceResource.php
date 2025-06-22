<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="BasketPerformance",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="basket_code", type="string"),
 *     @OA\Property(property="period_type", type="string", enum={"hour", "day", "week", "month", "quarter", "year", "all_time"}),
 *     @OA\Property(property="period_start", type="string", format="date-time"),
 *     @OA\Property(property="period_end", type="string", format="date-time"),
 *     @OA\Property(property="start_value", type="number"),
 *     @OA\Property(property="end_value", type="number"),
 *     @OA\Property(property="high_value", type="number"),
 *     @OA\Property(property="low_value", type="number"),
 *     @OA\Property(property="average_value", type="number"),
 *     @OA\Property(property="return_value", type="number"),
 *     @OA\Property(property="return_percentage", type="number"),
 *     @OA\Property(property="formatted_return", type="string"),
 *     @OA\Property(property="volatility", type="number"),
 *     @OA\Property(property="sharpe_ratio", type="number"),
 *     @OA\Property(property="max_drawdown", type="number"),
 *     @OA\Property(property="performance_rating", type="string"),
 *     @OA\Property(property="risk_rating", type="string"),
 *     @OA\Property(property="annualized_return", type="number"),
 *     @OA\Property(property="value_count", type="integer"),
 *     @OA\Property(property="components", type="array", @OA\Items(ref="#/components/schemas/ComponentPerformance"))
 * )
 */
class BasketPerformanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'basket_code' => $this->basket_asset_code,
            'period_type' => $this->period_type,
            'period_start' => $this->period_start->toIso8601String(),
            'period_end' => $this->period_end->toIso8601String(),
            'start_value' => round($this->start_value, 4),
            'end_value' => round($this->end_value, 4),
            'high_value' => round($this->high_value, 4),
            'low_value' => round($this->low_value, 4),
            'average_value' => round($this->average_value, 4),
            'return_value' => round($this->return_value, 4),
            'return_percentage' => round($this->return_percentage, 2),
            'formatted_return' => $this->formatted_return,
            'volatility' => $this->volatility ? round($this->volatility, 2) : null,
            'sharpe_ratio' => $this->sharpe_ratio ? round($this->sharpe_ratio, 2) : null,
            'max_drawdown' => $this->max_drawdown ? round($this->max_drawdown, 2) : null,
            'performance_rating' => $this->performance_rating,
            'risk_rating' => $this->risk_rating,
            'annualized_return' => $this->getAnnualizedReturn(),
            'value_count' => $this->value_count,
            'components' => ComponentPerformanceResource::collection($this->whenLoaded('componentPerformances')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}