<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ComponentPerformance",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="basket_performance_id", type="integer"),
 *     @OA\Property(property="asset_code", type="string"),
 *     @OA\Property(property="asset_name", type="string"),
 *     @OA\Property(property="start_weight", type="number"),
 *     @OA\Property(property="end_weight", type="number"),
 *     @OA\Property(property="average_weight", type="number"),
 *     @OA\Property(property="weight_change", type="number"),
 *     @OA\Property(property="contribution_value", type="number"),
 *     @OA\Property(property="contribution_percentage", type="number"),
 *     @OA\Property(property="formatted_contribution", type="string"),
 *     @OA\Property(property="return_value", type="number"),
 *     @OA\Property(property="return_percentage", type="number"),
 *     @OA\Property(property="formatted_return", type="string"),
 *     @OA\Property(property="is_positive_contributor", type="boolean")
 * )
 */
class ComponentPerformanceResource extends JsonResource
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
            'basket_performance_id' => $this->basket_performance_id,
            'asset_code' => $this->asset_code,
            'asset_name' => $this->asset?->name ?? $this->asset_code,
            'start_weight' => round($this->start_weight, 2),
            'end_weight' => round($this->end_weight, 2),
            'average_weight' => round($this->average_weight, 2),
            'weight_change' => round($this->weight_change, 2),
            'contribution_value' => round($this->contribution_value, 4),
            'contribution_percentage' => round($this->contribution_percentage, 2),
            'formatted_contribution' => $this->formatted_contribution,
            'return_value' => round($this->return_value, 4),
            'return_percentage' => round($this->return_percentage, 2),
            'formatted_return' => $this->formatted_return,
            'is_positive_contributor' => $this->hasPositiveContribution(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}