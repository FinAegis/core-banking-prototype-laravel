<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RampSession
 */
class RampSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata ?? [];

        return [
            'id'              => $this->id,
            'provider'        => $this->provider,
            'type'            => $this->type,
            'type_label'      => $this->type === 'on' ? 'Buy Crypto' : 'Sell Crypto',
            'fiat_currency'   => $this->fiat_currency,
            'fiat_amount'     => $this->fiat_amount,
            'crypto_currency' => $this->crypto_currency,
            'crypto_amount'   => $this->crypto_amount,
            'status'          => $this->status,
            'status_label'    => ucfirst($this->status),
            'redirect_url'    => $metadata['redirect_url'] ?? null,
            'widget_config'   => $metadata['widget_config'] ?? null,
            'created_at'      => $this->created_at->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
        ];
    }
}
