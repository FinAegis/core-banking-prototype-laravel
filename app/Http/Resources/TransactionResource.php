<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = json_decode($this->metadata, true) ?? [];
        
        return [
            'id' => $this->id,
            'chain' => $this->chain,
            'transaction_hash' => $this->transaction_hash,
            'from_address' => $this->from_address,
            'to_address' => $this->to_address,
            'amount' => $this->amount,
            'asset' => $this->asset,
            'gas_used' => $this->gas_used,
            'gas_price' => $this->gas_price,
            'status' => $this->status,
            'confirmations' => $this->confirmations,
            'block_number' => $this->block_number,
            'metadata' => $metadata,
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}