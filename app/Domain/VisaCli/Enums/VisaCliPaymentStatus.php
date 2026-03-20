<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Enums;

enum VisaCliPaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}
