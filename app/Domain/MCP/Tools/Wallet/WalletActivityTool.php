<?php

declare(strict_types=1);

namespace App\Domain\MCP\Tools\Wallet;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\MobilePayment\Services\ActivityFeedService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * MCP tool that returns the caller's most recent wallet activity
 * (activity_feed_items) — the same denormalized feed the mobile app renders.
 * Reuses ActivityFeedService so the MCP surface can never drift from the
 * mobile one.
 *
 * Read-only — no idempotency_key required.
 */
final class WalletActivityTool implements MCPToolInterface
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function __construct(private readonly ActivityFeedService $service)
    {
    }

    public function getName(): string
    {
        return 'wallet.activity';
    }

    public function getCategory(): string
    {
        return 'wallet';
    }

    public function getDescription(): string
    {
        return 'Get the most recent wallet activity (transfers, merchant payments, shields) for the authenticated user. `limit` defaults to 10, max 50.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'limit' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => self::MAX_LIMIT,
                    'default'     => self::DEFAULT_LIMIT,
                    'description' => 'Number of activity items to return (1-50, default 10). Out-of-range values are clamped.',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'items' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'id'        => ['type' => 'string'],
                            'type'      => ['type' => 'string'],
                            'amount'    => ['type' => 'string'],
                            'asset'     => ['type' => 'string'],
                            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                            'status'    => ['type' => 'string'],
                            'protected' => ['type' => 'boolean'],
                        ],
                    ],
                ],
                'count'    => ['type' => 'integer'],
                'has_more' => ['type' => 'boolean'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCapabilities(): array
    {
        return ['async' => false];
    }

    public function isCacheable(): bool
    {
        return false;
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function validateInput(array $parameters): bool
    {
        if (isset($parameters['limit']) && ! is_numeric($parameters['limit'])) {
            return false;
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        return $this->resolveUser($userId) !== null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        $user = $this->resolveUser(null);
        if ($user === null) {
            return ToolExecutionResult::failure('Unauthenticated: wallet.activity requires a user-bound bearer token');
        }

        $limit = self::DEFAULT_LIMIT;
        if (isset($parameters['limit']) && is_numeric($parameters['limit'])) {
            $limit = (int) $parameters['limit'];
        }
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $feed = $this->service->getFeed((int) $user->id, null, $limit);

        /** @var list<array<string, mixed>> $items */
        $items = $feed['items'];

        return ToolExecutionResult::success([
            'items'    => $items,
            'count'    => count($items),
            'has_more' => (bool) $feed['has_more'],
        ]);
    }

    private function resolveUser(?string $userId): ?User
    {
        if ($userId !== null) {
            $u = User::find($userId);
            if ($u instanceof User) {
                return $u;
            }
        }

        $guarded = Auth::guard('api')->user();
        if ($guarded instanceof User) {
            return $guarded;
        }

        $default = Auth::user();

        return $default instanceof User ? $default : null;
    }
}
