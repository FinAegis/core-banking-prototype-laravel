<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Enums;

enum AgentStatus: string
{
    case REGISTERED = 'registered';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DEACTIVATED = 'deactivated';
}
