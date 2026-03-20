<?php

declare(strict_types=1);

use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Models\PartnerInvoice;
use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Exceptions\VisaCliPaymentException;
use App\Domain\VisaCli\Models\VisaCliPayment;
use App\Domain\VisaCli\Services\VisaCliPaymentGatewayService;

uses(Tests\TestCase::class);

function createGatewayTestPartner(): FinancialInstitutionPartner
{
    $application = FinancialInstitutionApplication::create([
        'application_number'       => 'FIA-2026-' . fake()->unique()->numerify('#####'),
        'institution_name'         => 'Test Partner',
        'legal_name'               => 'Test Partner Ltd',
        'registration_number'      => 'REG-' . fake()->numerify('######'),
        'tax_id'                   => 'TAX-' . fake()->numerify('######'),
        'country'                  => 'US',
        'institution_type'         => 'fintech',
        'years_in_operation'       => 3,
        'contact_name'             => 'Test User',
        'contact_email'            => 'test@partner.com',
        'contact_phone'            => '+1234567890',
        'contact_position'         => 'CTO',
        'headquarters_address'     => '123 Test St',
        'headquarters_city'        => 'New York',
        'headquarters_postal_code' => '10001',
        'headquarters_country'     => 'US',
        'business_description'     => 'Test partner',
        'target_markets'           => ['US'],
        'product_offerings'        => ['payments'],
        'required_currencies'      => ['USD'],
        'integration_requirements' => ['api'],
        'status'                   => 'approved',
    ]);

    return FinancialInstitutionPartner::create([
        'application_id'        => $application->id,
        'partner_code'          => 'TST-' . fake()->unique()->numerify('####'),
        'institution_name'      => 'Test Partner',
        'legal_name'            => 'Test Partner Ltd',
        'institution_type'      => 'fintech',
        'country'               => 'US',
        'status'                => 'active',
        'tier'                  => 'starter',
        'billing_cycle'         => 'monthly',
        'api_client_id'         => 'test_client_' . fake()->numerify('###'),
        'api_client_secret'     => encrypt('secret_123'),
        'webhook_secret'        => encrypt('webhook_123'),
        'sandbox_enabled'       => true,
        'production_enabled'    => false,
        'rate_limit_per_minute' => 300,
        'fee_structure'         => ['base' => 0],
        'risk_rating'           => 'low',
        'risk_score'            => 10.00,
        'primary_contact'       => ['name' => 'Test', 'email' => 'test@partner.com'],
    ]);
}

beforeEach(function (): void {
    /** @var VisaCliClientInterface&Mockery\MockInterface $client */
    $this->client = Mockery::mock(VisaCliClientInterface::class);
    $this->gateway = new VisaCliPaymentGatewayService($this->client);
    $this->partner = createGatewayTestPartner();
});

it('collects payment for a pending invoice', function (): void {
    $invoice = PartnerInvoice::create([
        'partner_id'           => $this->partner->id,
        'period_start'         => now()->subMonth(),
        'period_end'           => now(),
        'billing_cycle'        => 'monthly',
        'status'               => PartnerInvoice::STATUS_PENDING,
        'tier'                 => 'starter',
        'base_amount_usd'      => 299.00,
        'subtotal_usd'         => 299.00,
        'total_amount_usd'     => 299.00,
        'total_amount_display' => 299.00,
        'due_date'             => now()->addDays(30),
        'total_api_calls'      => 1000,
        'included_api_calls'   => 10000,
    ]);

    $this->client->shouldReceive('pay')
        ->once()
        ->andReturn(new VisaCliPaymentResult(
            paymentReference: 'visa_ref_001',
            status: VisaCliPaymentStatus::COMPLETED,
            amountCents: 29900,
            currency: 'USD',
            url: config('app.url') . '/api/partner/v1/billing/invoices/' . $invoice->id,
        ));

    $result = $this->gateway->collectPayment($invoice);

    expect($result->paymentReference)->toBe('visa_ref_001')
        ->and($result->status)->toBe(VisaCliPaymentStatus::COMPLETED)
        ->and($result->amountCents)->toBe(29900);

    $invoice->refresh();
    expect($invoice->isPaid())->toBeTrue()
        ->and($invoice->payment_method)->toBe('visa_cli')
        ->and($invoice->payment_reference)->toBe('visa_ref_001');

    $payment = VisaCliPayment::where('invoice_id', $invoice->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->payment_reference)->toBe('visa_ref_001');
});

it('rejects payment for already-paid invoice', function (): void {
    $invoice = PartnerInvoice::create([
        'partner_id'           => $this->partner->id,
        'period_start'         => now()->subMonth(),
        'period_end'           => now(),
        'billing_cycle'        => 'monthly',
        'status'               => PartnerInvoice::STATUS_PAID,
        'tier'                 => 'starter',
        'base_amount_usd'      => 99.00,
        'subtotal_usd'         => 99.00,
        'total_amount_usd'     => 99.00,
        'total_amount_display' => 99.00,
        'due_date'             => now()->addDays(30),
        'total_api_calls'      => 500,
        'included_api_calls'   => 10000,
    ]);

    $this->gateway->collectPayment($invoice);
})->throws(VisaCliPaymentException::class, 'cannot be paid');

it('retrieves payment status by reference', function (): void {
    VisaCliPayment::create([
        'agent_id'          => 'gateway',
        'url'               => 'https://example.com',
        'amount_cents'      => 1500,
        'currency'          => 'USD',
        'status'            => VisaCliPaymentStatus::COMPLETED,
        'payment_reference' => 'ref_lookup_test',
    ]);

    $result = $this->gateway->getPaymentStatus('ref_lookup_test');

    expect($result->paymentReference)->toBe('ref_lookup_test')
        ->and($result->status)->toBe(VisaCliPaymentStatus::COMPLETED)
        ->and($result->amountCents)->toBe(1500);
});

it('throws when looking up non-existent payment', function (): void {
    $this->gateway->getPaymentStatus('non_existent');
})->throws(VisaCliPaymentException::class, 'Payment not found');

it('refunds a completed payment', function (): void {
    VisaCliPayment::create([
        'agent_id'          => 'gateway',
        'url'               => 'https://example.com',
        'amount_cents'      => 2000,
        'currency'          => 'USD',
        'status'            => VisaCliPaymentStatus::COMPLETED,
        'payment_reference' => 'ref_refund_test',
    ]);

    $result = $this->gateway->refundPayment('ref_refund_test');

    expect($result->status)->toBe(VisaCliPaymentStatus::REFUNDED)
        ->and($result->amountCents)->toBe(2000);
});
