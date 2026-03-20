<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Contracts;

use App\Domain\VisaCli\DataObjects\VisaCliCard;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\DataObjects\VisaCliStatus;

interface VisaCliClientInterface
{
    /**
     * Get the current status of the Visa CLI installation.
     */
    public function getStatus(): VisaCliStatus;

    /**
     * Enroll a card for use with Visa CLI payments.
     *
     * @param array<string, mixed> $metadata
     */
    public function enrollCard(string $userId, array $metadata = []): VisaCliCard;

    /**
     * List all enrolled cards.
     *
     * @return array<VisaCliCard>
     */
    public function listCards(?string $userId = null): array;

    /**
     * Execute a payment to a URL.
     */
    public function pay(string $url, int $amountCents, ?string $cardId = null): VisaCliPaymentResult;

    /**
     * Check if Visa CLI is initialized and ready.
     */
    public function isInitialized(): bool;

    /**
     * Initialize the Visa CLI client.
     */
    public function initialize(): bool;
}
