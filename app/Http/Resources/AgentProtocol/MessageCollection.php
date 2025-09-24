<?php

declare(strict_types=1);

namespace App\Http\Resources\AgentProtocol;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MessageCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'messages' => $this->collection,
            'meta'     => [
                'total' => $this->collection->count(),
            ],
        ];
    }
}
