<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\VisaCli;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentRequest;
use App\Domain\VisaCli\Exceptions\VisaCliPaymentException;
use App\Domain\VisaCli\Services\VisaCliPaymentService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tool for executing Visa CLI payments in AI agent workflows.
 *
 * Allows AI agents to:
 * - Make programmatic Visa card payments to URLs
 * - Enforce spending limits per agent
 * - Track payment history
 */
class VisaCliPaymentTool implements MCPToolInterface
{
    public function __construct(
        private readonly VisaCliPaymentService $paymentService,
    ) {
    }

    public function getName(): string
    {
        return 'visacli.payment';
    }

    public function getCategory(): string
    {
        return 'visacli';
    }

    public function getDescription(): string
    {
        return 'Execute a Visa card payment to a URL using Visa CLI. '
            . 'Enforces per-agent spending limits and records all payment events for audit.';
    }

    /** @return array<string, mixed> */
    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'agent_id' => [
                    'type'        => 'string',
                    'description' => 'The agent identifier for spending limit enforcement.',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
                'url' => [
                    'type'        => 'string',
                    'description' => 'The payment target URL (API endpoint, service, etc.).',
                    'minLength'   => 1,
                ],
                'max_amount_cents' => [
                    'type'        => 'integer',
                    'description' => 'Maximum payment amount in USD cents.',
                    'minimum'     => 1,
                ],
                'card_id' => [
                    'type'        => 'string',
                    'description' => 'Optional enrolled card identifier to use for payment.',
                ],
                'purpose' => [
                    'type'        => 'string',
                    'description' => 'Purpose of the payment (e.g., "image_generation", "dataset_access").',
                ],
            ],
            'required' => ['agent_id', 'url', 'max_amount_cents'],
        ];
    }

    /** @return array<string, mixed> */
    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'payment_reference' => ['type' => 'string', 'description' => 'Unique payment reference ID'],
                'status'            => ['type' => 'string', 'description' => 'Payment status'],
                'amount_cents'      => ['type' => 'integer', 'description' => 'Actual amount charged in cents'],
                'currency'          => ['type' => 'string', 'description' => 'Currency code'],
                'url'               => ['type' => 'string', 'description' => 'Payment target URL'],
            ],
        ];
    }

    /** @param array<string, mixed> $parameters */
    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $startTime = microtime(true);

            $agentId = (string) $parameters['agent_id'];
            $url = (string) $parameters['url'];
            $amountCents = (int) $parameters['max_amount_cents'];

            Log::info('MCP Tool: Visa CLI payment requested', [
                'agent_id'        => $agentId,
                'url'             => $url,
                'amount_cents'    => $amountCents,
                'conversation_id' => $conversationId,
            ]);

            $request = new VisaCliPaymentRequest(
                agentId: $agentId,
                url: $url,
                amountCents: $amountCents,
                cardId: isset($parameters['card_id']) ? (string) $parameters['card_id'] : null,
                purpose: isset($parameters['purpose']) ? (string) $parameters['purpose'] : null,
            );

            $result = $this->paymentService->executePayment($request);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            Log::info('MCP Tool: Visa CLI payment completed', [
                'agent_id'  => $agentId,
                'reference' => $result->paymentReference,
                'status'    => $result->status->value,
            ]);

            return ToolExecutionResult::success($result->toArray(), $durationMs);
        } catch (VisaCliPaymentException $e) {
            Log::warning('MCP Tool: Visa CLI payment rejected', [
                'agent_id' => $parameters['agent_id'] ?? 'unknown',
                'error'    => $e->getMessage(),
            ]);

            return ToolExecutionResult::failure(
                $e->getMessage() . ' Check spending limits or Visa CLI configuration.'
            );
        } catch (Exception $e) {
            Log::error('MCP Tool error: visacli.payment', [
                'error'      => $e->getMessage(),
                'parameters' => ['agent_id' => $parameters['agent_id'] ?? 'unknown'],
            ]);

            return ToolExecutionResult::failure(
                'An unexpected error occurred processing the Visa CLI payment.'
            );
        }
    }

    /** @return array<int, string> */
    public function getCapabilities(): array
    {
        return [
            'write',
            'payment',
            'visa-cli',
            'spending-limits',
            'agent-autonomous',
        ];
    }

    public function isCacheable(): bool
    {
        return false;
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    /** @param array<string, mixed> $parameters */
    public function validateInput(array $parameters): bool
    {
        if (empty($parameters['agent_id']) || empty($parameters['url'])) {
            return false;
        }

        if (! isset($parameters['max_amount_cents']) || (int) $parameters['max_amount_cents'] <= 0) {
            return false;
        }

        return filter_var($parameters['url'], FILTER_VALIDATE_URL) !== false;
    }

    public function authorize(?string $userId): bool
    {
        // Visa CLI payments are initiated by AI agents in autonomous workflows.
        // Authorization is enforced via spending limits in VisaCliPaymentService.
        return true;
    }
}
