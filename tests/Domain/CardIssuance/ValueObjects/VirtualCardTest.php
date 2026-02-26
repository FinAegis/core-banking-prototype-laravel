<?php

declare(strict_types=1);

namespace Tests\Domain\CardIssuance\ValueObjects;

use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class VirtualCardTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    private function makeCard(array $overrides = []): VirtualCard
    {
        return new VirtualCard(
            cardToken: $overrides['cardToken'] ?? 'tok_test123',
            last4: $overrides['last4'] ?? '4242',
            network: $overrides['network'] ?? CardNetwork::VISA,
            status: $overrides['status'] ?? CardStatus::ACTIVE,
            cardholderName: $overrides['cardholderName'] ?? 'John Doe',
            expiresAt: $overrides['expiresAt'] ?? new DateTimeImmutable('+2 years'),
            pan: $overrides['pan'] ?? null,
            cvv: $overrides['cvv'] ?? null,
            metadata: $overrides['metadata'] ?? [],
            label: $overrides['label'] ?? null,
        );
    }

    public function test_is_usable_when_active_and_not_expired(): void
    {
        $card = $this->makeCard([
            'status'    => CardStatus::ACTIVE,
            'expiresAt' => new DateTimeImmutable('+1 year'),
        ]);

        $this->assertTrue($card->isUsable());
    }

    public function test_not_usable_when_frozen(): void
    {
        $card = $this->makeCard(['status' => CardStatus::FROZEN]);
        $this->assertFalse($card->isUsable());
    }

    public function test_not_usable_when_cancelled(): void
    {
        $card = $this->makeCard(['status' => CardStatus::CANCELLED]);
        $this->assertFalse($card->isUsable());
    }

    public function test_not_usable_when_pending(): void
    {
        $card = $this->makeCard(['status' => CardStatus::PENDING]);
        $this->assertFalse($card->isUsable());
    }

    public function test_not_usable_when_expired_status(): void
    {
        $card = $this->makeCard(['status' => CardStatus::EXPIRED]);
        $this->assertFalse($card->isUsable());
    }

    public function test_not_usable_when_past_expiry_date(): void
    {
        $card = $this->makeCard([
            'status'    => CardStatus::ACTIVE,
            'expiresAt' => new DateTimeImmutable('-1 day'),
        ]);

        $this->assertFalse($card->isUsable());
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $expiresAt = new DateTimeImmutable('2028-06-15');
        $card = $this->makeCard([
            'cardToken'      => 'tok_arr',
            'last4'          => '1234',
            'network'        => CardNetwork::MASTERCARD,
            'status'         => CardStatus::ACTIVE,
            'cardholderName' => 'Jane Smith',
            'expiresAt'      => $expiresAt,
            'label'          => 'My Card',
            'metadata'       => ['user_id' => 'u_1'],
        ]);

        $array = $card->toArray();

        $this->assertEquals('tok_arr', $array['card_token']);
        $this->assertEquals('1234', $array['last4']);
        $this->assertEquals('mastercard', $array['network']);
        $this->assertEquals('active', $array['status']);
        $this->assertEquals('Jane Smith', $array['cardholder_name']);
        $this->assertEquals('2028-06-15', $array['expires_at']);
        $this->assertEquals('My Card', $array['label']);
        $this->assertEquals(['user_id' => 'u_1'], $array['metadata']);
    }

    public function test_to_array_excludes_sensitive_fields(): void
    {
        $card = $this->makeCard([
            'pan' => '4242424242424242',
            'cvv' => '123',
        ]);

        $array = $card->toArray();

        $this->assertArrayNotHasKey('pan', $array);
        $this->assertArrayNotHasKey('cvv', $array);
    }

    public function test_card_is_readonly(): void
    {
        $reflection = new ReflectionClass(VirtualCard::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
