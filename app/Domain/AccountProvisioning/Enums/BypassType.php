<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Enums;

enum BypassType: string
{
    case DEVICE_ATTESTATION = 'device_attestation';
    case RATE_LIMIT = 'rate_limit';
    case SANCTIONS_SCREENING = 'sanctions_screening';
    case SMS_OTP = 'sms_otp';

    public function column(): string
    {
        return 'bypass_' . $this->value;
    }
}
