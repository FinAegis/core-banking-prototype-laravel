<?php

declare(strict_types=1);

namespace App\Domain\MCP\Tools\Ramp;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Ramp\Services\RampService;
use App\Models\RampSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * MCP tool that returns the current state of a ramp session previously
 * created via ramp.start. Looks up the RampSession by id and asks the
 * provider for a fresh status if the local copy is still in flight.
 *
 * Read-only — no idempotency_key required.
 */
final class RampStatusTool implements MCPToolInterface
{
    public function __construct(private readonly RampService $service)
    {
    }

    public function getName(): string
    {
        return 'ramp.status';
    }

    public function getCategory(): string
    {
        return 'ramp';
    }

    public function getDescription(): string
    {
        return 'Get the current status of a ramp session by session_id. Refreshes from the provider if the session is still in flight.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'session_id' => ['type' => 'string', 'description' => 'The ramp session id returned by ramp.start.'],
            ],
            'required' => ['session_id'],
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
                'session_id'    => ['type' => 'string'],
                'status'        => ['type' => 'string'],
                'provider'      => ['type' => 'string'],
                'crypto_amount' => ['type' => ['string', 'null']],
                'updated_at'    => ['type' => 'string', 'format' => 'date-time'],
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
        return isset($parameters['session_id']) && is_string($parameters['session_id']) && $parameters['session_id'] !== '';
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
            return ToolExecutionResult::failure('Unauthenticated: ramp.status requires a user-bound bearer token');
        }

        $sessionId = (string) ($parameters['session_id'] ?? '');
        if ($sessionId === '') {
            return ToolExecutionResult::failure('session_id is required');
        }

        $session = RampSession::where('id', $sessionId)->first();
        if ($session === null) {
            return ToolExecutionResult::failure("ramp session not found: {$sessionId}");
        }

        if ((int) $session->user_id !== (int) $user->id) {
            // Don't leak existence of another user's session.
            return ToolExecutionResult::failure("ramp session not found: {$sessionId}");
        }

        try {
            $fresh = $this->service->getSessionStatus($session);
        } catch (Throwable $t) {
            return ToolExecutionResult::failure('ramp status refresh failed: ' . $t->getMessage());
        }

        return ToolExecutionResult::success([
            'session_id'    => (string) $fresh->id,
            'status'        => (string) $fresh->status,
            'provider'      => (string) $fresh->provider,
            'crypto_amount' => $fresh->crypto_amount !== null ? (string) $fresh->crypto_amount : null,
            'updated_at'    => $fresh->updated_at->toIso8601String(),
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
