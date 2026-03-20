<?php

declare(strict_types=1);

use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Models\PartnerInvoice;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Models\VisaCliPayment;
use App\Domain\VisaCli\Services\DemoVisaCliClient;
use App\Domain\VisaCli\Services\VisaCliPaymentGatewayService;

uses(Tests\TestCase::class);

function createTestPartnerForInvoice(): FinancialInstitutionPartner
{
    $application = FinancialInstitutionApplication::create([
        'application_number'       => 'FIA-2026-' . fake()->unique()->numerify('#####'),
        'institution_name'         => 'Invoice Test Partner',
        'legal_name'               => 'Invoice Test Partner Ltd',
        'registration_number'      => 'REG-' . fake()->numerify('######'),
        'tax_id'                   => 'TAX-' . fake()->numerify('######'),
        'country'                  => 'US',
        'institution_type'         => 'fintech',
        'years_in_operation'       => 3,
        'contact_name'             => 'Test User',
        'contact_email'            => 'test@invoice.com',
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
        'institution_name'      => 'Invoice Test Partner',
        'legal_name'            => 'Invoice Test Partner Ltd',
        'institution_type'      => 'fintech',
        'country'               => 'US',
        'status'                => 'active',
        'tier'                  => 'growth',
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
        'primary_contact'       => ['name' => 'Test', 'email' => 'test@invoice.com'],
    ]);
}

beforeEach(function (): void {
    $this->client = new DemoVisaCliClient();
    $this->gateway = new VisaCliPaymentGatewayService($this->client);
    $this->partner = createTestPartnerForInvoice();
});

it('pays a pending invoice end-to-end', function (): void {
    $invoice = PartnerInvoice::create([
        'partner_id'           => $this->partner->id,
        'period_start'         => now()->subMonth(),
        'period_end'           => now(),
        'billing_cycle'        => 'monthly',
        'status'               => PartnerInvoice::STATUS_PENDING,
        'tier'                 => 'growth',
        'base_amount_usd'      => 499.00,
        'subtotal_usd'         => 499.00,
        'total_amount_usd'     => 499.00,
        'total_amount_display' => 499.00,
        'due_date'             => now()->addDays(30),
        'total_api_calls'      => 5000,
        'included_api_calls'   => 25000,
    ]);

    $result = $this->gateway->collectPayment($invoice);

    expect($result->status)->toBe(VisaCliPaymentStatus::COMPLETED)
        ->and($result->amountCents)->toBe(49900);

    $invoice->refresh();
    expect($invoice->isPaid())->toBeTrue()
        ->and($invoice->payment_method)->toBe('visa_cli');

    $payment = VisaCliPayment::where('invoice_id', $invoice->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->isCompleted())->toBeTrue();
});

it('pays an overdue invoice', function (): void {
    $invoice = PartnerInvoice::create([
        'partner_id'           => $this->partner->id,
        'period_start'         => now()->subMonths(2),
        'period_end'           => now()->subMonth(),
        'billing_cycle'        => 'monthly',
        'status'               => PartnerInvoice::STATUS_OVERDUE,
        'tier'                 => 'growth',
        'base_amount_usd'      => 299.00,
        'subtotal_usd'         => 299.00,
        'total_amount_usd'     => 299.00,
        'total_amount_display' => 299.00,
        'due_date'             => now()->subDays(15),
        'total_api_calls'      => 3000,
        'included_api_calls'   => 25000,
    ]);

    $result = $this->gateway->collectPayment($invoice);

    expect($result->status)->toBe(VisaCliPaymentStatus::COMPLETED);

    $invoice->refresh();
    expect($invoice->isPaid())->toBeTrue();
});

it('rejects already-paid invoice', function (): void {
    $invoice = PartnerInvoice::create([
        'partner_id'           => $this->partner->id,
        'period_start'         => now()->subMonth(),
        'period_end'           => now(),
        'billing_cycle'        => 'monthly',
        'status'               => PartnerInvoice::STATUS_PAID,
        'tier'                 => 'growth',
        'base_amount_usd'      => 499.00,
        'subtotal_usd'         => 499.00,
        'total_amount_usd'     => 499.00,
        'total_amount_display' => 499.00,
        'due_date'             => now()->addDays(30),
        'total_api_calls'      => 5000,
        'included_api_calls'   => 25000,
        'paid_at'              => now(),
    ]);

    expect(fn () => $this->gateway->collectPayment($invoice))
        ->toThrow(App\Domain\VisaCli\Exceptions\VisaCliPaymentException::class, 'cannot be paid');
});

it('refunds a completed invoice payment', function (): void {
    $invoice = PartnerInvoice::create([
        'partner_id'           => $this->partner->id,
        'period_start'         => now()->subMonth(),
        'period_end'           => now(),
        'billing_cycle'        => 'monthly',
        'status'               => PartnerInvoice::STATUS_PENDING,
        'tier'                 => 'starter',
        'base_amount_usd'      => 99.00,
        'subtotal_usd'         => 99.00,
        'total_amount_usd'     => 99.00,
        'total_amount_display' => 99.00,
        'due_date'             => now()->addDays(30),
        'total_api_calls'      => 500,
        'included_api_calls'   => 10000,
    ]);

    $payResult = $this->gateway->collectPayment($invoice);
    $refundResult = $this->gateway->refundPayment($payResult->paymentReference);

    expect($refundResult->status)->toBe(VisaCliPaymentStatus::REFUNDED)
        ->and($refundResult->amountCents)->toBe(9900);
});
