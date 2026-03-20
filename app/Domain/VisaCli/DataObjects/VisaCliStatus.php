<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\DataObjects;

final class VisaCliStatus
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly bool $initialized,
        public readonly ?string $version = null,
        public readonly ?string $githubUsername = null,
        public readonly int $enrolledCards = 0,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'initialized'     => $this->initialized,
            'version'         => $this->version,
            'github_username' => $this->githubUsername,
            'enrolled_cards'  => $this->enrolledCards,
            'metadata'        => $this->metadata,
        ];
    }
}
