<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\DataObjects;

use App\Domain\VisaCli\Enums\VisaCliCardStatus;

final class VisaCliCard
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $cardIdentifier,
        public readonly string $last4,
        public readonly string $network,
        public readonly VisaCliCardStatus $status,
        public readonly ?string $githubUsername = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'card_identifier' => $this->cardIdentifier,
            'last4'           => $this->last4,
            'network'         => $this->network,
            'status'          => $this->status->value,
            'github_username' => $this->githubUsername,
            'metadata'        => $this->metadata,
        ];
    }
}
