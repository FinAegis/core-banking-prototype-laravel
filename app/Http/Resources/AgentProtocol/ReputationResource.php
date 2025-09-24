<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReputationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reputation_id'           => $this->reputation_id,
            'agent_id'                => $this->agent_id,
            'score'                   => $this->score,
            'trust_level'             => $this->trust_level,
            'total_transactions'      => $this->total_transactions,
            'successful_transactions' => $this->successful_transactions,
            'failed_transactions'     => $this->failed_transactions,
            'disputed_transactions'   => $this->disputed_transactions,
            'success_rate'            => $this->success_rate,
            'last_decay_at'           => $this->last_decay_at?->toIso8601String(),
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
