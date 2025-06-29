<?php

namespace FinAegis\Exceptions;

class ValidationException extends FinAegisException
{
    /** @var array */
    private array $errors;
    
    public function __construct(string $message = '', int $statusCode = 400, array $errors = [], \Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->errors = $errors;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}