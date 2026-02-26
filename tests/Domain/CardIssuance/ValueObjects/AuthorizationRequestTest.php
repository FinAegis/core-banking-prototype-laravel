<?php

declare(strict_types=1);

namespace Tests\Domain\CardIssuance\ValueObjects;

use App\Domain\CardIssuance\ValueObjects\AuthorizationRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AuthorizationRequestTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $timestamp = new DateTimeImmutable('2026-01-15T10:30:00Z');
        $request = new AuthorizationRequest(
            authorizationId: 'auth_123',
            cardToken: 'card_456',
            amountCents: 2500,
            currency: 'USD',
            merchantName: 'Coffee Shop',
            merchantCategory: 'food_and_drink',
            merchantId: 'merch_789',
            merchantCity: 'Zurich',
            merchantCountry: 'CH',
            timestamp: $timestamp,
        );

        $this->assertEquals('auth_123', $request->authorizationId);
        $this->assertEquals('card_456', $request->cardToken);
        $this->assertEquals(2500, $request->amountCents);
        $this->assertEquals('USD', $request->currency);
        $this->assertEquals('Coffee Shop', $request->merchantName);
        $this->assertEquals('food_and_drink', $request->merchantCategory);
        $this->assertEquals('merch_789', $request->merchantId);
        $this->assertEquals('Zurich', $request->merchantCity);
        $this->assertEquals('CH', $request->merchantCountry);
        $this->assertSame($timestamp, $request->timestamp);
    }

    public function test_optional_fields_default_to_null(): void
    {
        $request = new AuthorizationRequest(
            authorizationId: 'auth_1',
            cardToken: 'card_1',
            amountCents: 100,
            currency: 'EUR',
            merchantName: 'Test',
            merchantCategory: 'general',
        );

        $this->assertNull($request->merchantId);
        $this->assertNull($request->merchantCity);
        $this->assertNull($request->merchantCountry);
        $this->assertNull($request->timestamp);
    }

    public function test_get_amount_decimal_converts_cents_to_decimal(): void
    {
        $request = new AuthorizationRequest(
            authorizationId: 'auth_1',
            cardToken: 'card_1',
            amountCents: 2550,
            currency: 'USD',
            merchantName: 'Test',
            merchantCategory: 'general',
        );

        $this->assertEquals('25.50', $request->getAmountDecimal());
    }

    public function test_get_amount_decimal_handles_zero(): void
    {
        $request = new AuthorizationRequest(
            authorizationId: 'auth_1',
            cardToken: 'card_1',
            amountCents: 0,
            currency: 'USD',
            merchantName: 'Test',
            merchantCategory: 'general',
        );

        $this->assertEquals('0.00', $request->getAmountDecimal());
    }

    public function test_get_amount_decimal_handles_small_amounts(): void
    {
        $request = new AuthorizationRequest(
            authorizationId: 'auth_1',
            cardToken: 'card_1',
            amountCents: 1,
            currency: 'USD',
            merchantName: 'Test',
            merchantCategory: 'general',
        );

        $this->assertEquals('0.01', $request->getAmountDecimal());
    }

    public function test_from_webhook_creates_instance_from_array(): void
    {
        $data = [
            'authorization_id'  => 'auth_webhook',
            'card_token'        => 'card_webhook',
            'amount'            => '5000',
            'currency'          => 'GBP',
            'merchant_name'     => 'Online Store',
            'merchant_category' => 'retail',
            'merchant_id'       => 'merch_w1',
            'merchant_city'     => 'London',
            'merchant_country'  => 'GB',
            'timestamp'         => '2026-02-01T12:00:00Z',
        ];

        $request = AuthorizationRequest::fromWebhook($data);

        $this->assertEquals('auth_webhook', $request->authorizationId);
        $this->assertEquals('card_webhook', $request->cardToken);
        $this->assertEquals(5000, $request->amountCents);
        $this->assertEquals('GBP', $request->currency);
        $this->assertEquals('Online Store', $request->merchantName);
        $this->assertEquals('retail', $request->merchantCategory);
        $this->assertEquals('merch_w1', $request->merchantId);
        $this->assertInstanceOf(DateTimeImmutable::class, $request->timestamp);
    }

    public function test_from_webhook_uses_defaults_for_optional_fields(): void
    {
        $data = [
            'authorization_id' => 'auth_min',
            'card_token'       => 'card_min',
            'amount'           => '100',
        ];

        $request = AuthorizationRequest::fromWebhook($data);

        $this->assertEquals('USD', $request->currency);
        $this->assertEquals('Unknown', $request->merchantName);
        $this->assertEquals('unknown', $request->merchantCategory);
        $this->assertNull($request->merchantId);
        $this->assertInstanceOf(DateTimeImmutable::class, $request->timestamp);
    }

    public function test_authorization_request_is_readonly(): void
    {
        $reflection = new ReflectionClass(AuthorizationRequest::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
