<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $reputation_id
 * @property string $agent_id
 * @property float $score
 * @property string $trust_level
 * @property int $total_transactions
 * @property int $successful_transactions
 * @property int $failed_transactions
 * @property int $disputed_transactions
 * @property float $success_rate
 * @property \Carbon\Carbon|null $last_decay_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ReputationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reputation_id'           => $this->reputation_id,
            'agent_id'                => $this->agent_id,
            'score'                   => $this->score,
            'trust_level'             => $this->trust_level,
            'total_transactions'      => $this->total_transactions,
            'successful_transactions' => $this->successful_transactions,
            'failed_transactions'     => $this->failed_transactions,
            'disputed_transactions'   => $this->disputed_transactions,
            'success_rate'            => $this->success_rate,
            'last_decay_at'           => $this->last_decay_at?->toIso8601String(),
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
