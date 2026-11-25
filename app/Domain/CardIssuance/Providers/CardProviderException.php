<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers;

use RuntimeException;

final class CardProviderException extends RuntimeException
{
    private function __construct(string $message, private readonly string $reason, private readonly ?string $minimum = null)
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

    /**
     * The card has a withdrawal moving money or waiting for an operator, so
     * it cannot be cancelled now.
     */
    public static function withdrawalInProgress(): self
    {
        return new self('A withdrawal from this card is still in progress.', 'withdrawal_in_progress');
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

    /**
     * The amount is below the top-up minimum for this card (USDC, 2 decimals);
     * refused like any amount outside the limits, with that minimum.
     */
    public static function belowMinimum(string $minimum): self
    {
        return new self("The top-up was refused: amount below {$minimum}.", 'amount_out_of_range', $minimum);
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

    /**
     * The minimum that refused the amount, when it is not the configured one.
     */
    public function minimum(): ?string
    {
        return $this->minimum;
    }
}
