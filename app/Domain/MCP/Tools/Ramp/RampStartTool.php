<?php

declare(strict_types=1);

namespace App\Domain\MCP\Tools\Ramp;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Ramp\Services\RampService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * MCP tool that wraps RampService::createSession() to expose the on/off-ramp
 * checkout flow over the public MCP server. Clients receive a hosted checkout
 * URL — they must complete the actual fiat→crypto (or crypto→fiat) leg in a
 * browser session, since regulated rails (Stripe Bridge, Mt Pelerin, etc.)
 * require user-present KYC.
 *
 * Authorization is per-user: we resolve the bearer-token's owning User from
 * the Passport `api` guard, since RampService::createSession() requires a
 * concrete User model to attach the session row.
 */
final class RampStartTool implements MCPToolInterface
{
    public function __construct(private readonly RampService $service)
    {
    }

    public function getName(): string
    {
        return 'ramp.start';
    }

    public function getCategory(): string
    {
        return 'ramp';
    }

    public function getDescription(): string
    {
        return 'Start an on-ramp or off-ramp session. Returns a hosted checkout URL the user must complete in a browser.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'type'            => ['type' => 'string', 'enum' => ['on', 'off']],
                'fiat_currency'   => ['type' => 'string', 'enum' => ['USD', 'EUR', 'GBP']],
                'fiat_amount'     => ['type' => 'string', 'description' => 'Amount as a numeric string in major units (e.g. "100.00").'],
                'crypto_currency' => ['type' => 'string', 'enum' => ['USDC', 'EURC', 'USDT']],
                'wallet_address'  => ['type' => 'string'],
                'quote_id'        => ['type' => 'string', 'description' => 'Optional quote_id from a prior ramp.quote.'],
            ],
            'required' => ['type', 'fiat_currency', 'fiat_amount', 'crypto_currency', 'wallet_address'],
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
                'session_id'   => ['type' => 'string'],
                'checkout_url' => ['type' => 'string', 'format' => 'uri'],
                'provider'     => ['type' => 'string'],
                'status'       => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCapabilities(): array
    {
        return ['async' => true, 'requires_browser' => true];
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
        return isset(
            $parameters['type'],
            $parameters['fiat_currency'],
            $parameters['fiat_amount'],
            $parameters['crypto_currency'],
            $parameters['wallet_address'],
        );
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
        // McpOAuthGuard switches the default guard to `api`, so Auth::user() now
        // resolves to the bearer-token's owning user under MCP. Internal AI
        // callers continue to work because they hit the same Auth facade with
        // their own guard configuration.
        $user = $this->resolveUser(null);
        if ($user === null) {
            return ToolExecutionResult::failure('Unauthenticated: ramp.start requires a user-bound bearer token');
        }

        try {
            $session = $this->service->createSession(
                user: $user,
                type: (string) $parameters['type'],
                fiatCurrency: (string) $parameters['fiat_currency'],
                fiatAmount: (string) $parameters['fiat_amount'],
                cryptoCurrency: (string) $parameters['crypto_currency'],
                walletAddress: (string) $parameters['wallet_address'],
                quoteId: isset($parameters['quote_id']) ? (string) $parameters['quote_id'] : null,
            );
        } catch (Throwable $t) {
            return ToolExecutionResult::failure('ramp session failed: ' . $t->getMessage());
        }

        $metadata = $session->metadata ?? [];

        return ToolExecutionResult::success([
            'session_id'   => (string) $session->id,
            'checkout_url' => is_string($metadata['checkout_url'] ?? null) ? $metadata['checkout_url'] : null,
            'provider'     => (string) $session->provider,
            'status'       => (string) $session->status,
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
