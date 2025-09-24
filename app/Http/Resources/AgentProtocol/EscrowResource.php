<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EscrowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'escrow_id'         => $this->escrow_id,
            'status'            => $this->status,
            'sender_agent_id'   => $this->sender_agent_id,
            'receiver_agent_id' => $this->receiver_agent_id,
            'amount'            => $this->amount,
            'currency'          => $this->currency,
            'conditions'        => $this->conditions,
            'expires_at'        => $this->expires_at?->toIso8601String(),
            'metadata'          => $this->metadata,
            'created_at'        => $this->created_at?->toIso8601String(),
            'released_at'       => $this->released_at?->toIso8601String(),
            'disputed_at'       => $this->disputed_at?->toIso8601String(),
        ];
    }
}
