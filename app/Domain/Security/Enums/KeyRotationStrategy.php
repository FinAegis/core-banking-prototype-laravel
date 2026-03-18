<?php

declare(strict_types=1);

namespace App\Domain\Security\Enums;

enum KeyRotationStrategy: string
{
    case IMMEDIATE = 'immediate';
    case GRACEFUL = 'graceful';
    case SCHEDULED = 'scheduled';

    public function description(): string
    {
        return match ($this) {
            self::IMMEDIATE => 'Replace keys immediately, re-encrypt all data',
            self::GRACEFUL  => 'New keys for encryption, old keys kept for decryption during transition',
            self::SCHEDULED => 'Schedule rotation for maintenance window',
        };
    }
}
