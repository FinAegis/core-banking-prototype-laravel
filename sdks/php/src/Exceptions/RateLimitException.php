<?php

namespace FinAegis\Exceptions;

class RateLimitException extends FinAegisException
{
    /** @var ?int */
    private ?int $retryAfter;
    
    public function __construct(string $message = '', int $statusCode = 429, ?int $retryAfter = null, \Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->retryAfter = $retryAfter;
    }
    
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}