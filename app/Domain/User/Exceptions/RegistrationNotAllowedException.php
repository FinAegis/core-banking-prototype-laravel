<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A new user account was requested through an API sign-up path while
 * registration is closed (ERR_REG_001) or invite-only without a valid
 * invitation (ERR_REG_002). See config/registration.php.
 */
class RegistrationNotAllowedException extends RuntimeException
{
    public const CODE_CLOSED = 'ERR_REG_001';

    public const CODE_INVITATION_REQUIRED = 'ERR_REG_002';

    private function __construct(
        public readonly string $errorCode,
        public readonly string $reason,
    ) {
        parent::__construct($reason);
    }

    public static function closed(): self
    {
        return new self(self::CODE_CLOSED, 'REGISTRATION_CLOSED');
    }

    public static function invitationRequired(): self
    {
        return new self(self::CODE_INVITATION_REQUIRED, 'INVITATION_REQUIRED');
    }

    /**
     * 403 with the registered error code and a machine-readable reason.
     */
    public function toResponse(): JsonResponse
    {
        return ErrorResponse::make($this->errorCode, null, ['reason' => $this->reason]);
    }

    /**
     * Rendered for any request that lets the exception escape.
     */
    public function render(): JsonResponse
    {
        return $this->toResponse();
    }

    /**
     * Expected outcome of a closed sign-up, not an application error.
     */
    public function report(): void
    {
    }
}
