<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\DataObjects\MessageDeliveryRequest;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use Exception;
use JsonException;
use Workflow\Activity;

class ValidateMessageActivity extends Activity
{
    private AgentRegistryService $registryService;

    public function __construct()
    {
        $this->registryService = app(AgentRegistryService::class);
    }

    public function execute(MessageDeliveryRequest $request): array
    {
        $errors = [];
        $isValid = true;

        // Validate message ID format
        if (empty($request->messageId)) {
            $errors[] = 'Message ID is required';
            $isValid = false;
        } elseif (! $this->isValidUuid($request->messageId)) {
            $errors[] = 'Message ID must be a valid UUID';
            $isValid = false;
        }

        // Validate sender agent
        if (empty($request->fromAgentId)) {
            $errors[] = 'From agent ID is required';
            $isValid = false;
        } elseif (! $this->agentExists($request->fromAgentId)) {
            $errors[] = 'From agent does not exist';
            $isValid = false;
        }

        // Validate recipient agent
        if (empty($request->toAgentId)) {
            $errors[] = 'To agent ID is required';
            $isValid = false;
        } elseif (! $this->agentExists($request->toAgentId)) {
            $errors[] = 'To agent does not exist';
            $isValid = false;
        }

        // Validate message type
        if (empty($request->messageType)) {
            $errors[] = 'Message type is required';
            $isValid = false;
        } elseif (! $this->isValidMessageType($request->messageType)) {
            $errors[] = 'Invalid message type';
            $isValid = false;
        }

        // Validate payload
        if (empty($request->payload)) {
            $errors[] = 'Message payload is required';
            $isValid = false;
        } elseif (! $this->isValidPayload($request->payload)) {
            $errors[] = 'Invalid message payload format';
            $isValid = false;
        }

        // Validate priority
        if ($request->priority < 0 || $request->priority > 100) {
            $errors[] = 'Priority must be between 0 and 100';
            $isValid = false;
        }

        // Validate headers if present
        if (! empty($request->headers) && ! is_array($request->headers)) {
            $errors[] = 'Headers must be an array';
            $isValid = false;
        }

        // Validate acknowledgment timeout if required
        if ($request->requiresAcknowledgment && $request->acknowledgmentTimeout !== null) {
            if ($request->acknowledgmentTimeout < 1 || $request->acknowledgmentTimeout > 3600) {
                $errors[] = 'Acknowledgment timeout must be between 1 and 3600 seconds';
                $isValid = false;
            }
        }

        // Check message size limits
        $serializedPayload = json_encode($request->payload);
        if ($serializedPayload === false) {
            $errors[] = 'Failed to serialize message payload';
            $isValid = false;
            $payloadSize = 0;
        } else {
            $payloadSize = strlen($serializedPayload);
            if ($payloadSize > 1048576) { // 1MB limit
                $errors[] = 'Message payload exceeds maximum size of 1MB';
                $isValid = false;
            }
        }

        return [
            'isValid'  => $isValid,
            'errors'   => $errors,
            'metadata' => [
                'validatedAt' => now()->toIso8601String(),
                'payloadSize' => $payloadSize,
                'messageType' => $request->messageType,
                'priority'    => $request->priority,
            ],
        ];
    }

    private function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }

    private function agentExists(string $agentId): bool
    {
        try {
            return $this->registryService->agentExists($agentId);
        } catch (Exception $e) {
            // If registry service is unavailable, allow the message through
            // This ensures the system remains available even if the registry is down
            return true;
        }
    }

    private function isValidMessageType(string $type): bool
    {
        $validTypes = [
            'direct',
            'broadcast',
            'request',
            'response',
            'event',
            'command',
            'query',
            'notification',
            'acknowledgment',
            'error',
        ];

        return in_array($type, $validTypes, true);
    }

    private function isValidPayload(array $payload): bool
    {
        // Basic validation - payload should be JSON-serializable
        try {
            json_encode($payload, JSON_THROW_ON_ERROR);

            return true;
        } catch (JsonException $e) {
            return false;
        }
    }
}
