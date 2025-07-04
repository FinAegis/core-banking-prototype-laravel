<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockchainWalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = json_decode($this->metadata, true) ?? [];
        $settings = json_decode($this->settings, true) ?? [];
        
        return [
            'wallet_id' => $this->wallet_id,
            'type' => $this->type,
            'status' => $this->status,
            'settings' => $settings,
            'has_backup' => isset($metadata['last_backup']),
            'last_backup_at' => $metadata['last_backup']['created_at'] ?? null,
            'freeze_reason' => $metadata['freeze_reason'] ?? null,
            'frozen_at' => $metadata['frozen_at'] ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}