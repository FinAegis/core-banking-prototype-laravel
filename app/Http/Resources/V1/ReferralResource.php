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
            'referee' => $this->whenLoaded('referee', fn () => $this->referee !== null ? [
                'id'   => $this->referee->id,
                'name' => $this->referee->name,
            ] : null),
            'status'        => $this->status,
            'reward_amount' => (int) config('relayer.sponsorship.default_free_tx', 5),
            'completed_at'  => $this->completed_at?->toIso8601String(),
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}
