<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows;

use App\Domain\AgentProtocol\DataObjects\MessageDeliveryRequest;
use App\Domain\AgentProtocol\DataObjects\MessageDeliveryResult;
use App\Domain\AgentProtocol\Workflows\Activities\AcknowledgeMessageActivity;
use App\Domain\AgentProtocol\Workflows\Activities\DeliverMessageActivity;
use App\Domain\AgentProtocol\Workflows\Activities\HandleMessageRetryActivity;
use App\Domain\AgentProtocol\Workflows\Activities\QueueMessageActivity;
use App\Domain\AgentProtocol\Workflows\Activities\RouteMessageActivity;
use App\Domain\AgentProtocol\Workflows\Activities\ValidateMessageActivity;
use Carbon\CarbonInterval;
use Generator;
use React\Promise\Promise;
use Throwable;
use Workflow\Activity\ActivityOptions;
use Workflow\Workflow;

class MessageDeliveryWorkflow extends Workflow
{
    private MessageDeliveryRequest $request;

    private MessageDeliveryResult $result;

    private int $retryCount = 0;

    private const MAX_RETRIES = 3;

    private const DEFAULT_TIMEOUT = 30;

    public function __construct()
    {
        $this->result = new MessageDeliveryResult();
    }

    public function execute(MessageDeliveryRequest $request): Promise
    {
        $this->request = $request;

        return Workflow::async(function () {
            try {
                yield from $this->processMessage();
            } catch (Throwable $e) {
                yield from $this->handleFailure($e);
            }

            return $this->result;
        });
    }

    private function processMessage(): Generator
    {
        yield from $this->validateMessage();

        if (! $this->result->isValid) {
            $this->result->status = 'validation_failed';

            return;
        }

        yield from $this->queueMessage();

        yield from $this->routeMessage();

        yield from $this->deliverMessage();

        if ($this->request->requiresAcknowledgment) {
            yield from $this->awaitAcknowledgment();
        }

        $this->result->status = 'completed';
        $this->result->completedAt = now()->toIso8601String();
    }

    private function validateMessage(): Generator
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(CarbonInterval::seconds(10))
            ->withRetryOptions([
                'initialInterval' => 1,
                'maximumInterval' => 10,
                'maximumAttempts' => 2,
            ]);

        $validation = yield Workflow::executeActivity(
            ValidateMessageActivity::class,
            [$this->request],
            $options
        );

        $this->result->isValid = $validation['isValid'];
        $this->result->validationErrors = $validation['errors'] ?? [];

        if (! $this->result->isValid) {
            $this->result->failureReason = 'Message validation failed: ' . implode(', ', $this->result->validationErrors);
        }
    }

    private function queueMessage(): Generator
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(CarbonInterval::seconds(5))
            ->withRetryOptions([
                'maximumAttempts' => 3,
            ]);

        $queueResult = yield Workflow::executeActivity(
            QueueMessageActivity::class,
            [
                $this->request->messageId,
                $this->request->priority,
                $this->request->queueName ?? 'agent-messages',
            ],
            $options
        );

        $this->result->queuedAt = $queueResult['queuedAt'];
        $this->result->queueName = $queueResult['queueName'];
    }

    private function routeMessage(): Generator
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(CarbonInterval::seconds(10));

        $routingResult = yield Workflow::executeActivity(
            RouteMessageActivity::class,
            [
                $this->request->fromAgentId,
                $this->request->toAgentId,
                $this->request->messageType,
                $this->request->payload,
            ],
            $options
        );

        $this->result->routingPath = $routingResult['path'] ?? [];
        $this->result->deliveryEndpoint = $routingResult['endpoint'] ?? null;
    }

    private function deliverMessage(): Generator
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(CarbonInterval::seconds(self::DEFAULT_TIMEOUT))
            ->withRetryOptions([
                'maximumAttempts'    => self::MAX_RETRIES,
                'backoffCoefficient' => 2,
            ]);

        try {
            $deliveryResult = yield Workflow::executeActivity(
                DeliverMessageActivity::class,
                [
                    $this->request->messageId,
                    $this->result->deliveryEndpoint,
                    $this->request->payload,
                    $this->request->headers,
                ],
                $options
            );

            $this->result->deliveredAt = $deliveryResult['deliveredAt'];
            $this->result->deliveryMethod = $deliveryResult['method'];
            $this->result->deliveryResponse = $deliveryResult['response'] ?? null;
        } catch (Throwable $e) {
            if ($this->retryCount < self::MAX_RETRIES) {
                yield from $this->retryDelivery($e->getMessage());
            } else {
                throw $e;
            }
        }
    }

    private function awaitAcknowledgment(): Generator
    {
        $timeout = $this->request->acknowledgmentTimeout ?? 60;

        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(CarbonInterval::seconds($timeout));

        try {
            $timer = Workflow::timer(CarbonInterval::seconds($timeout));
            $acknowledgment = Workflow::executeActivity(
                AcknowledgeMessageActivity::class,
                [$this->request->messageId],
                $options
            );

            $result = yield Workflow::awaitWithTimeout(
                CarbonInterval::seconds($timeout),
                $acknowledgment
            );

            if ($result === null) {
                $this->result->acknowledgmentTimedOut = true;
                $this->result->status = 'acknowledgment_timeout';
            } else {
                $this->result->acknowledgedAt = $result['acknowledgedAt'];
                $this->result->acknowledgmentId = $result['acknowledgmentId'] ?? null;
            }
        } catch (Throwable $e) {
            $this->result->acknowledgmentError = $e->getMessage();
        }
    }

    private function retryDelivery(string $reason): Generator
    {
        $this->retryCount++;

        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(CarbonInterval::seconds(5));

        $retryResult = yield Workflow::executeActivity(
            HandleMessageRetryActivity::class,
            [
                $this->request->messageId,
                $this->retryCount,
                $reason,
                $this->calculateBackoffDelay(),
            ],
            $options
        );

        $this->result->retryHistory[] = [
            'attempt'   => $this->retryCount,
            'reason'    => $reason,
            'retriedAt' => $retryResult['retriedAt'],
            'nextDelay' => $retryResult['nextDelay'],
        ];

        yield Workflow::timer(CarbonInterval::seconds($this->calculateBackoffDelay()));

        yield from $this->deliverMessage();
    }

    private function handleFailure(Throwable $e): Generator
    {
        $this->result->status = 'failed';
        $this->result->failureReason = $e->getMessage();
        $this->result->errorDetails = [
            'class' => get_class($e),
            'code'  => $e->getCode(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        if ($this->request->enableCompensation ?? false) {
            yield from $this->compensate();
        }
    }

    public function compensate(): Generator
    {
        if (! empty($this->result->deliveredAt)) {
            try {
                yield Workflow::executeChildWorkflow(
                    MessageCompensationWorkflow::class,
                    [$this->request, $this->result]
                );

                $this->result->compensationCompleted = true;
            } catch (Throwable $e) {
                $this->result->compensationFailed = true;
                $this->result->compensationError = $e->getMessage();
            }
        }
    }

    private function calculateBackoffDelay(): int
    {
        return min(300, pow(2, $this->retryCount) * 10);
    }

    public function getStatus(): array
    {
        return [
            'workflowId'     => $this->getWorkflowId(),
            'status'         => $this->result->status ?? 'processing',
            'messageId'      => $this->request->messageId ?? null,
            'retryCount'     => $this->retryCount,
            'deliveredAt'    => $this->result->deliveredAt ?? null,
            'acknowledgedAt' => $this->result->acknowledgedAt ?? null,
        ];
    }
}
