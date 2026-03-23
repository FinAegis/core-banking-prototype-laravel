<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\DataObjects;

final class AgentOnboardingRequest
{
    public function __construct(
        public readonly string $virtualsAgentId,
        public readonly int $employerUserId,
        public readonly string $agentName,
        public readonly ?string $agentDescription = null,
        public readonly string $chain = 'base',
        public readonly ?int $dailyLimitCents = null,
        public readonly ?int $perTxLimitCents = null,
    ) {
    }
}
