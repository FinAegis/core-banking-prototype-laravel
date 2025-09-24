<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentDiscoveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'agent_id'     => $this->agent_id,
            'did'          => $this->did,
            'name'         => $this->name,
            'type'         => $this->type,
            'organization' => $this->organization,
            'capabilities' => $this->capabilities,
            'endpoints'    => $this->endpoints,
            'status'       => $this->status,
        ];
    }
}
