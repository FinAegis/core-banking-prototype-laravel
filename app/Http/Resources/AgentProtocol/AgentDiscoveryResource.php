<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $agent_id
 * @property string $did
 * @property string $name
 * @property string $type
 * @property string|null $organization
 * @property array $capabilities
 * @property array $endpoints
 * @property string $status
 */
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
