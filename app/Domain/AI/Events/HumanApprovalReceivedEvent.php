<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class HumanApprovalReceivedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $approvalId,
        public readonly bool $approved,
        public readonly string $approverId,
        public readonly ?string $comments = null,
        public readonly array $metadata = []
    ) {
    }
}
