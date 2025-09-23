<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows;

use App\Domain\AgentProtocol\DataObjects\MessageDeliveryRequest;
use App\Domain\AgentProtocol\DataObjects\MessageDeliveryResult;
use React\Promise\Promise;
use Workflow\Workflow;

class MessageCompensationWorkflow extends Workflow
{
    public function execute(
        MessageDeliveryRequest $request,
        MessageDeliveryResult $result
    ): Promise {
        return Workflow::async(function () use ($request, $result) {
            // Implement compensation logic here
            // For now, just log the compensation attempt
            yield Workflow::timer(1); // Simulate some work

            return [
                'compensated'    => true,
                'messageId'      => $request->messageId,
                'originalStatus' => $result->status,
                'compensatedAt'  => now()->toIso8601String(),
            ];
        });
    }
}
