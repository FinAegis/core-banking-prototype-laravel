<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class SponsorshipStatusResource extends JsonResource
{
    private int $remaining;

    private bool $eligible;

    public function __construct($resource, bool $eligible, int $remaining)
    {
        parent::__construct($resource);
        $this->eligible = $eligible;
        $this->remaining = $remaining;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'eligible'          => $this->eligible,
            'remaining_free_tx' => $this->remaining,
            'free_until'        => $this->free_tx_until?->toIso8601String(),
            'total_sponsored'   => $this->sponsored_tx_used,
        ];
    }
}
