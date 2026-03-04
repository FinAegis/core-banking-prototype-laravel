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
        return [
            'id'              => $this->id,
            'provider'        => $this->provider,
            'type'            => $this->type,
            'fiat_currency'   => $this->fiat_currency,
            'fiat_amount'     => $this->fiat_amount,
            'crypto_currency' => $this->crypto_currency,
            'crypto_amount'   => $this->crypto_amount,
            'status'          => $this->status,
            'redirect_url'    => $this->metadata['redirect_url'] ?? null,
            'widget_config'   => $this->metadata['widget_config'] ?? null,
            'created_at'      => $this->created_at->toIso8601String(),
        ];
    }
}
