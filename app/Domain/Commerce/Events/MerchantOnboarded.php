<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Enums\MerchantStatus;
use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a merchant is successfully onboarded.
 */
class MerchantOnboarded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $merchantId,
        public readonly string $merchantName,
        public readonly MerchantStatus $status,
        public readonly DateTimeInterface $onboardedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'merchant_id'   => $this->merchantId,
            'merchant_name' => $this->merchantName,
            'status'        => $this->status->value,
            'onboarded_at'  => $this->onboardedAt->format('c'),
        ];
    }
}
