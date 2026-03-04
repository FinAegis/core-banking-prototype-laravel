<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Referral
 */
class ReferralResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'referee' => $this->whenLoaded('referee', fn () => [
                'id'   => $this->referee->id,
                'name' => $this->referee->name,
            ]),
            'status'       => $this->status,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
        ];
    }
}
