<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'status'         => $this->status,
            'amount'         => $this->amount,
            'currency'       => $this->currency,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
