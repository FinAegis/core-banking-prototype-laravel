<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Enums;

enum ShardType: string
{
    case DEVICE = 'device';
    case AUTH = 'auth';
    case RECOVERY = 'recovery';

    public function label(): string
    {
        return match ($this) {
            self::DEVICE   => 'Device Enclave',
            self::AUTH     => 'Authentication (HSM)',
            self::RECOVERY => 'Recovery Backup',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DEVICE   => 'Stored in device secure enclave (Keychain/Keystore)',
            self::AUTH     => 'Stored in HSM, released upon authentication',
            self::RECOVERY => 'Encrypted with user password, stored in cloud',
        };
    }

    public function isHsmStored(): bool
    {
        return $this === self::AUTH;
    }

    public function requiresPassword(): bool
    {
        return $this === self::RECOVERY;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
