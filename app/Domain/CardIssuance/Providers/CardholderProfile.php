<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers;

/**
 * What a user submits to become a cardholder: identity, address, and the
 * identity-check questionnaire the provider needs before it issues a card.
 */
final readonly class CardholderProfile
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public string $dateOfBirth,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public ?string $state,
        public string $postalCode,
        public string $country,
        public string $nationality,
        public string $occupation,
        public string $annualSalary,
        public string $accountPurpose,
        public string $expectedMonthlyVolume,
        public string $documentIssueDate,
    ) {
    }

    /**
     * The identity-check answers, keyed as the provider names them.
     *
     * @return array<string, string>
     */
    public function questionnaire(): array
    {
        return [
            'occupation'            => $this->occupation,
            'annualSalary'          => $this->annualSalary,
            'accountPurpose'        => $this->accountPurpose,
            'expectedMonthlyVolume' => $this->expectedMonthlyVolume,
            'documentIssueDate'     => $this->documentIssueDate,
        ];
    }
}
