<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message_id'              => $this->message_id,
            'sender_agent_id'         => $this->sender_agent_id,
            'receiver_agent_id'       => $this->receiver_agent_id,
            'message_type'            => $this->message_type,
            'content'                 => $this->content,
            'status'                  => $this->status,
            'priority'                => $this->priority,
            'requires_acknowledgment' => $this->requires_acknowledgment,
            'acknowledged_at'         => $this->acknowledged_at?->toIso8601String(),
            'expires_at'              => $this->expires_at?->toIso8601String(),
            'metadata'                => $this->metadata,
            'created_at'              => $this->created_at->toIso8601String(),
        ];
    }
}
