<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\VisaCli;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tool for listing enrolled Visa CLI cards.
 *
 * Read-only tool that returns enrolled cards available for payments.
 */
class VisaCliCardsTool implements MCPToolInterface
{
    public function __construct(
        private readonly VisaCliClientInterface $client,
    ) {
    }

    public function getName(): string
    {
        return 'visacli.cards';
    }

    public function getCategory(): string
    {
        return 'visacli';
    }

    public function getDescription(): string
    {
        return 'List enrolled Visa CLI cards available for making payments. '
            . 'Returns card identifiers, last 4 digits, network, and status.';
    }

    /** @return array<string, mixed> */
    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'user_id' => [
                    'type'        => 'string',
                    'description' => 'Optional user ID to filter cards by user.',
                ],
            ],
            'required' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'cards' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'card_identifier' => ['type' => 'string'],
                            'last4'           => ['type' => 'string'],
                            'network'         => ['type' => 'string'],
                            'status'          => ['type' => 'string'],
                        ],
                    ],
                ],
                'count' => ['type' => 'integer'],
            ],
        ];
    }

    /** @param array<string, mixed> $parameters */
    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $startTime = microtime(true);

            $userId = isset($parameters['user_id']) ? (string) $parameters['user_id'] : null;

            Log::info('MCP Tool: Listing Visa CLI cards', [
                'user_id'         => $userId,
                'conversation_id' => $conversationId,
            ]);

            $cards = $this->client->listCards($userId);

            $cardData = array_map(fn ($card) => $card->toArray(), $cards);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return ToolExecutionResult::success([
                'cards' => $cardData,
                'count' => count($cardData),
            ], $durationMs);
        } catch (Exception $e) {
            Log::error('MCP Tool error: visacli.cards', [
                'error' => $e->getMessage(),
            ]);

            return ToolExecutionResult::failure(
                'Failed to list Visa CLI cards: ' . $e->getMessage()
            );
        }
    }

    /** @return array<int, string> */
    public function getCapabilities(): array
    {
        return [
            'read',
            'visa-cli',
            'card-management',
        ];
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtl(): int
    {
        return 300; // 5 minutes
    }

    /** @param array<string, mixed> $parameters */
    public function validateInput(array $parameters): bool
    {
        // No required parameters — all optional
        return true;
    }

    public function authorize(?string $userId): bool
    {
        return true;
    }
}
