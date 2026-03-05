<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Banner
 */
class BannerResource extends JsonResource
{
    private const CTA_LABELS = [
        'url'      => 'Learn More',
        'screen'   => 'View',
        'deeplink' => 'Open',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'subtitle'    => $this->subtitle,
            'image_url'   => $this->image_url,
            'action_url'  => $this->action_url,
            'action_type' => $this->action_type,
            'cta_label'   => $this->cta_label ?? self::CTA_LABELS[$this->action_type] ?? 'View',
            'position'    => $this->position,
        ];
    }
}
