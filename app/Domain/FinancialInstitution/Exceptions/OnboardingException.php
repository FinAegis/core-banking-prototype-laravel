<?php

namespace App\Domain\FinancialInstitution\Exceptions;

class OnboardingException extends \Exception
{
    public function __construct(string $message = "Onboarding process failed", int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}