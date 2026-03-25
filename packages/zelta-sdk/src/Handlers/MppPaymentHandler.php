<?php

declare(strict_types=1);

namespace Zelta\Handlers;

use Psr\Http\Message\ResponseInterface;
use Zelta\Contracts\PaymentHandlerInterface;

/**
 * Handles MPP (Machine Payments Protocol) 402 responses.
 *
 * Parses the WWW-Authenticate: Payment challenge header,
 * selects a rail, and returns an Authorization: Payment credential.
 */
class MppPaymentHandler implements PaymentHandlerInterface
{
    /** @var list<string> */
    private array $preferredRails;

    /**
     * @param list<string> $preferredRails Rail preference order
     */
    public function __construct(
        array $preferredRails = ['stripe', 'x402', 'tempo', 'lightning'],
    ) {
        $this->preferredRails = $preferredRails;
    }

    public function canHandle(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() !== 402) {
            return false;
        }

        $authenticate = $response->getHeaderLine('WWW-Authenticate');

        return str_starts_with($authenticate, 'Payment ');
    }

    public function handlePaymentRequired(ResponseInterface $response, string $url): array
    {
        $authenticate = $response->getHeaderLine('WWW-Authenticate');
        $encoded = substr($authenticate, strlen('Payment '));

        $decoded = json_decode(
            base64_decode(strtr($encoded, '-_', '+/'), true) ?: '',
            true,
        );

        if (! is_array($decoded)) {
            return [];
        }

        $availableRails = $decoded['available_rails'] ?? [];
        $selectedRail = $this->selectRail($availableRails);

        if ($selectedRail === null) {
            return [];
        }

        // Build a minimal credential — in production this would be signed
        $credential = [
            'challenge_id' => $decoded['id'] ?? '',
            'rail'         => $selectedRail,
            'proof'        => 'sdk-placeholder',
        ];

        $credentialEncoded = rtrim(strtr(
            base64_encode((string) json_encode($credential)),
            '+/',
            '-_',
        ), '=');

        return [
            'Authorization' => "Payment {$credentialEncoded}",
        ];
    }

    /**
     * @param list<string> $availableRails
     */
    private function selectRail(array $availableRails): ?string
    {
        foreach ($this->preferredRails as $rail) {
            if (in_array($rail, $availableRails, true)) {
                return $rail;
            }
        }

        return $availableRails[0] ?? null;
    }
}
