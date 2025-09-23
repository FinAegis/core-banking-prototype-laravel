<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

class MessageDeliveryResult
{
    public string $status = 'processing';

    public bool $isValid = true;

    public array $validationErrors = [];

    public ?string $failureReason = null;

    public ?string $queuedAt = null;

    public ?string $queueName = null;

    public array $routingPath = [];

    public ?string $deliveryEndpoint = null;

    public ?string $deliveredAt = null;

    public ?string $deliveryMethod = null;

    public mixed $deliveryResponse = null;

    public ?string $acknowledgedAt = null;

    public ?string $acknowledgmentId = null;

    public bool $acknowledgmentTimedOut = false;

    public ?string $acknowledgmentError = null;

    public array $retryHistory = [];

    public ?string $completedAt = null;

    public array $errorDetails = [];

    public bool $compensationCompleted = false;

    public bool $compensationFailed = false;

    public ?string $compensationError = null;

    public function toArray(): array
    {
        return [
            'status'                 => $this->status,
            'isValid'                => $this->isValid,
            'validationErrors'       => $this->validationErrors,
            'failureReason'          => $this->failureReason,
            'queuedAt'               => $this->queuedAt,
            'queueName'              => $this->queueName,
            'routingPath'            => $this->routingPath,
            'deliveryEndpoint'       => $this->deliveryEndpoint,
            'deliveredAt'            => $this->deliveredAt,
            'deliveryMethod'         => $this->deliveryMethod,
            'deliveryResponse'       => $this->deliveryResponse,
            'acknowledgedAt'         => $this->acknowledgedAt,
            'acknowledgmentId'       => $this->acknowledgmentId,
            'acknowledgmentTimedOut' => $this->acknowledgmentTimedOut,
            'acknowledgmentError'    => $this->acknowledgmentError,
            'retryHistory'           => $this->retryHistory,
            'completedAt'            => $this->completedAt,
            'errorDetails'           => $this->errorDetails,
            'compensationCompleted'  => $this->compensationCompleted,
            'compensationFailed'     => $this->compensationFailed,
            'compensationError'      => $this->compensationError,
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'completed' && ! empty($this->deliveredAt);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed' || $this->status === 'validation_failed';
    }

    public function wasAcknowledged(): bool
    {
        return ! empty($this->acknowledgedAt) && ! $this->acknowledgmentTimedOut;
    }
}
