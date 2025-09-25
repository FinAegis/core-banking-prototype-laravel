<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $transaction_id
 * @property string $status
 * @property string $sender_agent_id
 * @property string $receiver_agent_id
 * @property float $amount
 * @property string $currency
 * @property string|null $description
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $completed_at
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transaction_id'    => $this->transaction_id,
            'status'            => $this->status,
            'sender_agent_id'   => $this->sender_agent_id,
            'receiver_agent_id' => $this->receiver_agent_id,
            'amount'            => $this->amount,
            'currency'          => $this->currency,
            'description'       => $this->description,
            'metadata'          => $this->metadata,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
            'completed_at'      => $this->completed_at?->toIso8601String(),
        ];
    }
}
