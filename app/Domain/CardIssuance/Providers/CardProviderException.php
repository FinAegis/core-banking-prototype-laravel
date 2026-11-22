<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers;

use RuntimeException;

final class CardProviderException extends RuntimeException
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function unsupported(string $provider): self
    {
        return new self("Card provider '{$provider}' is not supported.", 'unsupported');
    }

    public static function notEligible(): self
    {
        return new self('Cards are not available for this account yet.', 'not_eligible');
    }

    public static function restrictedNationality(string $country): self
    {
        return new self("Cards are not available for nationality or country {$country} yet.", 'restricted_nationality');
    }

    public static function cardLimit(): self
    {
        return new self('The user already holds the allowed number of cards.', 'card_limit');
    }

    public static function holdsMoney(): self
    {
        return new self('The card still holds money, so it cannot be cancelled.', 'holds_money');
    }

    /**
     * A top-up the request cannot start or change: topups_disabled,
     * amount_out_of_range, network_unsupported, card_unavailable,
     * topup_in_progress or not_cancellable.
     */
    public static function topUp(string $reason): self
    {
        return new self("The top-up was refused: {$reason}.", $reason);
    }

    public static function providerUnavailable(string $provider, ?string $code): self
    {
        $label = $code !== null && $code !== '' ? " [{$code}]" : '';

        return new self("Card provider '{$provider}' could not complete the request{$label}.", 'provider_unavailable');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
