<?php

declare(strict_types=1);

namespace App\Domain\AI\Workflows;

use App\Domain\AI\Events\HumanApprovalReceivedEvent;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class HumanApprovalWorkflow extends Workflow
{
    private string $conversationId;

    private string $approvalId;

    private bool $approvalReceived = false;

    private bool $approved = false;

    private string $approverId = '';

    private ?string $comments = null;

    public function execute(
        string $conversationId,
        string $action,
        array $context,
        float $timeout = 3600 // 1 hour default timeout
    ): \Generator {
        $this->conversationId = $conversationId;
        $this->approvalId = uniqid('approval_');

        // Step 1: Request human intervention
        yield $this->requestHumanIntervention($action, $context);

        // Step 2: Wait for approval signal with timeout
        $signalReceived = yield WorkflowStub::awaitWithTimeout(
            $timeout,
            fn () => $this->approvalReceived
        );

        // Step 3: Process approval (timed out if signal not received)
        if (! $signalReceived) {
            $this->approved = false;
            $this->approverId = 'timeout';
            $this->comments = 'Approval request timed out';
        }

        // Step 4: Record approval event
        yield $this->recordApproval();

        return [
            'approved'    => $this->approved,
            'approval_id' => $this->approvalId,
            'approver_id' => $this->approverId,
            'comments'    => $this->comments,
            'timed_out'   => ! $signalReceived,
        ];
    }

    #[SignalMethod]
    public function approve(string $approverId, ?string $comments = null): void
    {
        $this->approvalReceived = true;
        $this->approved = true;
        $this->approverId = $approverId;
        $this->comments = $comments;
    }

    #[SignalMethod]
    public function reject(string $approverId, ?string $comments = null): void
    {
        $this->approvalReceived = true;
        $this->approved = false;
        $this->approverId = $approverId;
        $this->comments = $comments;
    }

    private function requestHumanIntervention(string $action, array $context): \Generator
    {
        event(new HumanInterventionRequestedEvent(
            $this->conversationId,
            'Approval required for: ' . $action,
            $context,
            0.0, // Low confidence requiring approval
            $action
        ));

        yield;
    }

    private function recordApproval(): \Generator
    {
        event(new HumanApprovalReceivedEvent(
            $this->conversationId,
            $this->approvalId,
            $this->approved,
            $this->approverId,
            $this->comments
        ));

        yield;
    }
}
