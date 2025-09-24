<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'agent_id'         => $this->agent_id,
            'did'              => $this->did,
            'name'             => $this->name,
            'type'             => $this->type,
            'status'           => $this->status,
            'network_id'       => $this->network_id,
            'organization'     => $this->organization,
            'endpoints'        => $this->endpoints,
            'capabilities'     => $this->capabilities,
            'metadata'         => $this->metadata,
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'created_at'       => $this->created_at->toIso8601String(),
            'updated_at'       => $this->updated_at->toIso8601String(),
        ];
    }
}
