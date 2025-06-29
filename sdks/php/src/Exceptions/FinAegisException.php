<?php

namespace FinAegis\Exceptions;

class FinAegisException extends \Exception
{
    /** @var int */
    protected int $statusCode;
    
    /** @var array */
    protected array $responseData;
    
    public function __construct(string $message = '', int $statusCode = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->responseData = [];
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    public function getResponseData(): array
    {
        return $this->responseData;
    }
    
    public function setResponseData(array $data): void
    {
        $this->responseData = $data;
    }
}