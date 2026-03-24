<?php

declare(strict_types=1);

namespace App\Domain\SMS\DataObjects;

/**
 * Pre-built AP2 Intent Mandate template for SMS campaigns.
 *
 * Example: "Agent X can spend up to €50/day on SMS to EU numbers"
 */
readonly class SmsIntentMandate
{
    /**
     * @param string        $intent         Natural language intent (e.g., "Send SMS notifications to EU customers")
     * @param int           $budgetCents    Daily budget in cents
     * @param string        $budgetCurrency Currency code
     * @param string        $delegatorDid   DID of the entity granting the mandate
     * @param string        $agentDid       DID of the agent receiving the mandate
     * @param array<string> $allowedCountries ISO 3166-1 alpha-2 codes (empty = all)
     * @param int|null      $maxPerMessage  Max price per message in cents (null = no limit)
     * @param int|null      $maxMessages    Max messages per day (null = no limit)
     * @param string|null   $expiresAt      RFC 3339 expiry
     */
    public function __construct(
        public string $intent,
        public int $budgetCents,
        public string $budgetCurrency,
        public string $delegatorDid,
        public string $agentDid,
        public array $allowedCountries = [],
        public ?int $maxPerMessage = null,
        public ?int $maxMessages = null,
        public ?string $expiresAt = null,
    ) {
    }

    /**
     * Convert to AP2 mandate payload format.
     *
     * @return array<string, mixed>
     */
    public function toMandatePayload(): array
    {
        return array_filter([
            'type'   => 'sms_campaign',
            'intent' => $this->intent,
            'budget' => [
                'amount'   => $this->budgetCents,
                'currency' => $this->budgetCurrency,
                'period'   => 'daily',
            ],
            'constraints' => array_filter([
                'allowed_countries' => $this->allowedCountries !== [] ? $this->allowedCountries : null,
                'max_per_message'   => $this->maxPerMessage,
                'max_messages'      => $this->maxMessages,
            ], fn ($v) => $v !== null),
            'delegator_did'    => $this->delegatorDid,
            'agent_did'        => $this->agentDid,
            'expires_at'       => $this->expiresAt,
            'service_provider' => 'vertexsms',
            'payment_methods'  => ['x402', 'mpp'],
        ], fn ($v) => $v !== null);
    }

    /**
     * Create a default EU SMS campaign mandate.
     */
    public static function euCampaign(string $delegatorDid, string $agentDid, int $dailyBudgetCents = 5000): self
    {
        return new self(
            intent: 'Send SMS notifications to EU customers',
            budgetCents: $dailyBudgetCents,
            budgetCurrency: 'EUR',
            delegatorDid: $delegatorDid,
            agentDid: $agentDid,
            allowedCountries: [
                'LT', 'LV', 'EE', 'DE', 'FR', 'ES', 'IT', 'NL', 'BE',
                'AT', 'PL', 'CZ', 'SK', 'HU', 'RO', 'BG', 'HR', 'SI',
                'FI', 'SE', 'DK', 'IE', 'PT', 'GR',
            ],
            maxPerMessage: 100, // €1.00 max per message
        );
    }
}
