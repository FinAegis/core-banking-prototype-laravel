<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Enums;

enum VisaCliCardStatus: string
{
    case ENROLLED = 'enrolled';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REMOVED = 'removed';
}
