<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\CardIssuance\ValueObjects;

use App\Domain\CardIssuance\ValueObjects\CardTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CardTransactionTest extends TestCase
{
    private CardTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transaction = new CardTransaction(
            transactionId: 'txn_abc123',
            cardToken: 'card_demo_xyz',
            merchantName: 'Starbucks',
            merchantCategory: '5814',
            amountCents: 475,
            currency: 'USD',
            status: 'settled',
            timestamp: new DateTimeImmutable('2026-03-01T12:00:00Z'),
        );
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $array = $this->transaction->toArray();

        $this->assertEquals('txn_abc123', $array['id']);
        $this->assertEquals(4.75, $array['amount']);
        $this->assertEquals('USD', $array['currency']);
        $this->assertEquals('Starbucks', $array['merchant']);
        $this->assertEquals('5814', $array['category']);
        $this->assertEquals('settled', $array['status']);
        $this->assertEquals('2026-03-01T12:00:00+00:00', $array['timestamp']);
    }

    public function test_get_amount_decimal_converts_cents_to_dollars(): void
    {
        $this->assertEquals(4.75, $this->transaction->getAmountDecimal());
    }

    public function test_get_amount_decimal_with_zero(): void
    {
        $transaction = new CardTransaction(
            transactionId: 'txn_zero',
            cardToken: 'card_demo_xyz',
            merchantName: 'Test',
            merchantCategory: '0000',
            amountCents: 0,
            currency: 'USD',
            status: 'pending',
            timestamp: new DateTimeImmutable(),
        );

        $this->assertEquals(0.0, $transaction->getAmountDecimal());
    }

    public function test_get_amount_decimal_with_large_amount(): void
    {
        $transaction = new CardTransaction(
            transactionId: 'txn_large',
            cardToken: 'card_demo_xyz',
            merchantName: 'Amazon',
            merchantCategory: '5942',
            amountCents: 999999,
            currency: 'USD',
            status: 'settled',
            timestamp: new DateTimeImmutable(),
        );

        $this->assertEquals(9999.99, $transaction->getAmountDecimal());
    }

    public function test_to_array_does_not_include_card_token(): void
    {
        $array = $this->transaction->toArray();

        $this->assertArrayNotHasKey('card_token', $array);
        $this->assertArrayNotHasKey('cardToken', $array);
    }
}
